<?php
/** e-Services reference data — PDEs, methods, review types, categories, currencies, countries. */
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/refdata.php';
es_require_perm('refdata.manage');

global $conn;

$defs = es_refdata_defs();
$entity = isset($_GET['entity'], $defs[$_GET['entity']]) ? $_GET['entity'] : 'pde';
$def = $defs[$entity];

$counts = [];
foreach ($defs as $k => $d) {
    $counts[$k] = (int) ($conn->query("SELECT COUNT(*) FROM `{$d['table']}`")->fetch_row()[0] ?? 0);
}

$q = trim($_GET['q'] ?? '');
$cols = array_merge([$def['key']], $def['list'], ['active']);
$cols = array_values(array_unique($cols));
$select = implode(', ', array_map(fn($c) => "`$c`", $cols));

$where = '';
$args = [];
$types = '';
if ($q !== '' && $def['list']) {
    $like = "%$q%";
    $where = 'WHERE ' . implode(' OR ', array_map(fn($c) => "`$c` LIKE ?", $def['list']));
    $types = str_repeat('s', count($def['list']));
    $args  = array_fill(0, count($def['list']), $like);
}
$orderCol = $def['list'][0] ?? $def['key'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 10;
$rowTotal = (int) (db_one("SELECT COUNT(*) c FROM `{$def['table']}` $where", $types, $args)['c'] ?? 0);
$rowPages = max(1, (int) ceil($rowTotal / $per));
$page = min($page, $rowPages);
$rows = db_all("SELECT $select FROM `{$def['table']}` $where ORDER BY `$orderCol` LIMIT $per OFFSET " . (($page - 1) * $per), $types, $args);

es_layout_head('Reference data', 'refdata');
es_admin_nav('refdata');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title">Reference data</h1>
    <p class="f-subtitle">Lookup values used across the bid-registry and supplier forms</p>
  </div>
  <button type="button" class="btn btn-success btn-sm"
          data-drawer="admin_refdata_form.php?entity=<?= e($entity) ?>&amp;partial=1"
          data-drawer-title="New <?= e(rtrim($def['label'], 's')) ?>">
    <i class="bi bi-plus-lg me-1"></i>Add
  </button>
</div>

<div class="f-chips">
  <?php foreach ($defs as $k => $d): ?>
    <a href="?entity=<?= e($k) ?>" class="chip <?= $entity === $k ? 'active' : '' ?>">
      <?= e($d['label']) ?><span class="chip-count"><?= (int) $counts[$k] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="entity" value="<?= e($entity) ?>">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search <?= e(strtolower($def['label'])) ?>…" autocomplete="off">
  </div>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Search</button>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-list-columns"></i><p>No <?= e(strtolower($def['label'])) ?> yet.</p></div>
<?php else: ?>
  <div class="f-panel table-responsive">
    <table class="table table-borderless f-table align-middle mb-0">
      <thead>
        <tr>
          <?php foreach ($def['list'] as $c): ?><th><?= e(ucwords(str_replace('_', ' ', $c))) ?></th><?php endforeach; ?>
          <th>Active</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $kv = $r[$def['key']]; ?>
          <tr>
            <?php foreach ($def['list'] as $c): ?>
              <td class="<?= $c === $def['list'][0] ? 'fw-semibold' : 'text-muted' ?>">
                <?= $c === 'fee' ? number_format((float) $r[$c]) : e($r[$c] ?? '—') ?>
              </td>
            <?php endforeach; ?>
            <td><span class="pill <?= $r['active'] ? 't-green' : 't-neutral' ?>"><?= $r['active'] ? 'active' : 'inactive' ?></span></td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit"
                      data-drawer="admin_refdata_form.php?entity=<?= e($entity) ?>&amp;id=<?= e(urlencode((string) $kv)) ?>&amp;partial=1"
                      data-drawer-title="Edit <?= e(rtrim($def['label'], 's')) ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" action="admin_refdata_save.php" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
                <input type="hidden" name="entity" value="<?= e($entity) ?>">
                <input type="hidden" name="op" value="toggle">
                <input type="hidden" name="key" value="<?= e($kv) ?>">
                <button class="btn btn-sm btn-outline-secondary"><?= $r['active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= es_pager($page, $rowPages, fn(int $p) => '?' . http_build_query(['entity' => $entity, 'q' => $q, 'page' => $p]), $rowTotal, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
