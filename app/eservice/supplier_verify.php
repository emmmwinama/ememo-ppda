<?php
/**
 * PUBLIC supplier-registration verification — the target of the certificate QR
 * code. No login required. Shows only public registry facts (name, code,
 * country, status, expiry, categories) — never contact, tax, bank or
 * shareholder data.
 */
require __DIR__ . '/../../config/database.php';   // -> $conn (mysqli)
require __DIR__ . '/inc/cert.php';

mysqli_report(MYSQLI_REPORT_OFF);
@$conn->set_charset('utf8mb4');

function h($s): string { return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$code  = trim($_GET['code'] ?? '');
$token = trim($_GET['c'] ?? '');
$linkOk = $token === '' ? null : es_cert_token_ok($code, $token);

$sup = null;
if ($code !== '') {
    $st = $conn->prepare(
        "SELECT s.id, s.supplier_code, s.name, s.trading_name, s.status, s.expire_date,
                c.name AS country_name
           FROM es_supplier s LEFT JOIN es_country c ON c.id = s.country_id
          WHERE s.supplier_code = ? OR s.id = ?
          LIMIT 1"
    );
    $cid = ctype_digit($code) ? (int) $code : 0;
    $st->bind_param('si', $code, $cid);
    $st->execute();
    $sup = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
}

$cats = [];
if ($sup) {
    $r = $conn->query(
        "SELECT c.type, c.name FROM es_supplier_category sc
           JOIN es_category c ON c.id = sc.category_id
          WHERE sc.supplier_id = " . (int) $sup['id'] . " ORDER BY c.type, c.name"
    );
    while ($r && $row = $r->fetch_assoc()) $cats[$row['type']][] = $row['name'];
}

$expired = $sup && $sup['expire_date'] && strtotime($sup['expire_date']) < time();
$valid   = $sup && !$expired && in_array($sup['status'], ['active'], true);

if ($sup) {
    $verdict = $valid
        ? ['ok',   'bi-patch-check-fill', 'Valid registration',
           'This entity is currently registered with the Public Procurement and Disposal of Assets Authority.']
        : ($expired
            ? ['warn', 'bi-clock-history', 'Registration expired',
               'This entity was registered with PPDA but the registration has lapsed.']
            : ['warn', 'bi-exclamation-triangle-fill', 'Registration not active',
               'This entity is on the PPDA register but its status is “' . $sup['status'] . '”.']);
} else {
    $verdict = ['bad', 'bi-x-octagon-fill', 'Not found',
        'No supplier with that code is on the PPDA register. This certificate could not be verified.'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify supplier registration · PPDA</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../../assets/css/theme.css" rel="stylesheet">
  <style>
    body { background: var(--bg); font-family: 'Segoe UI', system-ui, sans-serif; color: var(--text); margin: 0; }
    .wrap { max-width: 560px; margin: 3.5rem auto; padding: 0 1rem; }
    .card2 { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden;
             box-shadow: 0 1px 2px rgba(16,24,32,.06), 0 8px 30px rgba(16,24,32,.06); }
    .top { padding: 1.6rem 1.6rem 1.3rem; display: flex; gap: 1rem; align-items: flex-start; }
    .top .bi { font-size: 2.4rem; flex-shrink: 0; }
    .v-ok   { color: #15803d; } .v-ok .top   { background: #eafaf0; }
    .v-warn { color: #b45309; } .v-warn .top { background: #fdf5e7; }
    .v-bad  { color: #be123c; } .v-bad .top  { background: #fdeef0; }
    .v-title { font-size: 1.15rem; font-weight: 800; }
    .v-sub { color: var(--muted); font-size: .9rem; margin-top: .15rem; }
    .body { padding: 1.4rem 1.6rem 1.7rem; }
    dl { display: grid; grid-template-columns: 130px 1fr; gap: .55rem 1rem; margin: 0; font-size: .92rem; }
    dt { color: var(--muted); font-weight: 600; }
    dd { margin: 0; font-weight: 600; }
    .pill2 { display: inline-block; padding: .12rem .55rem; border-radius: 999px; font-size: .74rem; font-weight: 700;
             background: var(--brand-light); color: var(--brand-dark); margin: 0 .2rem .2rem 0; }
    .foot { text-align: center; color: var(--muted); font-size: .8rem; margin-top: 1.4rem; }
    .brand { text-align: center; margin-bottom: 1rem; font-weight: 800; color: var(--brand-dark); letter-spacing: .02em; }
    .linknote { font-size: .78rem; margin-top: 1rem; padding: .5rem .7rem; border-radius: 8px; }
    .linknote.ok  { background: #eafaf0; color: #15803d; }
    .linknote.bad { background: #fdeef0; color: #be123c; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand"><i class="bi bi-shield-check me-1"></i>PPDA · Supplier registration check</div>
    <div class="card2 v-<?= h($verdict[0]) ?>">
      <div class="top">
        <i class="bi <?= h($verdict[1]) ?>"></i>
        <div>
          <div class="v-title"><?= h($verdict[2]) ?></div>
          <div class="v-sub"><?= h($verdict[3]) ?></div>
        </div>
      </div>
      <?php if ($sup): ?>
        <div class="body">
          <dl>
            <dt>Supplier</dt><dd><?= h($sup['name']) ?><?php if ($sup['trading_name']): ?> <span class="v-sub">t/a <?= h($sup['trading_name']) ?></span><?php endif; ?></dd>
            <dt>Supplier code</dt><dd><?= h($sup['supplier_code'] ?: '—') ?></dd>
            <dt>Country</dt><dd><?= h($sup['country_name'] ?: '—') ?></dd>
            <dt>Status</dt><dd><?= h(ucfirst($sup['status'])) ?></dd>
            <dt>Expires</dt><dd><?= $sup['expire_date'] ? h(date('d F Y', strtotime($sup['expire_date']))) : '—' ?></dd>
            <?php if (!empty($cats)): ?>
              <dt>Categories</dt>
              <dd>
                <?php foreach (['goods' => 'Goods', 'services' => 'Services', 'works' => 'Works'] as $k => $lbl):
                  if (empty($cats[$k])) continue; ?>
                  <div style="margin-bottom:.3rem;"><span class="v-sub"><?= $lbl ?>:</span>
                    <?php foreach ($cats[$k] as $n): ?><span class="pill2"><?= h($n) ?></span><?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </dd>
            <?php endif; ?>
          </dl>
          <?php if ($linkOk === true): ?>
            <div class="linknote ok"><i class="bi bi-check-circle me-1"></i>This verification link is authentic.</div>
          <?php elseif ($linkOk === false): ?>
            <div class="linknote bad"><i class="bi bi-exclamation-triangle me-1"></i>The check code on this link does not match — it may have been altered. The registry details above are still shown from the official register.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="foot">
      Checked against the live PPDA supplier register on <?= h(date('d F Y H:i')) ?>.<br>
      www.ppda.mw
    </div>
  </div>
</body>
</html>
