<?php
/** Report — external (incoming) letters. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('external_letters')) { rpt_head('External letters', 'em_letters'); echo '<div class="f-state"><p>External-letter tables not present.</p></div>'; rpt_foot(); exit; }

$received = (int) db_scalar("SELECT COUNT(*) FROM external_letters WHERE " . $bw('received_date'));
$closedIn = rpt_has_table('external_letter_actions')
    ? (int) db_scalar("SELECT COUNT(DISTINCT l.id) FROM external_letters l
                        JOIN external_letter_actions a ON a.letter_id = l.id
                       WHERE l.status = 'closed' AND " . $bw('a.submitted_at'))
    : (int) db_scalar("SELECT COUNT(*) FROM external_letters WHERE status='closed'");
$openNow  = (int) db_scalar("SELECT COUNT(*) FROM external_letters WHERE status <> 'closed'");
$overdue  = (int) db_scalar("SELECT COUNT(*) FROM external_letters WHERE status IN ('assigned','in_progress','delegation_pending') AND received_date < (CURDATE() - INTERVAL 30 DAY)");
$avgClose = rpt_has_table('external_letter_actions')
    ? db_scalar("SELECT AVG(TIMESTAMPDIFF(HOUR, l.received_date, x.done))/24
                   FROM external_letters l
                   JOIN (SELECT letter_id, MAX(submitted_at) done FROM external_letter_actions GROUP BY letter_id) x ON x.letter_id = l.id
                  WHERE l.status = 'closed' AND " . rpt_between('x.done', $r))
    : 0;

$byStatus = db_all("SELECT status, COUNT(*) n FROM external_letters GROUP BY status ORDER BY n DESC");

$byFrom = db_all(
    "SELECT COALESCE(NULLIF(TRIM(received_from),''),'(unspecified)') source, COUNT(*) n,
            SUM(status='closed') closed,
            SUM(status<>'closed') open_now
       FROM external_letters WHERE " . $bw('received_date') . "
      GROUP BY source ORDER BY n DESC LIMIT 20"
);
$cols = [
    'source'   => 'Received from',
    'n'        => ['label' => 'Letters', 'align' => 'end'],
    'closed'   => ['label' => 'Closed', 'align' => 'end'],
    'open_now' => ['label' => 'Still open', 'align' => 'end'],
];
rpt_maybe_csv('ememo-external-letters-by-source', $cols, $byFrom);

$fill = function (array $rows) use ($r) {
    $by = []; foreach ($rows as $x) $by[$x['d']] = (int) $x['n'];
    $out = []; $cur = date('Y-m-01', strtotime($r['from'])); $end = date('Y-m-01', strtotime($r['to']));
    for ($i = 0; $i < 36 && $cur <= $end; $i++) { $out[] = ['d' => date('M y', strtotime($cur)), 'n' => $by[date('Y-m', strtotime($cur))] ?? 0]; $cur = date('Y-m-01', strtotime("$cur +1 month")); }
    return $out;
};
$recSeries = $fill(db_all("SELECT DATE_FORMAT(received_date,'%Y-%m') d, COUNT(*) n FROM external_letters WHERE " . $bw('received_date') . " GROUP BY d"));

rpt_head('External letters', 'em_letters', 'Incoming correspondence: intake, closure and backlog — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [$received, 'Received in period', 's-green', 'bi-envelope-plus'],
    [$closedIn, 'Closed in period',   's-sky',   'bi-envelope-check'],
    [$openNow,  'Open now',           's-amber', 'bi-envelope-open'],
    [$overdue,  'Open > 30 days',     's-rose',  'bi-exclamation-triangle'],
    [$avgClose > 0 ? round($avgClose, 1) . ' d' : '—', 'Avg received → closed', 's-violet', 'bi-stopwatch'],
]);
rpt_timebars($recSeries, 'Letters received per month', 'bi-envelope');
echo '<div class="row g-3 mt-1"><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [$x['status'] ?: '—', $x['n']], $byStatus), 'By current status', 'bi-list-check');
echo '</div></div><div class="mt-3">';
rpt_table($cols, $byFrom, 'By sender (top 20)', 'bi-building');
echo '</div>';
rpt_foot();
