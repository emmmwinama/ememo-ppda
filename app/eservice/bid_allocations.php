<?php
/** Allocations — the DG / allocator workspace.
 *  Tab "new": registry-checked submissions waiting for a first assignment.
 *  Tab "allocated": submissions already with an officer — reassignable, with a
 *  full move history kept on the submission record. */
require __DIR__ . '/inc/layout.php';
es_require_perm('submission.allocate');

global $conn, $ES_UID;

$canPrio     = es_can('submission.reprioritise');   // re-set a submission's priority while allocating
$canReassign = es_can('submission.reassign');

$tab   = ($_GET['tab'] ?? 'new') === 'allocated' ? 'allocated' : 'new';
$q     = trim($_GET['q'] ?? '');
$fPrio = in_array($_GET['prio'] ?? '', ['normal', 'high', 'urgent'], true) ? $_GET['prio'] : '';
$sort  = $_GET['sort'] ?? ($tab === 'new' ? 'old' : 'recent');
$page  = max(1, (int) ($_GET['page'] ?? 1));
$per   = 10;
$carry = ['tab' => $tab, 'q' => $q, 'prio' => $fPrio, 'sort' => $sort];
$hasFilter = $q !== '' || $fPrio !== '';

$statusSet = $tab === 'new' ? "('pending_allocation')" : "('assigned','in_analysis')";

$where = ["r.status IN $statusSet"];
$types = '';
$args  = [];
if ($q !== '') {
    $where[] = '(r.serial_no LIKE ? OR r.subject LIKE ? OR r.tender_number LIKE ? OR p.name LIKE ?)';
    $l = "%$q%"; $types .= 'ssss'; array_push($args, $l, $l, $l, $l);
}
if ($fPrio !== '') { $where[] = 'r.importance = ?'; $types .= 's'; $args[] = $fPrio; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$orderSql = match ($sort) {
    'old'    => 'COALESCE(r.registry_checked_at, r.ts_create) ASC',
    'waiting'=> 'COALESCE(r.registry_checked_at, r.ts_create) ASC',
    'recent' => 'r.allocated_at DESC, r.ts_update DESC',
    'prio'   => "FIELD(r.importance,'urgent','high','normal'), COALESCE(r.allocated_at, r.ts_create) DESC",
    'pde'    => 'p.name ASC',
    default  => $tab === 'new' ? 'COALESCE(r.registry_checked_at, r.ts_create) ASC' : 'r.allocated_at DESC',
};

$countNew = (int) ($conn->query("SELECT COUNT(*) FROM es_bid_registry WHERE status = 'pending_allocation'")->fetch_row()[0] ?? 0);
$countAll = (int) ($conn->query("SELECT COUNT(*) FROM es_bid_registry WHERE status IN ('assigned','in_analysis')")->fetch_row()[0] ?? 0);
$reassigns = (int) (($rq = $conn->query("SELECT COUNT(*) FROM es_bid_allocation WHERE action = 'reassign'")) ? ($rq->fetch_row()[0] ?? 0) : 0);
$oldest   = db_one("SELECT MIN(ts_create) w FROM es_bid_registry WHERE status = 'pending_allocation'");

$total  = (int) (db_one("SELECT COUNT(*) c FROM es_bid_registry r LEFT JOIN es_pde p ON p.id=r.pde_id $whereSql", $types, $args)['c'] ?? 0);
$pages  = max(1, (int) ceil($total / $per));
$page   = min($page, $pages);
$offset = ($page - 1) * $per;

$rows = db_all(
    "SELECT r.id, r.serial_no, r.subject, r.tender_number, r.ref_code_pde, r.importance, r.origin,
            r.accompanied_documents, r.status, r.ts_create, r.registry_checked_at, r.allocated_at,
            r.registry_comment, r.assigned_officer_id,
            p.name AS pde_name, m.name AS method_name, ck.full_name AS checked_name,
            of.full_name AS officer_name, ab.full_name AS allocated_by_name,
            an.id AS analysis_id, an.stage AS an_stage, co.full_name AS owner_name,
            (SELECT COUNT(*) FROM es_bid_attachment a WHERE a.registry_id = r.id) AS doc_count,
            (SELECT COUNT(*) FROM es_bid_allocation al WHERE al.registry_id = r.id AND al.action = 'reassign') AS moves
       FROM es_bid_registry r
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN users ck ON ck.id = r.registry_checked_by
       LEFT JOIN users of ON of.id = r.assigned_officer_id
       LEFT JOIN users ab ON ab.id = r.allocated_by
       LEFT JOIN es_bid_analysis an ON an.id = (SELECT MAX(a2.id) FROM es_bid_analysis a2 WHERE a2.registry_id = r.id)
       LEFT JOIN users co ON co.id = an.current_owner_id
       $whereSql
      ORDER BY $orderSql
      LIMIT $per OFFSET $offset",
    $types, $args
);

$officers = db_all(
    "SELECT u.id, u.full_name FROM es_user_role x JOIN users u ON u.id = x.user_id
      WHERE x.role = 'officer' GROUP BY u.id, u.full_name ORDER BY u.full_name"
);

es_layout_head('Allocations', 'allocations');
?>

<div class="f-head">
  <h1 class="f-title">Allocations</h1>
  <p class="f-subtitle">Assign registry-checked submissions to technical officers, and move them if needed</p>
</div>

<div class="f-stats">
  <?php if ($tab === 'new'): ?>
    <div class="f-stat s-sky"><i class="bi bi-diagram-3"></i>
      <div class="f-stat-value"><?= number_format($countNew) ?></div><div class="f-stat-label">Awaiting allocation</div></div>
    <div class="f-stat <?= ($oldest['w'] ?? null) && strtotime($oldest['w']) < strtotime('-3 days') ? 's-rose' : 's-amber' ?>"><i class="bi bi-hourglass-bottom"></i>
      <div class="f-stat-value"><?= ($oldest['w'] ?? null) ? e(str_replace(' ago', '', es_ago($oldest['w']))) : '—' ?></div><div class="f-stat-label">Longest wait</div></div>
    <div class="f-stat s-green"><i class="bi bi-people"></i>
      <div class="f-stat-value"><?= number_format(count($officers)) ?></div><div class="f-stat-label">Technical officers</div></div>
  <?php else: ?>
    <div class="f-stat s-sky"><i class="bi bi-people"></i>
      <div class="f-stat-value"><?= number_format($countAll) ?></div><div class="f-stat-label">With officers now</div></div>
    <div class="f-stat s-violet"><i class="bi bi-shuffle"></i>
      <div class="f-stat-value"><?= number_format($reassigns) ?></div><div class="f-stat-label">Reassignments logged</div></div>
    <div class="f-stat s-green"><i class="bi bi-person-check"></i>
      <div class="f-stat-value"><?= number_format(count($officers)) ?></div><div class="f-stat-label">Technical officers</div></div>
  <?php endif; ?>
</div>

<div class="f-chips">
  <a href="?tab=new" class="chip <?= $tab === 'new' ? 'active' : '' ?>">New submissions<span class="chip-count"><?= $countNew ?></span></a>
  <a href="?tab=allocated" class="chip <?= $tab === 'allocated' ? 'active' : '' ?>">Allocated<span class="chip-count"><?= $countAll ?></span></a>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search serial, subject, tender no. or PDE…" autocomplete="off">
  </div>
  <select name="prio" class="form-select f-control">
    <option value="">Any priority</option>
    <?php foreach (['urgent', 'high', 'normal'] as $p): ?>
      <option value="<?= $p ?>" <?= $fPrio === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" class="form-select f-control">
    <?php
    $opts = $tab === 'new'
        ? ['old' => 'Longest waiting', 'recent' => 'Most recent', 'prio' => 'Priority', 'pde' => 'PDE A–Z']
        : ['recent' => 'Recently allocated', 'old' => 'Oldest', 'prio' => 'Priority', 'pde' => 'PDE A–Z'];
    foreach ($opts as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Apply</button>
  <?php if ($hasFilter): ?><a href="?tab=<?= e($tab) ?>" class="btn btn-link btn-sm text-decoration-none" style="height:40px;">Clear</a><?php endif; ?>
</form>

<?php if (!$officers && $tab === 'new'): ?>
  <div class="f-state is-error"><i class="bi bi-people"></i><p>No users hold the <em>officer</em> e-Services role yet. Add it under <em>Administration → Users</em> before allocating.</p></div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="f-state">
    <i class="bi bi-<?= $tab === 'new' ? 'check2-circle' : 'inbox' ?>"></i>
    <p><?= $tab === 'new' ? 'Nothing waiting for allocation' : 'Nothing allocated' ?><?= $hasFilter ? ' matches these filters' : '' ?>.</p>
  </div>
<?php else: ?>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($rows as $r):
        $started = $r['analysis_id'] !== null;
        $holder  = $started ? ($r['owner_name'] ?? $r['officer_name']) : $r['officer_name'];
        $nTypes  = count(array_filter(explode(',', (string) $r['accompanied_documents'])));
        $log     = $tab === 'new' ? [] : es_alloc_log((int) $r['id']);
        $daysWaiting = $r['ts_create'] ? (int) floor((time() - strtotime($r['ts_create'])) / 86400) : 0;
        $waitTint = $daysWaiting > 7 ? 't-rose' : ($daysWaiting > 3 ? 't-amber' : 't-green');
    ?>
      <div class="f-panel rec">
        <div class="rec-head">
          <div class="rec-headmain">
            <div class="rec-title"><?= e($r['subject']) ?></div>
            <div class="rec-meta">
              <span><?= e($r['pde_name'] ?? '—') ?></span>
              <span class="sep">·</span><span class="font-monospace"><?= e($r['serial_no']) ?></span>
              <?php if ($r['method_name']): ?><span class="sep">·</span><span><?= e($r['method_name']) ?></span><?php endif; ?>
              <span class="sep">·</span><span><?= (int) $r['doc_count'] ?> file<?= (int) $r['doc_count'] === 1 ? '' : 's' ?><?php if ($tab === 'new'): ?>, <?= $nTypes ?>/10 doc types<?php endif; ?></span>
              <?php if ($tab !== 'new'): ?>
                <span class="sep">·</span><span>allocated <?= e(es_ago($r['allocated_at'])) ?><?= $r['allocated_by_name'] ? ' by ' . e($r['allocated_by_name']) : '' ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="rec-badges">
            <?= es_priority_badge($r['importance']) ?>
            <?= es_source_badge($r['origin']) ?>
          </div>
        </div>

        <?php if ($tab === 'new'): ?>
          <div class="rec-status">
            <span class="pill <?= $waitTint ?>"><?= $daysWaiting ?> day<?= $daysWaiting === 1 ? '' : 's' ?> since submission</span>
            <span class="lbl">submitted <?= e(date('d M Y', strtotime($r['ts_create']))) ?><?php if ($r['registry_checked_at']): ?> · checked <?= e(es_ago($r['registry_checked_at'])) ?><?php endif; ?></span>
          </div>
          <?php if (trim((string) $r['registry_comment']) !== ''): ?>
            <div class="rec-note"><strong>Registry note:</strong> <?= e($r['registry_comment']) ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="rec-status">
            <span class="lbl">Currently with</span>
            <span class="who"><?= e($holder ?: '—') ?></span>
            <?= es_status_badge($started ? $r['an_stage'] : 'assigned') ?>
            <?php if (!$started): ?><span class="lbl">not started</span><?php endif; ?>
            <?php if ($started && $r['owner_name'] && $r['officer_name'] && $r['owner_name'] !== $r['officer_name']): ?>
              <span class="lbl">· officer of record <?= e($r['officer_name']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($log): ?>
            <details class="rec-hist">
              <summary><?= count($log) ?> movement<?= count($log) === 1 ? '' : 's' ?> on record</summary>
              <div class="rec-hist-body">
                <?php foreach ($log as $ev): ?>
                  <div class="rec-hist-item">
                    <?php if ($ev['action'] === 'reassign'): ?>
                      <strong><?= e($ev['from_name'] ?? '—') ?></strong> &rarr; <strong><?= e($ev['to_name'] ?? '—') ?></strong>
                    <?php else: ?>
                      Allocated to <strong><?= e($ev['to_name'] ?? '—') ?></strong>
                    <?php endif; ?>
                    <span class="lbl">&middot; <?= e($ev['by_name'] ?? '—') ?> &middot; <?= e(date('d M Y H:i', strtotime($ev['ts_create']))) ?></span>
                    <?php if (trim((string) $ev['reason']) !== ''): ?>
                      <div class="rec-hist-reason">&ldquo;<?= e($ev['reason']) ?>&rdquo;</div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'new'): ?>
          <form method="post" action="bid_registry_allocate.php" class="rec-actions">
            <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="from" value="allocations">
            <div class="fld">
              <label>Assign to officer</label>
              <select name="officer_id" class="form-select form-select-sm" required <?= $officers ? '' : 'disabled' ?>>
                <option value="">— select —</option>
                <?php foreach ($officers as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['full_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <?php if ($canPrio): ?>
              <div class="fld">
                <label>Priority</label>
                <select name="importance" class="form-select form-select-sm">
                  <?php foreach (['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $pk => $pl): ?>
                    <option value="<?= $pk ?>" <?= $r['importance'] === $pk ? 'selected' : '' ?>><?= $pl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div class="fld fld--wide">
              <label>Instruction <span class="text-muted">(optional)</span></label>
              <input type="text" name="comment" class="form-control form-control-sm" placeholder="e.g. lead the prior-review team">
            </div>
            <button class="btn btn-success btn-sm" <?= $officers ? '' : 'disabled' ?>><i class="bi bi-send me-1"></i>Allocate</button>
            <?= es_submission_button((int) $r['id'], $r['serial_no'], 'View', 'btn btn-link btn-sm text-decoration-none px-1') ?>
          </form>
        <?php elseif (!$canReassign): ?>
          <div class="rec-actions">
            <span class="text-muted small">You don't have permission to reassign.</span>
            <?= es_submission_button((int) $r['id'], $r['serial_no'], 'View', 'btn btn-link btn-sm text-decoration-none px-1') ?>
          </div>
        <?php else: ?>
          <form method="post" action="bid_reassign.php" class="rec-actions">
            <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div class="fld">
              <label>Reassign to</label>
              <select name="officer_id" class="form-select form-select-sm" required>
                <option value="">— select —</option>
                <?php foreach ($officers as $o): if ((int) $o['id'] === (int) $r['assigned_officer_id']) continue; ?>
                  <option value="<?= (int) $o['id'] ?>"><?= e($o['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($canPrio): ?>
              <div class="fld">
                <label>Priority</label>
                <select name="importance" class="form-select form-select-sm">
                  <?php foreach (['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $pk => $pl): ?>
                    <option value="<?= $pk ?>" <?= $r['importance'] === $pk ? 'selected' : '' ?>><?= $pl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div class="fld fld--wide">
              <label>Reason <span class="text-muted">(required)</span></label>
              <input type="text" name="reason" class="form-control form-control-sm" required placeholder="Why is it moving?">
            </div>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-shuffle me-1"></i>Reassign</button>
            <?= es_submission_button((int) $r['id'], $r['serial_no'], 'View', 'btn btn-link btn-sm text-decoration-none px-1') ?>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query($carry + ['page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
