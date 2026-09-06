<?php
/** Admin — role definitions. */
require __DIR__ . '/inc/layout.php';
es_require_perm('rbac.manage');

global $conn;

$roles = db_all(
    "SELECT r.role_key, r.label, r.description, r.is_system, r.sort,
            (SELECT COUNT(*) FROM es_role_permission rp WHERE rp.role_key = r.role_key) AS perm_count,
            (SELECT COUNT(*) FROM es_user_role ur WHERE ur.role = r.role_key) AS user_count
       FROM es_role r ORDER BY r.sort, r.label"
);

es_layout_head('Roles & permissions', 'roles');
es_admin_nav('roles');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title">Roles &amp; permissions</h1>
    <p class="f-subtitle">Define roles and bundle the permissions each one carries</p>
  </div>
  <button type="button" class="btn btn-success btn-sm"
          data-drawer="admin_role_form.php?partial=1" data-drawer-title="New role">
    <i class="bi bi-plus-lg me-1"></i>New role
  </button>
</div>

<div class="f-panel table-responsive">
  <table class="table table-borderless f-table align-middle mb-0">
    <thead><tr><th>Role</th><th>Key</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td class="fw-semibold">
            <?= e($r['label']) ?>
            <?php if ($r['is_system']): ?><span class="es-badge" style="color:#475569;background:#eef1f4"><i class="bi bi-lock"></i>system</span><?php endif; ?>
            <?php if ($r['description']): ?><br><span class="text-muted small"><?= e($r['description']) ?></span><?php endif; ?>
          </td>
          <td class="font-monospace text-muted"><?= e($r['role_key']) ?></td>
          <td>
            <a href="admin_role_perms.php?role=<?= e(urlencode($r['role_key'])) ?>" class="pill t-sky text-decoration-none">
              <?= (int) $r['perm_count'] ?> permission<?= $r['perm_count'] == 1 ? '' : 's' ?> · edit
            </a>
          </td>
          <td class="text-muted"><?= (int) $r['user_count'] ?></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    data-drawer="admin_role_form.php?role=<?= e(urlencode($r['role_key'])) ?>&amp;partial=1"
                    data-drawer-title="Edit role">
              <i class="bi bi-pencil"></i>
            </button>
            <?php if (!$r['is_system']): ?>
              <form method="post" action="admin_roles_save.php" class="d-inline"
                    onsubmit="return confirm('Delete role “<?= e($r['label']) ?>”? Users lose it.');">
                <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
                <input type="hidden" name="op" value="delete">
                <input type="hidden" name="role_key" value="<?= e($r['role_key']) ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php es_layout_foot(); ?>
