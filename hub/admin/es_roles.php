<?php
/** Administration — e-Services roles & their permission bundles. */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

if (!@$conn->query("SHOW TABLES LIKE 'es_role'")->num_rows) {
    hub_head('Roles & permissions', 'es_roles');
    echo '<div class="f-state is-error"><i class="bi bi-database"></i><p>The e-Services schema isn\'t installed.</p></div>';
    hub_foot();
    exit;
}

$roleKey = $_GET['role'] ?? '';
$role = $roleKey !== '' ? db_one("SELECT * FROM es_role WHERE role_key = ?", 's', [$roleKey]) : null;

// ---------- permission matrix for one role ----------
if ($role) {
    $perms = db_all("SELECT perm_key, label, grp FROM es_permission ORDER BY grp, sort, label");
    $have  = array_column(db_all("SELECT perm_key FROM es_role_permission WHERE role_key = ?", 's', [$roleKey]), 'perm_key');
    $byGroup = [];
    foreach ($perms as $p) $byGroup[$p['grp']][] = $p;

    hub_head('Permissions · ' . $role['label'], 'es_roles');
    ?>
    <p class="mb-3"><a href="es_roles.php" class="text-muted small"><i class="bi bi-arrow-left"></i> All roles</a></p>
    <div class="f-head">
      <h1 class="f-title"><?= e($role['label']) ?> <span class="font-monospace text-muted" style="font-size:1rem;"><?= e($role['role_key']) ?></span></h1>
      <p class="f-subtitle">Tick the permissions this role carries<?= $role['role_key'] === 'admin' ? ' · Administrator implicitly has everything.' : '' ?></p>
    </div>
    <form method="post" action="es_perms_save.php" class="f-card" style="gap:1.25rem;max-width:760px;">
      <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
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
        <a href="es_roles.php" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save permissions</button>
      </div>
    </form>
    <?php
    hub_foot();
    exit;
}

// ---------- role list ----------
$editKey = $_GET['edit'] ?? '';
$editRow = $editKey !== '' ? db_one("SELECT * FROM es_role WHERE role_key = ?", 's', [$editKey]) : null;
$roles = db_all(
    "SELECT r.role_key, r.label, r.description, r.is_system, r.sort,
            (SELECT COUNT(*) FROM es_role_permission rp WHERE rp.role_key = r.role_key) AS perm_count,
            (SELECT COUNT(*) FROM es_user_role ur WHERE ur.role = r.role_key) AS user_count
       FROM es_role r ORDER BY r.sort, r.label"
);

hub_head('Roles & permissions', 'es_roles', 'Define e-Services roles and bundle the permissions each one carries');
?>

<div class="f-panel mb-3">
  <div class="f-panel-head"><i class="bi bi-plus-lg"></i> <?= $editRow ? 'Edit role' : 'New role' ?></div>
  <div class="f-panel-body">
    <form method="post" action="es_roles_save.php" class="row g-2 align-items-end">
      <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
      <input type="hidden" name="op" value="save">
      <?php if ($editRow): ?><input type="hidden" name="orig_key" value="<?= e($editRow['role_key']) ?>"><?php endif; ?>
      <div class="col-sm-3"><label class="form-label">Key</label>
        <input name="role_key" class="form-control font-monospace" required pattern="[a-z0-9_.]+" maxlength="40"
               value="<?= e($editRow['role_key'] ?? '') ?>" <?= ($editRow && $editRow['is_system']) ? 'readonly' : '' ?>></div>
      <div class="col-sm-3"><label class="form-label">Label</label>
        <input name="label" class="form-control" required maxlength="80" value="<?= e($editRow['label'] ?? '') ?>"></div>
      <div class="col-sm-4"><label class="form-label">Description</label>
        <input name="description" class="form-control" maxlength="255" value="<?= e($editRow['description'] ?? '') ?>"></div>
      <div class="col-sm-1"><label class="form-label">Sort</label>
        <input name="sort" type="number" class="form-control" value="<?= e($editRow['sort'] ?? '100') ?>"></div>
      <div class="col-sm-1">
        <button class="btn btn-success w-100"><?= $editRow ? 'Save' : 'Add' ?></button>
      </div>
      <?php if ($editRow): ?><div class="col-12"><a href="es_roles.php" class="small text-muted">Cancel edit</a></div><?php endif; ?>
    </form>
  </div>
</div>

<div class="f-panel table-responsive">
  <table class="table table-borderless f-table align-middle mb-0">
    <thead><tr><th>Role</th><th>Key</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td class="fw-semibold">
            <?= e($r['label']) ?>
            <?php if ($r['is_system']): ?><span class="pill t-neutral"><i class="bi bi-lock"></i> system</span><?php endif; ?>
            <?php if ($r['description']): ?><br><span class="text-muted small"><?= e($r['description']) ?></span><?php endif; ?>
          </td>
          <td class="font-monospace text-muted"><?= e($r['role_key']) ?></td>
          <td><a href="?role=<?= e(urlencode($r['role_key'])) ?>" class="pill t-sky text-decoration-none"><?= (int) $r['perm_count'] ?> permission<?= $r['perm_count'] == 1 ? '' : 's' ?> · edit</a></td>
          <td class="text-muted"><?= (int) $r['user_count'] ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= e(urlencode($r['role_key'])) ?>"><i class="bi bi-pencil"></i></a>
            <?php if (!$r['is_system']): ?>
              <form method="post" action="es_roles_save.php" class="d-inline" onsubmit="return confirm('Delete role “<?= e($r['label']) ?>”?');">
                <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
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

<?php hub_foot(); ?>
