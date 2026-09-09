<?php
/** Report — security events (admin only). */
require __DIR__ . '/inc/layout.php';
rpt_require('audit');

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('security_event')) {
    rpt_head('Security', 'x_security');
    echo '<div class="f-state is-error"><i class="bi bi-database-exclamation"></i><p>Run <code>db/migrations/20260909_security.sql</code> to enable security logging.</p></div>';
    rpt_foot();
    exit;
}

$ct = fn(string $t) => (int) db_scalar("SELECT COUNT(*) FROM security_event WHERE event_type = '$t' AND " . $bw('ts'));

$loginOk   = $ct('login_success');
$loginBad  = $ct('login_failed');
$lockouts  = $ct('login_lockout');
$denied    = $ct('access_denied');
$adminActs = $ct('admin_action');

$byType = db_all("SELECT event_type, severity, COUNT(*) n FROM security_event WHERE " . $bw('ts') . " GROUP BY event_type, severity ORDER BY n DESC");
$cols = [
    'event_type' => 'Event',
    'severity'   => 'Severity',
    'n'          => ['label' => 'Count', 'align' => 'end'],
];
rpt_maybe_csv('security-events-by-type', $cols, $byType);

$failByUser = db_all(
    "SELECT COALESCE(username,'(unknown)') username, COUNT(*) n, MAX(ts) last_ts
       FROM security_event WHERE event_type = 'login_failed' AND " . $bw('ts') . "
      GROUP BY username ORDER BY n DESC LIMIT 15"
);
$adminFeed = db_all(
    "SELECT ts, username, detail FROM security_event
      WHERE event_type = 'admin_action' AND " . $bw('ts') . "
      ORDER BY id DESC LIMIT 30"
);

$dayRows = db_all("SELECT DATE(ts) d, COUNT(*) n FROM security_event WHERE event_type='login_failed' AND ts >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(ts) ORDER BY d");
$byDay = [];
foreach ($dayRows as $x) $byDay[$x['d']] = (int) $x['n'];
$series = [];
for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $series[] = ['d' => date('j/n', strtotime($d)), 'n' => $byDay[$d] ?? 0]; }

rpt_head('Security', 'x_security', 'Authentication and access-control events — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [$loginOk,   'Successful sign-ins', 's-green', 'bi-box-arrow-in-right'],
    [$loginBad,  'Failed sign-ins',     's-amber', 'bi-shield-exclamation'],
    [$lockouts,  'Account lock-outs',   's-rose',  'bi-lock'],
    [$denied,    'Permission denials',  's-violet','bi-slash-circle'],
    [$adminActs, 'Admin actions',       's-sky',   'bi-journal-text'],
]);
rpt_timebars($series, 'Failed sign-ins — last 14 days', 'bi-shield-exclamation');
echo '<div class="mt-3">';
rpt_table($cols, $byType, 'Events by type', 'bi-list-columns');
echo '</div><div class="row g-3 mt-0"><div class="col-lg-5">';
rpt_table([
    'username' => 'Username',
    'n'        => ['label' => 'Failures', 'align' => 'end'],
    'last_ts'  => ['label' => 'Last', 'fmt' => fn($v) => $v ? date('d M H:i', strtotime($v)) : '—', 'align' => 'end'],
], $failByUser, 'Most-targeted usernames', 'bi-person-x');
echo '</div><div class="col-lg-7">';
rpt_table([
    'ts'       => ['label' => 'When', 'fmt' => fn($v) => date('d M H:i', strtotime($v))],
    'username' => 'By',
    'detail'   => 'Action',
], $adminFeed, 'Admin actions', 'bi-journal-text');
echo '</div></div>';
rpt_foot();
