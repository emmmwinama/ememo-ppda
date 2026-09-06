<?php
/** Admin — bundle permissions into a role. */
require __DIR__ . '/inc/layout.php';
es_require_perm('rbac.manage');

global $conn;

$key = $_GET['role'] ?? '';
$role = db_one("SELECT * FROM es_role WHERE role_key = ?", 's', [$key]);
if (!$role) { http_response_code(404); es_layout_head('Not found'); echo '<div class="f-state is-error"><p>Role not found.</p></div>'; es_layout_foot(); exit; }

$perms = db_all("SELECT perm_key, label, grp FROM es_permission ORDER BY grp, sort, label");
$have  = array_column(db_all("SELECT perm_key FROM es_role_permission WHERE role_key = ?", 's', [$key]), 'perm_key');

$byGroup = [];
foreach ($perms as $p) $byGroup[$p['grp']][] = $p;

es_layout_head('Permissions · ' . $role['label'], 'roles');
es_admin_nav('roles');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title"><?= e($role['label']) ?> <span class="font-monospace text-muted" style="font-size:1rem;"><?= e($role['role_key']) ?></span></h1>
    <p class="f-subtitle">Tick the permissions this role carries<?= $role['role_key'] === 'admin' ? ' · Administrator has everything regardless.' : '' ?></p>
  </div>
  <a href="admin_roles.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Roles</a>
</div>

<form method="post" action="admin_perms_save.php" class="f-card" style="gap:1.25rem;">
  <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
  <input type="hidden" name="role_key" value="<?= e($role['role_key']) ?>">

  <?php foreach ($byGroup as $grp => $list): ?>
    <div>
      <div class="fw-bold small text-uppercase text-muted mb-2" style="letter-spacing:.04em;"><?= e($grp) ?></div>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($list as $p): ?>
          <label class="d-flex gap-2 align-items-start" style="font-size:.9rem;">
            <input type="checkbox" name="perms[]" value="<?= e($p['perm_key']) ?>" class="mt-1" <?= in_array($p['perm_key'], $have, true) ? 'checked' : '' ?>>
            <span><span class="fw-semibold"><?= e($p['label']) ?></span> <span class="text-muted font-monospace small">· <?= e($p['perm_key']) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="d-flex justify-content-end gap-2">
    <a href="admin_roles.php" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save permissions</button>
  </div>
</form>

<?php es_layout_foot(); ?>
