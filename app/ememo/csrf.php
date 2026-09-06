<?php
// Shared CSRF token helpers. Included by auth.php so every page that
// requires login also gets a token, and every POST through auth.php
// gets verified.

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Checks the token sent by the client (X-CSRF-Token header, or a
 * csrf_token POST field for non-fetch callers) against the session's
 * token. Responds 403 JSON and stops execution on mismatch.
 */
function csrf_verify(): void {
    $sent     = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';

    if ($expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'Invalid or missing security token. Please refresh the page and try again.',
        ]);
        exit;
    }
}
