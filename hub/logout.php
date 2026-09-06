<?php
// logout.php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy session cookie (if any)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Finally destroy the session
session_destroy();

// Return JSON for AJAX
header('Content-Type: application/json');
echo json_encode(['success' => true]);
