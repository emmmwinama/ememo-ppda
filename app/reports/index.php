<?php
/** Reports — cross-module overview. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $col) => rpt_between($col, $r);

$hasMemos   = rpt_has_table('memos');
$hasLetters = rpt_has_table('external_letters');
$hasReg     = rpt_has_table('es_bid_registry');
$hasAn      = rpt_has_table('es_bid_analysis');
$hasSup     = rpt_has_table('es_supplier');
$hasResp    = rpt_has_table('es_pde_response');

$memoNew    = $hasMemos ? db_scalar("SELECT COUNT(*) FROM memos WHERE " . $bw('created_at')) : 0;
$memoFin    = $hasMemos ? db_scalar("SELECT COUNT(*) FROM memos WHERE finalized_at IS NOT NULL AND " . $bw('finalized_at')) : 0;
$memoTurn   = $hasMemos ? db_scalar("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, finalized_at))
                                       FROM memos WHERE finalized_at IS NOT NULL AND " . $bw('finalized_at')) : 0;
$lettersOpen = $hasLetters ? db_scalar("SELECT COUNT(*) FROM external_letters WHERE status NOT IN ('closed')") : 0;

$subNew     = $hasReg ? db_scalar("SELECT COUNT(*) FROM es_bid_registry WHERE " . $bw('ts_create')) : 0;
$anDecided  = $hasAn ? db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE decided_at IS NOT NULL AND " . $bw('decided_at')) : 0;
$supActive  = $hasSup ? db_scalar("SELECT COUNT(*) FROM es_supplier WHERE status = 'active'") : 0;
$respIssued = $hasResp ? db_scalar("SELECT COUNT(*) FROM es_pde_response WHERE published = 1 AND published_at IS NOT NULL AND " . $bw('published_at')) : 0;

// monthly memo + submission intake
$fill = function (array $rows, string $key = 'd', string $val = 'n') use ($r): array {
    $by = [];
    foreach ($rows as $x) $by[$x[$key]] = (int) $x[$val];
    $out = []; $cur = date('Y-m-01', strtotime($r['from']));
    $end = date('Y-m-01', strtotime($r['to']));
    for ($i = 0; $i < 36 && $cur <= $end; $i++) {
        $out[] = ['d' => date('M y', strtotime($cur)), 'n' => $by[date('Y-m', strtotime($cur))] ?? 0];
        $cur = date('Y-m-01', strtotime("$cur +1 month"));
    }
    return $out;
};
$memoSeries = $hasMemos ? $fill(db_all("SELECT DATE_FORMAT(created_at,'%Y-%m') d, COUNT(*) n FROM memos WHERE " . $bw('created_at') . " GROUP BY d")) : [];
$subSeries  = $hasReg ? $fill(db_all("SELECT DATE_FORMAT(ts_create,'%Y-%m') d, COUNT(*) n FROM es_bid_registry WHERE " . $bw('ts_create') . " GROUP BY d")) : [];

rpt_head('Reports overview', 'overview', 'Operational and compliance reporting across e-Memo and e-Services');
rpt_toolbar($r, false);
?>

<p class="text-muted small mb-3">Reporting period: <strong><?= e($r['label']) ?></strong> (<?= (int) $r['days'] ?> days).</p>

<h2 class="f-subtitle mb-2" style="font-weight:700;color:var(--text);">e-Memo</h2>
<?php rpt_kpis([
    [$memoNew,  'Memos created',   's-green', 'bi-file-earmark-plus'],
    [$memoFin,  'Memos finalized', 's-sky',   'bi-file-earmark-check'],
    [$memoTurn > 0 ? round($memoTurn / 24, 1) . ' d' : '—', 'Avg draft → finalized', 's-violet', 'bi-stopwatch'],
    [$lettersOpen, 'External letters open now', 's-amber', 'bi-envelope-open'],
]); ?>

<h2 class="f-subtitle mb-2 mt-4" style="font-weight:700;color:var(--text);">e-Services</h2>
<?php rpt_kpis([
    [$subNew,     'Submissions received', 's-green', 'bi-journal-plus'],
    [$anDecided,  'Analyses decided',     's-sky',   'bi-clipboard-check'],
    [$respIssued, 'PDE response letters', 's-violet','bi-envelope-paper'],
    [$supActive,  'Active suppliers',     's-amber', 'bi-building-check'],
]); ?>

<div class="row g-3 mt-1">
  <div class="col-lg-6"><?php rpt_timebars($memoSeries, 'Memos created per month', 'bi-file-earmark-text'); ?></div>
  <div class="col-lg-6"><?php rpt_timebars($subSeries, 'Submissions received per month', 'bi-journal-plus'); ?></div>
</div>

<div class="f-panel mt-3"><div class="f-panel-body">
  <div class="fw-bold mb-2">Report index</div>
  <div class="row g-2" style="font-size:.9rem;">
    <?php foreach (rpt_nav_items() as $it): if ($it['key'] === 'overview' || ($it['key'] === 'x_security' && !rpt_is_admin())) continue; ?>
      <div class="col-md-4"><a href="<?= e($it['href']) ?>" class="text-decoration-none"><i class="bi <?= e($it['icon']) ?> me-2 text-muted"></i><?= e($it['label']) ?> <span class="text-muted small">· <?= e($it['group']) ?></span></a></div>
    <?php endforeach; ?>
  </div>
</div></div>

<?php rpt_foot(); ?>
