<?php
/** Report — per-user e-Memo workload. */
require __DIR__ . '/inc/layout.php';
rpt_require();

$r  = rpt_range();
$bw = fn(string $c) => rpt_between($c, $r);

if (!rpt_has_table('memos')) { rpt_head('User workload', 'em_work'); echo '<div class="f-state"><p>e-Memo tables not present.</p></div>'; rpt_foot(); exit; }

$originated = "SELECT originator_id uid, COUNT(*) n FROM memos WHERE " . $bw('created_at') . " GROUP BY originator_id";
$endorsed   = rpt_has_table('memo_endorsements')
    ? "SELECT endorser_id uid, COUNT(*) n FROM memo_endorsements WHERE endorsed_at IS NOT NULL AND " . $bw('endorsed_at') . " GROUP BY endorser_id"
    : "SELECT NULL uid, 0 n WHERE 1=0";
$decided    = rpt_has_table('memo_approvals')
    ? "SELECT approver_id uid, COUNT(*) n FROM memo_approvals WHERE decision IN ('Approved','Rejected') AND decision_date IS NOT NULL AND " . $bw('decision_date') . " GROUP BY approver_id"
    : "SELECT NULL uid, 0 n WHERE 1=0";

$rows = db_all(
    "SELECT u.id, COALESCE(u.full_name, u.username) name, d.name dept,
            COALESCE(o.n,0) originated, COALESCE(en.n,0) endorsed, COALESCE(de.n,0) decided,
            COALESCE(ap.n,0) pending_approvals,
            COALESCE(lt.n,0) letters_assigned
       FROM users u
       LEFT JOIN sections s   ON s.id = u.section_id
       LEFT JOIN departments d ON d.id = u.department_id
       LEFT JOIN ($originated) o  ON o.uid  = u.id
       LEFT JOIN ($endorsed) en   ON en.uid = u.id
       LEFT JOIN ($decided) de     ON de.uid = u.id
       LEFT JOIN (SELECT approver_id uid, COUNT(*) n FROM memo_approvals WHERE decision = 'Pending' GROUP BY approver_id) ap ON ap.uid = u.id
       LEFT JOIN (SELECT current_assignee uid, COUNT(*) n FROM external_letters WHERE status <> 'closed' GROUP BY current_assignee) lt ON lt.uid = u.id
      WHERE u.active = 1
      HAVING (originated + endorsed + decided + pending_approvals + letters_assigned) > 0
      ORDER BY (originated + endorsed + decided) DESC, name"
);

$cols = [
    'name'              => 'User',
    'dept'              => 'Department',
    'originated'        => ['label' => 'Memos originated', 'align' => 'end'],
    'endorsed'          => ['label' => 'Endorsements', 'align' => 'end'],
    'decided'           => ['label' => 'Approvals/rejections', 'align' => 'end'],
    'pending_approvals' => ['label' => 'Pending on them', 'align' => 'end'],
    'letters_assigned'  => ['label' => 'Letters assigned', 'align' => 'end'],
];
rpt_maybe_csv('ememo-user-workload', $cols, $rows);

rpt_head('User workload', 'em_work', 'What each active user has produced and what is waiting on them — ' . $r['label']);
rpt_toolbar($r);
rpt_kpis([
    [count($rows), 'Active contributors', 's-green', 'bi-people'],
    [array_sum(array_column($rows, 'originated')), 'Memos originated', 's-sky', 'bi-file-earmark-plus'],
    [array_sum(array_column($rows, 'decided')), 'Decisions taken', 's-violet', 'bi-check2-square'],
    [array_sum(array_column($rows, 'pending_approvals')), 'Approvals pending', 's-amber', 'bi-hourglass'],
]);
rpt_table($cols, $rows, 'Per user (most active first)', 'bi-person-lines-fill');
rpt_foot();
