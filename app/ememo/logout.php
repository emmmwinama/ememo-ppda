<?php
// /app/someapp/logout.php

session_start();

// 1) Unset all session variables
$_SESSION = [];

// 2) Destroy session cookie if present
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

// 3) Destroy the session
session_destroy();

// 4) Redirect two levels up into the SSO login page
header('Location: ../../hub/login.php');
exit;
