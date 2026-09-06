<?php
/** Bid analysis — the technical officer's review worklist.
 *  Every tab except "All" is scoped to work assigned to the signed-in officer.
 *  "All" lists every review (read-only peek at peers' work). */
require __DIR__ . '/inc/layout.php';
es_require_role('officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

const ES_SLA_DAYS = 14;   // an active review older than this (since submission) is flagged

$q    = trim($_GET['q'] ?? '');
$fPde = ($_GET['pde'] ?? '') !== '' ? (int) $_GET['pde'] : 0;
$sort = in_array($_GET['sort'] ?? '', ['recent', 'old', 'stage', 'pde'], true) ? $_GET['sort'] : 'recent';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 10;

$mine    = "a.officer_id = " . $ES_UID;                 // assigned to me as officer
$live    = "a.archived = 0";
$active  = "a.stage NOT IN ('approved','rejected') AND $live";
$overdue = "$active AND COALESCE(a.submitted_at, r.ts_create) < (NOW() - INTERVAL " . ES_SLA_DAYS . " DAY)";

// [label, where-clause, add "assigned to me as officer" scope?]
$TABS = [
    'mine'       => ['My queue',            "a.current_owner_id = $ES_UID AND $active", false],
    'tostart'    => ['To start',            "1=0", false],   // handled separately (no analysis row yet)
    'new'        => ['Draft',               "a.stage = 'draft' AND $live", true],
    'returned'   => ['Returned to me',      "a.stage = 'returned' AND $live", true],
    'supervisor' => ['At supervisor',       "a.stage = 'supervisor_review' AND $live", true],
    'director'   => ['At director',         "a.stage = 'director_review' AND $live", true],
    'dg'         => ['At DG',               "a.stage = 'dg_review' AND $live", true],
    'board'      => ['At board',            "a.stage = 'board_review' AND $live", true],
    'overdue'    => ['Overdue',             $overdue, true],
    'archived'   => ['Archived',            "a.archived = 1", true],
    'done'       => ['Approved / rejected', "a.stage IN ('approved','rejected')", true],
    'all'        => ['All reviews',         "$live", false],
];
$tab = isset($_GET['tab'], $TABS[$_GET['tab']]) ? $_GET['tab'] : 'mine';

$carry = ['tab' => $tab, 'q' => $q, 'pde' => $fPde ?: '', 'sort' => $sort];
$hasFilter = $q !== '' || $fPde;

// ---- "allocated to me, analysis not started yet" -----------------------
$notStartedWhere = "r.status = 'assigned' AND r.assigned_officer_id = " . $ES_UID
                 . " AND NOT EXISTS (SELECT 1 FROM es_bid_analysis a WHERE a.registry_id = r.id)";
$cToStart = (int) (db_one("SELECT COUNT(*) c FROM es_bid_registry r WHERE $notStartedWhere")['c'] ?? 0);
$toStart = [];
if (in_array($tab, ['mine', 'tostart'], true)) {
    $toStart = db_all(
        "SELECT r.id, r.serial_no, r.subject, r.tender_number, r.importance, r.origin,
                r.ts_create, r.allocated_at, r.accompanied_documents, r.registry_comment AS instruction,
                p.name AS pde_name, m.name AS method_name,
                (SELECT COUNT(*) FROM es_bid_attachment x WHERE x.registry_id = r.id) AS atts
           FROM es_bid_registry r
           LEFT JOIN es_pde p ON p.id = r.pde_id
           LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
          WHERE $notStartedWhere
          ORDER BY r.allocated_at DESC, r.ts_create DESC"
    );
    // the allocation instruction, if the move-log table is present
    if ($toStart && $conn->query("SHOW TABLES LIKE 'es_bid_allocation'")->num_rows) {
        $ids = implode(',', array_map(fn($x) => (int) $x['id'], $toStart));
        $notes = [];
        foreach (db_all("SELECT registry_id, reason FROM es_bid_allocation
                          WHERE registry_id IN ($ids) AND reason IS NOT NULL AND reason <> ''
                          ORDER BY id") as $n) $notes[$n['registry_id']] = $n['reason'];
        foreach ($toStart as &$x) if (isset($notes[$x['id']])) $x['instruction'] = $notes[$x['id']];
        unset($x);
    }
}

// ---- counts -----------------------------------------------------------
$mineJoin = "FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id";
$stageCount = [];
foreach (db_all("SELECT stage, COUNT(*) c $mineJoin WHERE $mine AND a.archived = 0 GROUP BY stage") as $x) $stageCount[$x['stage']] = (int) $x['c'];
$stg = fn(string ...$s) => array_sum(array_map(fn($k) => $stageCount[$k] ?? 0, $s));

$cArchived = (int) (db_one("SELECT COUNT(*) c $mineJoin WHERE $mine AND a.archived = 1")['c'] ?? 0);
$cMineQ   = (int) (db_one("SELECT COUNT(*) c $mineJoin WHERE a.current_owner_id = ? AND $active", 'i', [$ES_UID])['c'] ?? 0);
$cMine    = $cMineQ + $cToStart;
$cOverdue = (int) (db_one("SELECT COUNT(*) c $mineJoin WHERE $mine AND $overdue")['c'] ?? 0);
// archived work still counts as active
$cActive  = $stg('draft', 'returned', 'supervisor_review', 'director_review', 'dg_review', 'board_review') + $cToStart + $cArchived;
$cAll     = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis WHERE archived = 0")['c'] ?? 0);

$tabCount = [
    'mine' => $cMine, 'tostart' => $cToStart, 'new' => $stg('draft'), 'returned' => $stg('returned'),
    'supervisor' => $stg('supervisor_review'), 'director' => $stg('director_review'),
    'dg' => $stg('dg_review'), 'board' => $stg('board_review'), 'overdue' => $cOverdue,
    'archived' => $cArchived, 'done' => $stg('approved', 'rejected'), 'all' => $cAll,
];

// ---- list -----------------------------------------------------------
$showAnalyses = $tab !== 'tostart';
$rows = []; $total = 0; $pages = 1;
if ($showAnalyses) {
    $where = [$TABS[$tab][1]];
    if ($TABS[$tab][2]) $where[] = $mine;
    $types = ''; $args = [];
    if ($q !== '') {
        $where[] = '(r.serial_no LIKE ? OR r.subject LIKE ? OR p.name LIKE ?)';
        $l = "%$q%"; $types .= 'sss'; array_push($args, $l, $l, $l);
    }
    if ($fPde) { $where[] = 'r.pde_id = ?'; $types .= 'i'; $args[] = $fPde; }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $orderSql = match ($sort) {
        'old'   => 'COALESCE(a.submitted_at, r.ts_create) ASC',
        'stage' => "FIELD(a.stage,'draft','returned','supervisor_review','director_review','dg_review','board_review','approved','rejected'), a.ts_update DESC",
        'pde'   => 'p.name ASC, a.ts_update DESC',
        default => 'a.ts_update DESC',
    };

    $total = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id LEFT JOIN es_pde p ON p.id = r.pde_id $whereSql", $types, $args)['c'] ?? 0);
    $pages = max(1, (int) ceil($total / $per));
    $page  = min($page, $pages);
    $offset = ($page - 1) * $per;

    $rows = db_all(
        "SELECT a.id, a.stage, a.final_outcome, a.ts_update, a.officer_id,
                r.id AS registry_id, r.serial_no, r.subject, r.ts_create AS submitted_ts, r.allocated_at,
                p.name AS pde_name, m.name AS method_name,
                o.full_name AS officer_name, c.full_name AS owner_name,
                (SELECT COUNT(*) FROM es_bid_message    x WHERE x.analysis_id = a.id) AS notes,
                (SELECT COUNT(*) FROM es_bid_lot        x WHERE x.analysis_id = a.id) AS lots,
                (SELECT COUNT(*) FROM es_bid_attachment x WHERE x.analysis_id = a.id OR x.registry_id = r.id) AS atts,
                (SELECT MAX(x.ts_create) FROM es_bid_routing x WHERE x.analysis_id = a.id) AS last_route,
                (SELECT x.comments FROM es_bid_routing x WHERE x.analysis_id = a.id AND x.action = 'return' ORDER BY x.id DESC LIMIT 1) AS return_note
           FROM es_bid_analysis a
           JOIN es_bid_registry r ON r.id = a.registry_id
           LEFT JOIN es_pde p ON p.id = r.pde_id
           LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
           LEFT JOIN users o ON o.id = a.officer_id
           LEFT JOIN users c ON c.id = a.current_owner_id
           $whereSql
          ORDER BY $orderSql
          LIMIT $per OFFSET $offset",
        $types, $args
    );
}

$OUTCOME = [
    'compliant' => ['Compliant', 't-green'], 'no_objection' => ['No objection', 't-green'],
    'non_compliant' => ['Non-compliant', 't-rose'], 'objection' => ['Objection', 't-rose'],
];
$pdeList = db_all("SELECT id, name FROM es_pde WHERE active = 1 ORDER BY name");
$csrf    = e(es_csrf_token());
$turnMap = es_turnaround_map(array_column($rows ?? [], 'id'));

es_layout_head('Bid analysis', 'analysis');
?>

<div class="f-head">
  <h1 class="f-title">Bid analysis</h1>
  <p class="f-subtitle">Your assigned reviews and where each one sits in the workflow. <?= es_turnaround_legend() ?></p>
</div>

<div class="f-stats">
  <div class="f-stat s-sky"><i class="bi bi-person-workspace"></i>
    <div class="f-stat-value"><?= number_format($cMine) ?></div><div class="f-stat-label">Awaiting my action</div></div>
  <div class="f-stat <?= $cOverdue > 0 ? 's-rose' : 's-amber' ?>"><i class="bi bi-alarm"></i>
    <div class="f-stat-value"><?= number_format($cOverdue) ?></div><div class="f-stat-label">Mine overdue (&gt; <?= ES_SLA_DAYS ?>d)</div></div>
  <div class="f-stat s-green"><i class="bi bi-clipboard-data"></i>
    <div class="f-stat-value"><?= number_format($cActive) ?></div><div class="f-stat-label">My reviews in progress</div></div>
</div>

<div class="f-chips">
  <?php foreach ($TABS as $k => [$lbl]): ?>
    <a href="?<?= e(http_build_query(['tab' => $k] + $carry)) ?>" class="chip <?= $tab === $k ? 'active' : '' ?>">
      <?= e($lbl) ?><span class="chip-count"><?= (int) ($tabCount[$k] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($showAnalyses): ?>
<form class="f-toolbar" method="get">
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
    <?php foreach (['recent' => 'Recently updated', 'old' => 'Oldest first', 'stage' => 'By stage', 'pde' => 'PDE A–Z'] as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Apply</button>
  <?php if ($hasFilter): ?><a href="?tab=<?= e($tab) ?>" class="btn btn-link btn-sm text-decoration-none" style="height:40px;">Clear</a><?php endif; ?>
</form>
<?php endif; ?>

<div class="d-flex flex-column gap-3">

  <?php /* ---- allocated to me, not started yet ---- */ ?>
  <?php foreach ($toStart as $r): ?>
    <div class="f-panel rec" style="border-left:3px solid var(--brand);">
      <div class="rec-head">
        <div class="rec-headmain">
          <div class="rec-title"><?= e($r['subject']) ?></div>
          <div class="rec-meta">
            <span><?= e($r['pde_name'] ?? '—') ?></span>
            <span class="sep">·</span><span class="font-monospace"><?= e($r['serial_no']) ?></span>
            <?php if ($r['method_name']): ?><span class="sep">·</span><span><?= e($r['method_name']) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="rec-badges">
          <span class="es-badge es-badge-status"><span class="es-dot" style="background:#2a8f2e"></span>Allocated to you</span>
          <?= es_priority_badge($r['importance']) ?>
        </div>
      </div>
      <div class="rec-metrics">
        <span>allocated <b><?= e(es_span($r['allocated_at'])) ?></b></span>
        <span class="sep">·</span>
        <span><b><?= (int) $r['atts'] ?></b> file<?= (int) $r['atts'] === 1 ? '' : 's' ?></span>
      </div>
      <?= es_turn_strip($r['allocated_at'] ? ['officer' => ['secs' => time() - strtotime($r['allocated_at']), 'open' => true, 'n' => 0]] : [], 'draft') ?>
      <?php if (trim((string) $r['instruction']) !== ''): ?>
        <div class="rec-note"><strong>Instruction:</strong> <?= e($r['instruction']) ?></div>
      <?php endif; ?>
      <div class="rec-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary"
                data-drawer="bid_submission_peek.php?id=<?= (int) $r['id'] ?>&amp;partial=1"
                data-drawer-title="Submission · <?= e($r['serial_no']) ?>">
          <i class="bi bi-file-earmark-text me-1"></i>View submission
        </button>
        <form method="post" action="bid_analysis_start.php" class="d-inline">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="registry_id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-sm btn-success"><i class="bi bi-play-fill me-1"></i>Start analysis</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <?php /* ---- analyses ---- */ ?>
  <?php foreach ($rows as $r):
      $subDays = $r['submitted_ts'] ? (int) floor((time() - strtotime($r['submitted_ts'])) / 86400) : 0;
      $subHot  = $subDays > ES_SLA_DAYS;
      $lastTs  = $r['last_route'] ?: $r['ts_update'];
      $isDone  = in_array($r['stage'], ['approved', 'rejected'], true);
      $mineRow = (int) $r['officer_id'] === $ES_UID;
  ?>
    <div class="f-panel rec">
      <div class="rec-head">
        <div class="rec-headmain">
          <div class="rec-title"><?= e($r['subject']) ?></div>
          <div class="rec-meta">
            <span><?= e($r['pde_name'] ?? '—') ?></span>
            <span class="sep">·</span><span class="font-monospace"><?= e($r['serial_no']) ?></span>
            <?php if ($r['method_name']): ?><span class="sep">·</span><span><?= e($r['method_name']) ?></span><?php endif; ?>
            <span class="sep">·</span><span><?= $mineRow ? 'Assigned to me' : 'Reviewer: ' . e($r['officer_name'] ?? '—') ?></span>
            <?php if (!$isDone && $r['owner_name'] && $r['owner_name'] !== $r['officer_name']): ?>
              <span class="sep">·</span><span>with <strong><?= e($r['owner_name']) ?></strong></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="rec-badges">
          <?= es_status_badge($r['stage']) ?>
          <?php if ($isDone && isset($OUTCOME[$r['final_outcome']])): ?>
            <span class="pill <?= $OUTCOME[$r['final_outcome']][1] ?>"><?= e($OUTCOME[$r['final_outcome']][0]) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($r['stage'] === 'returned' && trim((string) $r['return_note']) !== ''): ?>
        <div class="rec-note is-return"><i class="bi bi-arrow-return-left me-1"></i><?= e($r['return_note']) ?></div>
      <?php endif; ?>

      <div class="rec-metrics">
        <span class="<?= $subHot ? 'm-hot' : '' ?>">since submission <b><?= e(es_span($r['submitted_ts'])) ?></b></span>
        <span class="sep">·</span>
        <span>allocated <b><?= e(es_span($r['allocated_at'])) ?></b></span>
        <span class="sep">·</span>
        <span>last action <b><?= e(es_span($lastTs)) ?></b></span>
        <span class="sep">·</span>
        <span><i class="bi bi-chat-left-text"></i> <b><?= (int) $r['notes'] ?></b></span>
        <span><i class="bi bi-box"></i> <b><?= (int) $r['lots'] ?></b></span>
        <span><i class="bi bi-paperclip"></i> <b><?= (int) $r['atts'] ?></b></span>
      </div>

      <?= es_turn_strip($turnMap[(int) $r['id']] ?? [], $r['stage']) ?>

      <div class="rec-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary"
                data-drawer="bid_submission_peek.php?id=<?= (int) $r['registry_id'] ?>&amp;partial=1"
                data-drawer-title="Submission · <?= e($r['serial_no']) ?>">
          <i class="bi bi-file-earmark-text me-1"></i>View submission
        </button>
        <a href="bid_analysis_view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-success">Open analysis</a>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$toStart && !$rows): ?>
    <div class="f-state"><i class="bi bi-clipboard-check"></i>
      <p><?= $tab === 'mine' ? 'Nothing is waiting on you right now.' : ($tab === 'tostart' ? 'No submissions are waiting for you to start.' : 'Nothing here') ?><?= $hasFilter ? ' matches these filters' : '' ?>.</p>
    </div>
  <?php endif; ?>
</div>

<?php if ($showAnalyses && $rows): ?>
  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query($carry + ['page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
