<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/../../hub/auth_lib.php';
mysqli_report(MYSQLI_REPORT_OFF);

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username !== '' && ememo_login_locked($conn, $username)) {
    ememo_note_login($conn, $username, 'blocked');
    $_SESSION['login_error'] = "This account is temporarily locked after repeated failed attempts.";
    header('Location: login.php');
    exit;
}

$user = ememo_verify_credentials($conn, $username, $password);

if ($user !== null && !isset($user['error'])) {
    ememo_note_login($conn, $username, 'success');
    $_SESSION['user_id']                = $user['id'];
    $_SESSION['username']               = $user['username'];
    $_SESSION['role']                   = $user['role'];
    $_SESSION['is_controlling_officer'] = (int) $user['is_controlling_officer'];

    header('Location: ' . ($user['password_changed'] == 0 ? 'password.php' : 'index.php'));
    exit;
} elseif ($user !== null && isset($user['error'])) {
    ememo_note_login($conn, $username, 'inactive');
    $_SESSION['login_error'] = "Your account is deactivated. Contact admin.";
    header('Location: login.php');
    exit;
} else {
    ememo_note_login($conn, $username, 'failed');
    $_SESSION['login_error'] = "Invalid username or password.";
    header('Location: login.php');
    exit;
}
