<?php
// Shared credential-check helpers used by hub/login_handler.php,
// hub/login_api.php, and app/ememo/process_login.php.

require_once __DIR__ . '/../lib/security.php';   // log_security_event(), security_schema_ready()

const EMEMO_LOGIN_MAX_FAILS    = 5;
const EMEMO_LOGIN_LOCK_MINUTES = 15;

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

    $ok = $user && (
        password_verify($password, trim((string) $user['password']))
        || (strlen((string) $user['password']) === 32 && hash_equals(strtolower($user['password']), md5($password)))
    );
    if (!$ok) {
        return null;
    }
    if (!(bool) $user['active']) {
        return ['error' => 'inactive'];
    }
    return $user;
}

/** True while the account is inside a lock-out window. */
function ememo_login_locked(mysqli $conn, string $username): bool {
    if (!security_schema_ready($conn)) return false;
    $st = $conn->prepare("SELECT locked_until FROM users WHERE username = ? LIMIT 1");
    $st->bind_param('s', $username);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time();
}

/**
 * Record the outcome of a login attempt: writes a security_event and, when the
 * hardening columns exist, maintains failed_logins / locked_until / last_login_at.
 *
 * @param string $outcome  'success' | 'failed' | 'inactive' | 'blocked'
 */
function ememo_note_login(mysqli $conn, string $username, string $outcome): void {
    $ready = security_schema_ready($conn);

    // resolve the user id/counter (needed on failure too, for the lock-out)
    $uid = null; $fails = 0;
    if ($st = $conn->prepare("SELECT id" . ($ready ? ", failed_logins" : "") . " FROM users WHERE username = ? LIMIT 1")) {
        $st->bind_param('s', $username);
        $st->execute();
        if ($row = $st->get_result()->fetch_assoc()) {
            $uid   = (int) $row['id'];
            $fails = (int) ($row['failed_logins'] ?? 0);
        }
        $st->close();
    }

    if ($outcome === 'success') {
        log_security_event($conn, 'login_success', ['app' => 'hub', 'user_id' => $uid, 'username' => $username]);
        if ($ready && $uid) {
            $conn->query("UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = $uid");
        }
        return;
    }

    if ($outcome === 'blocked') {
        log_security_event($conn, 'login_blocked', [
            'app' => 'hub', 'severity' => 'warning', 'user_id' => $uid, 'username' => $username,
            'detail' => 'attempt while locked out',
        ]);
        return;
    }

    // failed / inactive
    $detail = $outcome === 'inactive' ? 'account deactivated' : null;
    log_security_event($conn, 'login_failed', [
        'app' => 'hub', 'severity' => 'notice', 'user_id' => $uid, 'username' => $username, 'detail' => $detail,
    ]);

    if ($ready && $uid) {
        $n = $fails + 1;
        if ($n >= EMEMO_LOGIN_MAX_FAILS) {
            $conn->query("UPDATE users SET failed_logins = $n,
                          locked_until = NOW() + INTERVAL " . EMEMO_LOGIN_LOCK_MINUTES . " MINUTE WHERE id = $uid");
            log_security_event($conn, 'login_lockout', [
                'app' => 'hub', 'severity' => 'critical', 'user_id' => $uid, 'username' => $username,
                'detail' => "$n consecutive failures — locked for " . EMEMO_LOGIN_LOCK_MINUTES . " min",
            ]);
        } else {
            $conn->query("UPDATE users SET failed_logins = $n WHERE id = $uid");
        }
    }
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
