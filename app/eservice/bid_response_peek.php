<?php
/** PDE response letter — shown in the right-side slide-over from a review list.
 *  Content is editable and can be published here by anyone with response.write.
 *  ?partial=1 → fragment only. */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/ui.php';

$partial = isset($_GET['partial']);
if (!$partial) require_once __DIR__ . '/inc/layout.php';

if (!es_has_role('officer', 'supervisor', 'director', 'dg', 'board', 'registry')) {
    http_response_code(403);
    exit($partial ? '<div class="f-state is-error" style="padding:2rem;"><p>Not allowed.</p></div>' : 'Not allowed.');
}

global $conn;

$aid  = (int) ($_GET['analysis_id'] ?? 0);
$from = in_array($_GET['from'] ?? '', ['dgreview', 'responses'], true) ? $_GET['from'] : 'dgreview';
$btab = preg_replace('/[^a-z_]/', '', (string) ($_GET['tab'] ?? $_GET['dg_tab'] ?? 'inbox')) ?: 'inbox';

$a = db_one(
    "SELECT a.id, a.stage, r.serial_no, r.subject, p.name AS pde_name
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
      WHERE a.id = ?",
    'i', [$aid]
);
if (!$a) {
    http_response_code(404);
    exit($partial ? '<div class="f-state is-error" style="padding:2rem;"><p>Analysis not found.</p></div>' : 'Not found.');
}

$resp   = db_one("SELECT * FROM es_pde_response WHERE analysis_id = ? ORDER BY id DESC LIMIT 1", 'i', [$aid]);
$canWrite   = es_can('response.write');
$canPublish = es_can('response.publish');
$published = !empty($resp['published']);

if (!$partial) es_layout_head('Response letter · ' . $a['serial_no'], $from === 'responses' ? 'responses' : 'rev_dg');
?>

<div class="peek">
  <?php if (!$partial): ?><div class="f-head"><h1 class="f-title"><?= e($a['serial_no']) ?> — response letter</h1></div><?php endif; ?>

  <div class="peek-title"><?= e($a['subject']) ?></div>
  <div class="peek-sub">
    <span class="font-monospace"><?= e($a['serial_no']) ?></span>
    <?php if ($a['pde_name']): ?><span>·</span><span><?= e($a['pde_name']) ?></span><?php endif; ?>
    <?php if (!$resp): ?><span class="pill t-amber">not started</span>
    <?php elseif ($published): ?><span class="pill t-green">published</span>
    <?php else: ?><span class="pill t-neutral">draft</span><?php endif; ?>
  </div>

  <?php if ($resp && $published): ?>
    <div class="peek-sec">Published <?= e(date('d M Y', strtotime($resp['published_at']))) ?></div>
    <div class="d-flex gap-2 mb-2">
      <a href="response_letter.php?id=<?= (int) $resp['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>
      <a href="response_letter.php?id=<?= (int) $resp['id'] ?>&amp;format=pdf" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
    </div>
  <?php endif; ?>

  <?php if ($canWrite): ?>
    <div class="peek-sec">Letter content</div>
    <form method="post" action="pde_response_save.php">
      <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
      <input type="hidden" name="from" value="<?= e($from) ?>">
      <?php if ($from === 'dgreview'): ?><input type="hidden" name="dg_tab" value="<?= e($btab) ?>"><?php endif; ?>
      <input type="hidden" name="analysis_id" value="<?= (int) $a['id'] ?>">
      <textarea name="body" class="form-control form-control-sm" rows="14"
                placeholder="Findings and PPDA's decision to the procuring entity…"><?= e($resp['body'] ?? '') ?></textarea>
      <div class="d-flex gap-2 justify-content-end mt-2">
        <button name="action" value="draft" class="btn btn-sm btn-outline-secondary">Save draft</button>
        <?php if ($canPublish): ?>
          <button name="action" value="publish" class="btn btn-sm btn-success"><i class="bi bi-send me-1"></i><?= $published ? 'Re-publish' : 'Publish' ?></button>
        <?php endif; ?>
      </div>
    </form>
  <?php elseif ($resp): ?>
    <div class="peek-sec">Letter content</div>
    <div style="white-space:pre-wrap; font-size:.88rem; padding:.7rem .85rem; background:var(--bg); border:1px solid var(--border); border-radius:8px;"><?= e($resp['body']) ?></div>
  <?php else: ?>
    <p class="text-muted small mb-0">No response letter has been drafted yet.</p>
  <?php endif; ?>

  <a class="btn btn-outline-secondary btn-sm mt-3" href="bid_analysis_view.php?id=<?= (int) $a['id'] ?>">
    <i class="bi bi-box-arrow-up-right me-1"></i>Open the analysis
  </a>
</div>

<style>
  .peek { padding: 1.4rem; }
  .peek-title { font-weight: 700; font-size: 1rem; color: var(--text); }
  .peek-sub { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; margin: .55rem 0 1rem; font-size: .85rem; color: var(--muted); }
  .peek-sec { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted);
    margin: 1.15rem 0 .5rem; padding-top: .8rem; border-top: 1px solid var(--border); }
</style>

<?php if (!$partial) es_layout_foot(); ?>
