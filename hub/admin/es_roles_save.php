<?php
/** Administration — persist an e-Services role (save / delete). */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_guard.php';

global $conn;

$op = $_POST['op'] ?? '';

if ($op === 'delete') {
    $key = $_POST['role_key'] ?? '';
    $row = db_one("SELECT is_system FROM es_role WHERE role_key = ?", 's', [$key]);
    if (!$row)                  hub_flash('Role not found.', 'error');
    elseif ($row['is_system'])  hub_flash('System roles cannot be deleted.', 'error');
    else {
        $st = $conn->prepare("DELETE FROM es_role WHERE role_key = ?");
        $st->bind_param('s', $key);
        if ($st->execute()) { hub_audit("Deleted e-Services role '$key'"); hub_flash('Role deleted.', 'success'); }
        else hub_flash('Delete failed — remove the role from all users first.', 'error');
        $st->close();
    }
    redirect('es_roles.php');
}

$origKey = $_POST['orig_key'] ?? '';
$key     = strtolower(trim($_POST['role_key'] ?? ''));
$label   = trim($_POST['label'] ?? '');
$desc    = trim($_POST['description'] ?? '');
$sort    = (int) ($_POST['sort'] ?? 100);
$d       = $desc !== '' ? $desc : null;

if ($key === '' || $label === '' || !preg_match('/^[a-z0-9_.]+$/', $key)) {
    hub_flash('A valid lowercase key and a label are required.', 'error');
    redirect('es_roles.php');
}

$existing = $origKey !== '' ? db_one("SELECT role_key, is_system FROM es_role WHERE role_key = ?", 's', [$origKey]) : null;

if ($existing) {
    if ($existing['is_system']) $key = $existing['role_key'];
    $st = $conn->prepare("UPDATE es_role SET role_key = ?, label = ?, description = ?, sort = ? WHERE role_key = ?");
    $st->bind_param('sssis', $key, $label, $d, $sort, $origKey);
    $ok = $st->execute();
    $st->close();
    if ($ok) hub_audit("Updated e-Services role '$key'");
    hub_flash($ok ? 'Role saved.' : 'Save failed (duplicate key?).', $ok ? 'success' : 'error');
} else {
    if (db_one("SELECT 1 x FROM es_role WHERE role_key = ?", 's', [$key])) {
        hub_flash('A role with that key already exists.', 'error');
        redirect('es_roles.php');
    }
    $st = $conn->prepare("INSERT INTO es_role (role_key, label, description, is_system, sort) VALUES (?, ?, ?, 0, ?)");
    $st->bind_param('sssi', $key, $label, $d, $sort);
    $ok = $st->execute();
    $st->close();
    if ($ok) hub_audit("Added e-Services role '$key'");
    hub_flash($ok ? 'Role added.' : 'Could not add the role.', $ok ? 'success' : 'error');
}
redirect('es_roles.php');
