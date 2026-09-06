<?php
/** Suppliers — read-only lookup register (legacy data lands here via the importer). */
require __DIR__ . '/inc/layout.php';

global $conn;

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 10;

$where = [];
$types = '';
$args  = [];
if ($q !== '') {
    $where[] = "(s.name LIKE ? OR s.trading_name LIKE ? OR s.tin LIKE ? OR s.supplier_code LIKE ?)";
    $like = "%$q%";
    $types .= 'ssss';
    array_push($args, $like, $like, $like, $like);
}
$vs = ['pending', 'active', 'expired', 'suspended', 'blacklisted'];
if (in_array($status, $vs, true)) { $where[] = 's.status = ?'; $types .= 's'; $args[] = $status; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$installed = (bool) $conn->query("SHOW TABLES LIKE 'es_supplier'")->num_rows;
$total = $installed ? (int) (db_one("SELECT COUNT(*) c FROM es_supplier s $wsql", $types, $args)['c'] ?? 0) : 0;
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$rows  = $installed ? db_all(
    "SELECT s.id, s.supplier_code, s.name, s.trading_name, s.tin, s.status, s.expire_date, s.source,
            c.name AS country_name
       FROM es_supplier s LEFT JOIN es_country c ON c.id = s.country_id
       $wsql ORDER BY s.name LIMIT $per OFFSET " . (($page - 1) * $per),
    $types, $args
) : [];

$tint = ['active' => 't-green', 'pending' => 't-amber', 'expired' => 't-rose', 'suspended' => 't-rose', 'blacklisted' => 't-dark'];
$pageUrl = fn(int $p) => '?' . http_build_query(['q' => $q, 'status' => $status, 'page' => $p]);

es_layout_head('Suppliers', 'suppliers');
?>

<div class="f-head">
  <h1 class="f-title">Suppliers</h1>
  <p class="f-subtitle">Registered supplier lookup — data imported from the e-Services register</p>
</div>

<?php if (!$installed): ?>
  <div class="f-state"><i class="bi bi-database"></i><p>Run <code>sql/01_schema.sql</code> to create the supplier tables.</p></div>
<?php else: ?>
  <form class="f-toolbar" method="get">
    <div class="f-search">
      <i class="bi bi-search"></i>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, trading name, TIN or code…" autocomplete="off">
    </div>
    <select name="status" class="form-select form-select-sm f-control" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach ($vs as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Search</button>
  </form>

  <?php if (!$rows): ?>
    <div class="f-state"><i class="bi bi-building"></i><p><?= $total === 0 ? 'No suppliers imported yet. See sql/README for the importer.' : 'No suppliers match this view.' ?></p></div>
  <?php else: ?>
    <div class="f-panel table-responsive">
      <table class="table table-borderless f-table align-middle mb-0">
        <thead><tr><th>Code</th><th>Name</th><th>TIN</th><th>Country</th><th>Status</th><th>Expires</th><th>Source</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="font-monospace"><?= e($r['supplier_code'] ?? '—') ?></td>
              <td class="fw-semibold"><?= e($r['name']) ?><?php if ($r['trading_name']): ?><br><span class="text-muted small">t/a <?= e($r['trading_name']) ?></span><?php endif; ?></td>
              <td class="text-muted"><?= e($r['tin'] ?? '—') ?></td>
              <td class="text-muted"><?= e($r['country_name'] ?? '—') ?></td>
              <td><span class="pill <?= $tint[$r['status']] ?? 't-neutral' ?>"><?= e($r['status']) ?></span></td>
              <td class="text-muted"><?= e($r['expire_date'] ?? '—') ?></td>
              <td class="text-muted small"><?= e($r['source']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= es_pager($page, $pages, $pageUrl, $total, $per) ?>
  <?php endif; ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
