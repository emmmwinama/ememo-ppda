<?php
/** Administration — e-Services reference data (PDEs, methods, categories, currencies, countries). */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

if (!@$conn->query("SHOW TABLES LIKE 'es_pde'")->num_rows) {
    hub_head('e-Services reference data', 'es_ref');
    echo '<div class="f-state is-error"><i class="bi bi-database"></i><p>The e-Services schema isn\'t installed.</p></div>';
    hub_foot();
    exit;
}

require __DIR__ . '/../../app/eservice/inc/refdata.php';   // es_refdata_defs()
$defs   = es_refdata_defs();
$entity = isset($defs[$_GET['entity'] ?? '']) ? $_GET['entity'] : 'pde';
$def    = $defs[$entity];
$keyCol = $def['key'];
$keyIsStr = $keyCol === 'code';

$counts = [];
foreach ($defs as $k => $d) {
    $counts[$k] = (int) ($conn->query("SELECT COUNT(*) FROM `{$d['table']}`")->fetch_row()[0] ?? 0);
}

$editKey = $_GET['edit'] ?? '';
$editRow = null;
if ($editKey !== '') {
    $kv = $keyIsStr ? (string) $editKey : (int) $editKey;
    $editRow = db_one("SELECT * FROM `{$def['table']}` WHERE `$keyCol` = ?", $keyIsStr ? 's' : 'i', [$kv]);
}

$listCols = array_values(array_unique(array_merge([$keyCol], $def['list'], ['active'])));
$select   = implode(', ', array_map(fn($c) => "`$c`", $listCols));
$orderCol = $def['list'][0] ?? $keyCol;
$rows = db_all("SELECT $select FROM `{$def['table']}` ORDER BY `$orderCol`");

hub_head('e-Services reference data', 'es_ref', 'Lookup values used across the bid-registry and supplier forms');
?>

<div class="f-chips">
  <?php foreach ($defs as $k => $d): ?>
    <a href="?entity=<?= e($k) ?>" class="chip <?= $entity === $k ? 'active' : '' ?>"><?= e($d['label']) ?><span class="chip-count"><?= (int) $counts[$k] ?></span></a>
  <?php endforeach; ?>
</div>

<div class="f-panel mb-3">
  <div class="f-panel-head"><i class="bi bi-plus-lg"></i> <?= $editRow ? 'Edit' : 'Add' ?> <?= e(rtrim($def['label'], 's')) ?></div>
  <div class="f-panel-body">
    <form method="post" action="es_refdata_save.php" class="row g-3 align-items-end">
      <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
      <input type="hidden" name="entity" value="<?= e($entity) ?>">
      <input type="hidden" name="op" value="save">
      <?php if ($editRow): ?><input type="hidden" name="key" value="<?= e($editRow[$keyCol]) ?>"><?php endif; ?>

      <?php foreach ($def['fields'] as $name => $fld):
        $locked = $editRow && !empty($fld['lock_on_edit']); ?>
        <div class="col-md-4">
          <label class="form-label"><?= e($fld['label']) ?><?= !empty($fld['required']) ? ' <span class="text-danger">*</span>' : '' ?></label>
          <?php if (($fld['type'] ?? 'text') === 'select'): ?>
            <select name="<?= e($name) ?>" class="form-select" <?= !empty($fld['required']) ? 'required' : '' ?>>
              <?php foreach ($fld['options'] as $opt): ?>
                <option value="<?= e($opt) ?>" <?= ($editRow[$name] ?? '') === $opt ? 'selected' : '' ?>><?= e(ucfirst($opt)) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="<?= e($fld['type'] ?? 'text') ?>" name="<?= e($name) ?>" class="form-control"
                   value="<?= e($editRow[$name] ?? '') ?>"
                   <?= !empty($fld['required']) ? 'required' : '' ?>
                   <?= !empty($fld['maxlength']) ? 'maxlength="' . (int) $fld['maxlength'] . '"' : '' ?>
                   <?= ($fld['type'] ?? '') === 'number' ? 'step="0.01"' : '' ?>
                   <?= $locked ? 'readonly' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="col-md-3">
        <label class="d-flex align-items-center gap-2" style="font-size:.9rem;">
          <input type="checkbox" name="active" value="1" <?= (!$editRow || $editRow['active']) ? 'checked' : '' ?>> Active
        </label>
      </div>
      <div class="col-md-3">
        <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= $editRow ? 'Save' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="?entity=<?= e($entity) ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-list-columns"></i><p>No <?= e(strtolower($def['label'])) ?> yet.</p></div>
<?php else: ?>
  <div class="f-panel table-responsive">
    <table class="table table-borderless f-table align-middle mb-0">
      <thead><tr>
        <?php foreach ($def['list'] as $c): ?><th><?= e(ucwords(str_replace('_', ' ', $c))) ?></th><?php endforeach; ?>
        <th>Active</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $kv = $r[$keyCol]; ?>
          <tr>
            <?php foreach ($def['list'] as $c): ?>
              <td class="<?= $c === $def['list'][0] ? 'fw-semibold' : 'text-muted' ?>"><?= $c === 'fee' ? number_format((float) $r[$c]) : e($r[$c] ?? '—') ?></td>
            <?php endforeach; ?>
            <td><span class="pill <?= $r['active'] ? 't-green' : 't-neutral' ?>"><?= $r['active'] ? 'active' : 'inactive' ?></span></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="?entity=<?= e($entity) ?>&edit=<?= e(urlencode((string) $kv)) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="es_refdata_save.php" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
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
<?php endif; ?>

<?php hub_foot(); ?>
