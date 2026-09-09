<?php
/** Administration — reset / set a user's password. */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_guard.php';

global $conn;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('users.php');

$id = (int) ($_POST['id'] ?? 0);
$u  = db_one("SELECT id, username FROM users WHERE id = ?", 'i', [$id]);
if (!$u) { hub_flash('User not found.', 'error'); redirect('users.php'); }

$given      = trim($_POST['password'] ?? '');
$mustChange = !empty($_POST['must_change']);
$unlock     = !empty($_POST['unlock']);

$generated = null;
if ($given === '') {
    // readable temp password: 3 groups of 4 from an unambiguous alphabet
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $generated = '';
    for ($i = 0; $i < 12; $i++) {
        if ($i && $i % 4 === 0) $generated .= '-';
        $generated .= $abc[random_int(0, strlen($abc) - 1)];
    }
    $given = $generated;
} elseif (strlen($given) < 8) {
    hub_flash('A password must be at least 8 characters.', 'error');
    redirect('user_edit.php?id=' . $id);
}

$hash = password_hash($given, PASSWORD_BCRYPT);
$pwdChanged = $mustChange ? 0 : 1;

$sql = "UPDATE users SET password = ?, password_changed = ?, pwd_updated_at = NOW()";
if ($unlock) $sql .= ", locked_until = NULL, failed_logins = 0";
$sql .= " WHERE id = ?";
$st = $conn->prepare($sql);
$st->bind_param('sii', $hash, $pwdChanged, $id);
$st->execute();
$st->close();

hub_audit('Reset password for ' . $u['username'] . ' (#' . $id . ')'
        . ($mustChange ? ' — change required at next sign-in' : '')
        . ($unlock ? ' — account unlocked' : ''), 'notice');

if ($generated !== null) {
    hub_flash('Temporary password for @' . $u['username'] . ': ' . $generated
            . '  — copy it now, it is not shown again.', 'success');
} else {
    hub_flash('Password updated for @' . $u['username'] . '.', 'success');
}
redirect('user_edit.php?id=' . $id);
