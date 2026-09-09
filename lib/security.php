<?php
/**
 * Platform security event log + SOC helpers.
 *
 * Dependency-free on purpose: every function takes an open mysqli and never
 * throws, so it is safe to call from the login handler (hub/init.php) and from
 * the access-control choke points in each app.
 *
 *   require_once __DIR__ . '/../lib/security.php';   // path varies by caller
 *   log_security_event($conn, 'login_failed', ['username' => $u, 'severity' => 'notice']);
 */

/**
 * Record one security event. Silently no-ops if the table is missing so a
 * fresh install without the migration still logs users in.
 *
 * @param array{
 *   app?:string, severity?:string, user_id?:int|null, username?:string|null,
 *   ip?:string|null, user_agent?:string|null, route?:string|null, detail?:mixed
 * } $o
 */
function log_security_event(mysqli $conn, string $type, array $o = []): void
{
    static $ok = null;
    if ($ok === null) {
        $ok = false;
        if ($r = @$conn->query("SHOW TABLES LIKE 'security_event'")) {
            $ok = $r->num_rows > 0;
            $r->free();
        }
    }
    if (!$ok) return;

    $app      = $o['app']      ?? 'hub';
    $severity = $o['severity'] ?? 'info';
    $userId   = isset($o['user_id']) ? (int) $o['user_id'] : null;
    $username = $o['username'] ?? null;
    $ip       = $o['ip']         ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    $ua       = $o['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
    $route    = $o['route']      ?? ($_SERVER['REQUEST_URI'] ?? null);
    $detail   = $o['detail']     ?? null;
    if (is_array($detail) || is_object($detail)) {
        $detail = json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $ua     = $ua     !== null ? mb_substr((string) $ua, 0, 255) : null;
    $route  = $route  !== null ? mb_substr((string) $route, 0, 190) : null;
    $detail = $detail !== null ? mb_substr((string) $detail, 0, 1000) : null;

    $st = @$conn->prepare(
        "INSERT INTO security_event (app, event_type, severity, user_id, username, ip, user_agent, route, detail)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    if (!$st) return;
    $st->bind_param('sssisssss', $app, $type, $severity, $userId, $username, $ip, $ua, $route, $detail);
    @$st->execute();
    $st->close();
}

/** True when the security schema (table + user columns) is installed. */
function security_schema_ready(mysqli $conn): bool
{
    if (!($r = @$conn->query("SHOW TABLES LIKE 'security_event'")) || !$r->num_rows) return false;
    $r->free();
    if (!($r = @$conn->query("SHOW COLUMNS FROM users LIKE 'last_login_at'")) || !$r->num_rows) return false;
    $r->free();
    return true;
}

/**
 * Aggregates for hub/admin/soc.php. All windows are relative to NOW().
 * Returns a bag of scalars + small arrays; the page renders them.
 */
function soc_metrics(mysqli $conn): array
{
    $one = function (string $sql) use ($conn) {
        $r = @$conn->query($sql);
        return $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
    };
    $rows = function (string $sql) use ($conn) {
        $r = @$conn->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    };

    $m = [];
    $m['login_ok_24h']     = $one("SELECT COUNT(*) FROM security_event WHERE event_type='login_success' AND ts >= NOW() - INTERVAL 1 DAY");
    $m['login_fail_24h']   = $one("SELECT COUNT(*) FROM security_event WHERE event_type='login_failed'  AND ts >= NOW() - INTERVAL 1 DAY");
    $m['locked_now']       = $one("SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()");
    $m['critical_7d']      = $one("SELECT COUNT(*) FROM security_event WHERE severity='critical' AND ts >= NOW() - INTERVAL 7 DAY");
    $m['denied_7d']        = $one("SELECT COUNT(*) FROM security_event WHERE event_type='access_denied' AND ts >= NOW() - INTERVAL 7 DAY");
    $m['fail_ratio']       = $m['login_ok_24h'] > 0
        ? round($m['login_fail_24h'] / max(1, $m['login_ok_24h'] + $m['login_fail_24h']) * 100)
        : ($m['login_fail_24h'] > 0 ? 100 : 0);

    // failed logins per day, last 14 days (dense — fill gaps in the page)
    $m['fail_by_day'] = $rows(
        "SELECT DATE(ts) d, COUNT(*) n
           FROM security_event
          WHERE event_type='login_failed' AND ts >= CURDATE() - INTERVAL 13 DAY
          GROUP BY DATE(ts) ORDER BY d"
    );
    $m['lockout_days'] = array_column($rows(
        "SELECT DISTINCT DATE(ts) d FROM security_event
          WHERE event_type='login_lockout' AND ts >= CURDATE() - INTERVAL 13 DAY"
    ), 'd');

    $m['top_targets'] = $rows(
        "SELECT username, COUNT(*) n, MAX(ts) last_ts
           FROM security_event
          WHERE event_type='login_failed' AND ts >= NOW() - INTERVAL 7 DAY AND username IS NOT NULL
          GROUP BY username ORDER BY n DESC LIMIT 8"
    );
    $m['top_ips'] = $rows(
        "SELECT ip, COUNT(*) n, MAX(ts) last_ts
           FROM security_event
          WHERE event_type='login_failed' AND ts >= NOW() - INTERVAL 7 DAY AND ip IS NOT NULL
          GROUP BY ip ORDER BY n DESC LIMIT 8"
    );
    $m['denied_by'] = $rows(
        "SELECT COALESCE(username,'—') username, route, detail, COUNT(*) n, MAX(ts) last_ts
           FROM security_event
          WHERE event_type='access_denied' AND ts >= NOW() - INTERVAL 7 DAY
          GROUP BY username, route, detail ORDER BY n DESC LIMIT 15"
    );
    $m['admin_feed'] = $rows(
        "SELECT ts, username, detail, severity
           FROM security_event
          WHERE event_type='admin_action' ORDER BY id DESC LIMIT 25"
    );
    $m['recent_logins'] = $rows(
        "SELECT ts, username, ip FROM security_event
          WHERE event_type='login_success' ORDER BY id DESC LIMIT 12"
    );
    $m['after_hours_7d'] = $one(
        "SELECT COUNT(*) FROM security_event
          WHERE ts >= NOW() - INTERVAL 7 DAY AND (HOUR(ts) < 6 OR HOUR(ts) >= 19)"
    );

    // account hygiene
    $m['never_logged_in'] = $one("SELECT COUNT(*) FROM users WHERE active=1 AND last_login_at IS NULL");
    $m['stale_password']  = $one("SELECT COUNT(*) FROM users WHERE active=1 AND (pwd_updated_at IS NULL OR pwd_updated_at < NOW() - INTERVAL 180 DAY)");
    $m['dormant']         = $one("SELECT COUNT(*) FROM users WHERE active=1 AND last_login_at IS NOT NULL AND last_login_at < NOW() - INTERVAL 90 DAY");
    $m['admins']          = $rows("SELECT username, full_name, last_login_at FROM users WHERE role='admin' ORDER BY username");

    // legacy import runs
    $m['imports'] = $rows("SELECT phase, dry_run, inserted, updated, skipped, ts_create FROM es_import_log ORDER BY id DESC LIMIT 8");

    return $m;
}

/**
 * Filtered event feed for hub/admin/audit.php.
 * @param array{type?:string,severity?:string,user?:string,from?:string,to?:string,q?:string,limit?:int,offset?:int} $f
 * @return array{rows:array,total:int}
 */
function soc_recent(mysqli $conn, array $f = []): array
{
    $where = [];
    $types = '';
    $args  = [];
    if (!empty($f['type']))     { $where[] = 'event_type = ?';    $types .= 's'; $args[] = $f['type']; }
    if (!empty($f['severity'])) { $where[] = 'severity = ?';      $types .= 's'; $args[] = $f['severity']; }
    if (!empty($f['user']))     { $where[] = 'username LIKE ?';   $types .= 's'; $args[] = '%' . $f['user'] . '%'; }
    if (!empty($f['from']))     { $where[] = 'ts >= ?';           $types .= 's'; $args[] = $f['from'] . ' 00:00:00'; }
    if (!empty($f['to']))       { $where[] = 'ts <= ?';           $types .= 's'; $args[] = $f['to'] . ' 23:59:59'; }
    if (!empty($f['q']))        { $where[] = '(detail LIKE ? OR route LIKE ? OR ip LIKE ?)';
                                  $types .= 'sss'; $q = '%' . $f['q'] . '%'; array_push($args, $q, $q, $q); }
    $wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $limit  = max(1, min(200, (int) ($f['limit'] ?? 50)));
    $offset = max(0, (int) ($f['offset'] ?? 0));

    $total = 0;
    if ($st = $conn->prepare("SELECT COUNT(*) FROM security_event $wsql")) {
        if ($types !== '') $st->bind_param($types, ...$args);
        $st->execute();
        $total = (int) ($st->get_result()->fetch_row()[0] ?? 0);
        $st->close();
    }

    $rows = [];
    if ($st = $conn->prepare("SELECT * FROM security_event $wsql ORDER BY id DESC LIMIT $limit OFFSET $offset")) {
        if ($types !== '') $st->bind_param($types, ...$args);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    }
    return ['rows' => $rows, 'total' => $total];
}
