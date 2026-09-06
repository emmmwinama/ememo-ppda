<?php
/** Read-only submission summary, shown in the slide-over on the bid-analysis list.
 *  Any reviewer may look; there are no actions here. ?partial=1 → fragment only. */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/ui.php';   // es_status_badge / es_source_badge / es_priority_badge

$partial = isset($_GET['partial']);
if (!$partial) require_once __DIR__ . '/inc/layout.php';

if (!es_has_role('officer', 'supervisor', 'director', 'dg', 'board', 'registry', 'allocator')) {
    http_response_code(403);
    exit($partial ? '<div class="f-state is-error" style="padding:2rem;"><p>Not allowed.</p></div>' : 'Not allowed.');
}

global $conn;

$id = (int) ($_GET['id'] ?? 0);
$r  = db_one(
    "SELECT r.*, p.name AS pde_name, m.name AS method_name, t.name AS rtype_name,
            rb.full_name AS received_name, ck.full_name AS checked_name,
            of.full_name AS officer_name
       FROM es_bid_registry r
       LEFT JOIN es_pde p  ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN es_review_type t ON t.id = r.review_type_id
       LEFT JOIN users rb ON rb.id = r.received_by
       LEFT JOIN users ck ON ck.id = r.registry_checked_by
       LEFT JOIN users of ON of.id = r.assigned_officer_id
      WHERE r.id = ?",
    'i', [$id]
);
if (!$r) {
    http_response_code(404);
    exit($partial ? '<div class="f-state is-error" style="padding:2rem;"><p>Submission not found.</p></div>' : 'Not found.');
}

$atts = db_all(
    "SELECT id, original_name, file_path FROM es_bid_attachment WHERE registry_id = ? ORDER BY id",
    'i', [$id]
);

$declared = array_filter(explode(',', (string) $r['accompanied_documents']));
$docMap   = es_accompanying_docs();

if (!$partial) es_layout_head('Submission · ' . $r['serial_no'], 'analysis');
?>

<div class="peek">
  <?php if (!$partial): ?><div class="f-head"><h1 class="f-title"><?= e($r['serial_no']) ?></h1></div><?php endif; ?>

  <div class="peek-title"><?= e($r['subject']) ?></div>
  <div class="peek-sub">
    <?= es_status_badge($r['status']) ?>
    <?= es_source_badge($r['origin']) ?>
    <?= es_priority_badge($r['importance']) ?>
  </div>

  <dl class="peek-grid">
    <dt>PDE</dt><dd><?= e($r['pde_name'] ?? '—') ?></dd>
    <dt>Serial</dt><dd class="font-monospace"><?= e($r['serial_no']) ?></dd>
    <dt>Tender no.</dt><dd><?= e($r['tender_number'] ?: '—') ?></dd>
    <dt>PDE ref</dt><dd><?= e($r['ref_code_pde'] ?: '—') ?></dd>
    <dt>Method</dt><dd><?= e($r['method_name'] ?: '—') ?></dd>
    <dt>Review type</dt><dd><?= e($r['rtype_name'] ?: '—') ?></dd>
    <dt>Channel</dt><dd><?= e(ucfirst($r['channel'])) ?></dd>
    <dt>Signed</dt><dd><?= $r['submission_signed'] ? 'Yes' . ($r['date_of_signing'] ? ' · ' . e($r['date_of_signing']) : '') : 'No' ?></dd>
    <dt>In proc. plan</dt><dd><?= $r['in_procurement_plan'] ? 'Yes' : 'No' ?></dd>
    <dt>Submitted</dt><dd><?= e(date('d M Y H:i', strtotime($r['ts_create']))) ?> · <?= e(es_ago($r['ts_create'])) ?></dd>
    <?php if ($r['received_name']): ?><dt>Entered by</dt><dd><?= e($r['received_name']) ?></dd><?php endif; ?>
    <?php if ($r['registry_checked_at']): ?><dt>Registry check</dt><dd><?= e($r['checked_name'] ?? 'registry') ?> · <?= e(es_ago($r['registry_checked_at'])) ?></dd><?php endif; ?>
    <?php if ($r['officer_name']): ?><dt>Officer</dt><dd><?= e($r['officer_name']) ?></dd><?php endif; ?>
  </dl>

  <div class="peek-sec">Accompanied documents</div>
  <?php if ($declared): ?>
    <div class="d-flex flex-wrap gap-1">
      <?php foreach ($docMap as $k => $lbl): if (!in_array($k, $declared, true)) continue; ?>
        <span class="pill t-green"><i class="bi bi-check2 me-1"></i><?= e($lbl) ?></span>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-muted small mb-0">None declared.</p>
  <?php endif; ?>

  <div class="peek-sec">Documents (<?= count($atts) ?>)</div>
  <?php if ($atts): ?>
    <div class="d-flex flex-column gap-1">
      <?php foreach ($atts as $a): ?>
        <a href="bid_attachment.php?id=<?= (int) $a['id'] ?>" target="_blank" style="font-size:.88rem;">
          <i class="bi bi-file-earmark-text me-1"></i><?= e($a['original_name'] ?: basename($a['file_path'])) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-muted small mb-0">No files attached.</p>
  <?php endif; ?>

  <?php if (trim((string) $r['submission_details']) !== ''): ?>
    <div class="peek-sec">Submission details</div>
    <div style="white-space:pre-wrap; font-size:.88rem;"><?= e($r['submission_details']) ?></div>
  <?php endif; ?>

  <?php if (trim((string) $r['registry_comment']) !== ''): ?>
    <div class="peek-sec">Registry note</div>
    <div style="white-space:pre-wrap; font-size:.88rem;"><?= e($r['registry_comment']) ?></div>
  <?php endif; ?>

  <a class="btn btn-outline-secondary btn-sm mt-3" href="bid_registry_view.php?id=<?= (int) $r['id'] ?>">
    <i class="bi bi-box-arrow-up-right me-1"></i>Open full submission page
  </a>
</div>

<style>
  .peek { padding: 1.4rem; }
  .peek-title { font-weight: 700; font-size: 1rem; color: var(--text); }
  .peek-sub { display: flex; gap: .4rem; flex-wrap: wrap; margin: .55rem 0 1rem; }
  .peek-grid { display: grid; grid-template-columns: max-content 1fr; gap: .35rem .9rem; margin: 0 0 .5rem; font-size: .85rem; }
  .peek-grid dt { color: var(--muted); }
  .peek-grid dd { margin: 0; color: var(--text); }
  .peek-sec { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted);
    margin: 1.15rem 0 .5rem; padding-top: .8rem; border-top: 1px solid var(--border); }
</style>

<?php if (!$partial) es_layout_foot(); ?>
