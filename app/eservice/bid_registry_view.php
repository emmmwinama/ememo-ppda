<?php
/** Submission — single entry, with the registry-check / allocation actions. */
require __DIR__ . '/inc/layout.php';
es_require_role('registry', 'pde', 'allocator', 'officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

$id = (int) ($_GET['id'] ?? 0);
$r  = db_one(
    "SELECT r.*, p.name AS pde_name, m.name AS method_name, t.name AS rtype_name,
            u.full_name AS officer_name, rb.full_name AS received_name,
            ck.full_name AS checked_name, al.full_name AS allocated_name
       FROM es_bid_registry r
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN es_review_type t ON t.id = r.review_type_id
       LEFT JOIN users u  ON u.id  = r.assigned_officer_id
       LEFT JOIN users rb ON rb.id = r.received_by
       LEFT JOIN users ck ON ck.id = r.registry_checked_by
       LEFT JOIN users al ON al.id = r.allocated_by
      WHERE r.id = ?",
    'i', [$id]
);
if (!$r) { http_response_code(404); es_layout_head('Not found'); echo '<div class="f-state is-error"><i class="bi bi-question-circle"></i><p>Submission not found.</p></div>'; es_layout_foot(); exit; }

$pdeScope = es_pde_scope();
if ($pdeScope !== null && (int) $r['pde_id'] !== (int) $pdeScope) {
    http_response_code(403); es_layout_head('Not allowed');
    echo '<div class="f-state is-error"><i class="bi bi-shield-lock"></i><p>This submission belongs to another entity.</p></div>';
    es_layout_foot(); exit;
}
$isPde = $pdeScope !== null;

$analysis = db_one(
    "SELECT a.id, a.stage, a.current_owner_id, co.full_name AS owner_name
       FROM es_bid_analysis a LEFT JOIN users co ON co.id = a.current_owner_id
      WHERE a.registry_id = ? ORDER BY a.id DESC LIMIT 1",
    'i', [$id]
);
$allocLog = es_alloc_log($id);
$atts     = db_all("SELECT id, original_name, file_path, ts_create, uploaded_by,
                           (SELECT full_name FROM users WHERE id = es_bid_attachment.uploaded_by) AS by_name
                      FROM es_bid_attachment WHERE registry_id = ? ORDER BY id", 'i', [$id]);

$attList = function (array $atts): string {
    if (!$atts) return '<p class="text-muted small mb-0"><i class="bi bi-paperclip me-1"></i>No documents attached yet.</p>';
    $h = '<div class="d-flex flex-column gap-1">';
    foreach ($atts as $a) {
        $h .= '<div class="d-flex align-items-center gap-2" style="font-size:.88rem;">'
            . '<i class="bi bi-file-earmark-text text-muted"></i>'
            . '<a href="bid_attachment.php?id=' . (int) $a['id'] . '" target="_blank">' . e($a['original_name'] ?: basename($a['file_path'])) . '</a>'
            . ($a['by_name'] ? '<span class="text-muted small">· ' . e($a['by_name']) . '</span>' : '')
            . '</div>';
    }
    return $h . '</div>';
};

$docChecklist = function (?string $csv, bool $showMissing = false): string {
    $map = es_accompanying_docs();
    $set = array_filter(explode(',', (string) $csv));
    if (!$set && !$showMissing) return '<span class="text-muted">— none declared —</span>';
    $h = '<div class="d-flex flex-wrap gap-1">';
    foreach ($map as $k => $lbl) {
        $on = in_array($k, $set, true);
        if (!$on && !$showMissing) continue;
        $h .= '<span class="pill ' . ($on ? 't-green' : 't-neutral') . '"><i class="bi bi-' . ($on ? 'check2' : 'dash') . ' me-1"></i>' . e($lbl) . '</span>';
    }
    return $h . '</div>';
};

$canRegCheck = es_can('submission.registry_check') && $r['status'] === 'pending_registry';
$canAllocate = es_can('submission.allocate') && $r['status'] === 'pending_allocation';
$canResubmit = $isPde && $r['status'] === 'returned_to_pde';
$canWithdraw = $isPde && ($pdeScope ?? 0) > 0 && $r['status'] === 'pending_registry';
$canEditReg  = es_can('submission.registry_check') && in_array($r['status'], ['pending_registry', 'pending_allocation'], true);
$canStart    = es_can('analysis.start') && !$analysis && $r['status'] === 'assigned'
               && ((int) $r['assigned_officer_id'] === $ES_UID || es_can('submission.allocate'));

$officers = ($canAllocate)
    ? db_all("SELECT u.id, u.full_name FROM es_user_role x JOIN users u ON u.id = x.user_id
              WHERE x.role = 'officer' GROUP BY u.id, u.full_name ORDER BY u.full_name")
    : [];

$statusTint = [
    'pending_registry' => 't-amber', 'returned_to_pde' => 't-rose', 'pending_allocation' => 't-sky',
    'assigned' => 't-sky', 'in_analysis' => 't-amber', 'completed' => 't-green',
    'closed' => 't-slate', 'withdrawn' => 't-rose',
];
$statusLabel = [
    'pending_registry' => 'Registry check', 'returned_to_pde' => 'Returned to PDE',
    'pending_allocation' => 'Awaiting allocation', 'assigned' => 'Allocated',
    'in_analysis' => 'In analysis', 'completed' => 'Completed', 'closed' => 'Closed', 'withdrawn' => 'Withdrawn',
];

es_layout_head('Submission · ' . $r['serial_no'], 'registry');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title"><?= e($r['serial_no']) ?>
      <span style="vertical-align:middle;"><?= es_status_badge($r['status']) ?></span>
    </h1>
    <p class="f-subtitle"><?= e($r['subject']) ?>
      <span class="text-muted">· <?= count($atts) ?> doc<?= count($atts) === 1 ? '' : 's' ?>
      · logged <?= e(es_ago($r['ts_create'])) ?></span>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="bid_registry.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <?php if ($canResubmit || $canEditReg || $canWithdraw): ?>
      <button type="button" class="btn btn-outline-secondary btn-sm"
              data-drawer="bid_registry_form.php?id=<?= $id ?>&amp;partial=1" data-drawer-title="Edit submission">
        <i class="bi bi-pencil me-1"></i><?= $canResubmit ? 'Edit &amp; resubmit' : 'Edit' ?>
      </button>
    <?php endif; ?>
    <?php if ($canWithdraw): ?>
      <form method="post" action="bid_registry_save.php" class="d-inline"
            onsubmit="return confirm('Withdraw this submission? The PPDA registry will no longer process it.');">
        <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
        <input type="hidden" name="op" value="withdraw">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Withdraw</button>
      </form>
    <?php endif; ?>
    <?php if ($analysis && !$isPde): ?>
      <a href="bid_analysis_view.php?id=<?= (int) $analysis['id'] ?>" class="btn btn-success btn-sm">Open analysis</a>
    <?php elseif ($canStart): ?>
      <form method="post" action="bid_analysis_start.php" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
        <input type="hidden" name="registry_id" value="<?= $id ?>">
        <button class="btn btn-success btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Start analysis</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="f-panel" style="padding:1.3rem;">
      <div class="row g-3" style="font-size:.9rem;">
        <?php
        $field = function ($label, $val, $raw = false) {
            echo '<div class="col-md-4"><div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">' . e($label) . '</div><div class="fw-semibold">' . ($raw ? $val : e($val ?: '—')) . '</div></div>';
        };
        $field('Source', es_source_badge($r['origin']), true);
        $field('Priority', es_priority_badge($r['importance']), true);
        $field('Channel', ucfirst($r['channel']));
        if (!$isPde) $field('PDE', $r['pde_name']);
        $field('PDE ref code', $r['ref_code_pde']);
        $field('Tender number', $r['tender_number']);
        $field('Procurement method', $r['method_name']);
        $field('Review type', $r['rtype_name']);
        $field('Submission signed', $r['submission_signed'] ? 'Yes' : 'No');
        $field('Date of signing', $r['date_of_signing']);
        $field('In procurement plan', $r['in_procurement_plan'] ? 'Yes' : 'No');
        $field('Entered by', $r['received_name']);
        $field('Logged', date('d M Y H:i', strtotime($r['ts_create'])));
        $field('Allocated officer', $r['officer_name']);
        ?>
        <div class="col-12">
          <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Accompanied documents</div>
          <div class="mt-1"><?= $docChecklist($r['accompanied_documents']) ?></div>
        </div>
        <?php if (trim((string) $r['submission_details']) !== ''): ?>
          <div class="col-12">
            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Submission details</div>
            <div style="white-space:pre-wrap;"><?= e($r['submission_details']) ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="f-panel mt-3" style="padding:1.3rem;">
      <div class="fw-bold mb-2"><i class="bi bi-paperclip me-1"></i>Documents<?= $atts ? ' (' . count($atts) . ')' : '' ?></div>
      <?= $attList($atts) ?>
      <?php if (($canResubmit || $canEditReg) && !$atts): ?>
        <p class="text-muted small mb-0 mt-2">Add the bid pack from <em>Edit</em>.</p>
      <?php endif; ?>
    </div>

    <?php
    $showProgress = $r['registry_checked_at'] || $r['registry_comment'] || $r['allocated_at'] || $allocLog;
    $withStages   = ['assigned', 'in_analysis', 'completed'];
    if ($showProgress): ?>
      <div class="f-panel mt-3" style="padding:1.3rem; font-size:.88rem;">
        <div class="fw-bold mb-2"><i class="bi bi-clock-history me-1"></i>Progress</div>

        <?php if (in_array($r['status'], $withStages, true)): ?>
          <div class="mb-3 d-flex align-items-center gap-2 flex-wrap" style="padding:.55rem .7rem; background:var(--bg); border-radius:9px;">
            <span class="text-muted">Currently with</span>
            <span class="fw-semibold"><i class="bi bi-person-fill me-1"></i><?= e($analysis['owner_name'] ?? $r['officer_name'] ?? '—') ?></span>
            <?php if ($analysis): ?>
              <?= es_status_badge($analysis['stage']) ?>
            <?php else: ?>
              <?= es_status_badge('assigned') ?><span class="text-muted">analysis not started</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($r['registry_checked_at']): ?>
          <div class="mb-2"><span class="pill t-neutral">Registry check</span>
            <?= e($r['checked_name'] ?? 'registry') ?> · <?= e(date('d M Y H:i', strtotime($r['registry_checked_at']))) ?>
            <?php if ($r['status'] === 'returned_to_pde'): ?><span class="pill t-rose ms-1">returned</span><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (trim((string) $r['registry_comment']) !== ''): ?>
          <div class="mb-2" style="white-space:pre-wrap;"><strong>Registry note:</strong> <?= e($r['registry_comment']) ?></div>
        <?php endif; ?>

        <?php foreach ($allocLog as $e): ?>
          <div class="mb-2">
            <?php if ($e['action'] === 'reassign'): ?>
              <span class="pill t-sky">Reassigned</span>
              <strong><?= e($e['from_name'] ?? '—') ?></strong> → <strong><?= e($e['to_name'] ?? '—') ?></strong>
            <?php else: ?>
              <span class="pill t-neutral">Allocated</span>
              to <strong><?= e($e['to_name'] ?? '—') ?></strong>
            <?php endif; ?>
            by <?= e($e['by_name'] ?? '—') ?> · <?= e(date('d M Y H:i', strtotime($e['ts_create']))) ?>
            <?php if (trim((string) $e['reason']) !== ''): ?>
              <div class="text-muted" style="white-space:pre-wrap;">“<?= e($e['reason']) ?>”</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if (!$allocLog && $r['allocated_at']): ?>
          <div><span class="pill t-neutral">Allocated</span>
            by <?= e($r['allocated_name'] ?? '—') ?> to <?= e($r['officer_name'] ?? '—') ?> · <?= e(date('d M Y H:i', strtotime($r['allocated_at']))) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- action rail -->
  <div class="col-lg-5" id="act">
    <?php if ($canRegCheck): ?>
      <div class="f-panel" style="padding:1.3rem;">
        <div class="fw-bold mb-2"><i class="bi bi-check2-square me-1"></i>Registry check</div>
        <p class="text-muted small">Confirm the pack has all required documents and details, then send it for allocation — or return it to the PDE.</p>
        <div class="mb-3" style="padding:.7rem .8rem; background:var(--bg); border-radius:9px;">
          <div class="small fw-semibold mb-1">Declared in the pack</div>
          <?= $docChecklist($r['accompanied_documents'], true) ?>
          <div class="small fw-semibold mt-3 mb-1">Uploaded files</div>
          <?= $attList($atts) ?>
        </div>
        <form method="post" action="bid_registry_check.php">
          <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <textarea name="comment" class="form-control form-control-sm mb-2" rows="3" placeholder="Notes (required when returning)"></textarea>
          <div class="d-flex gap-2">
            <button name="action" value="approve" class="btn btn-success btn-sm flex-grow-1"><i class="bi bi-check-lg me-1"></i>Approve &amp; send for allocation</button>
            <button name="action" value="return" class="btn btn-outline-danger btn-sm">Return to PDE</button>
          </div>
        </form>
      </div>
    <?php elseif ($canAllocate): ?>
      <div class="f-panel" style="padding:1.3rem;">
        <div class="fw-bold mb-2"><i class="bi bi-diagram-3 me-1"></i>Allocate</div>
        <p class="text-muted small">Assign this submission to a technical officer.</p>
        <form method="post" action="bid_registry_allocate.php">
          <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <label class="form-label small fw-semibold">Officer</label>
          <select name="officer_id" class="form-select form-select-sm mb-2" required>
            <option value="">— select —</option>
            <?php foreach ($officers as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['full_name']) ?></option><?php endforeach; ?>
          </select>
          <?php if (!$officers): ?><p class="text-danger small">No users hold the “officer” e-Services role yet.</p><?php endif; ?>
          <textarea name="comment" class="form-control form-control-sm mb-2" rows="2" placeholder="Instruction (optional)"></textarea>
          <button class="btn btn-success btn-sm w-100"><i class="bi bi-send me-1"></i>Allocate</button>
        </form>
      </div>
    <?php elseif ($canResubmit): ?>
      <div class="f-panel" style="padding:1.3rem;">
        <div class="fw-bold mb-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Returned by the registry</div>
        <p class="mb-2" style="white-space:pre-wrap; font-size:.9rem;"><?= e($r['registry_comment'] ?: 'The registry asked for changes.') ?></p>
        <button type="button" class="btn btn-success btn-sm"
                data-drawer="bid_registry_form.php?id=<?= $id ?>&amp;partial=1" data-drawer-title="Edit &amp; resubmit">
          <i class="bi bi-pencil-square me-1"></i>Edit &amp; resubmit
        </button>
      </div>
    <?php else: ?>
      <div class="f-panel" style="padding:1.3rem; font-size:.88rem;">
        <div class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>Status</div>
        <p class="text-muted mb-0"><?= e($statusLabel[$r['status']] ?? $r['status']) ?><?php
          if ($r['status'] === 'pending_registry') echo ' — with the PPDA registry.';
          elseif ($r['status'] === 'pending_allocation') echo ' — with the DG for allocation.';
          elseif (in_array($r['status'], ['assigned', 'in_analysis'], true)) echo ' — with ' . e($r['officer_name'] ?? 'the technical officer') . '.';
        ?></p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php es_layout_foot(); ?>
