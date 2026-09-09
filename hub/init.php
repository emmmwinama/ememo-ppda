<?php
// init.php — SSO login handler (JSON API for hub/login.php).

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';                 // -> $conn
require_once __DIR__ . '/../lib/security.php';    // -> log_security_event()

const LOGIN_MAX_FAILS   = 5;
const LOGIN_LOCK_MINUTES = 15;

/** Uniform JSON reply + exit. */
function reply(array $payload): void {
    echo json_encode($payload);
    exit;
}

// ---- parse input --------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    reply(['success' => false, 'message' => 'Invalid request.']);
}
$username = trim($input['username'] ?? '');
$password = (string) ($input['password'] ?? '');

if ($username === '' || $password === '') {
    reply(['success' => false, 'message' => 'Username and password are required.']);
}

if ($conn->connect_error) {
    error_log("DB connection failed: {$conn->connect_error}");
    reply(['success' => false, 'message' => 'Internal server error.']);
}

$hasSecurity = false;
if ($r = @$conn->query("SHOW COLUMNS FROM users LIKE 'locked_until'")) {
    $hasSecurity = $r->num_rows > 0;
    $r->free();
}

// ---- fetch user -------------------------------------------------------
$cols = "id, username, password, role, active, password_changed"
      . ($hasSecurity ? ", failed_logins, locked_until" : "");
$stmt = $conn->prepare("SELECT $cols FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$fail = function (string $msg) use ($conn, $username, $user, $hasSecurity) {
    log_security_event($conn, 'login_failed', [
        'app' => 'hub', 'severity' => 'notice',
        'user_id' => $user['id'] ?? null, 'username' => $username,
    ]);
    if ($hasSecurity && $user) {
        $n = (int) $user['failed_logins'] + 1;
        if ($n >= LOGIN_MAX_FAILS) {
            $conn->query("UPDATE users SET failed_logins = $n,
                          locked_until = NOW() + INTERVAL " . LOGIN_LOCK_MINUTES . " MINUTE
                          WHERE id = " . (int) $user['id']);
            log_security_event($conn, 'login_lockout', [
                'app' => 'hub', 'severity' => 'critical',
                'user_id' => $user['id'], 'username' => $username,
                'detail' => "$n consecutive failures — locked for " . LOGIN_LOCK_MINUTES . " min",
            ]);
        } else {
            $conn->query("UPDATE users SET failed_logins = $n WHERE id = " . (int) $user['id']);
        }
    }
    reply(['success' => false, 'message' => $msg]);
};

if (!$user) {
    $fail('Invalid credentials.');
}

// ---- lock-out gate --------------------------------------------------
if ($hasSecurity && !empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
    log_security_event($conn, 'login_blocked', [
        'app' => 'hub', 'severity' => 'warning',
        'user_id' => $user['id'], 'username' => $username,
        'detail' => 'attempt while locked',
    ]);
    reply(['success' => false, 'message' => 'This account is temporarily locked. Try again later or contact an administrator.']);
}

// ---- verify password ----------------------------------------------
$verify = password_verify($password, trim((string) $user['password']));
if (!$verify && strlen((string) $user['password']) === 32 && hash_equals(strtolower($user['password']), md5($password))) {
    $verify = true;   // legacy MD5 fallback (to be removed once all users migrated)
}
if (!$verify) {
    $fail('Invalid credentials.');
}

if (!(bool) $user['active']) {
    log_security_event($conn, 'login_failed', [
        'app' => 'hub', 'severity' => 'notice',
        'user_id' => $user['id'], 'username' => $username, 'detail' => 'account deactivated',
    ]);
    reply(['success' => false, 'message' => 'Account deactivated. Contact an administrator.']);
}

// ---- success ----------------------------------------------------
if ($hasSecurity) {
    $conn->query("UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW()
                  WHERE id = " . (int) $user['id']);
}
log_security_event($conn, 'login_success', [
    'app' => 'hub', 'severity' => 'info',
    'user_id' => $user['id'], 'username' => $user['username'],
]);

session_regenerate_id(true);
$_SESSION['user_id']          = $user['id'];
$_SESSION['username']         = $user['username'];
$_SESSION['name']             = $user['username'];
$_SESSION['role']             = $user['role'];
$_SESSION['password_changed'] = $user['password_changed'];

$_SESSION['has_signature'] = false;
if ($sig = $conn->prepare("SELECT COUNT(*) FROM signatures WHERE user_id = ?")) {
    $sig->bind_param('i', $user['id']);
    $sig->execute();
    $sig->bind_result($sigCount);
    $sig->fetch();
    $_SESSION['has_signature'] = ($sigCount > 0);
    $sig->close();
}

$redirect = ((int) $user['password_changed'] === 0) ? 'change-password.php' : 'index.php';
reply(['success' => true, 'redirect' => $redirect]);
