<?php
/** Report — e-Services review SLAs and backlog. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('es_bid_analysis')) { rpt_head('Review SLAs', 'es_sla'); echo '<div class="f-state"><p>e-Services schema not installed.</p></div>'; rpt_foot(); exit; }

// backlog (current)
$allocBacklog = (int) db_scalar("SELECT COUNT(*) FROM es_bid_registry WHERE status = 'pending_allocation' AND registry_checked_at < (NOW() - INTERVAL 7 DAY)");
$openOld      = (int) db_scalar("SELECT COUNT(*) FROM es_bid_analysis WHERE archived = 0 AND stage NOT IN ('approved','rejected') AND ts_create < (NOW() - INTERVAL 21 DAY)");

// average legs (period, decided rows)
$avgRegToAlloc = db_scalar("SELECT AVG(TIMESTAMPDIFF(HOUR, ts_create, registry_checked_at))/24
                              FROM es_bid_registry
                             WHERE registry_checked_at IS NOT NULL AND " . $bw('registry_checked_at'));
$avgAllocToStart = rpt_has_table('es_bid_allocation')
    ? db_scalar("SELECT AVG(TIMESTAMPDIFF(HOUR, r.allocated_at, a.ts_create))/24
                   FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id
                  WHERE r.allocated_at IS NOT NULL AND " . $bw('a.ts_create'))
    : 0;
$avgStartToDecision = db_scalar("SELECT AVG(TIMESTAMPDIFF(HOUR, ts_create, decided_at))/24
                                   FROM es_bid_analysis WHERE decided_at IS NOT NULL AND " . $bw('decided_at'));

// open analyses by stage: how many, how old
$byStage = db_all(
    "SELECT stage, COUNT(*) n,
            ROUND(AVG(TIMESTAMPDIFF(HOUR, ts_update, NOW()))/24, 1) avg_age_days,
            ROUND(MAX(TIMESTAMPDIFF(HOUR, ts_update, NOW()))/24, 1) oldest_days
       FROM es_bid_analysis
      WHERE archived = 0 AND stage NOT IN ('approved','rejected')
      GROUP BY stage
      ORDER BY FIELD(stage,'draft','supervisor_review','director_review','dg_review','board_review')"
);
$cols = [
    'stage'        => ['label' => 'Stage', 'fmt' => fn($v) => str_replace('_', ' ', (string) $v)],
    'n'            => ['label' => 'Open now', 'align' => 'end'],
    'avg_age_days' => ['label' => 'Avg days since last action', 'align' => 'end'],
    'oldest_days'  => ['label' => 'Oldest (days)', 'align' => 'end'],
];
rpt_maybe_csv('es-review-sla-by-stage', $cols, $byStage);

// per-stage turnaround from the routing trail (time entering a stage -> next decisive action)
$legRows = rpt_has_table('es_bid_routing') ? db_all(
    "SELECT to_stage stage,
            COUNT(*) moves,
            ROUND(AVG(TIMESTAMPDIFF(HOUR, ts_create,
                  (SELECT MIN(t2.ts_create) FROM es_bid_routing t2
                    WHERE t2.analysis_id = t.analysis_id AND t2.id > t.id
                      AND t2.action IN ('submit','endorse','return','approve','reject'))))/24, 1) avg_days_in_stage
       FROM es_bid_routing t
      WHERE t.action IN ('submit','endorse','return') AND to_stage IS NOT NULL AND " . $bw('t.ts_create') . "
      GROUP BY to_stage
      ORDER BY FIELD(to_stage,'supervisor_review','director_review','dg_review','board_review')"
) : [];

// response letters
$respPub  = rpt_has_table('es_pde_response') ? (int) db_scalar("SELECT COUNT(*) FROM es_pde_response WHERE published = 1 AND published_at IS NOT NULL AND " . $bw('published_at')) : 0;
$respSent = rpt_has_table('es_pde_response') ? (int) db_scalar("SELECT COUNT(*) FROM es_pde_response WHERE sent_at IS NOT NULL AND " . $bw('sent_at')) : 0;
$respWait = rpt_has_table('es_pde_response') ? (int) db_scalar("SELECT COUNT(*) FROM es_pde_response WHERE published = 1 AND sent_at IS NULL") : 0;

rpt_head('Review SLAs', 'es_sla', 'How long each leg of the review takes, and what is overdue — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [$avgRegToAlloc > 0 ? round($avgRegToAlloc, 1) . ' d' : '—', 'Avg receipt → registry check', 's-sky', 'bi-clipboard-check'],
    [$avgStartToDecision > 0 ? round($avgStartToDecision, 1) . ' d' : '—', 'Avg analysis start → decision', 's-violet', 'bi-stopwatch'],
    [$allocBacklog, 'Awaiting allocation > 7 days', 's-amber', 'bi-hourglass-split'],
    [$openOld, 'Open analyses > 21 days', 's-rose', 'bi-exclamation-triangle'],
    [$respWait, 'Response letters not yet sent', 's-green', 'bi-envelope-exclamation'],
]);
if ($legRows) rpt_bars(array_map(fn($x) => [str_replace('_', ' ', (string) $x['stage']) . ' (' . (int) $x['moves'] . ')', $x['avg_days_in_stage']], $legRows), 'Average days in each review stage', 'bi-hourglass');
echo '<div class="mt-3">';
rpt_table($cols, $byStage, 'Open analyses by stage (backlog age)', 'bi-list-check');
echo '</div>';
echo '<div class="f-panel mt-3"><div class="f-panel-body" style="font-size:.9rem;"><div class="fw-bold mb-2"><i class="bi bi-envelope-paper me-1"></i>PDE response letters</div>';
echo 'Published in period: <strong>' . $respPub . '</strong> &nbsp;·&nbsp; Dispatched to PDE: <strong>' . $respSent . '</strong> &nbsp;·&nbsp; Published but not dispatched: <strong>' . $respWait . '</strong>';
echo '</div></div>';
rpt_foot();
