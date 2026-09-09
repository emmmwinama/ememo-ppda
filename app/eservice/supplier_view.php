<?php
/** Supplier — full record, compiled from every es_supplier_* table. */
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/cert.php';

global $conn;

$id = (int) ($_GET['id'] ?? 0);
$s  = db_one(
    "SELECT s.*, c.name AS country_name
       FROM es_supplier s
       LEFT JOIN es_country c ON c.id = s.country_id
      WHERE s.id = ?",
    'i', [$id]
);
if (!$s) {
    es_layout_head('Supplier not found', 'suppliers');
    echo '<div class="f-state is-error"><i class="bi bi-building-x"></i><p>No supplier with that id.</p></div>';
    es_layout_foot();
    exit;
}

// Bank details + shareholder identity numbers are internal-only.
$full = es_can('supplier.view');

$cats = db_all(
    "SELECT c.type, c.name FROM es_supplier_category sc
       JOIN es_category c ON c.id = sc.category_id
      WHERE sc.supplier_id = ? ORDER BY c.type, c.name",
    'i', [$id]
);
$catBy = ['goods' => [], 'services' => [], 'works' => []];
foreach ($cats as $c) $catBy[$c['type']][] = $c['name'];

$shareholders = db_all("SELECT * FROM es_supplier_shareholder WHERE supplier_id = ? ORDER BY percentage DESC, last_name", 'i', [$id]);
$banks        = $full ? db_all("SELECT * FROM es_supplier_bank WHERE supplier_id = ? ORDER BY id", 'i', [$id]) : [];
$certs        = db_all("SELECT * FROM es_supplier_certificate WHERE supplier_id = ? ORDER BY COALESCE(issue_date,'1900-01-01') DESC, id DESC", 'i', [$id]);
$atts         = db_all("SELECT id, kind, original_name, file_path, ts_create FROM es_supplier_attachment WHERE supplier_id = ? ORDER BY kind, id", 'i', [$id]);

$statusTint = ['active' => 't-green', 'pending' => 't-amber', 'expired' => 't-rose', 'suspended' => 't-rose', 'blacklisted' => 't-dark'];
$expired    = $s['expire_date'] && strtotime($s['expire_date']) < time();

$row = function (string $label, $value, bool $mono = false) {
    if ($value === null || $value === '' ) $value = '—';
    echo '<div class="col-md-4"><div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">'
       . e($label) . '</div><div class="fw-semibold' . ($mono ? ' font-monospace' : '') . '">' . e($value) . '</div></div>';
};

es_layout_head('Supplier · ' . $s['name'], 'suppliers');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title"><?= e($s['name']) ?>
      <span style="vertical-align:middle;"><span class="pill <?= $statusTint[$s['status']] ?? 't-neutral' ?>"><?= e($s['status']) ?></span></span>
      <?php if ($expired): ?><span class="pill t-rose">expired</span><?php endif; ?>
    </h1>
    <p class="f-subtitle">
      <?php if ($s['trading_name']): ?>t/a <?= e($s['trading_name']) ?> · <?php endif; ?>
      <?php if ($s['supplier_code']): ?>Code <span class="font-monospace"><?= e($s['supplier_code']) ?></span> · <?php endif; ?>
      Source: <?= e($s['source']) ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="suppliers.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Register</a>
    <a href="supplier_certificate.php?id=<?= (int) $id ?>" class="btn btn-success btn-sm" target="_blank">
      <i class="bi bi-file-earmark-pdf me-1"></i>Certificate PDF
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">

    <div class="f-panel" style="padding:1.3rem;">
      <div class="fw-bold mb-3"><i class="bi bi-info-circle me-1"></i>Identity</div>
      <div class="row g-3" style="font-size:.9rem;">
        <?php
        $row('Registered name', $s['name']);
        $row('Trading name', $s['trading_name']);
        $row('Supplier code', $s['supplier_code'], true);
        $row('Country of establishment', $s['country_name']);
        $row('Date registered', $s['date_registered'] ? date('d M Y', strtotime($s['date_registered'])) : null);
        $row('Registration expires', $s['expire_date'] ? date('d M Y', strtotime($s['expire_date'])) : null);
        $row('Years in operation', $s['years_operations']);
        $row('Employees', $s['num_employees']);
        if ($full) {
            $row('TIN', $s['tin'], true);
            $row('VAT number', $s['vat_number'], true);
            $row('NCIC number', $s['ncic_number'], true);
            $row('Company number', $s['company_number'], true);
        }
        ?>
      </div>
    </div>

    <div class="f-panel mt-3" style="padding:1.3rem;">
      <div class="fw-bold mb-3"><i class="bi bi-telephone me-1"></i>Contact</div>
      <div class="row g-3" style="font-size:.9rem;">
        <?php
        $row('Email', $s['email']);
        $row('Website', $s['website']);
        $row('Business phone', $s['business_phone']);
        $row('Mobile', $s['mobile_phone']);
        $row('City', $s['city']);
        $row('Postal address', $s['postal_address']);
        $row('Physical address', $s['physical_address']);
        ?>
      </div>
    </div>

    <div class="f-panel mt-3" style="padding:1.3rem;">
      <div class="fw-bold mb-2"><i class="bi bi-tags me-1"></i>Categories</div>
      <?php foreach (['goods' => 'Goods', 'services' => 'Services', 'works' => 'Works'] as $k => $lbl): ?>
        <div class="mb-2">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em;"><?= $lbl ?></div>
          <?php if ($catBy[$k]): ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
              <?php foreach ($catBy[$k] as $n): ?><span class="pill t-green"><?= e($n) ?></span><?php endforeach; ?>
            </div>
          <?php else: ?><span class="text-muted small">None</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($shareholders): ?>
    <div class="f-panel mt-3 table-responsive">
      <div class="f-panel-head"><i class="bi bi-people"></i> Shareholders / directors</div>
      <table class="table table-borderless f-table align-middle mb-0" style="font-size:.86rem;">
        <thead><tr><th>Name</th><th>Gender</th><?php if ($full): ?><th>National ID</th><th>TIN</th><?php endif; ?><th>Contact</th><th>%</th></tr></thead>
        <tbody>
          <?php foreach ($shareholders as $h): ?>
            <tr>
              <td class="fw-semibold"><?= e(trim($h['first_name'] . ' ' . $h['last_name'])) ?></td>
              <td class="text-muted"><?= e($h['gender'] ?: '—') ?></td>
              <?php if ($full): ?>
                <td class="font-monospace"><?= e($h['national_id'] ?: '—') ?></td>
                <td class="font-monospace"><?= e($h['tin'] ?: '—') ?></td>
              <?php endif; ?>
              <td class="text-muted small"><?= e($h['email'] ?: $h['contact_number'] ?: '—') ?></td>
              <td><?= $h['percentage'] !== null ? e(rtrim(rtrim(number_format((float) $h['percentage'], 2), '0'), '.')) . '%' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($full && $banks): ?>
    <div class="f-panel mt-3 table-responsive">
      <div class="f-panel-head"><i class="bi bi-bank"></i> Bank accounts <span class="text-muted small ms-2">internal only</span></div>
      <table class="table table-borderless f-table align-middle mb-0" style="font-size:.86rem;">
        <thead><tr><th>Bank / branch</th><th>Account name</th><th>Account no.</th><th>Type</th><th>Currency</th><th>SWIFT</th></tr></thead>
        <tbody>
          <?php foreach ($banks as $b): ?>
            <tr>
              <td class="fw-semibold"><?= e($b['bank_name']) ?><?php if ($b['branch_name']): ?><br><span class="text-muted small"><?= e($b['branch_name']) ?></span><?php endif; ?></td>
              <td class="text-muted"><?= e($b['account_name'] ?: '—') ?></td>
              <td class="font-monospace"><?= e($b['account_number'] ?: '—') ?></td>
              <td class="text-muted"><?= e($b['account_type'] ?: '—') ?></td>
              <td class="text-muted"><?= e($b['currency_code'] ?: '—') ?></td>
              <td class="font-monospace"><?= e($b['swift_code'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>

  <div class="col-lg-4">
    <div class="f-panel" style="padding:1.3rem;">
      <div class="fw-bold mb-2"><i class="bi bi-patch-check me-1"></i>Registration certificate</div>
      <?php if ($certs): $c0 = $certs[0]; ?>
        <div class="kv mb-3" style="grid-template-columns:auto 1fr;">
          <dt>Certificate no.</dt><dd class="font-monospace"><?= e($c0['certificate_no'] ?: '—') ?></dd>
          <dt>Issued</dt><dd><?= $c0['issue_date'] ? e(date('d M Y', strtotime($c0['issue_date']))) : '—' ?></dd>
          <dt>Expires</dt><dd><?= $c0['expire_date'] ? e(date('d M Y', strtotime($c0['expire_date']))) : '—' ?></dd>
        </div>
      <?php else: ?>
        <p class="text-muted small">No certificate record on file — a certificate will be generated from the supplier's registration details.</p>
      <?php endif; ?>
      <a href="supplier_certificate.php?id=<?= (int) $id ?>" class="btn btn-success btn-sm w-100" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i>Generate certificate PDF
      </a>
      <?php $vcode = (string) ($s['supplier_code'] ?: $s['id']); ?>
      <a href="supplier_verify.php?code=<?= e(rawurlencode($vcode)) ?>&amp;c=<?= e(es_cert_token($vcode)) ?>"
         class="btn btn-outline-secondary btn-sm w-100 mt-2" target="_blank">
        <i class="bi bi-qr-code me-1"></i>Open public verification
      </a>
    </div>

    <div class="f-panel mt-3" style="padding:1.3rem;">
      <div class="fw-bold mb-2"><i class="bi bi-paperclip me-1"></i>Documents<?= $atts ? ' (' . count($atts) . ')' : '' ?></div>
      <?php if (!$atts): ?>
        <p class="text-muted small mb-0">No documents attached.</p>
      <?php else: ?>
        <div class="d-flex flex-column gap-2" style="font-size:.86rem;">
          <?php foreach ($atts as $a): ?>
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-file-earmark-text text-muted mt-1"></i>
              <div>
                <a href="supplier_attachment.php?id=<?= (int) $a['id'] ?>" target="_blank"><?= e($a['original_name'] ?: basename($a['file_path'])) ?></a>
                <?php if ($a['kind']): ?><div class="text-muted small"><?= e($a['kind']) ?></div><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="f-panel mt-3" style="padding:1.3rem; font-size:.85rem;">
      <div class="fw-bold mb-2"><i class="bi bi-clock-history me-1"></i>Record</div>
      <div class="kv" style="grid-template-columns:auto 1fr;">
        <dt>Added</dt><dd><?= e(date('d M Y', strtotime($s['ts_create']))) ?></dd>
        <dt>Updated</dt><dd><?= e(date('d M Y', strtotime($s['ts_update']))) ?></dd>
        <dt>Source</dt><dd><?= e($s['source']) ?><?= $s['legacy_id'] ? ' · legacy #' . (int) $s['legacy_id'] : '' ?></dd>
      </div>
    </div>
  </div>
</div>

<?php es_layout_foot(); ?>
