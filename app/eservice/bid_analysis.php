<?php
/** Bid analysis — list. Defaults to "my queue", toggle to all. */
require __DIR__ . '/inc/layout.php';
es_require_role('officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

$scope = ($_GET['scope'] ?? 'mine') === 'all' ? 'all' : 'mine';
$q     = trim($_GET['q'] ?? '');

$where = [];
$types = '';
$args  = [];
if ($scope === 'mine') {
    $where[] = "a.current_owner_id = ?";
    $types  .= 'i';
    $args[]  = $ES_UID;
}
if ($q !== '') {
    $where[] = "(r.serial_no LIKE ? OR r.subject LIKE ?)";
    $like = "%$q%";
    $types .= 'ss';
    array_push($args, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 10;
$total = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id $whereSql", $types, $args)['c'] ?? 0);
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);

$rows = db_all(
    "SELECT a.id, a.stage, a.final_outcome, a.ts_update,
            r.serial_no, r.subject, p.name AS pde_name,
            o.full_name AS officer_name, c.full_name AS owner_name
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN users o ON o.id = a.officer_id
       LEFT JOIN users c ON c.id = a.current_owner_id
       $whereSql
      ORDER BY a.ts_update DESC
      LIMIT $per OFFSET " . (($page - 1) * $per),
    $types, $args
);

$stageTint = [
    'draft' => 't-neutral', 'supervisor_review' => 't-amber', 'director_review' => 't-amber',
    'dg_review' => 't-sky', 'board_review' => 't-sky', 'approved' => 't-green',
    'returned' => 't-rose', 'rejected' => 't-rose',
];

es_layout_head('Bid analysis', 'analysis');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title">Bid analysis</h1>
    <p class="f-subtitle">Structured post-procurement reviews and their workflow stage</p>
  </div>
</div>

<div class="f-chips">
  <a href="?scope=mine" class="chip <?= $scope === 'mine' ? 'active' : '' ?>">Awaiting my action</a>
  <a href="?scope=all" class="chip <?= $scope === 'all' ? 'active' : '' ?>">All analyses</a>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="scope" value="<?= e($scope) ?>">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search serial or subject…" autocomplete="off">
  </div>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Search</button>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-clipboard-check"></i><p><?= $scope === 'mine' ? 'Nothing is waiting on you right now.' : 'No analyses yet.' ?></p></div>
<?php else: ?>
  <div class="f-panel table-responsive">
    <table class="table table-borderless f-table align-middle mb-0">
      <thead><tr><th>Serial</th><th>Subject</th><th>PDE</th><th>Officer</th><th>Stage</th><th>With</th><th>Updated</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="font-monospace"><?= e($r['serial_no']) ?></td>
            <td class="fw-semibold"><?= e($r['subject']) ?></td>
            <td class="text-muted"><?= e($r['pde_name'] ?? '—') ?></td>
            <td class="text-muted"><?= e($r['officer_name'] ?? '—') ?></td>
            <td><?= es_status_badge($r['stage']) ?></td>
            <td class="text-muted"><?= e($r['owner_name'] ?? '—') ?></td>
            <td class="text-muted"><?= e(date('d M Y', strtotime($r['ts_update']))) ?></td>
            <td class="text-end"><a href="bid_analysis_view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-success">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query(['scope' => $scope, 'q' => $q, 'page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
