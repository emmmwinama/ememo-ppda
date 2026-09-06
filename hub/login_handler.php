<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_lib.php';
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

$user = ememo_verify_credentials($conn, $username, $password);

if ($user === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
    exit;
}

if (isset($user['error'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Account is deactivated. Contact admin.'
    ]);
    exit;
}

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
