<?php
/** e-Services sign-out — clears the shared SSO session and returns to the hub login. */
session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: ../../hub/login.php');
exit;
