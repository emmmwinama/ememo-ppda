<?php
/**
 * Reports app bootstrap — shared by app/reports/*.php.
 * Read-only: every page here only SELECTs and aggregates.
 *
 *   require __DIR__ . '/inc/bootstrap.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';   // -> $conn (mysqli)
mysqli_report(MYSQLI_REPORT_OFF);
$conn->set_charset('utf8mb4');

define('RPT_ROOT', dirname(__DIR__));
define('RPT_LOGIN_URL', '../../hub/login.php');
define('RPT_HUB_URL', '../../hub/index.php');

if (empty($_SESSION['user_id'])) {
    header('Location: ' . RPT_LOGIN_URL);
    exit;
}
$RPT_UID = (int) $_SESSION['user_id'];

function e($s): string {
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $to): void { header('Location: ' . $to); exit; }

/** Current user (id, username, full_name, email, role). Cached. */
function rpt_user(): array {
    static $u = null;
    if ($u !== null) return $u;
    global $conn, $RPT_UID;
    $u = ['id' => $RPT_UID, 'username' => $_SESSION['username'] ?? '', 'full_name' => '',
          'email' => '', 'role' => $_SESSION['role'] ?? ''];
    if ($st = $conn->prepare('SELECT username, full_name, email, role FROM users WHERE id = ? LIMIT 1')) {
        $st->bind_param('i', $RPT_UID);
        $st->execute();
        if ($row = $st->get_result()->fetch_assoc()) {
            $u = array_merge($u, array_filter([
                'username' => $row['username'], 'full_name' => $row['full_name'],
                'email' => $row['email'], 'role' => $row['role'],
            ], fn($v) => $v !== null && $v !== ''));
        }
        $st->close();
    }
    if (($u['full_name'] ?? '') === '') $u['full_name'] = $u['username'];
    return $u;
}

/** e-Services role keys held by the current user. */
function rpt_es_roles(): array {
    static $r = null;
    if ($r !== null) return $r;
    global $conn, $RPT_UID;
    $r = [];
    if ($res = @$conn->query('SELECT role FROM es_user_role WHERE user_id = ' . (int) $RPT_UID)) {
        while ($x = $res->fetch_row()) $r[] = $x[0];
        $res->free();
    }
    return $r;
}

function rpt_is_admin(): bool {
    return (rpt_user()['role'] ?? '') === 'admin' || in_array('admin', rpt_es_roles(), true);
}

/**
 * Access gate. $area 'audit' is admin-only; everything else is open to any
 * authenticated user (reports are aggregate — names and counts, no record PII).
 */
function rpt_require(string $area = 'general'): void {
    $ok = $area === 'audit' ? rpt_is_admin() : !empty($_SESSION['user_id']);
    if ($ok) return;
    http_response_code(403);
    if (function_exists('rpt_head')) {
        rpt_head('Not allowed', '');
        echo '<div class="f-state is-error"><i class="bi bi-shield-lock"></i>'
           . '<p>This report is restricted to administrators.</p>'
           . '<p class="mt-2"><a class="btn btn-sm btn-outline-secondary" href="index.php">Back to reports</a></p></div>';
        rpt_foot();
    } else {
        echo '403 — administrators only.';
    }
    exit;
}

// ---- tiny query helpers ------------------------------------------------
function db_one(string $sql, string $types = '', array $params = []) {
    global $conn;
    $st = @$conn->prepare($sql);
    if (!$st) return null;
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}
function db_all(string $sql, string $types = '', array $params = []): array {
    global $conn;
    $st = @$conn->prepare($sql);
    if (!$st) return [];
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
/** Scalar from an arbitrary aggregate query (returns 0 on any error). */
function db_scalar(string $sql): float {
    global $conn;
    $r = @$conn->query($sql);
    return $r ? (float) ($r->fetch_row()[0] ?? 0) : 0.0;
}
/** True when a table exists (so a report degrades on a partial schema). */
function rpt_has_table(string $t): bool {
    global $conn;
    static $cache = [];
    if (isset($cache[$t])) return $cache[$t];
    $t2 = str_replace('`', '', $t);
    $r = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t2) . "'");
    return $cache[$t] = (bool) ($r && $r->num_rows);
}
