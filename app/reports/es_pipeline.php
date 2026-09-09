<?php
/** Report — e-Services submissions pipeline. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('es_bid_registry')) { rpt_head('Submissions pipeline', 'es_pipe'); echo '<div class="f-state"><p>e-Services schema not installed.</p></div>'; rpt_foot(); exit; }

$sc = fn(string $st) => (int) db_scalar("SELECT COUNT(*) FROM es_bid_registry WHERE status = '$st'");
$received  = (int) db_scalar("SELECT COUNT(*) FROM es_bid_registry WHERE " . $bw('ts_create'));
$completed = (int) db_scalar("SELECT COUNT(*) FROM es_bid_registry WHERE status IN ('completed','closed') AND " . $bw('ts_update'));

$byStatus = db_all("SELECT status, COUNT(*) n FROM es_bid_registry GROUP BY status ORDER BY n DESC");

$byPde = db_all(
    "SELECT COALESCE(p.name,'(unassigned)') pde, COUNT(*) n,
            SUM(r.status IN ('completed','closed')) completed,
            SUM(r.status = 'returned_to_pde') returned,
            SUM(r.origin = 'pde') via_portal
       FROM es_bid_registry r LEFT JOIN es_pde p ON p.id = r.pde_id
      WHERE " . $bw('r.ts_create') . "
      GROUP BY pde ORDER BY n DESC LIMIT 20"
);
$cols = [
    'pde'        => 'PDE',
    'n'          => ['label' => 'Submissions', 'align' => 'end'],
    'completed'  => ['label' => 'Completed', 'align' => 'end'],
    'returned'   => ['label' => 'Returned', 'align' => 'end'],
    'via_portal' => ['label' => 'Via PDE portal', 'align' => 'end'],
];
rpt_maybe_csv('es-pipeline-by-pde', $cols, $byPde);

$byMethod = db_all("SELECT COALESCE(m.name,'(none)') method, COUNT(*) n FROM es_bid_registry r LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id WHERE " . $bw('r.ts_create') . " GROUP BY method ORDER BY n DESC");
$byOrigin = db_all("SELECT origin, COUNT(*) n FROM es_bid_registry WHERE " . $bw('ts_create') . " GROUP BY origin");
$byImp    = db_all("SELECT importance, COUNT(*) n FROM es_bid_registry WHERE " . $bw('ts_create') . " GROUP BY importance ORDER BY FIELD(importance,'urgent','high','normal')");

$fill = function (array $rows) use ($r) {
    $by = []; foreach ($rows as $x) $by[$x['d']] = (int) $x['n'];
    $out = []; $cur = date('Y-m-01', strtotime($r['from'])); $end = date('Y-m-01', strtotime($r['to']));
    for ($i = 0; $i < 36 && $cur <= $end; $i++) { $out[] = ['d' => date('M y', strtotime($cur)), 'n' => $by[date('Y-m', strtotime($cur))] ?? 0]; $cur = date('Y-m-01', strtotime("$cur +1 month")); }
    return $out;
};
$series = $fill(db_all("SELECT DATE_FORMAT(ts_create,'%Y-%m') d, COUNT(*) n FROM es_bid_registry WHERE " . $bw('ts_create') . " GROUP BY d"));

rpt_head('Submissions pipeline', 'es_pipe', 'PDE submissions and where they sit in the review pipeline — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [$received, 'Received in period', 's-green', 'bi-journal-plus'],
    [$sc('pending_registry'), 'Awaiting registry check', 's-amber', 'bi-clipboard-check'],
    [$sc('pending_allocation'), 'Awaiting allocation', 's-sky', 'bi-diagram-3'],
    [$sc('in_analysis') + $sc('assigned'), 'In analysis now', 's-violet', 'bi-clipboard-data'],
    [$completed, 'Completed / closed in period', 's-rose', 'bi-check2-circle'],
]);
rpt_timebars($series, 'Submissions received per month', 'bi-journal-plus');
echo '<div class="row g-3 mt-1"><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [$x['status'], $x['n']], $byStatus), 'By current status', 'bi-funnel');
echo '</div><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [ucfirst((string) $x['origin']), $x['n']], $byOrigin), 'By source', 'bi-box-arrow-in-down');
echo '</div><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [ucfirst((string) $x['importance']), $x['n']], $byImp), 'By priority', 'bi-flag');
echo '</div><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [$x['method'], $x['n']], $byMethod), 'By procurement method', 'bi-list-ul');
echo '</div></div><div class="mt-3">';
rpt_table($cols, $byPde, 'By PDE (top 20)', 'bi-buildings');
echo '</div>';
rpt_foot();
