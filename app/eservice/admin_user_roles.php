<?php
/** Admin — assign e-Services roles to one user (drawer fragment on ?partial=1). */
require __DIR__ . '/inc/bootstrap.php';
$partial = isset($_GET['partial']);
if (!$partial) require_once __DIR__ . '/inc/layout.php';
es_require_perm('users.manage');

global $conn;

$uid = (int) ($_GET['uid'] ?? 0);
$u = db_one("SELECT id, full_name, username, email FROM users WHERE id = ?", 'i', [$uid]);
if (!$u) { http_response_code(404); exit('User not found.'); }

$have = [];
$havePde = null;
foreach (db_all("SELECT role, pde_id FROM es_user_role WHERE user_id = ?", 'i', [$uid]) as $r) {
    $have[] = $r['role'];
    if ($r['role'] === 'pde') $havePde = $r['pde_id'];
}
$roles = db_all("SELECT role_key, label, description FROM es_role ORDER BY sort, label");
$pdes  = db_all("SELECT id, name FROM es_pde WHERE active = 1 ORDER BY name");

ob_start(); ?>
  <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
  <input type="hidden" name="uid" value="<?= $uid ?>">
  <p class="text-muted small mb-3"><strong><?= e($u['full_name'] ?: $u['username']) ?></strong> · <?= e($u['email'] ?: $u['username']) ?></p>

  <div class="d-flex flex-column gap-2">
    <?php foreach ($roles as $r): ?>
      <label class="d-flex gap-2 align-items-start" style="font-size:.9rem;">
        <input type="checkbox" name="roles[]" value="<?= e($r['role_key']) ?>" <?= in_array($r['role_key'], $have, true) ? 'checked' : '' ?> class="mt-1">
        <span>
          <span class="fw-semibold"><?= e($r['label']) ?></span> <span class="text-muted">· <?= e($r['role_key']) ?></span>
          <?php if ($r['description']): ?><br><span class="text-muted small"><?= e($r['description']) ?></span><?php endif; ?>
        </span>
      </label>
    <?php endforeach; ?>
  </div>

  <div class="mt-3" id="pdeWrap" style="<?= in_array('pde', $have, true) ? '' : 'display:none;' ?>">
    <label class="form-label">Which PDE does this user represent?</label>
    <select name="pde_id" class="form-select form-select-sm">
      <option value="">— select —</option>
      <?php foreach ($pdes as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= (int) $havePde === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <script>
    (function () {
      var cb = document.querySelector('input[name="roles[]"][value="pde"]');
      var w  = document.getElementById('pdeWrap');
      if (cb && w) cb.addEventListener('change', function () { w.style.display = cb.checked ? '' : 'none'; });
    })();
  </script>
<?php
$FIELDS = ob_get_clean();

if ($partial) { ?>
  <form method="post" action="admin_users_save.php" class="es-drawer-form">
    <div class="es-drawer-fields"><?= $FIELDS ?></div>
    <div class="es-drawer-foot">
      <button type="button" class="btn btn-outline-secondary" data-drawer-close>Cancel</button>
      <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save roles</button>
    </div>
  </form>
<?php exit; }

es_layout_head('Roles · ' . ($u['full_name'] ?: $u['username']), 'users');
es_admin_nav('users');
?>
<div class="f-head"><h1 class="f-title">Roles · <?= e($u['full_name'] ?: $u['username']) ?></h1></div>
<form method="post" action="admin_users_save.php" class="f-card" style="max-width:560px;">
  <?= $FIELDS ?>
  <div class="d-flex justify-content-end gap-2 mt-2">
    <a href="admin_users.php" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-success">Save roles</button>
  </div>
</form>
<?php es_layout_foot(); ?>
