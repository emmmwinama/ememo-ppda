<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/../../hub/auth_lib.php';

$username = trim($_POST['username']);
$password = trim($_POST['password']);

$user = ememo_verify_credentials($conn, $username, $password);

if ($user !== null && !isset($user['error'])) {
    // Store core user info in session
    $_SESSION['user_id']                   = $user['id'];
    $_SESSION['username']                  = $user['username'];
    $_SESSION['role']                      = $user['role'];
    $_SESSION['is_controlling_officer']    = (int)$user['is_controlling_officer'];

    if ($user['password_changed'] == 0) {
        // Force change password
        header('Location: password.php');
    } else {
        // Normal dashboard
        header('Location: index.php');
    }
} elseif ($user !== null && isset($user['error'])) {
    $_SESSION['login_error'] = "Your account is deactivated. Contact admin.";
    header('Location: login.php');
    exit;
} else {
    $_SESSION['login_error'] = "Invalid username or password.";
    header('Location: login.php');
}
