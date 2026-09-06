<?php
/** Reference-data add / edit form. Drawer fragment on ?partial=1. */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/refdata.php';

$partial = isset($_GET['partial']);
if (!$partial) require_once __DIR__ . '/inc/layout.php';
es_require_perm('refdata.manage');

global $conn;

$defs = es_refdata_defs();
$entity = $_GET['entity'] ?? '';
if (!isset($defs[$entity])) { http_response_code(400); exit('Unknown entity.'); }
$def = $defs[$entity];

$keyVal = $_GET['id'] ?? '';
$row = null;
if ($keyVal !== '') {
    $kt = $def['key'] === 'code' ? 's' : 'i';
    $kv = $def['key'] === 'code' ? (string) $keyVal : (int) $keyVal;
    $row = db_one("SELECT * FROM `{$def['table']}` WHERE `{$def['key']}` = ?", $kt, [$kv]);
    if (!$row) { http_response_code(404); exit('Record not found.'); }
}
$isEdit = (bool) $row;
$v = fn(string $k, $d = '') => e($row[$k] ?? $d);

ob_start(); ?>
  <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
  <input type="hidden" name="entity" value="<?= e($entity) ?>">
  <input type="hidden" name="op" value="save">
  <?php if ($isEdit): ?><input type="hidden" name="key" value="<?= e($row[$def['key']]) ?>"><?php endif; ?>

  <div class="row g-3">
    <?php foreach ($def['fields'] as $name => $fld):
      $locked = $isEdit && !empty($fld['lock_on_edit']); ?>
      <div class="col-12">
        <label class="form-label"><?= e($fld['label']) ?><?= !empty($fld['required']) ? ' <span class="text-danger">*</span>' : '' ?></label>
        <?php if (($fld['type'] ?? 'text') === 'select'): ?>
          <select name="<?= e($name) ?>" class="form-select" <?= !empty($fld['required']) ? 'required' : '' ?> <?= $locked ? 'disabled' : '' ?>>
            <?php foreach ($fld['options'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($row[$name] ?? '') === $opt ? 'selected' : '' ?>><?= e(ucfirst($opt)) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="<?= e($fld['type'] ?? 'text') ?>" name="<?= e($name) ?>" class="form-control"
                 value="<?= $v($name) ?>"
                 <?= !empty($fld['required']) ? 'required' : '' ?>
                 <?= !empty($fld['maxlength']) ? 'maxlength="' . (int) $fld['maxlength'] . '"' : '' ?>
                 <?= !empty($fld['upper']) ? 'style="text-transform:uppercase;"' : '' ?>
                 <?= ($fld['type'] ?? '') === 'number' ? 'step="0.01"' : '' ?>
                 <?= $locked ? 'readonly' : '' ?>>
        <?php endif; ?>
        <?php if ($locked): ?><div class="form-text">Cannot be changed after creation.</div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="col-12">
      <label class="d-flex align-items-center gap-2">
        <input type="checkbox" name="active" value="1" <?= (!$isEdit || $row['active']) ? 'checked' : '' ?>> Active
      </label>
    </div>
  </div>
<?php
$FIELDS = ob_get_clean();
$submit = $isEdit ? 'Save changes' : 'Add ' . strtolower(rtrim($def['label'], 's'));

if ($partial) { ?>
  <form method="post" action="admin_refdata_save.php" class="es-drawer-form">
    <div class="es-drawer-fields"><?= $FIELDS ?></div>
    <div class="es-drawer-foot">
      <button type="button" class="btn btn-outline-secondary" data-drawer-close>Cancel</button>
      <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= e($submit) ?></button>
    </div>
  </form>
<?php exit; }

es_layout_head(($isEdit ? 'Edit ' : 'New ') . rtrim($def['label'], 's'), 'refdata'); ?>
<div class="f-head"><h1 class="f-title"><?= $isEdit ? 'Edit' : 'New' ?> <?= e(rtrim($def['label'], 's')) ?></h1></div>
<form method="post" action="admin_refdata_save.php" class="f-card" style="max-width:560px; gap:1rem;">
  <?= $FIELDS ?>
  <div class="d-flex justify-content-end gap-2">
    <a href="admin_refdata.php?entity=<?= e($entity) ?>" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= e($submit) ?></button>
  </div>
</form>
<?php es_layout_foot(); ?>
