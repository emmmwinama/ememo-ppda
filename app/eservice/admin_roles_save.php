<?php
/** Admin — persist a role (save / delete). */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('rbac.manage');

global $conn;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('admin_roles.php');
}

$op = $_POST['op'] ?? '';

if ($op === 'delete') {
    $key = $_POST['role_key'] ?? '';
    $row = db_one("SELECT is_system FROM es_role WHERE role_key = ?", 's', [$key]);
    if (!$row)               { flash('Role not found.', 'error'); }
    elseif ($row['is_system']) { flash('System roles cannot be deleted.', 'error'); }
    else {
        $st = $conn->prepare("DELETE FROM es_role WHERE role_key = ?");   // cascades role_permission; user_role rows blocked by FK
        $st->bind_param('s', $key);
        if (!$st->execute()) flash('Delete failed — remove it from all users first.', 'error');
        else flash('Role deleted.', 'success');
        $st->close();
    }
    redirect('admin_roles.php');
}

// ---- save ----
$origKey = $_POST['orig_key'] ?? '';
$key     = strtolower(trim($_POST['role_key'] ?? ''));
$label   = trim($_POST['label'] ?? '');
$desc    = trim($_POST['description'] ?? '');
$sort    = (int) ($_POST['sort'] ?? 100);

if ($key === '' || $label === '' || !preg_match('/^[a-z0-9_.]+$/', $key)) {
    flash('A valid key and label are required.', 'error');
    redirect('admin_roles.php');
}

$existing = $origKey !== '' ? db_one("SELECT role_key, is_system FROM es_role WHERE role_key = ?", 's', [$origKey]) : null;

if ($existing) {
    // system roles keep their key
    if ($existing['is_system']) $key = $existing['role_key'];
    $st = $conn->prepare("UPDATE es_role SET role_key = ?, label = ?, description = ?, sort = ? WHERE role_key = ?");
    $d = $desc !== '' ? $desc : null;
    $st->bind_param('sssis', $key, $label, $d, $sort, $origKey);
    $ok = $st->execute();
    $err = $conn->error;
    $st->close();
    flash($ok ? 'Role saved.' : "Save failed: $err", $ok ? 'success' : 'error');
} else {
    if (db_one("SELECT 1 x FROM es_role WHERE role_key = ?", 's', [$key])) {
        flash('A role with that key already exists.', 'error');
        redirect('admin_roles.php');
    }
    $st = $conn->prepare("INSERT INTO es_role (role_key, label, description, is_system, sort) VALUES (?, ?, ?, 0, ?)");
    $d = $desc !== '' ? $desc : null;
    $st->bind_param('sssi', $key, $label, $d, $sort);
    $ok = $st->execute();
    $err = $conn->error;
    $st->close();
    flash($ok ? 'Role added.' : "Could not add: $err", $ok ? 'success' : 'error');
}
redirect('admin_roles.php');
