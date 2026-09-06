<?php
/** Admin — add / edit a role (drawer fragment on ?partial=1). */
require __DIR__ . '/inc/bootstrap.php';
$partial = isset($_GET['partial']);
if (!$partial) require_once __DIR__ . '/inc/layout.php';
es_require_perm('rbac.manage');

global $conn;

$key = $_GET['role'] ?? '';
$row = $key !== '' ? db_one("SELECT * FROM es_role WHERE role_key = ?", 's', [$key]) : null;
if ($key !== '' && !$row) { http_response_code(404); exit('Role not found.'); }
$isEdit  = (bool) $row;
$isSystem = $isEdit && $row['is_system'];
$v = fn($k, $d = '') => e($row[$k] ?? $d);

ob_start(); ?>
  <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
  <input type="hidden" name="op" value="save">
  <?php if ($isEdit): ?><input type="hidden" name="orig_key" value="<?= e($row['role_key']) ?>"><?php endif; ?>
  <div class="row g-3">
    <div class="col-12">
      <label class="form-label">Key <span class="text-danger">*</span></label>
      <input type="text" name="role_key" class="form-control" value="<?= $v('role_key') ?>" required
             pattern="[a-z0-9_.]+" maxlength="40" <?= $isSystem ? 'readonly' : '' ?>>
      <div class="form-text"><?= $isSystem ? 'System role key can\'t change.' : 'Lowercase letters, digits, dot, underscore. Used in code checks.' ?></div>
    </div>
    <div class="col-12">
      <label class="form-label">Label <span class="text-danger">*</span></label>
      <input type="text" name="label" class="form-control" value="<?= $v('label') ?>" required maxlength="80">
    </div>
    <div class="col-12">
      <label class="form-label">Description</label>
      <input type="text" name="description" class="form-control" value="<?= $v('description') ?>" maxlength="255">
    </div>
    <div class="col-md-4">
      <label class="form-label">Sort</label>
      <input type="number" name="sort" class="form-control" value="<?= $v('sort', '100') ?>">
    </div>
  </div>
<?php
$FIELDS = ob_get_clean();

if ($partial) { ?>
  <form method="post" action="admin_roles_save.php" class="es-drawer-form">
    <div class="es-drawer-fields"><?= $FIELDS ?></div>
    <div class="es-drawer-foot">
      <button type="button" class="btn btn-outline-secondary" data-drawer-close>Cancel</button>
      <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save' : 'Add role' ?></button>
    </div>
  </form>
<?php exit; }

es_layout_head($isEdit ? 'Edit role' : 'New role', 'roles');
es_admin_nav('roles');
?>
<div class="f-head"><h1 class="f-title"><?= $isEdit ? 'Edit role' : 'New role' ?></h1></div>
<form method="post" action="admin_roles_save.php" class="f-card" style="max-width:520px;">
  <?= $FIELDS ?>
  <div class="d-flex justify-content-end gap-2 mt-2">
    <a href="admin_roles.php" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-success"><?= $isEdit ? 'Save' : 'Add role' ?></button>
  </div>
</form>
<?php es_layout_foot(); ?>
