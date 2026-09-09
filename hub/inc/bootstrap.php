<?php
/**
 * Hub / Administration console bootstrap — shared by hub/admin/*.php.
 *
 *  - Reuses the SSO session ($_SESSION['user_id'] set by hub/init.php).
 *  - Opens the shared database (config/database.php) as $conn (mysqli).
 *  - Small helpers: e(), redirect(), hub_user(), hub_csrf_token()/hub_csrf_check(),
 *    hub_flash()/hub_take_flash(), db_one()/db_all(), hub_audit().
 *
 * Include first on every console page:  require __DIR__ . '/../inc/bootstrap.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';   // -> $conn (mysqli)
mysqli_report(MYSQLI_REPORT_OFF);
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/../../lib/security.php';

define('HUB_ROOT', dirname(__DIR__));                   // .../hub
define('HUB_LOGIN_URL', '../login.php');                // from hub/admin/*

// --- Auth guard (session only; role gate is in hub/admin/_guard.php) -------
if (empty($_SESSION['user_id'])) {
    header('Location: ' . HUB_LOGIN_URL);
    exit;
}
$HUB_UID = (int) $_SESSION['user_id'];

// --- Helpers -------------------------------------------------------------
function e($s): string {
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

/** Current user row (id, username, full_name, email, role, active). Cached. */
function hub_user(): array {
    static $u = null;
    if ($u !== null) return $u;
    global $conn, $HUB_UID;
    $u = ['id' => $HUB_UID, 'username' => $_SESSION['username'] ?? '', 'full_name' => '',
          'email' => '', 'role' => $_SESSION['role'] ?? '', 'active' => 1];
    if ($st = $conn->prepare('SELECT username, full_name, email, role, active FROM users WHERE id = ? LIMIT 1')) {
        $st->bind_param('i', $HUB_UID);
        $st->execute();
        if ($row = $st->get_result()->fetch_assoc()) {
            $u['username']  = $row['username'] ?: $u['username'];
            $u['full_name'] = $row['full_name'] ?: $u['username'];
            $u['email']     = $row['email'] ?? '';
            $u['role']      = $row['role'] ?? $u['role'];
            $u['active']    = (int) $row['active'];
        }
        $st->close();
    }
    if ($u['full_name'] === '') $u['full_name'] = $u['username'];
    return $u;
}

/** e-Services roles held by the current user (from es_user_role) + implicit admin. */
function hub_es_roles(): array {
    static $roles = null;
    if ($roles !== null) return $roles;
    global $conn, $HUB_UID;
    $roles = [];
    if ($res = @$conn->query('SELECT role FROM es_user_role WHERE user_id = ' . (int) $HUB_UID)) {
        while ($r = $res->fetch_row()) $roles[] = $r[0];
        $res->free();
    }
    return $roles;
}

/** Platform administrator: ememo users.role='admin' OR an es 'admin' role. */
function hub_is_admin(): bool {
    return (hub_user()['role'] ?? '') === 'admin' || in_array('admin', hub_es_roles(), true);
}

// --- CSRF -------------------------------------------------------------
function hub_csrf_token(): string {
    if (empty($_SESSION['hub_csrf'])) {
        $_SESSION['hub_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['hub_csrf'];
}
function hub_csrf_check(): bool {
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['hub_csrf'] ?? '', $sent);
}
/** Verify CSRF on a POST or die 400. Call at the top of every *_save.php. */
function hub_csrf_require(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hub_csrf_check()) {
        global $conn;
        log_security_event($conn, 'csrf_failure', [
            'severity' => 'warning', 'user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'username' => $_SESSION['username'] ?? null, 'app' => 'hub',
        ]);
        http_response_code(400);
        exit('Bad request (CSRF).');
    }
}

// --- Flash messages -------------------------------------------------
function hub_flash(string $msg, string $type = 'success'): void {
    $_SESSION['hub_flash'][] = ['msg' => $msg, 'type' => $type];
}
function hub_take_flash(): array {
    $f = $_SESSION['hub_flash'] ?? [];
    unset($_SESSION['hub_flash']);
    return $f;
}

// --- Audit shortcut ----------------------------------------------
/** Record an admin action against the current user. */
function hub_audit(string $detail, string $severity = 'info'): void {
    global $conn;
    $u = hub_user();
    log_security_event($conn, 'admin_action', [
        'app' => 'hub', 'severity' => $severity,
        'user_id' => $u['id'], 'username' => $u['username'],
        'detail' => $detail,
    ]);
}

// --- Small DB conveniences ---------------------------------------
function db_one(string $sql, string $types = '', array $params = []) {
    global $conn;
    $st = $conn->prepare($sql);
    if (!$st) return null;
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}
function db_all(string $sql, string $types = '', array $params = []): array {
    global $conn;
    $st = $conn->prepare($sql);
    if (!$st) return [];
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

/** Windowed pager identical in look to e-Services es_pager(). */
function hub_pager(int $page, int $pages, callable $urlFor, int $total = 0, int $per = 0): string {
    $pages = max(1, $pages);
    $page  = max(1, min($page, $pages));
    $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $out = '';
    if ($total > 0 && $per > 0) {
        $from = ($page - 1) * $per + 1;
        $to   = min($page * $per, $total);
        $out .= '<div class="es-pg-info">Showing ' . number_format($from) . '–' . number_format($to)
              . ' of ' . number_format($total) . '</div>';
    }
    if ($pages <= 1) return $out;
    $set = [1, $pages];
    for ($i = $page - 2; $i <= $page + 2; $i++) if ($i >= 1 && $i <= $pages) $set[] = $i;
    $set = array_values(array_unique($set));
    sort($set);
    $btn = fn($label, $p) => '<a class="es-pg-b" href="' . $h($urlFor($p)) . '">' . $h($label) . '</a>';
    $off = fn($label) => '<span class="es-pg-b is-off">' . $h($label) . '</span>';
    $out .= '<nav class="es-pg">';
    $out .= $page > 1 ? $btn('‹ Prev', $page - 1) : $off('‹ Prev');
    $prev = 0;
    foreach ($set as $p) {
        if ($prev && $p - $prev > 1) $out .= '<span class="es-pg-gap">…</span>';
        $out .= $p === $page ? '<span class="es-pg-b is-cur">' . $p . '</span>' : $btn((string) $p, $p);
        $prev = $p;
    }
    $out .= $page < $pages ? $btn('Next ›', $page + 1) : $off('Next ›');
    $out .= '</nav>';
    return $out;
}
