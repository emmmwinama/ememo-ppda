<?php
/** Submission form — add / edit. Drawer fragment when ?partial=1.
 *  Two modes: a PDE user files their own submission; the registry logs one on
 *  behalf of a PDE. Allocation is NOT done here. */
require __DIR__ . '/inc/bootstrap.php';

$partial = isset($_GET['partial']);
if (!$partial) require_once __DIR__ . '/inc/layout.php';
es_require_role('registry', 'pde');

global $conn, $ES_UID;

$pdeScope = es_pde_scope();
$isPde    = $pdeScope !== null && $pdeScope > 0;
if ($pdeScope === -1) { http_response_code(403); exit('Your PDE account is not linked to an entity yet.'); }

$id  = (int) ($_GET['id'] ?? 0);
$row = $id ? db_one("SELECT * FROM es_bid_registry WHERE id = ?", 'i', [$id]) : null;

if ($id) {
    if (!$row) { http_response_code(404); exit($partial ? 'Not found.' : (es_layout_head('Not found') ?? '') . 'Not found.'); }
    // a PDE may only touch its own rows, and only before the registry check
    if ($isPde && ((int) $row['pde_id'] !== (int) $pdeScope || !in_array($row['status'], ['pending_registry', 'returned_to_pde'], true))) {
        http_response_code(403); exit('This submission can no longer be edited.');
    }
    if (!$isPde && !in_array($row['status'], ['pending_registry', 'pending_allocation', 'returned_to_pde'], true)) {
        http_response_code(403); exit('This submission can no longer be edited from here.');
    }
}

$pdes    = $isPde ? [] : db_all("SELECT id, name FROM es_pde WHERE active = 1 ORDER BY name");
$methods = db_all("SELECT id, name FROM es_procurement_method WHERE active = 1 ORDER BY name");
$rtypes  = db_all("SELECT id, name FROM es_review_type WHERE active = 1 ORDER BY name");
$myPde   = $isPde ? db_one("SELECT name FROM es_pde WHERE id = ?", 'i', [(int) $pdeScope]) : null;
$atts    = $id ? db_all("SELECT id, original_name, file_path FROM es_bid_attachment WHERE registry_id = ? ORDER BY id", 'i', [$id]) : [];

$v = fn(string $k, $d = '') => e($row[$k] ?? $d);
$submitLabel = $id ? 'Save changes' : ($isPde ? 'Submit to PPDA' : 'Log submission');

ob_start(); ?>
  <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="row g-3">
    <div class="col-12">
      <label class="form-label">Subject <span class="text-danger">*</span></label>
      <input type="text" name="subject" class="form-control" required value="<?= $v('subject') ?>" placeholder="e.g. Supply and delivery of ICT equipment">
    </div>

    <?php if ($isPde): ?>
      <div class="col-12">
        <label class="form-label">Procuring &amp; disposing entity</label>
        <input type="text" class="form-control" value="<?= e($myPde['name'] ?? ('PDE #' . $pdeScope)) ?>" readonly>
      </div>
    <?php else: ?>
      <div class="col-md-7">
        <label class="form-label">PDE <span class="text-danger">*</span></label>
        <select name="pde_id" class="form-select" required>
          <option value="">— select —</option>
          <?php foreach ($pdes as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= ((int) ($row['pde_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label">Channel</label>
        <select name="channel" class="form-select">
          <?php foreach (['physical' => 'Hard copy (registry scan)', 'email' => 'Email', 'portal' => 'Portal'] as $c => $lbl): ?>
            <option value="<?= $c ?>" <?= (($row['channel'] ?? 'physical') === $c) ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="col-md-7">
      <label class="form-label">Tender number</label>
      <input type="text" name="tender_number" class="form-control" value="<?= $v('tender_number') ?>">
    </div>
    <div class="col-md-5">
      <label class="form-label">PDE reference code</label>
      <input type="text" name="ref_code_pde" class="form-control" value="<?= $v('ref_code_pde') ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Procurement method</label>
      <select name="procurement_method_id" class="form-select">
        <option value="">— select —</option>
        <?php foreach ($methods as $m): ?>
          <option value="<?= (int) $m['id'] ?>" <?= ((int) ($row['procurement_method_id'] ?? 0) === (int) $m['id']) ? 'selected' : '' ?>><?= e($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Review type</label>
      <select name="review_type_id" class="form-select">
        <option value="">— select —</option>
        <?php foreach ($rtypes as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= ((int) ($row['review_type_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">Priority</label>
      <select name="importance" class="form-select">
        <?php foreach (['normal', 'high', 'urgent'] as $c): ?>
          <option value="<?= $c ?>" <?= (($row['importance'] ?? 'normal') === $c) ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Date of signing</label>
      <input type="date" name="date_of_signing" class="form-control" value="<?= $v('date_of_signing') ?>">
    </div>

    <div class="col-12">
      <div class="d-flex gap-4 flex-wrap py-1">
        <label class="d-flex align-items-center gap-2"><input type="checkbox" name="submission_signed" value="1" <?= !empty($row['submission_signed']) ? 'checked' : '' ?>> Submission letter signed</label>
        <label class="d-flex align-items-center gap-2"><input type="checkbox" name="in_procurement_plan" value="1" <?= !empty($row['in_procurement_plan']) ? 'checked' : '' ?>> In approved procurement plan</label>
      </div>
    </div>

    <?php $checkedDocs = array_filter(explode(',', (string) ($row['accompanied_documents'] ?? ''))); ?>
    <div class="col-12">
      <label class="form-label">Accompanied documents</label>
      <div class="row g-1">
        <?php foreach (es_accompanying_docs() as $k => $lbl): ?>
          <div class="col-md-6">
            <label class="d-flex align-items-center gap-2">
              <input type="checkbox" name="accompanied_documents[]" value="<?= e($k) ?>" <?= in_array($k, $checkedDocs, true) ? 'checked' : '' ?>>
              <?= e($lbl) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="text-muted small mb-0 mt-1">Tick each item included in this submission pack.</p>
    </div>

    <div class="col-12">
      <label class="form-label">Submission details / notes</label>
      <textarea name="submission_details" class="form-control" rows="3" placeholder="Anything the reviewer should know — scope, references, correspondence…"><?= $v('submission_details') ?></textarea>
    </div>

    <?php $needFile = $isPde && !$id;   // a PDE's first submission must carry the pack ?>
    <div class="col-12">
      <label class="form-label">Attach documents<?= $needFile ? ' <span class="text-danger">*</span>' : '' ?></label>
      <?php if ($atts): ?>
        <div class="d-flex flex-column gap-1 mb-2">
          <?php foreach ($atts as $a): ?>
            <label class="d-flex align-items-center gap-2 small">
              <input type="checkbox" name="remove_att[]" value="<?= (int) $a['id'] ?>">
              <i class="bi bi-paperclip text-muted"></i>
              <a href="bid_attachment.php?id=<?= (int) $a['id'] ?>" target="_blank"><?= e($a['original_name'] ?: basename($a['file_path'])) ?></a>
              <span class="text-muted">— tick to remove</span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <input type="file" name="docs[]" class="form-control" multiple
             accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip" <?= $needFile ? 'required' : '' ?>>
      <p class="text-muted small mb-0 mt-1">PDF, Word, Excel, images or ZIP. Up to 20&nbsp;MB per file.<?= $needFile ? ' At least one file is required.' : '' ?></p>
    </div>

    <?php if ($isPde): ?>
      <div class="col-12">
        <div class="pde-hint">
          <i class="bi bi-info-circle-fill"></i>
          <span>Once you submit, the PPDA registry checks the pack is complete. You'll see the status change to
            <strong>Under review</strong> when it is allocated to a technical officer, and you can download the
            PPDA response letter here when the review concludes. A submission can be edited or withdrawn only
            while it is still awaiting the registry check.</span>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php
$FIELDS = ob_get_clean();

if ($partial) { ?>
  <form method="post" action="bid_registry_save.php" class="es-drawer-form" enctype="multipart/form-data">
    <div class="es-drawer-fields"><?= $FIELDS ?></div>
    <div class="es-drawer-foot">
      <button type="button" class="btn btn-outline-secondary" data-drawer-close>Cancel</button>
      <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= e($submitLabel) ?></button>
    </div>
  </form>
<?php exit; }

es_layout_head($id ? 'Edit submission' : 'New submission', 'registry');
?>
<div class="f-head"><h1 class="f-title"><?= $id ? 'Edit submission' : 'New submission' ?></h1>
  <p class="f-subtitle"><?= $id ? e($row['serial_no']) : ($isPde ? 'File a submission for your entity' : 'Log a submission received from a PDE') ?></p>
</div>
<form method="post" action="bid_registry_save.php" class="f-card" style="gap:1.1rem; max-width:820px;" enctype="multipart/form-data">
  <?= $FIELDS ?>
  <div class="d-flex justify-content-end gap-2">
    <a href="bid_registry.php" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= e($submitLabel) ?></button>
  </div>
</form>
<?php es_layout_foot(); ?>
