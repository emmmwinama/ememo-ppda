<?php
/** Administration — create / update a user + sync e-Services roles. */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_guard.php';

global $conn, $HUB_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('users.php');

$id    = (int) ($_POST['id'] ?? 0);
$isNew = $id === 0;

$full_name = trim($_POST['full_name'] ?? '');
$username  = trim($_POST['username'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone_number'] ?? '');
$role      = trim($_POST['role'] ?? '');
$deptId    = ($_POST['department_id'] ?? '') !== '' ? (int) $_POST['department_id'] : null;
$sectId    = ($_POST['section_id'] ?? '')    !== '' ? (int) $_POST['section_id']    : null;
$posId     = ($_POST['position_id'] ?? '')   !== '' ? (int) $_POST['position_id']   : null;
$active    = !empty($_POST['active']) ? 1 : 0;
$isCo      = !empty($_POST['is_controlling_officer']) ? 1 : 0;
$isSec     = !empty($_POST['is_secretary']) ? 1 : 0;
$password  = trim($_POST['password'] ?? '');
$mustChange = !empty($_POST['must_change']);

$esWanted = array_values(array_intersect(
    (array) ($_POST['es_roles'] ?? []),
    array_column(db_all("SELECT role_key FROM es_role"), 'role_key')
));
$pdeId = ($_POST['pde_id'] ?? '') !== '' ? (int) $_POST['pde_id'] : null;

$back = $isNew ? 'user_edit.php' : 'user_edit.php?id=' . $id;

// ---- validation ----------------------------------------------------------
$err = null;
if (!$full_name || !$username || !$email || !$role)          $err = 'Name, username, email and role are required.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))          $err = 'That email address is not valid.';
elseif (!in_array($role, ['originator', 'endorser', 'approver', 'admin'], true)) $err = 'Invalid e-Memo role.';
elseif ($isNew && strlen($password) < 8)                     $err = 'Set an initial password of at least 8 characters.';
elseif (in_array('pde', $esWanted, true) && !$pdeId)         $err = 'Choose which PDE this user represents.';

if (!$err) {
    $dupe = db_one("SELECT id FROM users WHERE username = ? AND id <> ?", 'si', [$username, $id]);
    if ($dupe) $err = 'That username is already taken.';
}
// self lock-out guards
if (!$err && !$isNew && $id === $HUB_UID) {
    if ($role !== 'admin' && (hub_user()['role'] ?? '') === 'admin' && !in_array('admin', $esWanted, true)) {
        $err = "You can't remove your own administrator access.";
    } elseif (!$active) {
        $err = "You can't deactivate your own account.";
    }
}
if ($err) { hub_flash($err, 'error'); redirect($back); }

// ---- write -------------------------------------------------------------
$conn->begin_transaction();
try {
    if ($isNew) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pwdChanged = $mustChange ? 0 : 1;
        $st = $conn->prepare(
            "INSERT INTO users
               (full_name, username, email, phone_number, role, department_id, section_id, position_id,
                active, is_controlling_officer, is_secretary, password, password_changed, pwd_updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
        );
        $st->bind_param('sssssiiiiiisi', $full_name, $username, $email, $phone, $role,
            $deptId, $sectId, $posId, $active, $isCo, $isSec, $hash, $pwdChanged);
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
    } else {
        $st = $conn->prepare(
            "UPDATE users SET full_name=?, username=?, email=?, phone_number=?, role=?,
                    department_id=?, section_id=?, position_id=?, active=?,
                    is_controlling_officer=?, is_secretary=?
              WHERE id=?"
        );
        $st->bind_param('sssssiiiiiii', $full_name, $username, $email, $phone, $role,
            $deptId, $sectId, $posId, $active, $isCo, $isSec, $id);
        $st->execute();
        $st->close();
    }

    // sync e-Services roles
    $conn->query("DELETE FROM es_user_role WHERE user_id = " . (int) $id);
    if ($esWanted) {
        $st = $conn->prepare("INSERT INTO es_user_role (user_id, role, pde_id) VALUES (?,?,?)");
        foreach ($esWanted as $rk) {
            $pv = $rk === 'pde' ? $pdeId : null;
            $st->bind_param('isi', $id, $rk, $pv);
            $st->execute();
        }
        $st->close();
    }

    $conn->commit();
    hub_audit(($isNew ? 'Created user ' : 'Updated user ') . $username . ' (#' . $id . ')'
            . ' — eMemo=' . $role . ', eServices=[' . implode(',', $esWanted) . ']');
    hub_flash($isNew ? 'User created.' : 'User updated.', 'success');
} catch (Throwable $ex) {
    $conn->rollback();
    hub_flash('Could not save: ' . $ex->getMessage(), 'error');
    redirect($back);
}

redirect('user_edit.php?id=' . $id);
