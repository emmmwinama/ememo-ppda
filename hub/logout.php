<?php
// logout.php — clears the shared SSO session.
// Returns JSON to an AJAX caller (hub landing page); redirects a normal
// browser navigation (app "Sign out" links) to the login page.
session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

$accept   = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsXhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || stripos($accept, 'application/json') !== false
    || (($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'cors');

if ($wantsXhr && stripos($accept, 'text/html') === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => 'login.php']);
    exit;
}

header('Location: login.php');
exit;
