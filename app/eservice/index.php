<?php
/** e-Services dashboard. */
require __DIR__ . '/inc/layout.php';

global $conn, $ES_UID;

// Detect whether the schema has been installed yet.
$installed = (bool) $conn->query("SHOW TABLES LIKE 'es_bid_registry'")->num_rows;

$stats = ['suppliers' => 0, 'registry_open' => 0, 'analysis_active' => 0, 'my_queue' => 0];
$recent = [];

if ($installed) {
    $stats['suppliers']       = (int) ($conn->query("SELECT COUNT(*) FROM es_supplier")->fetch_row()[0] ?? 0);
    $stats['registry_open']   = (int) ($conn->query("SELECT COUNT(*) FROM es_bid_registry WHERE status IN ('pending_registry','pending_allocation','assigned','in_analysis')")->fetch_row()[0] ?? 0);
    $stats['analysis_active']  = (int) ($conn->query("SELECT COUNT(*) FROM es_bid_analysis WHERE stage NOT IN ('approved','rejected')")->fetch_row()[0] ?? 0);
    $stats['my_queue']        = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis WHERE current_owner_id = ? AND stage NOT IN ('approved','rejected','draft')", 'i', [$ES_UID])['c'] ?? 0);

    $recent = db_all(
        "SELECT r.id, r.serial_no, r.subject, r.status, r.ts_update,
                p.name AS pde_name
           FROM es_bid_registry r
           LEFT JOIN es_pde p ON p.id = r.pde_id
          ORDER BY r.ts_update DESC
          LIMIT 8"
    );
}

$statusTint = [
    'pending_registry' => 't-amber', 'returned_to_pde' => 't-rose', 'pending_allocation' => 't-sky',
    'assigned' => 't-sky', 'in_analysis' => 't-amber',
    'completed' => 't-green', 'closed' => 't-slate', 'withdrawn' => 't-rose',
];

es_layout_head('Dashboard', 'dashboard');
?>

<div class="f-head">
  <h1 class="f-title">e-Services</h1>
  <p class="f-subtitle">Supplier register and bid-analysis workflow</p>
</div>

<?php if (!$installed): ?>
  <div class="f-panel" style="padding:1.25rem; border-color:#f3ddb6; background:#fffdf5;">
    <h2 style="font-size:1rem; font-weight:800; margin:0 0 .4rem;">Schema not installed yet</h2>
    <p class="text-muted" style="margin:0 0 .6rem; font-size:.9rem;">
      Load the two SQL files into the ememo database, then reload this page:
    </p>
    <pre style="background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-md); padding:.8rem; font-size:.82rem; margin:0;">mysql -u ppda_u375699389_ememo -p ppda_u375699389_ememo &lt; app/eservice/sql/01_schema.sql
mysql -u ppda_u375699389_ememo -p ppda_u375699389_ememo &lt; app/eservice/sql/02_seed.sql</pre>
  </div>
<?php else: ?>

  <div class="kpi-row" style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1.6rem;">
    <?php foreach ([
      ['Suppliers on register', $stats['suppliers'],      't-green',   'building'],
      ['Open in registry',      $stats['registry_open'],  't-amber',   'journal-text'],
      ['Analyses in progress',  $stats['analysis_active'], 't-sky',     'clipboard-data'],
      ['Awaiting my action',    $stats['my_queue'],       't-rose',    'person-workspace'],
    ] as [$label, $value, $tint, $icon]): ?>
      <div class="f-card" style="flex:1 1 170px;">
        <div class="kpi-icon <?= $tint ?>" style="width:38px;height:38px;border-radius:11px;display:inline-flex;align-items:center;justify-content:center;font-size:1.05rem;margin-bottom:.6rem;">
          <i class="bi bi-<?= $icon ?>"></i>
        </div>
        <div style="font-size:1.6rem;font-weight:800;line-height:1;color:var(--text);"><?= number_format($value) ?></div>
        <div style="font-size:.8rem;color:var(--muted);margin-top:.35rem;"><?= e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="f-panel">
    <div class="f-panel-head"><i class="bi bi-clock-history"></i> Recent registry activity</div>
    <?php if (!$recent): ?>
      <div class="f-state"><i class="bi bi-journal"></i><p>Nothing in the registry yet.</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-borderless f-table align-middle mb-0">
          <thead><tr><th>Serial</th><th>Subject</th><th>PDE</th><th>Status</th><th>Updated</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td class="font-monospace"><a href="bid_registry_view.php?id=<?= (int) $r['id'] ?>" class="text-decoration-none"><?= e($r['serial_no']) ?></a></td>
                <td class="fw-semibold"><?= e($r['subject']) ?></td>
                <td class="text-muted"><?= e($r['pde_name'] ?? '—') ?></td>
                <td><?= es_status_badge($r['status']) ?></td>
                <td class="text-muted"><?= e(date('d M Y', strtotime($r['ts_update']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php es_layout_foot(); ?>
