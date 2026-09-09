<?php
/** Administration — Security Operations dashboard. */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

if (!security_schema_ready($conn)) {
    hub_head('SOC dashboard', 'soc');
    echo '<div class="f-state is-error"><i class="bi bi-database-exclamation"></i>'
       . '<p>Run <code>db/migrations/20260909_security.sql</code> to enable security monitoring.</p></div>';
    hub_foot();
    exit;
}

$m = soc_metrics($conn);

// dense 14-day series for the failed-login bars
$byDay = [];
foreach ($m['fail_by_day'] as $r) $byDay[$r['d']] = (int) $r['n'];
$series = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $series[$d] = $byDay[$d] ?? 0;
}
$peak = max(1, max($series));
$lockDays = array_flip($m['lockout_days']);

hub_head('SOC dashboard', 'soc', 'Authentication, access-control and account-hygiene signals across the platform');
?>

<div class="f-stats">
  <div class="f-stat s-green"><i class="bi bi-box-arrow-in-right"></i>
    <div class="f-stat-value"><?= number_format($m['login_ok_24h']) ?></div>
    <div class="f-stat-label">Successful sign-ins · 24h</div></div>
  <div class="f-stat <?= $m['login_fail_24h'] > 20 ? 's-rose' : 's-amber' ?>"><i class="bi bi-shield-exclamation"></i>
    <div class="f-stat-value"><?= number_format($m['login_fail_24h']) ?></div>
    <div class="f-stat-label">Failed sign-ins · 24h (<?= (int) $m['fail_ratio'] ?>% of attempts)</div></div>
  <div class="f-stat <?= $m['locked_now'] ? 's-rose' : 's-sky' ?>"><i class="bi bi-lock"></i>
    <div class="f-stat-value"><?= number_format($m['locked_now']) ?></div>
    <div class="f-stat-label">Accounts locked right now</div></div>
  <div class="f-stat <?= $m['critical_7d'] ? 's-rose' : 's-green' ?>"><i class="bi bi-exclamation-octagon"></i>
    <div class="f-stat-value"><?= number_format($m['critical_7d']) ?></div>
    <div class="f-stat-label">Critical events · 7d</div></div>
  <div class="f-stat s-violet"><i class="bi bi-slash-circle"></i>
    <div class="f-stat-value"><?= number_format($m['denied_7d']) ?></div>
    <div class="f-stat-label">Permission denials · 7d</div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-bar-chart"></i> Failed sign-ins — last 14 days <span class="text-muted small ms-2">red bar = a lock-out was triggered that day</span></div>
      <div class="f-panel-body">
        <div class="bars">
          <?php foreach ($series as $d => $n): ?>
            <div class="bar <?= isset($lockDays[$d]) ? 'is-alert' : '' ?>" style="height:<?= max(2, round($n / $peak * 118)) ?>px" title="<?= e($d) ?>: <?= (int) $n ?> failed"><?php if ($n): ?><span><?= (int) $n ?></span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
        <div class="bars-x">
          <?php foreach ($series as $d => $n): ?><span><?= e(date('j/n', strtotime($d))) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="f-panel h-100">
      <div class="f-panel-head"><i class="bi bi-clock"></i> After-hours activity</div>
      <div class="f-panel-body">
        <div class="f-stat-value" style="color:var(--text)"><?= number_format($m['after_hours_7d']) ?></div>
        <p class="text-muted small mb-0">events outside 06:00–19:00 in the last 7 days</p>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-0">
  <div class="col-lg-6">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-person-x"></i> Most-targeted usernames · 7d</div>
      <?php if (!$m['top_targets']): ?><div class="f-panel-body"><p class="text-muted small mb-0">No failed sign-ins.</p></div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.85rem;">
          <thead><tr><th>Username</th><th>Failures</th><th>Last</th></tr></thead>
          <tbody><?php foreach ($m['top_targets'] as $t): ?>
            <tr><td class="fw-semibold"><?= e($t['username']) ?></td><td><?= (int) $t['n'] ?></td>
                <td class="text-muted"><?= e(date('d M H:i', strtotime($t['last_ts']))) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-hdd-network"></i> Top source IPs · 7d</div>
      <?php if (!$m['top_ips']): ?><div class="f-panel-body"><p class="text-muted small mb-0">No failed sign-ins.</p></div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.85rem;">
          <thead><tr><th>IP</th><th>Failures</th><th>Last</th></tr></thead>
          <tbody><?php foreach ($m['top_ips'] as $t): ?>
            <tr><td class="font-monospace"><?= e($t['ip']) ?></td><td><?= (int) $t['n'] ?></td>
                <td class="text-muted"><?= e(date('d M H:i', strtotime($t['last_ts']))) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="f-panel mt-3">
  <div class="f-panel-head"><i class="bi bi-slash-circle"></i> Permission denials · 7d <span class="text-muted small ms-2">who is hitting access-control walls</span></div>
  <?php if (!$m['denied_by']): ?><div class="f-panel-body"><p class="text-muted small mb-0">None.</p></div>
  <?php else: ?>
    <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.85rem;">
      <thead><tr><th>User</th><th>Route</th><th>Detail</th><th>Count</th><th>Last</th></tr></thead>
      <tbody><?php foreach ($m['denied_by'] as $r): ?>
        <tr><td class="fw-semibold"><?= e($r['username']) ?></td>
            <td class="text-muted font-monospace" style="font-size:.78rem;"><?= e($r['route']) ?></td>
            <td class="text-muted"><?= e($r['detail'] ?? '') ?></td>
            <td><?= (int) $r['n'] ?></td>
            <td class="text-muted"><?= e(date('d M H:i', strtotime($r['last_ts']))) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="row g-3 mt-0">
  <div class="col-lg-7">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-journal-text"></i> Admin actions</div>
      <?php if (!$m['admin_feed']): ?><div class="f-panel-body"><p class="text-muted small mb-0">Nothing logged.</p></div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.83rem;">
          <tbody><?php foreach ($m['admin_feed'] as $a): ?>
            <tr><td class="text-muted" style="white-space:nowrap;"><?= e(date('d M H:i', strtotime($a['ts']))) ?></td>
                <td class="fw-semibold"><?= e($a['username'] ?? '—') ?></td>
                <td class="text-muted"><?= e($a['detail'] ?? '') ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-heart-pulse"></i> Account hygiene</div>
      <div class="f-panel-body">
        <dl class="kv mb-0">
          <dt>Never signed in</dt><dd><?= (int) $m['never_logged_in'] ?> active account(s)</dd>
          <dt>Stale password</dt><dd><?= (int) $m['stale_password'] ?> &gt; 180 days / unset</dd>
          <dt>Dormant</dt><dd><?= (int) $m['dormant'] ?> active but idle &gt; 90 days</dd>
          <dt>Administrators</dt><dd><?= count($m['admins']) ?> — <?= e(implode(', ', array_map(fn($a) => $a['username'], $m['admins']))) ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<?php if ($m['imports']): ?>
<div class="f-panel mt-3">
  <div class="f-panel-head"><i class="bi bi-database-down"></i> Legacy import runs</div>
  <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.83rem;">
    <thead><tr><th>When</th><th>Phase</th><th>Mode</th><th>Ins</th><th>Upd</th><th>Skip</th></tr></thead>
    <tbody><?php foreach ($m['imports'] as $l): ?>
      <tr><td class="text-muted"><?= e(date('d M H:i', strtotime($l['ts_create']))) ?></td>
          <td class="fw-semibold"><?= e($l['phase']) ?></td>
          <td><?= $l['dry_run'] ? '<span class="pill t-neutral">dry</span>' : '<span class="pill t-green">live</span>' ?></td>
          <td><?= (int) $l['inserted'] ?></td><td><?= (int) $l['updated'] ?></td><td><?= (int) $l['skipped'] ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<p class="text-muted small mt-3">
  Full event stream: <a href="audit.php">Audit log</a>.
  Panel selection follows standard SOC practice — auth-failure trend, top offenders, MTTD-style
  timing, permission-denial and account-anomaly views.
</p>

<?php hub_foot(); ?>
