<?php
/** Report — combined user activity across e-Memo and e-Services. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

// Build a UNION of every "someone did something" event we can see.
$parts = [];
if (rpt_has_table('memo_trail'))            $parts[] = "SELECT user_id uid, action_time ts, 'ememo' app FROM memo_trail WHERE " . $bw('action_time');
if (rpt_has_table('memo_movements'))        $parts[] = "SELECT from_user_id uid, `timestamp` ts, 'ememo' app FROM memo_movements WHERE " . $bw('`timestamp`');
if (rpt_has_table('external_letter_actions')) $parts[] = "SELECT submitted_by uid, submitted_at ts, 'ememo' app FROM external_letter_actions WHERE " . $bw('submitted_at');
if (rpt_has_table('es_bid_routing'))        $parts[] = "SELECT from_user_id uid, ts_create ts, 'eservice' app FROM es_bid_routing WHERE " . $bw('ts_create');
if (rpt_has_table('es_bid_allocation'))     $parts[] = "SELECT by_user_id uid, ts_create ts, 'eservice' app FROM es_bid_allocation WHERE " . $bw('ts_create');

if (!$parts) { rpt_head('User activity', 'x_activity'); echo '<div class="f-state"><p>No activity tables present.</p></div>'; rpt_foot(); exit; }
$acts = '(' . implode(' UNION ALL ', $parts) . ') acts';

$tot   = (int) db_scalar("SELECT COUNT(*) FROM $acts");
$emN   = (int) db_scalar("SELECT COUNT(*) FROM $acts WHERE app = 'ememo'");
$esN   = (int) db_scalar("SELECT COUNT(*) FROM $acts WHERE app = 'eservice'");
$users = (int) db_scalar("SELECT COUNT(DISTINCT uid) FROM $acts WHERE uid IS NOT NULL");

$byUser = db_all(
    "SELECT COALESCE(u.full_name, u.username, CONCAT('user #', a.uid)) name,
            COUNT(*) actions,
            SUM(a.app = 'ememo') ememo,
            SUM(a.app = 'eservice') eservices,
            MAX(a.ts) last_seen
       FROM $acts a LEFT JOIN users u ON u.id = a.uid
      WHERE a.uid IS NOT NULL
      GROUP BY name ORDER BY actions DESC LIMIT 30"
);
$cols = [
    'name'      => 'User',
    'actions'   => ['label' => 'Actions', 'align' => 'end'],
    'ememo'     => ['label' => 'e-Memo', 'align' => 'end'],
    'eservices' => ['label' => 'e-Services', 'align' => 'end'],
    'last_seen' => ['label' => 'Last action', 'fmt' => fn($v) => $v ? date('d M Y H:i', strtotime($v)) : '—', 'align' => 'end'],
];
rpt_maybe_csv('cross-module-activity-by-user', $cols, $byUser);

$fill = function (array $rows) use ($r) {
    $by = []; foreach ($rows as $x) $by[$x['d']] = (int) $x['n'];
    $out = []; $cur = date('Y-m-01', strtotime($r['from'])); $end = date('Y-m-01', strtotime($r['to']));
    for ($i = 0; $i < 36 && $cur <= $end; $i++) { $out[] = ['d' => date('M y', strtotime($cur)), 'n' => $by[date('Y-m', strtotime($cur))] ?? 0]; $cur = date('Y-m-01', strtotime("$cur +1 month")); }
    return $out;
};
$series = $fill(db_all("SELECT DATE_FORMAT(ts,'%Y-%m') d, COUNT(*) n FROM $acts GROUP BY d"));

rpt_head('User activity', 'x_activity', 'Every recorded action across both modules — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [$tot,   'Total actions',      's-sky',    'bi-activity'],
    [$emN,   'e-Memo actions',     's-green',  'bi-file-earmark-text'],
    [$esN,   'e-Services actions', 's-violet', 'bi-diagram-3'],
    [$users, 'Distinct active users', 's-amber', 'bi-people'],
]);
rpt_timebars($series, 'Actions per month', 'bi-activity');
echo '<div class="mt-3">';
rpt_table($cols, $byUser, 'Most active users (top 30)', 'bi-person-lines-fill');
echo '</div>';
rpt_foot();
