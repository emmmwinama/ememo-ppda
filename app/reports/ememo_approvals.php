<?php
/** Report — e-Memo approvals & decisions. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('memo_approvals')) { rpt_head('Approvals', 'em_appr'); echo '<div class="f-state"><p>e-Memo approval tables not present.</p></div>'; rpt_foot(); exit; }

$byApprover = db_all(
    "SELECT COALESCE(u.full_name, u.username, CONCAT('user #', a.approver_id)) approver,
            COUNT(*) decisions,
            SUM(a.decision='Approved') approved,
            SUM(a.decision='Rejected') rejected,
            ROUND(AVG(TIMESTAMPDIFF(HOUR, m.created_at, a.decision_date))/24, 1) avg_days
       FROM memo_approvals a
       LEFT JOIN users u ON u.id = a.approver_id
       LEFT JOIN memos m ON m.id = a.memo_id
      WHERE a.decision IN ('Approved','Rejected') AND a.decision_date IS NOT NULL AND " . $bw('a.decision_date') . "
      GROUP BY approver ORDER BY decisions DESC"
);
foreach ($byApprover as &$row) {
    $d = max(1, (int) $row['decisions']);
    $row['rate'] = round((int) $row['approved'] / $d * 100) . '%';
}
unset($row);

$cols = [
    'approver'  => 'Approver',
    'decisions' => ['label' => 'Decisions', 'align' => 'end'],
    'approved'  => ['label' => 'Approved', 'align' => 'end'],
    'rejected'  => ['label' => 'Rejected', 'align' => 'end'],
    'rate'      => ['label' => 'Approval rate', 'align' => 'end'],
    'avg_days'  => ['label' => 'Avg days to decide', 'align' => 'end'],
];
rpt_maybe_csv('ememo-approvals-by-approver', $cols, $byApprover);

$tot = db_one("SELECT COUNT(*) n, SUM(decision='Approved') ap, SUM(decision='Rejected') rj
                 FROM memo_approvals
                WHERE decision IN ('Approved','Rejected') AND decision_date IS NOT NULL AND " . $bw('decision_date'))
     ?: ['n' => 0, 'ap' => 0, 'rj' => 0];
$pending = (int) db_scalar("SELECT COUNT(*) FROM memo_approvals WHERE decision = 'Pending'");
$n = max(1, (int) $tot['n']);

$trail = rpt_has_table('memo_trail') ? db_all(
    "SELECT action, COUNT(*) n FROM memo_trail
      WHERE action IN ('Returned','Rejected','Escalated','Endorsed','Approved','Finalized') AND " . $bw('action_time') . "
      GROUP BY action ORDER BY n DESC"
) : [];

rpt_head('Memo approvals', 'em_appr', 'Approval decisions, rejection rates and how quickly they are made — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [(int) $tot['n'],  'Decisions made', 's-green', 'bi-check2-square'],
    [(int) $tot['ap'], 'Approved',       's-sky',   'bi-hand-thumbs-up'],
    [(int) $tot['rj'], 'Rejected',       's-rose',  'bi-hand-thumbs-down'],
    [round((int) $tot['ap'] / $n * 100) . '%', 'Approval rate', 's-violet', 'bi-percent'],
    [$pending, 'Pending decisions now', 's-amber', 'bi-hourglass-split'],
]);
if ($trail) rpt_bars(array_map(fn($x) => [$x['action'], $x['n']], $trail), 'Workflow actions in the period', 'bi-arrow-left-right');
echo '<div class="mt-3">';
rpt_table($cols, $byApprover, 'By approver', 'bi-person-badge');
echo '</div>';
rpt_foot();
