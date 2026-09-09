<?php
/** Report — e-Services analysis outcomes. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('es_bid_analysis')) { rpt_head('Analysis outcomes', 'es_out'); echo '<div class="f-state"><p>e-Services schema not installed.</p></div>'; rpt_foot(); exit; }

$started  = (int) db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE " . $bw('ts_create'));
$decided  = (int) db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE decided_at IS NOT NULL AND " . $bw('decided_at'));
$approved = (int) db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE stage='approved' AND decided_at IS NOT NULL AND " . $bw('decided_at'));
$rejected = (int) db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE stage='rejected' AND decided_at IS NOT NULL AND " . $bw('decided_at'));
$archived = (int) db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE archived=1 AND archived_at IS NOT NULL AND " . $bw('archived_at'));
$noObj    = (int) db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE final_outcome IN ('no_objection','compliant') AND decided_at IS NOT NULL AND " . $bw('decided_at'));
$d = max(1, $decided);

$byStage   = db_all("SELECT stage, COUNT(*) n FROM es_bid_analysis WHERE archived=0 GROUP BY stage
                     ORDER BY FIELD(stage,'draft','supervisor_review','director_review','dg_review','board_review','approved','returned','rejected')");
$byOutcome = db_all("SELECT final_outcome, COUNT(*) n FROM es_bid_analysis WHERE decided_at IS NOT NULL AND " . $bw('decided_at') . " GROUP BY final_outcome ORDER BY n DESC");

$byOfficer = db_all(
    "SELECT COALESCE(u.full_name, u.username, CONCAT('user #', a.officer_id)) officer,
            COUNT(*) handled,
            SUM(a.decided_at IS NOT NULL) decided,
            SUM(a.stage='approved') approved,
            SUM(a.stage='rejected') rejected,
            ROUND(AVG(CASE WHEN a.decided_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, a.ts_create, a.decided_at)/24 END), 1) avg_days
       FROM es_bid_analysis a LEFT JOIN users u ON u.id = a.officer_id
      WHERE " . $bw('a.ts_create') . "
      GROUP BY officer ORDER BY handled DESC"
);
$cols = [
    'officer'  => 'Officer',
    'handled'  => ['label' => 'Analyses', 'align' => 'end'],
    'decided'  => ['label' => 'Decided', 'align' => 'end'],
    'approved' => ['label' => 'Approved', 'align' => 'end'],
    'rejected' => ['label' => 'Rejected', 'align' => 'end'],
    'avg_days' => ['label' => 'Avg days start → decision', 'align' => 'end'],
];
rpt_maybe_csv('es-analysis-outcomes-by-officer', $cols, $byOfficer);

$fill = function (array $rows) use ($r) {
    $by = []; foreach ($rows as $x) $by[$x['d']] = (int) $x['n'];
    $out = []; $cur = date('Y-m-01', strtotime($r['from'])); $end = date('Y-m-01', strtotime($r['to']));
    for ($i = 0; $i < 36 && $cur <= $end; $i++) { $out[] = ['d' => date('M y', strtotime($cur)), 'n' => $by[date('Y-m', strtotime($cur))] ?? 0]; $cur = date('Y-m-01', strtotime("$cur +1 month")); }
    return $out;
};
$series = $fill(db_all("SELECT DATE_FORMAT(decided_at,'%Y-%m') d, COUNT(*) n FROM es_bid_analysis WHERE decided_at IS NOT NULL AND " . $bw('decided_at') . " GROUP BY d"));

rpt_head('Analysis outcomes', 'es_out', 'Bid-analysis decisions, stages and officer output — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [$started,  'Analyses started', 's-green', 'bi-clipboard-plus'],
    [$decided,  'Decided',          's-sky',   'bi-clipboard-check'],
    [round($noObj / $d * 100) . '%', 'No-objection / compliant rate', 's-violet', 'bi-patch-check'],
    [$rejected, 'Rejected',         's-rose',  'bi-x-octagon'],
    [$archived, 'Archived without decision', 's-amber', 'bi-archive'],
]);
rpt_timebars($series, 'Analyses decided per month', 'bi-clipboard-check');
echo '<div class="row g-3 mt-1"><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [str_replace('_', ' ', (string) $x['stage']), $x['n']], $byStage), 'Open analyses by stage', 'bi-list-check');
echo '</div><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [str_replace('_', ' ', (string) $x['final_outcome']), $x['n']], $byOutcome), 'Decisions by outcome', 'bi-clipboard-data');
echo '</div></div><div class="mt-3">';
rpt_table($cols, $byOfficer, 'By officer', 'bi-person-badge');
echo '</div>';
rpt_foot();
