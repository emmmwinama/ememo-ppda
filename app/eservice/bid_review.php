<?php
/** Review worklist for the supervisor / director / DG stages.
 *  bid_review.php?role=supervisor|director|dg  — actions are on the analysis view. */
require __DIR__ . '/inc/layout.php';

$role = in_array($_GET['role'] ?? '', ['supervisor', 'director', 'dg'], true) ? $_GET['role'] : '';
if ($role === '') { redirect('bid_analysis.php'); }
es_require_role($role);

global $conn, $ES_UID;

const ES_SLA_DAYS = 14;

$CFG = [
    'supervisor' => ['stage' => 'supervisor_review', 'title' => 'Supervisory review',
                     'sub' => "Analyses submitted by technical officers for your endorsement", 'nav' => 'rev_supervisor'],
    'director'   => ['stage' => 'director_review',   'title' => 'Director review',
                     'sub' => "Analyses endorsed by supervisors for directorate review",       'nav' => 'rev_director'],
    'dg'         => ['stage' => 'dg_review',         'title' => 'DG review',
                     'sub' => "Analyses for the Director General's decision",                  'nav' => 'rev_dg'],
][$role];
$myStage = $CFG['stage'];

$q    = trim($_GET['q'] ?? '');
$fPde = ($_GET['pde'] ?? '') !== '' ? (int) $_GET['pde'] : 0;
$sort = in_array($_GET['sort'] ?? '', ['recent', 'old', 'pde'], true) ? $_GET['sort'] : 'recent';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 10;

$byMe = "SELECT 1 FROM es_bid_routing t WHERE t.analysis_id = a.id AND t.from_user_id = $ES_UID AND t.from_stage = '$myStage'";

$live = "a.archived = 0";
$TABS = [
    'inbox'    => ['Inbox',           "a.stage = '$myStage' AND $live"],
    'sent'     => ['Forwarded by me', "a.stage <> '$myStage' AND $live AND EXISTS ($byMe AND t.action IN ('endorse','approve','submit'))"],
    'returned' => ['Returned by me',  "$live AND EXISTS ($byMe AND t.action = 'return')"],
];
if ($role === 'dg') {
    $TABS['board'] = ['At the Board', "a.stage = 'board_review' AND $live"];
}
$TABS['done']     = ['Approved / rejected', "a.stage IN ('approved','rejected')"];
$TABS['archived'] = ['Archived',            "a.stage = '$myStage' AND a.archived = 1"];
// a cross-stage overview lives on its own page now — Submission tracking

$tab = isset($_GET['tab'], $TABS[$_GET['tab']]) ? $_GET['tab'] : 'inbox';
$carry = ['role' => $role, 'tab' => $tab, 'q' => $q, 'pde' => $fPde ?: '', 'sort' => $sort];
$hasFilter = $q !== '' || $fPde;

// counts
$cnt = [];
foreach ($TABS as $k => [$lbl, $w]) {
    $cnt[$k] = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id WHERE $w")['c'] ?? 0);
}
$cOverdue = (int) (db_one(
    "SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id
      WHERE a.stage = '$myStage' AND a.archived = 0 AND COALESCE(a.submitted_at, r.ts_create) < (NOW() - INTERVAL " . ES_SLA_DAYS . " DAY)"
)['c'] ?? 0);

// list
$where = [$TABS[$tab][1]];
$types = ''; $args = [];
if ($q !== '') { $where[] = '(r.serial_no LIKE ? OR r.subject LIKE ? OR p.name LIKE ?)'; $l = "%$q%"; $types .= 'sss'; array_push($args, $l, $l, $l); }
if ($fPde)    { $where[] = 'r.pde_id = ?'; $types .= 'i'; $args[] = $fPde; }
$whereSql = 'WHERE ' . implode(' AND ', $where);
$orderSql = match ($sort) {
    'old' => 'COALESCE(a.submitted_at, r.ts_create) ASC',
    'pde' => 'p.name ASC, a.ts_update DESC',
    default => 'a.ts_update DESC',
};

$total = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id LEFT JOIN es_pde p ON p.id = r.pde_id $whereSql", $types, $args)['c'] ?? 0);
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$respCols = $role === 'dg'
    ? ", pr.id AS response_id, pr.body AS response_body, pr.published AS response_published"
    : "";
$respJoin = $role === 'dg'
    ? "LEFT JOIN es_pde_response pr ON pr.id = (SELECT MAX(x.id) FROM es_pde_response x WHERE x.analysis_id = a.id)"
    : "";

$rows = db_all(
    "SELECT a.id AS analysis_id, a.stage, a.final_outcome, a.ts_update,
            r.id AS registry_id, r.serial_no, r.subject, r.ts_create AS submitted_ts, r.allocated_at,
            p.name AS pde_name, m.name AS method_name,
            o.full_name AS officer_name, c.full_name AS owner_name,
            (SELECT COUNT(*) FROM es_bid_message    x WHERE x.analysis_id = a.id) AS notes,
            (SELECT COUNT(*) FROM es_bid_attachment x WHERE x.analysis_id = a.id OR x.registry_id = r.id) AS atts,
            (SELECT MAX(x.ts_create) FROM es_bid_routing x WHERE x.analysis_id = a.id) AS last_ts
            $respCols
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN users o ON o.id = a.officer_id
       LEFT JOIN users c ON c.id = a.current_owner_id
       $respJoin
       $whereSql
      ORDER BY $orderSql
      LIMIT $per OFFSET $offset",
    $types, $args
);

$pdeList = db_all("SELECT id, name FROM es_pde WHERE active = 1 ORDER BY name");
$turnMap = es_turnaround_map(array_column($rows, 'analysis_id'));

es_layout_head($CFG['title'], $CFG['nav']);
?>

<div class="f-head">
  <h1 class="f-title"><?= e($CFG['title']) ?></h1>
  <p class="f-subtitle"><?= e($CFG['sub']) ?>. <?= es_turnaround_legend() ?></p>
</div>

<div class="f-stats">
  <div class="f-stat s-sky"><i class="bi bi-inbox"></i>
    <div class="f-stat-value"><?= number_format($cnt['inbox']) ?></div><div class="f-stat-label">In my inbox</div></div>
  <div class="f-stat <?= $cOverdue > 0 ? 's-rose' : 's-amber' ?>"><i class="bi bi-alarm"></i>
    <div class="f-stat-value"><?= number_format($cOverdue) ?></div><div class="f-stat-label">Overdue (&gt; <?= ES_SLA_DAYS ?>d)</div></div>
  <div class="f-stat s-green"><i class="bi bi-send-check"></i>
    <div class="f-stat-value"><?= number_format($cnt['sent']) ?></div><div class="f-stat-label">Forwarded by me</div></div>
</div>

<div class="f-chips">
  <?php foreach ($TABS as $k => [$lbl]): ?>
    <a href="?<?= e(http_build_query(['tab' => $k] + $carry)) ?>" class="chip <?= $tab === $k ? 'active' : '' ?>">
      <?= e($lbl) ?><span class="chip-count"><?= (int) $cnt[$k] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="role" value="<?= e($role) ?>">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search serial, subject or PDE…" autocomplete="off">
  </div>
  <select name="pde" class="form-select f-control">
    <option value="">All PDEs</option>
    <?php foreach ($pdeList as $p): ?>
      <option value="<?= (int) $p['id'] ?>" <?= $fPde === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" class="form-select f-control">
    <?php foreach (['recent' => 'Recently updated', 'old' => 'Oldest first', 'pde' => 'PDE A–Z'] as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Apply</button>
  <?php if ($hasFilter): ?><a href="?<?= e(http_build_query(['role' => $role, 'tab' => $tab])) ?>" class="btn btn-link btn-sm text-decoration-none" style="height:40px;">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-clipboard-check"></i><p>Nothing here<?= $hasFilter ? ' matches these filters' : '' ?>.</p></div>
<?php else: ?>
  <?php $showLetter = $role === 'dg'; ?>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($rows as $r):
        $openLbl = $tab === 'inbox' ? 'Review' : 'Open analysis';
        $extra = '';
        if ($showLetter) {
            $pub = !empty($r['response_id'])
                ? ($r['response_published'] ? ' <span class="pill t-green">published</span>' : ' <span class="pill t-neutral">draft</span>')
                : ' <span class="pill t-amber">none</span>';
            $extra = '<button type="button" class="btn btn-sm btn-outline-secondary"'
                   . ' data-drawer="bid_response_peek.php?analysis_id=' . (int) $r['analysis_id'] . '&amp;dg_tab=' . e($tab) . '&amp;partial=1"'
                   . ' data-drawer-title="Response letter &middot; ' . e($r['serial_no']) . '">'
                   . '<i class="bi bi-envelope-paper me-1"></i>View response' . $pub . '</button>';
        }
    ?>
      <?= es_analysis_card($r, ['open_label' => $openLbl, 'sla_days' => ES_SLA_DAYS, 'extra_actions' => $extra,
                                'turn' => $turnMap[(int) $r['analysis_id']] ?? []]) ?>
    <?php endforeach; ?>
  </div>
  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query($carry + ['page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
