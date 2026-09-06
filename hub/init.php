<?php
// init.php

// ————— DEBUG LOGGING SETUP —————
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
// ——————————————————————————————————

session_start();
header('Content-Type: application/json');

// Read input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.',
        'debug'   => ['json_error' => json_last_error_msg()]
    ]);
    exit;
}

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required.',
        'debug'   => [
            'username'        => $username,
            'received_pw_len' => strlen($password),
            'received_pw'     => $password
        ]
    ]);
    exit;
}

// Connect DB
require_once __DIR__ . '/db.php';
if ($conn->connect_error) {
    error_log("DB connection failed: {$conn->connect_error}");
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error.',
        'debug'   => ['db_error' => $conn->connect_error]
    ]);
    exit;
}

// Fetch user
$stmt = $conn->prepare("
    SELECT id, username, password, role, active, password_changed
    FROM users
    WHERE username = ?
");
if (!$stmt) {
    error_log("Prepare failed: {$conn->error}");
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error.',
        'debug'   => ['prepare_error' => $conn->error]
    ]);
    exit;
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials.',
        'debug'   => ['user_found' => false]
    ]);
    exit;
}

// Verify password
$verify = password_verify($password, $user['password']);

// optional MD5 fallback
if (!$verify && strlen($user['password']) === 32 && $user['password'] === md5($password)) {
    $verify = true;
}

if (!$verify) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials.',
        'debug'   => ['password_verify' => false]
    ]);
    exit;
}

// Check account active
if (!(bool)$user['active']) {
    echo json_encode([
        'success' => false,
        'message' => 'Account deactivated. Contact admin.',
        'debug'   => ['active' => $user['active']]
    ]);
    exit;
}

// Start session
$_SESSION['user_id']          = $user['id'];
$_SESSION['username']         = $user['username'];
$_SESSION['name']             = $user['username'];
$_SESSION['role']             = $user['role'];
$_SESSION['password_changed'] = $user['password_changed'];

// Check signature
$_SESSION['has_signature'] = false;
if ($sigStmt = $conn->prepare("SELECT COUNT(*) FROM signatures WHERE user_id = ?")) {
    $sigStmt->bind_param("i", $user['id']);
    $sigStmt->execute();
    $sigStmt->bind_result($sigCount);
    $sigStmt->fetch();
    $_SESSION['has_signature'] = ($sigCount > 0);
    $sigStmt->close();
}

// Determine redirect
$redirect = ($user['password_changed'] === 0) ? 'change-password.php' : 'index.php';

echo json_encode([
    'success' => true,
    'redirect' => $redirect
]);
exit;
