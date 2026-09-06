<?php
/** Submissions register — the registry's view of every PDE submission.
 *  Allocation lives on its own page (bid_allocations.php) for the DG / allocator. */
require __DIR__ . '/inc/layout.php';
es_require_role('registry', 'pde', 'allocator', 'officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

$pdeScope = es_pde_scope();                 // null = see all; >0 = one PDE; -1 = PDE user, no PDE
$isPde    = $pdeScope !== null;
$canAdd   = es_can('submission.create') || ($pdeScope !== null && $pdeScope > 0);

$TABS = [
    'all'         => ['label' => 'All active',          'statuses' => ['pending_registry', 'returned_to_pde', 'pending_allocation', 'assigned', 'in_analysis']],
    'reg_check'   => ['label' => 'Registry check',      'statuses' => ['pending_registry']],
    'returned'    => ['label' => 'Returned to PDE',     'statuses' => ['returned_to_pde']],
    'in_progress' => ['label' => 'In review',           'statuses' => ['pending_allocation', 'assigned', 'in_analysis']],
    'completed'   => ['label' => 'Completed',           'statuses' => ['completed']],
    'closed'      => ['label' => 'Closed / withdrawn',  'statuses' => ['closed', 'withdrawn']],
];
$tab = isset($_GET['tab'], $TABS[$_GET['tab']]) ? $_GET['tab'] : 'all';

$q       = trim($_GET['q'] ?? '');
$fPde    = !$isPde && ($_GET['pde'] ?? '') !== '' ? (int) $_GET['pde'] : 0;
$fPrio   = in_array($_GET['prio'] ?? '', ['normal', 'high', 'urgent'], true) ? $_GET['prio'] : '';
$fSrc    = in_array($_GET['src'] ?? '', ['registry', 'pde'], true) ? $_GET['src'] : '';
$sort    = in_array($_GET['sort'] ?? '', ['new', 'old', 'prio', 'pde', 'serial'], true) ? $_GET['sort'] : 'new';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$carry = ['tab' => $tab, 'q' => $q, 'pde' => $fPde ?: '', 'prio' => $fPrio, 'src' => $fSrc, 'sort' => $sort];
$hasFilter = $q !== '' || $fPde || $fPrio !== '' || $fSrc !== '';

// visibility clause reused everywhere
$scopeSql = '';
$scopeArg = [];
$scopeTypes = '';
if ($isPde) {
    $scopeSql   = ' AND r.pde_id = ?';
    $scopeTypes = 'i';
    $scopeArg   = [(int) $pdeScope];        // -1 matches nothing
}

// per-status counts (for the tab badges + headline panels)
$statusCount = [];
foreach (db_all("SELECT status, COUNT(*) c FROM es_bid_registry r WHERE 1=1 $scopeSql GROUP BY status", $scopeTypes, $scopeArg) as $row) {
    $statusCount[$row['status']] = (int) $row['c'];
}
$counts = array_fill_keys(array_keys($TABS), 0);
foreach ($TABS as $k => $def) {
    foreach ($def['statuses'] as $s) $counts[$k] += $statusCount[$s] ?? 0;
}
$sc = fn(string ...$st) => array_sum(array_map(fn($s) => $statusCount[$s] ?? 0, $st));

if ($isPde) {
    $panels = [
        ['s-amber', 'bi-hourglass-split',         $sc('pending_registry'),                              'Awaiting PPDA check'],
        ['s-rose',  'bi-arrow-counterclockwise',  $sc('returned_to_pde'),                               'Returned for changes'],
        ['s-sky',   'bi-people',                  $sc('pending_allocation', 'assigned', 'in_analysis'), 'Under review'],
        ['s-green', 'bi-check2-circle',           $sc('completed'),                                     'Completed'],
    ];
} else {
    $panels = [
        ['s-amber', 'bi-clipboard-check',         $sc('pending_registry'),        'Awaiting registry check'],
        ['s-rose',  'bi-arrow-counterclockwise',  $sc('returned_to_pde'),         'Returned to PDE'],
        ['s-sky',   'bi-diagram-3',               $sc('pending_allocation'),      'With the DG for allocation'],
        ['s-green', 'bi-people',                  $sc('assigned', 'in_analysis'), 'In review'],
    ];
}

// ------------------------------------------------------------- list query
$statuses = $TABS[$tab]['statuses'];
$in = implode(',', array_fill(0, count($statuses), '?'));
$where = ["r.status IN ($in)"];
$types = str_repeat('s', count($statuses)) . $scopeTypes;
$args  = array_merge($statuses, $scopeArg);
if ($isPde) $where[] = 'r.pde_id = ?';
if ($q !== '') {
    $where[] = '(r.serial_no LIKE ? OR r.subject LIKE ? OR r.tender_number LIKE ? OR p.name LIKE ?)';
    $like = "%$q%";
    $types .= 'ssss';
    array_push($args, $like, $like, $like, $like);
}
if ($fPde)         { $where[] = 'r.pde_id = ?';     $types .= 'i'; $args[] = $fPde; }
if ($fPrio !== '') { $where[] = 'r.importance = ?'; $types .= 's'; $args[] = $fPrio; }
if ($fSrc !== '')  { $where[] = 'r.origin = ?';     $types .= 's'; $args[] = $fSrc; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$orderSql = match ($sort) {
    'old'    => 'r.ts_create ASC',
    'prio'   => "FIELD(r.importance,'urgent','high','normal'), r.ts_create DESC",
    'pde'    => 'p.name ASC, r.ts_create DESC',
    'serial' => 'r.serial_no DESC',
    default  => 'r.ts_create DESC',
};

$total  = (int) (db_one("SELECT COUNT(*) c FROM es_bid_registry r LEFT JOIN es_pde p ON p.id=r.pde_id $whereSql", $types, $args)['c'] ?? 0);
$pages  = max(1, (int) ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = db_all(
    "SELECT r.id, r.serial_no, r.subject, r.tender_number, r.ref_code_pde, r.status, r.importance,
            r.origin, r.channel, r.submission_signed, r.in_procurement_plan, r.accompanied_documents,
            r.ts_create, r.registry_checked_at, r.allocated_at, r.registry_comment,
            p.name AS pde_name, m.name AS method_name,
            u.full_name AS officer_name, rb.full_name AS received_name,
            (SELECT COUNT(*) FROM es_bid_attachment a WHERE a.registry_id = r.id) AS doc_count,
            (SELECT pr.id FROM es_pde_response pr JOIN es_bid_analysis a2 ON a2.id = pr.analysis_id
              WHERE a2.registry_id = r.id AND pr.published = 1 ORDER BY pr.id DESC LIMIT 1) AS response_id
       FROM es_bid_registry r
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN users u  ON u.id  = r.assigned_officer_id
       LEFT JOIN users rb ON rb.id = r.received_by
       $whereSql
      ORDER BY $orderSql
      LIMIT $perPage OFFSET $offset",
    $types, $args
);

$RAIL = [
    'pending_registry' => '#b45309', 'returned_to_pde' => '#be123c', 'pending_allocation' => '#0369a1',
    'assigned' => '#0369a1', 'in_analysis' => '#b45309', 'completed' => '#15803d',
    'closed' => '#64748b', 'withdrawn' => '#be123c',
];

$canCheck = es_can('submission.registry_check');
$pdeList  = !$isPde ? db_all("SELECT id, name FROM es_pde WHERE active = 1 ORDER BY name") : [];

es_layout_head('Submissions', 'registry');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title"><?= $isPde ? 'My submissions' : 'Submissions register' ?></h1>
    <p class="f-subtitle"><?= $isPde
        ? 'Post-procurement review submissions for your entity'
        : 'Every PDE submission and where it is in the review pipeline' ?></p>
  </div>
  <?php if ($canAdd): ?>
    <button type="button" class="btn btn-success btn-sm"
            data-drawer="bid_registry_form.php?partial=1" data-drawer-title="<?= $isPde ? 'New submission' : 'Log a submission' ?>">
      <i class="bi bi-plus-lg me-1"></i><?= $isPde ? 'New submission' : 'Log a submission' ?>
    </button>
  <?php endif; ?>
</div>

<?php if ($pdeScope === -1): ?>
  <div class="f-state is-error"><i class="bi bi-shield-lock"></i><p>Your account has the PDE role but isn't linked to a procuring entity yet. Ask an administrator to set it under <em>Administration → Users</em>.</p></div>
<?php else: ?>

<div class="f-stats">
  <?php foreach ($panels as [$cls, $icon, $val, $lbl]): ?>
    <div class="f-stat <?= $cls ?>">
      <i class="bi <?= $icon ?>"></i>
      <div class="f-stat-value"><?= number_format((int) $val) ?></div>
      <div class="f-stat-label"><?= e($lbl) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="f-chips">
  <?php foreach ($TABS as $k => $def): if ($counts[$k] === 0 && $k !== $tab && $k !== 'all') continue; ?>
    <a href="?<?= e(http_build_query(['tab' => $k] + $carry)) ?>" class="chip <?= $tab === $k ? 'active' : '' ?>">
      <?= e($def['label']) ?><span class="chip-count"><?= (int) $counts[$k] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search serial, subject, tender no.<?= $isPde ? '' : ' or PDE' ?>…" autocomplete="off">
  </div>
  <?php if (!$isPde): ?>
    <select name="pde" class="form-select f-control">
      <option value="">All PDEs</option>
      <?php foreach ($pdeList as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= $fPde === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="src" class="form-select f-control">
      <option value="">Any source</option>
      <option value="registry" <?= $fSrc === 'registry' ? 'selected' : '' ?>>Registry entry</option>
      <option value="pde" <?= $fSrc === 'pde' ? 'selected' : '' ?>>PDE upload</option>
    </select>
  <?php endif; ?>
  <select name="prio" class="form-select f-control">
    <option value="">Any priority</option>
    <?php foreach (['urgent', 'high', 'normal'] as $p): ?>
      <option value="<?= $p ?>" <?= $fPrio === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" class="form-select f-control">
    <?php foreach (['new' => 'Newest first', 'old' => 'Oldest first', 'prio' => 'Priority', 'pde' => 'PDE A–Z', 'serial' => 'Serial no.'] as $k => $lbl): if ($k === 'pde' && $isPde) continue; ?>
      <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Apply</button>
  <?php if ($hasFilter): ?>
    <a href="?tab=<?= e($tab) ?>" class="btn btn-link btn-sm text-decoration-none" style="height:40px;">Clear</a>
  <?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-journal"></i><p>No submissions match<?= $hasFilter ? ' these filters' : '' ?>.</p></div>
<?php else: ?>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($rows as $r):
        $viewUrl  = 'bid_registry_view.php?id=' . (int) $r['id'];
        $isReturn = $r['status'] === 'returned_to_pde';
        if (in_array($r['status'], ['assigned', 'in_analysis'], true) && $r['allocated_at']) {
            [$ageWord, $ageTs] = ['allocated', $r['allocated_at']];
        } elseif ($r['status'] === 'pending_allocation' && $r['registry_checked_at']) {
            [$ageWord, $ageTs] = ['checked', $r['registry_checked_at']];
        } elseif ($isReturn && $r['registry_checked_at']) {
            [$ageWord, $ageTs] = ['returned', $r['registry_checked_at']];
        } else {
            [$ageWord, $ageTs] = ['submitted', $r['ts_create']];
        }
        $nTypes = count(array_filter(explode(',', (string) $r['accompanied_documents'])));
    ?>
      <div class="f-panel rec">
        <div class="rec-head">
          <div class="rec-headmain">
            <div class="rec-title"><?= e($isPde ? $r['subject'] : ($r['pde_name'] ?? '—')) ?></div>
            <?php if (!$isPde): ?><div class="rec-meta" style="margin-top:.1rem;"><?= e($r['subject']) ?></div><?php endif; ?>
            <div class="rec-meta">
              <span class="font-monospace"><?= e($r['serial_no']) ?></span>
              <?php if ($r['tender_number']): ?><span class="sep">·</span><span><?= e($r['tender_number']) ?></span><?php endif; ?>
              <?php if ($r['ref_code_pde']): ?><span class="sep">·</span><span>PDE ref <?= e($r['ref_code_pde']) ?></span><?php endif; ?>
              <?php if ($r['method_name']): ?><span class="sep">·</span><span><?= e($r['method_name']) ?></span><?php endif; ?>
              <?php if ($r['officer_name']): ?><span class="sep">·</span><span>Officer: <?= e($r['officer_name']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="rec-badges">
            <?= es_status_badge($r['status']) ?>
            <?= es_source_badge($r['origin']) ?>
            <?= es_priority_badge($r['importance']) ?>
          </div>
        </div>

        <?php if (in_array($r['status'], ['returned_to_pde', 'pending_allocation'], true) && trim((string) $r['registry_comment']) !== ''): ?>
          <div class="rec-note <?= $isReturn ? 'is-return' : '' ?>">
            <strong><?= $isReturn ? 'Returned:' : 'Registry note:' ?></strong> <?= e($r['registry_comment']) ?>
          </div>
        <?php endif; ?>

        <div class="rec-metrics">
          <span><b><?= $nTypes ?></b>/10 doc types</span>
          <span class="sep">·</span>
          <span><b><?= (int) $r['doc_count'] ?></b> file<?= (int) $r['doc_count'] === 1 ? '' : 's' ?></span>
          <span class="sep">·</span>
          <span><?= $ageWord ?> <b><?= e(es_ago($ageTs)) ?></b></span>
        </div>

        <div class="rec-actions">
          <?php if (!$isPde && $canCheck && $r['status'] === 'pending_registry'): ?>
            <a href="<?= $viewUrl ?>#act" class="btn btn-sm btn-success"><i class="bi bi-check2-square me-1"></i>Registry check</a>
          <?php endif; ?>
          <?php if ($r['response_id']): ?>
            <a href="response_letter.php?id=<?= (int) $r['response_id'] ?>" class="btn btn-sm btn-outline-success" target="_blank"><i class="bi bi-file-earmark-arrow-down me-1"></i>Response letter</a>
          <?php endif; ?>
          <a href="<?= $viewUrl ?>" class="btn btn-sm btn-outline-secondary">Open</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query($carry + ['page' => $p]), $total, $perPage) ?>
<?php endif; ?>

<?php endif; /* pdeScope === -1 */ ?>

<?php es_layout_foot(); ?>
