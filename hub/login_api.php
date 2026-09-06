<?php
// login_api.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_lib.php';
session_start();
header('Content-Type: application/json');

// 1) Read & sanitize
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required.'
    ]);
    exit;
}

// 2) Fetch user + flags, verify password
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

// 3) Populate session
$_SESSION['user_id']                = $user['id'];
$_SESSION['username']               = $user['username'];
$_SESSION['role']                   = $user['role'];
$_SESSION['password_changed']       = $user['password_changed'];
$_SESSION['is_controlling_officer'] = (bool)$user['is_controlling_officer'];
$_SESSION['has_signature']          = ememo_has_signature($conn, (int)$user['id']);

// 5) Determine redirect (for web) – mobile can ignore this if desired
$redirect = ($user['password_changed'] == 0)
    ? 'change-password.php'
    : 'index.php';

// 6) Return full JSON for mobile
echo json_encode([
    'success'                => true,
    'user_id'                => (int)$user['id'],
    'username'               => $user['username'],
    'role'                   => $user['role'],
    'is_controlling_officer' => (bool)$_SESSION['is_controlling_officer'],
    'has_signature'          => (bool)$_SESSION['has_signature'],
    'redirect'               => $redirect
]);
