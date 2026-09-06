<?php
/**
 * e-Services bootstrap — shared by every page under app/eservice/.
 *
 *  - Reuses the ememo SSO session ($_SESSION['user_id'] set by hub/login).
 *  - Opens the shared ememo database (config/database.php) as $conn (mysqli).
 *  - Exposes small helpers: e(), redirect(), current_user(), es_roles(),
 *    es_require_role(), flash(), take_flash(), es_csrf_token(), es_csrf_check().
 *
 * Include this first on every page:  require __DIR__ . '/inc/bootstrap.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Shared DB (defines $host,$user,$password,$database and opens $conn) ----
require_once __DIR__ . '/../../../config/database.php';   // -> $conn (mysqli)
mysqli_report(MYSQLI_REPORT_OFF);
$conn->set_charset('utf8mb4');

// --- Paths ---------------------------------------------------------------
define('ES_ROOT', dirname(__DIR__));                       // .../app/eservice
define('ES_BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/app/eservice/x'), '/'));
define('ES_HUB_URL', '../../hub/index.php');
define('ES_LOGIN_URL', '../../hub/login.php');
define('ES_UPLOAD_DIR', ES_ROOT . '/uploads');

// --- Auth guard --------------------------------------------------------
if (empty($_SESSION['user_id'])) {
    header('Location: ' . ES_LOGIN_URL);
    exit;
}
$ES_UID = (int) $_SESSION['user_id'];

// --- Helpers ----------------------------------------------------------
function e($s): string {
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function es_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Current ememo user row (id, username, full_name, email, role). Cached per request. */
function current_user(): array {
    static $u = null;
    if ($u !== null) return $u;
    global $conn, $ES_UID;
    $u = ['id' => $ES_UID, 'username' => $_SESSION['username'] ?? '', 'full_name' => '', 'email' => '', 'role' => $_SESSION['role'] ?? ''];
    if ($st = $conn->prepare('SELECT username, full_name, email, role FROM users WHERE id = ? LIMIT 1')) {
        $st->bind_param('i', $ES_UID);
        $st->execute();
        if ($row = $st->get_result()->fetch_assoc()) {
            $u['username']  = $row['username'] ?: $u['username'];
            $u['full_name'] = $row['full_name'] ?: $u['username'];
            $u['email']     = $row['email'] ?? '';
            $u['role']      = $row['role'] ?? $u['role'];
        }
        $st->close();
    }
    if ($u['full_name'] === '') $u['full_name'] = $u['username'];
    return $u;
}

/** e-Services roles held by the current user (from es_user_role). */
function es_roles(): array {
    static $roles = null;
    if ($roles !== null) return $roles;
    global $conn, $ES_UID;
    $roles = [];
    if ($res = $conn->query('SELECT role FROM es_user_role WHERE user_id = ' . (int) $ES_UID)) {
        while ($r = $res->fetch_row()) $roles[] = $r[0];
        $res->free();
    }
    // ememo admins are e-services admins too
    if (($_SESSION['role'] ?? '') === 'admin' && !in_array('admin', $roles, true)) {
        $roles[] = 'admin';
    }
    return $roles;
}

function es_has_role(string ...$want): bool {
    $have = es_roles();
    if (in_array('admin', $have, true)) return true;
    foreach ($want as $w) if (in_array($w, $have, true)) return true;
    return false;
}

/** Permission keys the current user has via their roles (admin = all). */
function es_perms(): array {
    static $perms = null;
    if ($perms !== null) return $perms;
    $roles = es_roles();
    if (in_array('admin', $roles, true)) return $perms = ['*'];
    $perms = [];
    if (!$roles) return $perms;
    global $conn;
    $in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $roles)) . "'";
    if ($res = $conn->query("SELECT DISTINCT perm_key FROM es_role_permission WHERE role_key IN ($in)")) {
        while ($r = $res->fetch_row()) $perms[] = $r[0];
        $res->free();
    }
    return $perms;
}

function es_can(string $perm): bool {
    $p = es_perms();
    return in_array('*', $p, true) || in_array($perm, $p, true);
}

function es_require_perm(string $perm): void {
    if (es_can($perm)) return;
    http_response_code(403);
    if (function_exists('es_layout_head')) {
        es_layout_head('Access denied');
        echo '<div class="f-state is-error"><i class="bi bi-shield-lock"></i><p>You don\'t have permission for this ('
           . e($perm) . ').</p><p class="mt-2"><a class="btn btn-sm btn-outline-secondary" href="index.php">Back</a></p></div>';
        es_layout_foot();
    } else {
        header('Content-Type: text/plain');
        echo "403 — missing permission: $perm";
    }
    exit;
}

/**
 * PDE visibility scope.
 *   null  -> unrestricted (registry / officers / dg / admin see every submission)
 *   > 0   -> a PDE user: only see es_bid_registry rows with this pde_id
 *   -1    -> a PDE user with no PDE assigned yet: sees nothing
 */
function es_pde_scope(): ?int {
    static $scope = false;
    if ($scope !== false) return $scope;
    global $conn, $ES_UID;
    if (es_has_role('registry', 'allocator', 'officer', 'supervisor', 'director', 'dg', 'board', 'admin')) {
        return $scope = null;
    }
    if (!in_array('pde', es_roles(), true)) return $scope = null;
    $r = $conn->query("SELECT pde_id FROM es_user_role WHERE user_id = " . (int) $ES_UID . " AND role = 'pde' AND pde_id IS NOT NULL LIMIT 1");
    $row = $r ? $r->fetch_row() : null;
    return $scope = ($row ? (int) $row[0] : -1);
}

function es_require_role(string ...$want): void {
    if (es_has_role(...$want)) return;
    http_response_code(403);
    // Full page when the layout is loaded (GET screens); plain text for POST handlers.
    if (function_exists('es_layout_head')) {
        es_layout_head('Access denied');
        echo '<div class="f-state is-error"><i class="bi bi-shield-lock"></i><p>You don\'t have an e-Services role that can open this page.</p>'
           . '<p class="mt-2"><a class="btn btn-sm btn-outline-secondary" href="index.php">Back to dashboard</a></p></div>';
        es_layout_foot();
    } else {
        header('Content-Type: text/plain');
        echo "403 — you don't have an e-Services role for this action.";
    }
    exit;
}

// --- Flash messages -------------------------------------------------
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['es_flash'][] = ['msg' => $msg, 'type' => $type];
}
function take_flash(): array {
    $f = $_SESSION['es_flash'] ?? [];
    unset($_SESSION['es_flash']);
    return $f;
}

// --- CSRF -----------------------------------------------------------
function es_csrf_token(): string {
    if (empty($_SESSION['es_csrf'])) {
        $_SESSION['es_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['es_csrf'];
}
function es_csrf_check(): bool {
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['es_csrf'] ?? '', $sent);
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

/**
 * Windowed pager:  ‹ Prev  1 … 4 5 [6] 7 8 … 653  Next ›   + "Showing X–Y of Z".
 * $urlFor(int $page): string  builds the href for a page (keep other query params).
 */
function es_pager(int $page, int $pages, callable $urlFor, int $total = 0, int $per = 0): string {
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

    $btn = fn($label, $p, $rel = '') =>
        '<a class="es-pg-b" href="' . $h($urlFor($p)) . '"' . ($rel ? " rel=\"$rel\"" : '') . '>' . $h($label) . '</a>';
    $off = fn($label) => '<span class="es-pg-b is-off">' . $h($label) . '</span>';

    $out .= '<nav class="es-pg">';
    $out .= $page > 1 ? $btn('‹ Prev', $page - 1, 'prev') : $off('‹ Prev');
    $prev = 0;
    foreach ($set as $p) {
        if ($prev && $p - $prev > 1) $out .= '<span class="es-pg-gap">…</span>';
        $out .= $p === $page ? '<span class="es-pg-b is-cur">' . $p . '</span>' : $btn((string) $p, $p);
        $prev = $p;
    }
    $out .= $page < $pages ? $btn('Next ›', $page + 1, 'next') : $off('Next ›');
    $out .= '</nav>';
    return $out;
}

/** The "accompanying documents" checklist on a submission — key => label, in display order.
 *  Keys are the SET() members of es_bid_registry.accompanied_documents. */
function es_accompanying_docs(): array {
    return [
        'ipdc_minutes'              => 'IPDC Minutes',
        'evaluation_report'         => 'Evaluation Report',
        'original_bidding_document' => 'Original Bidding Document',
        'advert'                    => 'Advert',
        'contract'                  => 'Contract',
        'treasury_authorization'    => 'Authorization from Treasury',
        'financial_proposal'        => 'Financial Proposal',
        'technical_proposal'        => 'Technical Proposal',
        'bidder_bid_documents'      => 'Bid Documents from Bidders',
        'general_submission'        => 'General Submission',
    ];
}

/** Allocation / reassignment history for a submission, newest first, with resolved names. */
function es_alloc_log(int $registryId): array {
    return db_all(
        "SELECT a.action, a.reason, a.ts_create,
                f.full_name AS from_name, t.full_name AS to_name, b.full_name AS by_name
           FROM es_bid_allocation a
           LEFT JOIN users f ON f.id = a.from_officer_id
           LEFT JOIN users t ON t.id = a.to_officer_id
           LEFT JOIN users b ON b.id = a.by_user_id
          WHERE a.registry_id = ?
          ORDER BY a.id DESC",
        'i', [$registryId]
    );
}

/** Very short elapsed time from $ts to now: "14d", "3h", "45m", "2mo", or "—". */
function es_span(?string $ts): string {
    if (!$ts || $ts === '0000-00-00 00:00:00') return '—';
    $s = max(0, time() - strtotime($ts));
    if ($s < 3600)     return max(1, (int) floor($s / 60)) . 'm';
    if ($s < 86400)    return (int) floor($s / 3600) . 'h';
    if ($s < 2592000)  return (int) floor($s / 86400) . 'd';
    if ($s < 31536000) return (int) floor($s / 2592000) . 'mo';
    return (int) floor($s / 31536000) . 'y';
}

/** Compact relative age, e.g. "just now", "3h ago", "5d ago", "2mo ago". */
function es_ago(?string $ts): string {
    if (!$ts || $ts === '0000-00-00 00:00:00') return '—';
    $s = time() - strtotime($ts);
    if ($s < 0) $s = 0;
    if ($s < 60)        return 'just now';
    if ($s < 3600)      return floor($s / 60) . 'm ago';
    if ($s < 86400)     return floor($s / 3600) . 'h ago';
    if ($s < 2592000)   return floor($s / 86400) . 'd ago';
    if ($s < 31536000)  return floor($s / 2592000) . 'mo ago';
    return floor($s / 31536000) . 'y ago';
}
