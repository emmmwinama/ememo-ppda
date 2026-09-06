<?php
/** Submission tracking — org-wide view of where every submission sits:
 *  awaiting allocation, or in a specific stage of review, with the people on it. */
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/ui.php';
es_require_perm('submission.track');

global $conn, $ES_UID;

const ES_SLA_DAYS = 14;

$q    = trim($_GET['q'] ?? '');
$fPde = ($_GET['pde'] ?? '') !== '' ? (int) $_GET['pde'] : 0;
$sort = in_array($_GET['sort'] ?? '', ['recent', 'old', 'pde'], true) ? $_GET['sort'] : 'recent';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 15;

// every clause is evaluated over: es_bid_registry r LEFT JOIN es_bid_analysis a
$noAn = 'a.id IS NULL';
$liv  = "(a.id IS NULL OR a.archived = 0)";   // not parked in the archive
$TABS = [
    'unallocated' => ['Awaiting allocation',    "r.status IN ('pending_registry','pending_allocation') AND $noAn"],
    'allocated'   => ['Allocated · not started', "r.status = 'assigned' AND $noAn"],
    'draft'       => ['Draft analysis',          "a.stage = 'draft' AND $liv"],
    'supervisor'  => ['Supervisor review',       "a.stage = 'supervisor_review' AND $liv"],
    'director'    => ['Director review',         "a.stage = 'director_review' AND $liv"],
    'dg'          => ['DG review',               "a.stage = 'dg_review' AND $liv"],
    'board'       => ['Board review',            "a.stage = 'board_review' AND $liv"],
    'returned'    => ['Returned to officer',     "a.stage = 'returned' AND $liv"],
    'archived'    => ['Archived',                "a.archived = 1"],
    'completed'   => ['Completed',               "a.stage IN ('approved','rejected') OR (r.status = 'completed' AND $noAn)"],
    'all'         => ['All submissions',         '1=1'],
];
$tab   = isset($_GET['tab'], $TABS[$_GET['tab']]) ? $_GET['tab'] : 'unallocated';
$carry = ['tab' => $tab, 'q' => $q, 'pde' => $fPde ?: '', 'sort' => $sort];
$hasFilter = $q !== '' || $fPde;

$from = "FROM es_bid_registry r
         LEFT JOIN es_bid_analysis a ON a.registry_id = r.id
         LEFT JOIN es_pde p ON p.id = r.pde_id";

$cnt = [];
foreach ($TABS as $k => [$lbl, $w]) {
    $cnt[$k] = (int) (db_one("SELECT COUNT(*) c $from WHERE $w")['c'] ?? 0);
}

$where = [$TABS[$tab][1]];
$types = ''; $args = [];
if ($q !== '') { $where[] = '(r.serial_no LIKE ? OR r.subject LIKE ? OR p.name LIKE ?)'; $l = "%$q%"; $types .= 'sss'; array_push($args, $l, $l, $l); }
if ($fPde)    { $where[] = 'r.pde_id = ?'; $types .= 'i'; $args[] = $fPde; }
$whereSql = 'WHERE ' . implode(' AND ', $where);
$orderSql = match ($sort) {
    'old' => 'r.ts_create ASC',
    'pde' => 'p.name ASC, r.ts_create DESC',
    default => 'COALESCE(a.ts_update, r.ts_update) DESC',
};

$total  = (int) (db_one("SELECT COUNT(*) c $from $whereSql", $types, $args)['c'] ?? 0);
$pages  = max(1, (int) ceil($total / $per));
$page   = min($page, $pages);
$offset = ($page - 1) * $per;

$rows = db_all(
    "SELECT r.id AS registry_id, r.serial_no, r.subject, r.status AS rstatus, r.ts_create AS submitted_ts,
            r.origin, r.registry_checked_at AS checked_ts, r.allocated_at AS allocated_ts,
            p.name AS pde_name,
            a.id AS analysis_id, a.stage AS astage, a.final_outcome, a.current_owner_id, a.archived,
            a.ts_create AS analysis_ts, a.decided_at AS decided_ts,
            COALESCE(o.full_name, ao.full_name) AS officer_name,
            co.full_name AS owner_name,
            COALESCE(
              (SELECT us.full_name FROM es_bid_routing ts JOIN users us ON us.id = ts.from_user_id
                WHERE ts.analysis_id = a.id AND ts.from_stage = 'supervisor_review'
                  AND ts.action IN ('submit','endorse','approve','reject','return')
                ORDER BY ts.id DESC LIMIT 1),
              CASE WHEN a.stage = 'supervisor_review' THEN co.full_name END
            ) AS supervisor_name,
            COALESCE(
              (SELECT ud.full_name FROM es_bid_routing td JOIN users ud ON ud.id = td.from_user_id
                WHERE td.analysis_id = a.id AND td.from_stage = 'director_review'
                  AND td.action IN ('submit','endorse','approve','reject','return')
                ORDER BY td.id DESC LIMIT 1),
              CASE WHEN a.stage = 'director_review' THEN co.full_name END
            ) AS director_name
       $from
       LEFT JOIN users o  ON o.id  = a.officer_id
       LEFT JOIN users ao ON ao.id = r.assigned_officer_id
       LEFT JOIN users co ON co.id = a.current_owner_id
       $whereSql
      ORDER BY $orderSql
      LIMIT $per OFFSET $offset",
    $types, $args
);

$pdeList = db_all("SELECT id, name FROM es_pde WHERE active = 1 ORDER BY name");

$inReview  = $cnt['draft'] + $cnt['supervisor'] + $cnt['director'] + $cnt['dg'] + $cnt['board'] + $cnt['returned'] + $cnt['archived'];
$doneStage = ['approved', 'rejected', 'completed', 'closed', 'withdrawn'];

// turnaround per role (shared helper) + registry-check turnaround (local, from the
// registry row rather than the routing trail).
$aids = array_values(array_filter(array_map(fn ($r) => (int) $r['analysis_id'], $rows)));
$turn = es_turnaround_map($aids);

$regTurn = function (array $r): ?array {
    $start = $r['submitted_ts'] ? strtotime($r['submitted_ts']) : null;
    if (!$start) return null;
    if ($r['checked_ts']) return ['secs' => max(0, strtotime($r['checked_ts']) - $start), 'open' => false, 'n' => 1];
    if ($r['rstatus'] === 'pending_registry') return ['secs' => time() - $start, 'open' => true, 'n' => 0];
    return null;
};

es_layout_head('Submission tracking', 'tracking');
?>

<div class="f-head">
  <h1 class="f-title">Submission tracking</h1>
  <p class="f-subtitle">Where every submission sits — allocation and each stage of review, with the people on it.
    <?= es_turnaround_legend() ?></p>
</div>

<div class="f-stats">
  <div class="f-stat s-amber"><i class="bi bi-inbox"></i>
    <div class="f-stat-value"><?= number_format($cnt['unallocated'] + $cnt['allocated']) ?></div><div class="f-stat-label">Awaiting / not started</div></div>
  <div class="f-stat s-sky"><i class="bi bi-arrow-repeat"></i>
    <div class="f-stat-value"><?= number_format($inReview) ?></div><div class="f-stat-label">In review</div></div>
  <div class="f-stat s-green"><i class="bi bi-check2-circle"></i>
    <div class="f-stat-value"><?= number_format($cnt['completed']) ?></div><div class="f-stat-label">Completed</div></div>
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
  <select name="pde" class="form-select f-control">
    <option value="">All PDEs</option>
    <?php foreach ($pdeList as $pd): ?>
      <option value="<?= (int) $pd['id'] ?>" <?= $fPde === (int) $pd['id'] ? 'selected' : '' ?>><?= e($pd['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" class="form-select f-control">
    <?php foreach (['recent' => 'Recently updated', 'old' => 'Oldest first', 'pde' => 'PDE A–Z'] as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Apply</button>
  <?php if ($hasFilter): ?><a href="?<?= e(http_build_query(['tab' => $tab])) ?>" class="btn btn-link btn-sm text-decoration-none" style="height:40px;">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-signpost-split"></i><p>Nothing here<?= $hasFilter ? ' matches these filters' : '' ?>.</p></div>
<?php else: ?>
  <div class="f-panel" style="padding:0;overflow:hidden;">
    <div class="table-responsive">
      <table class="table f-table align-middle mb-0" style="font-size:.86rem;">
        <thead>
          <tr>
            <th>Submission</th>
            <th title="Receipt / upload → registry sign-off">Registry</th>
            <th>Stage</th>
            <th>Reviewer</th><th>Supervisor</th><th>Director</th>
            <th title="Time at DG review">DG</th><th title="Time at Board review">Board</th>
            <th class="text-nowrap" title="Time since submission — or, once decided, receipt/upload → determination">Elapsed</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $stageKey = $r['astage'] ?: $r['rstatus'];
            $isDone   = in_array($stageKey, $doneStage, true);
            $days     = $r['submitted_ts'] ? (int) floor((time() - strtotime($r['submitted_ts'])) / 86400) : 0;

            // which reviewer roles a submission has picked up, purely by how far it has travelled:
            //   allocated / draft → reviewer   ·   supervisor → + supervisor
            //   director → + director          ·   dg / board / decided → reviewer + supervisor + director
            $rank = match ($r['astage']) {
                'supervisor_review'    => 1,
                'director_review'      => 2,
                'dg_review'            => 3,
                'board_review'         => 4,
                'approved', 'rejected' => 5,
                'draft', 'returned'    => 0,
                default                => -1,   // no analysis yet
            };
            $blank   = '<span class="text-muted">·</span>';
            $inqueue = '<span class="text-muted">in queue</span>';
            $aid     = (int) $r['analysis_id'];
            $durCell = fn(?array $t, int $g, int $o) => ($h = es_turn_chip($t, $g, $o)) !== '' ? $h : $blank;
            $named   = fn(string $name, string $circle) => '<div class="trk-name">' . $circle . '<span>' . $name . '</span></div>';

            // reviewer clock also runs while it is allocated but the analysis is not started yet
            $revTurn = $turn[$aid]['officer'] ?? null;
            if (!$revTurn && !$aid && $r['rstatus'] === 'assigned') {
                $st = $r['allocated_ts'] ?: $r['submitted_ts'];
                if ($st) $revTurn = ['secs' => time() - strtotime($st), 'open' => true, 'n' => 0, 'avg' => null];
            }

            $rev = $r['officer_name'] ? e($r['officer_name']) : $blank;
            $sup = $rank < 1 ? $blank
                 : ($r['supervisor_name'] ? e($r['supervisor_name']) : ($rank === 1 ? $inqueue : $blank));
            $dir = $rank < 2 ? $blank
                 : ($r['director_name'] ? e($r['director_name']) : ($rank === 2 ? $inqueue : $blank));
        ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($r['subject']) ?></div>
              <div class="text-muted"><?= e($r['pde_name'] ?? '—') ?> · <span class="font-monospace"><?= e($r['serial_no']) ?></span></div>
            </td>
            <td><?= $durCell($regTurn($r), 3, 7) ?></td>
            <td><?= es_status_badge($stageKey) ?><?= es_outcome_pill($r['final_outcome'] ?? null) ?><?= !empty($r['archived']) ? ' <span class="pill t-amber"><i class="bi bi-archive me-1"></i>archived</span>' : '' ?></td>
            <td><?= $named($rev, es_turn_chip($revTurn, 7, 14)) ?></td>
            <td><?= $named($sup, $rank >= 1 ? es_turn_chip($turn[$aid]['supervisor'] ?? null, 3, 7) : '') ?></td>
            <td><?= $named($dir, $rank >= 2 ? es_turn_chip($turn[$aid]['director'] ?? null, 3, 7) : '') ?></td>
            <td><?= $rank < 3 ? $blank : $durCell($turn[$aid]['dg'] ?? null, 3, 7) ?></td>
            <td><?= $rank < 4 ? $blank : $durCell($turn[$aid]['board'] ?? null, 5, 12) ?></td>
            <td class="<?= (!$isDone && $days > ES_SLA_DAYS) ? 'text-danger fw-semibold' : 'text-muted' ?>">
              <?php if ($isDone && $r['decided_ts'] && $r['submitted_ts']): ?>
                <?= es_turn_chip(['secs' => max(0, strtotime($r['decided_ts']) - strtotime($r['submitted_ts'])), 'n' => 1], 30, 45) ?>
              <?php elseif ($r['submitted_ts']): ?>
                <?= e(es_span($r['submitted_ts'])) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <?= es_submission_button((int) $r['registry_id'], $r['serial_no'], 'View', 'btn btn-outline-secondary btn-sm') ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query($carry + ['page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
