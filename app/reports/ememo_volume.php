<?php
/** Report — e-Memo volume. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('memos')) { rpt_head('Memo volume', 'em_volume'); echo '<div class="f-state"><p>e-Memo tables not present.</p></div>'; rpt_foot(); exit; }

$byDept = db_all(
    "SELECT COALESCE(d.name,'(no department)') dept, COUNT(*) n,
            SUM(m.communication_type='Memorandum') memoranda,
            SUM(m.communication_type='Loose Minute') minutes,
            SUM(m.status='Finalized') finalized
       FROM memos m
       LEFT JOIN sections s ON s.id = m.section_id
       LEFT JOIN departments d ON d.id = s.department_id
      WHERE " . $bw('m.created_at') . "
      GROUP BY dept ORDER BY n DESC"
);

$cols = [
    'dept'      => 'Department',
    'n'         => ['label' => 'Memos', 'align' => 'end'],
    'memoranda' => ['label' => 'Memoranda', 'align' => 'end'],
    'minutes'   => ['label' => 'Loose minutes', 'align' => 'end'],
    'finalized' => ['label' => 'Finalized', 'align' => 'end'],
];
rpt_maybe_csv('ememo-volume-by-department', $cols, $byDept);

$total   = array_sum(array_column($byDept, 'n'));
$memoTot = (int) db_scalar("SELECT COUNT(*) FROM memos WHERE communication_type='Memorandum' AND " . $bw('created_at'));
$lmTot   = (int) db_scalar("SELECT COUNT(*) FROM memos WHERE communication_type='Loose Minute' AND " . $bw('created_at'));
$direct  = rpt_has_table('direct_memos') ? (int) db_scalar("SELECT COUNT(*) FROM direct_memos WHERE " . $bw('created_at')) : 0;
$outLett = rpt_has_table('memo_outgoing_letters') ? (int) db_scalar("SELECT COUNT(*) FROM memo_outgoing_letters WHERE " . $bw('letter_date')) : 0;

$byStatus = db_all("SELECT status, COUNT(*) n FROM memos WHERE " . $bw('created_at') . " GROUP BY status ORDER BY n DESC");
$byType   = [['Memorandum', $memoTot], ['Loose Minute', $lmTot]];

$fill = function (array $rows) use ($r) {
    $by = []; foreach ($rows as $x) $by[$x['d']] = (int) $x['n'];
    $out = []; $cur = date('Y-m-01', strtotime($r['from'])); $end = date('Y-m-01', strtotime($r['to']));
    for ($i = 0; $i < 36 && $cur <= $end; $i++) { $out[] = ['d' => date('M y', strtotime($cur)), 'n' => $by[date('Y-m', strtotime($cur))] ?? 0]; $cur = date('Y-m-01', strtotime("$cur +1 month")); }
    return $out;
};
$series = $fill(db_all("SELECT DATE_FORMAT(created_at,'%Y-%m') d, COUNT(*) n FROM memos WHERE " . $bw('created_at') . " GROUP BY d"));

rpt_head('Memo volume', 'em_volume', 'How many memos were raised, of what kind, and by whom — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [$total,   'Memos created', 's-green', 'bi-file-earmark-plus'],
    [$memoTot, 'Memoranda',     's-sky',   'bi-file-earmark-text'],
    [$lmTot,   'Loose minutes', 's-violet','bi-file-earmark-ruled'],
    [$direct,  'Direct memos / circulars', 's-amber', 'bi-broadcast'],
    [$outLett, 'Outgoing letters', 's-rose', 'bi-envelope-arrow-up'],
]);
?>
<?php rpt_timebars($series, 'Memos created per month'); ?>
<div class="row g-3 mt-1">
  <div class="col-lg-6"><?php rpt_bars(array_map(fn($x) => [$x['status'] ?: '—', $x['n']], $byStatus), 'By current status', 'bi-list-check'); ?></div>
  <div class="col-lg-6"><?php rpt_bars($byType, 'By communication type', 'bi-collection'); ?></div>
</div>
<div class="mt-3"><?php rpt_table($cols, $byDept, 'By originating department', 'bi-building'); ?></div>
<?php rpt_foot(); ?>
