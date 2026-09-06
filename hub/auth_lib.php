<?php
// Shared credential-check helpers used by hub/login_handler.php,
// hub/login_api.php, and app/ememo/process_login.php.

/**
 * Looks up a user by username and verifies the password.
 * Returns null if the username/password don't match.
 * Returns ['error' => 'inactive'] if credentials match but the account is disabled.
 * Otherwise returns the user row (id, username, password, role, active,
 * password_changed, is_controlling_officer).
 */
function ememo_verify_credentials(mysqli $conn, string $username, string $password): ?array {
    $stmt = $conn->prepare(
        "SELECT id, username, password, role, active, password_changed, is_controlling_officer
           FROM users WHERE username = ? LIMIT 1"
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, trim($user['password']))) {
        return null;
    }

    if (!(bool)$user['active']) {
        return ['error' => 'inactive'];
    }

    return $user;
}

/** Whether the given user has an uploaded signature on file. */
function ememo_has_signature(mysqli $conn, int $userId): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM signatures WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}
