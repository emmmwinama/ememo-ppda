<?php
/** Administration — create or edit one user (profile + e-Memo + e-Services). */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn, $HUB_UID;

$id  = (int) ($_GET['id'] ?? 0);
$u   = $id ? db_one(
    "SELECT id, full_name, username, email, phone_number, role, department_id, section_id,
            position_id, active, is_controlling_officer, is_secretary, password_changed,
            last_login_at, pwd_updated_at, locked_until
       FROM users WHERE id = ?", 'i', [$id]
) : null;
if ($id && !$u) { hub_head('User', 'users'); echo '<div class="f-state is-error"><p>User not found.</p></div>'; hub_foot(); exit; }

$isNew   = !$id;
$roleEnum = ['originator', 'endorser', 'approver', 'admin'];

$departments = db_all("SELECT id, name FROM departments ORDER BY name");
$sections    = db_all("SELECT id, name, department_id FROM sections ORDER BY name");
$positions   = db_all("SELECT id, name, short_name FROM positions ORDER BY name");

$esRoles = db_all("SELECT role_key, label, description FROM es_role ORDER BY sort, label");
$pdes    = db_all("SELECT id, name FROM es_pde WHERE active = 1 ORDER BY name");
$haveEs  = [];
$havePde = null;
if ($id) {
    foreach (db_all("SELECT role, pde_id FROM es_user_role WHERE user_id = ?", 'i', [$id]) as $r) {
        $haveEs[] = $r['role'];
        if ($r['role'] === 'pde') $havePde = $r['pde_id'];
    }
}
$v = fn(string $k, $d = '') => e($u[$k] ?? $d);
$locked = $u && $u['locked_until'] && strtotime($u['locked_until']) > time();

hub_head($isNew ? 'New user' : ('Edit · ' . ($u['full_name'] ?: $u['username'])), 'users');
?>

<p class="mb-3"><a href="users.php" class="text-muted small"><i class="bi bi-arrow-left"></i> Back to users</a></p>

<form method="post" action="user_save.php" style="max-width:820px;">
  <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="f-panel mb-3">
    <div class="f-panel-head"><i class="bi bi-person"></i> Profile</div>
    <div class="f-panel-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" required value="<?= $v('full_name') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Username <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" required value="<?= $v('username') ?>" autocomplete="off">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" required value="<?= $v('email') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone</label>
          <input type="text" name="phone_number" class="form-control" value="<?= $v('phone_number') ?>">
        </div>
        <div class="col-12">
          <label class="d-flex gap-2 align-items-center" style="font-size:.9rem;">
            <input type="checkbox" name="active" value="1" <?= (!$u || $u['active']) ? 'checked' : '' ?>
              <?= ($u && $id === $HUB_UID) ? 'disabled' : '' ?>>
            <span>Account active <span class="text-muted">— unchecked disables sign-in</span></span>
          </label>
          <?php if ($u && $id === $HUB_UID): ?><div class="text-muted small mt-1">You can't deactivate your own account.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="f-panel mb-3">
    <div class="f-panel-head"><i class="bi bi-file-earmark-text"></i> e-Memo</div>
    <div class="f-panel-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Role <span class="text-danger">*</span></label>
          <select name="role" class="form-select" required <?= ($u && $id === $HUB_UID && $u['role'] === 'admin') ? '' : '' ?>>
            <?php foreach ($roleEnum as $r): ?>
              <option value="<?= $r ?>" <?= (($u['role'] ?? 'originator') === $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($u && $id === $HUB_UID && $u['role'] === 'admin'): ?>
            <div class="text-muted small mt-1">Changing your own role away from admin will lock you out of this console.</div>
          <?php endif; ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-select">
            <option value="">— none —</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= ((int) ($u['department_id'] ?? 0) === (int) $d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Section</label>
          <select name="section_id" class="form-select">
            <option value="">— none —</option>
            <?php foreach ($sections as $s): ?>
              <option value="<?= (int) $s['id'] ?>" data-dept="<?= (int) $s['department_id'] ?>" <?= ((int) ($u['section_id'] ?? 0) === (int) $s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Position</label>
          <select name="position_id" class="form-select">
            <option value="">— none —</option>
            <?php foreach ($positions as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= ((int) ($u['position_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?><?= $p['short_name'] ? ' (' . e($p['short_name']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8 d-flex align-items-end gap-4">
          <label class="d-flex gap-2 align-items-center" style="font-size:.9rem;">
            <input type="checkbox" name="is_controlling_officer" value="1" <?= ($u && $u['is_controlling_officer']) ? 'checked' : '' ?>>
            <span>Controlling officer</span>
          </label>
          <label class="d-flex gap-2 align-items-center" style="font-size:.9rem;">
            <input type="checkbox" name="is_secretary" value="1" <?= ($u && $u['is_secretary']) ? 'checked' : '' ?>>
            <span>Secretary</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="f-panel mb-3">
    <div class="f-panel-head"><i class="bi bi-diagram-3"></i> e-Services roles</div>
    <div class="f-panel-body">
      <?php if (!$esRoles): ?>
        <p class="text-muted small mb-0">The e-Services schema isn't installed.</p>
      <?php else: ?>
        <div class="row g-2">
          <?php foreach ($esRoles as $r): ?>
            <div class="col-md-6">
              <label class="d-flex gap-2 align-items-start" style="font-size:.88rem;">
                <input type="checkbox" name="es_roles[]" value="<?= e($r['role_key']) ?>" class="mt-1 es-role-cb"
                  <?= in_array($r['role_key'], $haveEs, true) ? 'checked' : '' ?>>
                <span>
                  <span class="fw-semibold"><?= e($r['label']) ?></span> <span class="text-muted">· <?= e($r['role_key']) ?></span>
                  <?php if ($r['description']): ?><br><span class="text-muted small"><?= e($r['description']) ?></span><?php endif; ?>
                </span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-3" id="pdeWrap" style="<?= in_array('pde', $haveEs, true) ? '' : 'display:none;' ?>">
          <label class="form-label">Which PDE does this user represent? <span class="text-danger">*</span></label>
          <select name="pde_id" class="form-select" style="max-width:420px;">
            <option value="">— select —</option>
            <?php foreach ($pdes as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= (int) $havePde === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isNew): ?>
    <div class="f-panel mb-3" id="password">
      <div class="f-panel-head"><i class="bi bi-key"></i> Initial password</div>
      <div class="f-panel-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="text" name="password" class="form-control" required minlength="8" autocomplete="off"
                   placeholder="At least 8 characters">
          </div>
          <div class="col-12">
            <label class="d-flex gap-2 align-items-center" style="font-size:.9rem;">
              <input type="checkbox" name="must_change" value="1" checked>
              <span>Require a password change at first sign-in</span>
            </label>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-end gap-2 mb-4" style="max-width:820px;">
    <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= $isNew ? 'Create user' : 'Save changes' ?></button>
  </div>
</form>

<?php if (!$isNew): ?>
  <div class="f-panel mb-4" id="password" style="max-width:820px;">
    <div class="f-panel-head"><i class="bi bi-key"></i> Password &amp; access</div>
    <div class="f-panel-body">
      <div class="kv mb-3">
        <dt>Last sign-in</dt><dd><?= $u['last_login_at'] ? e(date('d M Y H:i', strtotime($u['last_login_at']))) : 'never' ?></dd>
        <dt>Password set</dt><dd><?= $u['pwd_updated_at'] ? e(date('d M Y', strtotime($u['pwd_updated_at']))) : 'unknown' ?><?= $u['password_changed'] ? '' : ' · <span class="pill t-amber">change pending</span>' ?></dd>
        <dt>Account</dt><dd><?= $locked ? '<span class="pill t-rose">locked until ' . e(date('d M H:i', strtotime($u['locked_until']))) . '</span>' : '<span class="pill t-green">not locked</span>' ?></dd>
      </div>
      <form method="post" action="user_password.php" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="col-md-6">
          <label class="form-label">New password</label>
          <input type="text" name="password" class="form-control" minlength="8" autocomplete="off"
                 placeholder="Leave blank to generate one">
        </div>
        <div class="col-md-6 d-flex align-items-end gap-3 flex-wrap">
          <label class="d-flex gap-2 align-items-center" style="font-size:.9rem;">
            <input type="checkbox" name="must_change" value="1" checked>
            <span>Require change at next sign-in</span>
          </label>
          <?php if ($locked): ?>
            <label class="d-flex gap-2 align-items-center" style="font-size:.9rem;">
              <input type="checkbox" name="unlock" value="1" checked><span>Unlock account</span>
            </label>
          <?php endif; ?>
        </div>
        <div class="col-12">
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Reset password</button>
          <span class="text-muted small ms-2">The user is forced to set their own password on next sign-in.</span>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<script>
(function () {
  var pde = document.querySelector('.es-role-cb[value="pde"]');
  var w = document.getElementById('pdeWrap');
  if (pde && w) pde.addEventListener('change', function () { w.style.display = pde.checked ? '' : 'none'; });
  // filter sections by chosen department
  var dept = document.querySelector('select[name="department_id"]');
  var sec = document.querySelector('select[name="section_id"]');
  function sync() {
    if (!dept || !sec) return;
    var d = dept.value;
    Array.prototype.forEach.call(sec.options, function (o) {
      if (!o.value) return;
      o.hidden = d && o.dataset.dept && o.dataset.dept !== d;
    });
  }
  if (dept) dept.addEventListener('change', sync);
  sync();
})();
</script>

<?php hub_foot(); ?>
