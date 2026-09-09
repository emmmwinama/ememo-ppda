<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_lib.php';
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
header('Content-Type: application/json');

// Read & sanitize
$username = trim($_POST['username']  ?? '');
$password = trim($_POST['password']  ?? '');

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required.'
    ]);
    exit;
}

if (ememo_login_locked($conn, $username)) {
    ememo_note_login($conn, $username, 'blocked');
    echo json_encode([
        'success' => false,
        'message' => 'This account is temporarily locked after repeated failed attempts. Try again later or contact an administrator.'
    ]);
    exit;
}

$user = ememo_verify_credentials($conn, $username, $password);

if ($user === null) {
    ememo_note_login($conn, $username, 'failed');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
    exit;
}

if (isset($user['error'])) {
    ememo_note_login($conn, $username, 'inactive');
    echo json_encode([
        'success' => false,
        'message' => 'Account is deactivated. Contact admin.'
    ]);
    exit;
}

ememo_note_login($conn, $username, 'success');

// Set session variables
$_SESSION['user_id']                  = $user['id'];
$_SESSION['username']                 = $user['username'];
$_SESSION['role']                     = $user['role'];
$_SESSION['password_changed']         = $user['password_changed'];
$_SESSION['is_controlling_officer']   = (bool)$user['is_controlling_officer'];
$_SESSION['has_signature']            = ememo_has_signature($conn, (int)$user['id']);

// Return redirect target
$redirect = ($user['password_changed'] == 0) ? 'change-password.php' : 'index.php';

echo json_encode([
    'success'  => true,
    'redirect' => $redirect
]);
