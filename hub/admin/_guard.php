<?php
/**
 * Admin-only gate for every page under hub/admin/.
 * Include right after the layout/bootstrap:
 *
 *   require __DIR__ . '/../inc/layout.php';   // pulls in bootstrap.php
 *   require __DIR__ . '/_guard.php';
 *
 * or, for a POST-only handler that renders no layout:
 *
 *   require __DIR__ . '/../inc/bootstrap.php';
 *   require __DIR__ . '/_guard.php';
 */

if (!function_exists('hub_is_admin')) {
    require __DIR__ . '/../inc/bootstrap.php';
}

if (!hub_is_admin()) {
    global $conn;
    log_security_event($conn, 'access_denied', [
        'app' => 'hub', 'severity' => 'warning',
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'username' => $_SESSION['username'] ?? null,
        'detail' => 'hub/admin (not an administrator)',
    ]);
    http_response_code(403);
    if (function_exists('hub_head')) {
        hub_head('Access denied', '');
        echo '<div class="f-state is-error"><i class="bi bi-shield-lock"></i>'
           . '<p>You need a platform administrator account to open the administration console.</p>'
           . '<p class="mt-2"><a class="btn btn-sm btn-outline-secondary" href="../index.php">Back to the Digital Hub</a></p></div>';
        hub_foot();
    } else {
        header('Content-Type: text/plain');
        echo "403 — administrator access required.";
    }
    exit;
}

// Every mutating request into the console is CSRF-checked.
hub_csrf_require();
