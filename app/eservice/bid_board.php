<?php
/** Board review worklist. The Board is a group: members vote to approve an
 *  analysis; a majority approves and the review ends, otherwise it goes back
 *  to the DG. Voting itself happens on the analysis view. */
require __DIR__ . '/inc/layout.php';
es_require_role('board');

global $conn, $ES_UID;

const ES_SLA_DAYS = 14;

$q    = trim($_GET['q'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['recent', 'old', 'pde'], true) ? $_GET['sort'] : 'old';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 10;

$TABS = [
    'open'     => ['Awaiting the Board', "a.stage = 'board_review' AND a.archived = 0"],
    'mine'     => ['Awaiting my vote',   "a.stage = 'board_review' AND a.archived = 0 AND NOT EXISTS (SELECT 1 FROM es_bid_board_vote v WHERE v.analysis_id = a.id AND v.user_id = $ES_UID)"],
    'past'     => ['Decided by the Board',"a.stage <> 'board_review' AND EXISTS (SELECT 1 FROM es_bid_routing t WHERE t.analysis_id = a.id AND t.to_stage = 'board_review')"],
    'archived' => ['Archived',           "a.stage = 'board_review' AND a.archived = 1"],
    'all'      => ['All reviews',        "a.archived = 0"],
];
$tab = isset($_GET['tab'], $TABS[$_GET['tab']]) ? $_GET['tab'] : 'open';
$carry = ['tab' => $tab, 'q' => $q, 'sort' => $sort];

$boardTotal = (int) (db_one("SELECT COUNT(*) c FROM es_user_role WHERE role = 'board'")['c'] ?? 0);
$needed = intdiv($boardTotal, 2) + 1;

$cnt = [];
foreach ($TABS as $k => [$lbl, $w]) {
    $cnt[$k] = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id WHERE $w")['c'] ?? 0);
}

$where = [$TABS[$tab][1]];
$types = ''; $args = [];
if ($q !== '') { $where[] = '(r.serial_no LIKE ? OR r.subject LIKE ? OR p.name LIKE ?)'; $l = "%$q%"; $types .= 'sss'; array_push($args, $l, $l, $l); }
$whereSql = 'WHERE ' . implode(' AND ', $where);
$orderSql = match ($sort) { 'recent' => 'a.ts_update DESC', 'pde' => 'p.name ASC', default => 'COALESCE(a.submitted_at, r.ts_create) ASC' };

$total = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id LEFT JOIN es_pde p ON p.id = r.pde_id $whereSql", $types, $args)['c'] ?? 0);
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$rows = db_all(
    "SELECT a.id AS analysis_id, a.stage, a.final_outcome, a.ts_update,
            r.id AS registry_id, r.serial_no, r.subject, r.ts_create AS submitted_ts, r.allocated_at,
            p.name AS pde_name, m.name AS method_name, o.full_name AS officer_name,
            (SELECT COUNT(*) FROM es_bid_message    x WHERE x.analysis_id = a.id) AS notes,
            (SELECT COUNT(*) FROM es_bid_attachment x WHERE x.analysis_id = a.id OR x.registry_id = r.id) AS atts,
            (SELECT MAX(x.ts_create) FROM es_bid_routing x WHERE x.analysis_id = a.id) AS last_ts,
            (SELECT COUNT(*) FROM es_bid_board_vote v WHERE v.analysis_id = a.id AND v.decision = 'approve') AS v_approve,
            (SELECT COUNT(*) FROM es_bid_board_vote v WHERE v.analysis_id = a.id AND v.decision = 'return')  AS v_return,
            (SELECT v.decision FROM es_bid_board_vote v WHERE v.analysis_id = a.id AND v.user_id = ?) AS my_vote
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN users o ON o.id = a.officer_id
       $whereSql
      ORDER BY $orderSql
      LIMIT $per OFFSET $offset",
    'i' . $types, array_merge([$ES_UID], $args)
);
$turnMap = es_turnaround_map(array_column($rows, 'analysis_id'));

es_layout_head('Board review', 'rev_board');
?>

<div class="f-head">
  <h1 class="f-title">Board review</h1>
  <p class="f-subtitle">Group review — <?= $boardTotal ?> board member<?= $boardTotal === 1 ? '' : 's' ?>; <?= $needed ?> vote<?= $needed === 1 ? '' : 's' ?> decide. Approve ends the review; otherwise it returns to the DG.
    <?= es_turnaround_legend() ?></p>
</div>

<div class="f-stats">
  <div class="f-stat s-sky"><i class="bi bi-people-fill"></i>
    <div class="f-stat-value"><?= number_format($cnt['open']) ?></div><div class="f-stat-label">Awaiting the Board</div></div>
  <div class="f-stat s-amber"><i class="bi bi-hand-index"></i>
    <div class="f-stat-value"><?= number_format($cnt['mine']) ?></div><div class="f-stat-label">Awaiting my vote</div></div>
  <div class="f-stat s-green"><i class="bi bi-check2-all"></i>
    <div class="f-stat-value"><?= number_format($cnt['past']) ?></div><div class="f-stat-label">Decided by the Board</div></div>
</div>

<div class="f-chips">
  <?php foreach ($TABS as $k => [$lbl]): ?>
    <a href="?<?= e(http_build_query(['tab' => $k] + $carry)) ?>" class="chip <?= $tab === $k ? 'active' : '' ?>">
      <?= e($lbl) ?><span class="chip-count"><?= (int) $cnt[$k] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search serial, subject or PDE…" autocomplete="off">
  </div>
  <select name="sort" class="form-select f-control">
    <?php foreach (['old' => 'Oldest first', 'recent' => 'Recently updated', 'pde' => 'PDE A–Z'] as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Apply</button>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-people"></i><p>Nothing here.</p></div>
<?php else: ?>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($rows as $r):
        $a = (int) $r['v_approve']; $rt = (int) $r['v_return'];
        $tally = '<span class="es-badge" style="background:#e6f4e6;color:#1f6b23">'
               . '<i class="bi bi-hand-thumbs-up"></i>' . $a . ' approve</span>'
               . '<span class="es-badge" style="background:#fdecee;color:#be123c">'
               . '<i class="bi bi-hand-thumbs-down"></i>' . $rt . ' return</span>';
        if ($r['stage'] === 'board_review') {
            $tally .= '<span class="es-badge es-badge-status">' . ($a + $rt) . '/' . $boardTotal . ' voted</span>';
            if ($r['my_vote']) $tally .= '<span class="pill ' . ($r['my_vote'] === 'approve' ? 't-green' : 't-rose') . '">you: ' . e($r['my_vote']) . '</span>';
        }
        $open = $r['stage'] === 'board_review'
            ? ['open_label' => $r['my_vote'] ? 'Review · change vote' : 'Review & vote']
            : ['open_label' => 'Open analysis'];
        echo es_analysis_card($r, $open + ['extra_badges' => $tally, 'sla_days' => ES_SLA_DAYS,
                                           'turn' => $turnMap[(int) $r['analysis_id']] ?? []]);
    endforeach; ?>
  </div>
  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query($carry + ['page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
