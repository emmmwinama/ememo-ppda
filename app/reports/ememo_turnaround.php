<?php
/** Report — e-Memo turnaround (draft → finalized). */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('memos')) { rpt_head('Turnaround', 'em_turn'); echo '<div class="f-state"><p>e-Memo tables not present.</p></div>'; rpt_foot(); exit; }

$base = "FROM memos m
         LEFT JOIN sections s ON s.id = m.section_id
         LEFT JOIN departments d ON d.id = s.department_id
        WHERE m.finalized_at IS NOT NULL AND " . $bw('m.finalized_at');

$byDept = db_all(
    "SELECT COALESCE(d.name,'(no department)') dept, COUNT(*) n,
            ROUND(AVG(TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at))/24, 1) avg_days,
            ROUND(MAX(TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at))/24, 1) max_days,
            SUM(TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 168) le7,
            SUM(TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 336) le14
     $base GROUP BY dept ORDER BY avg_days DESC"
);
$cols = [
    'dept'     => 'Department',
    'n'        => ['label' => 'Finalized', 'align' => 'end'],
    'avg_days' => ['label' => 'Avg days', 'align' => 'end'],
    'max_days' => ['label' => 'Slowest (days)', 'align' => 'end'],
    'le7'      => ['label' => '≤ 7 days', 'align' => 'end'],
    'le14'     => ['label' => '≤ 14 days', 'align' => 'end'],
];
rpt_maybe_csv('ememo-turnaround-by-department', $cols, $byDept);

$agg = db_one(
    "SELECT COUNT(*) n,
            ROUND(AVG(TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at))/24, 1) avg_days,
            SUM(TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 168) le7,
            SUM(TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 336) le14
     $base"
) ?: ['n' => 0, 'avg_days' => 0, 'le7' => 0, 'le14' => 0];
$n = max(1, (int) $agg['n']);

$buckets = db_all(
    "SELECT CASE
              WHEN TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 72  THEN '0–3 days'
              WHEN TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 168 THEN '4–7 days'
              WHEN TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 336 THEN '8–14 days'
              WHEN TIMESTAMPDIFF(HOUR, m.created_at, m.finalized_at) <= 720 THEN '15–30 days'
              ELSE '30+ days' END bucket,
            COUNT(*) n
     $base GROUP BY bucket
     ORDER BY FIELD(bucket,'0–3 days','4–7 days','8–14 days','15–30 days','30+ days')"
);

rpt_head('Memo turnaround', 'em_turn', 'Time from first draft to finalization — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [(int) $agg['n'], 'Memos finalized', 's-green', 'bi-file-earmark-check'],
    [($agg['avg_days'] ?? 0) . ' d', 'Average turnaround', 's-sky', 'bi-stopwatch'],
    [round((int) $agg['le7'] / $n * 100) . '%', 'Finalized within 7 days', 's-violet', 'bi-lightning-charge'],
    [round((int) $agg['le14'] / $n * 100) . '%', 'Finalized within 14 days', 's-amber', 'bi-calendar-check'],
]);
rpt_bars(array_map(fn($b) => [$b['bucket'], $b['n']], $buckets), 'Turnaround distribution', 'bi-bar-chart-steps');
echo '<div class="mt-3">';
rpt_table($cols, $byDept, 'By department (slowest first)', 'bi-building');
echo '</div>';
rpt_foot();
