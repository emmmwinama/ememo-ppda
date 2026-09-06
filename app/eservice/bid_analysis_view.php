<?php
/** Bid analysis — the review workspace: checklist + workflow + trail + thread. */
require __DIR__ . '/inc/layout.php';
es_require_role('officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

$id = (int) ($_GET['id'] ?? 0);
$a  = db_one(
    "SELECT a.*, r.serial_no, r.subject, r.tender_number, r.id AS registry_id,
            p.name AS pde_name, m.name AS method_name,
            o.full_name AS officer_name, c.full_name AS owner_name
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN users o ON o.id = a.officer_id
       LEFT JOIN users c ON c.id = a.current_owner_id
      WHERE a.id = ?",
    'i', [$id]
);
if (!$a) { http_response_code(404); es_layout_head('Not found'); echo '<div class="f-state is-error"><i class="bi bi-question-circle"></i><p>Analysis not found.</p></div>'; es_layout_foot(); exit; }

$isOwner  = ((int) $a['current_owner_id'] === $ES_UID);
$editable = $isOwner && es_can('analysis.evaluate') && in_array($a['stage'], ['draft', 'returned'], true);

$trail = db_all(
    "SELECT t.*, f.full_name AS from_name, u.full_name AS to_name
       FROM es_bid_routing t
       LEFT JOIN users f ON f.id = t.from_user_id
       LEFT JOIN users u ON u.id = t.to_user_id
      WHERE t.analysis_id = ? ORDER BY t.id ASC",
    'i', [$id]
);
$messages = db_all(
    "SELECT g.body, g.ts_create, u.full_name
       FROM es_bid_message g LEFT JOIN users u ON u.id = g.user_id
      WHERE g.analysis_id = ? ORDER BY g.id ASC",
    'i', [$id]
);
$bidders = db_all("SELECT * FROM es_bid_bidder WHERE analysis_id = ? ORDER BY sort_order, bidder_number", 'i', [$id]);

// PDE response letter
$response      = db_one("SELECT * FROM es_pde_response WHERE analysis_id = ? ORDER BY id DESC LIMIT 1", 'i', [$id]);
$canWriteLetter = es_can('response.write')
    && (in_array($a['stage'], ['dg_review', 'approved', 'rejected'], true) || $response);

// Eligible recipients for each forward step
function es_users_with_role(string $role): array {
    global $conn;
    return db_all("SELECT u.id, u.full_name FROM es_user_role r JOIN users u ON u.id = r.user_id
                   WHERE r.role = ? GROUP BY u.id, u.full_name ORDER BY u.full_name", 's', [$role]);
}

// Workflow actions available now
$actions = [];
if ($isOwner) {
    if (in_array($a['stage'], ['draft', 'returned'], true) && es_has_role('officer')) {
        $actions[] = ['key' => 'submit', 'label' => 'Submit for supervisor review', 'to_role' => 'supervisor', 'btn' => 'success'];
    } elseif ($a['stage'] === 'supervisor_review' && es_has_role('supervisor')) {
        $actions[] = ['key' => 'endorse_director', 'label' => 'Endorse to director', 'to_role' => 'director', 'btn' => 'success'];
        $actions[] = ['key' => 'return_officer',   'label' => 'Return to officer', 'to_role' => null, 'btn' => 'outline-danger'];
    } elseif ($a['stage'] === 'director_review' && es_has_role('director')) {
        $actions[] = ['key' => 'endorse_dg',     'label' => 'Endorse to DG', 'to_role' => 'dg', 'btn' => 'success'];
        $actions[] = ['key' => 'return_supervisor', 'label' => 'Return to supervisor', 'to_role' => null, 'btn' => 'outline-danger'];
    } elseif ($a['stage'] === 'dg_review' && es_has_role('dg')) {
        $actions[] = ['key' => 'approve', 'label' => 'Approve', 'to_role' => null, 'btn' => 'success'];
        $actions[] = ['key' => 'reject',  'label' => 'Reject',  'to_role' => null, 'btn' => 'danger'];
        $actions[] = ['key' => 'return_director', 'label' => 'Return to director', 'to_role' => null, 'btn' => 'outline-danger'];
    }
}

$stageTint = [
    'draft' => 't-neutral', 'returned' => 't-rose', 'supervisor_review' => 't-amber',
    'director_review' => 't-amber', 'dg_review' => 't-sky', 'board_review' => 't-sky',
    'approved' => 't-green', 'rejected' => 't-rose',
];
$cb = fn($k) => !empty($a[$k]) ? 'checked' : '';
$checks = [
    'approved_proc_plan' => 'Approved procurement plan in place',
    'approved_workplan' => 'Approved annual work plan',
    'preferences_applied' => 'Preference / reservation scheme applied',
    'publication_done' => 'Bid notice published',
    'bid_opening_minutes_signed' => 'Bid opening minutes signed',
    'evaluation_report_signed' => 'Evaluation report signed',
    'ipdc_minutes_signed' => 'IPDC minutes signed',
    'all_bids_enclosed' => 'All bids enclosed',
    'original_bid_enclosed' => 'Original bid enclosed',
    'bids_still_valid' => 'Bids still valid',
];

es_layout_head('Analysis · ' . $a['serial_no'], 'analysis');
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title"><?= e($a['serial_no']) ?> <span style="vertical-align:middle;"><?= es_status_badge($a["stage"]) ?></span></h1>
    <p class="f-subtitle"><?= e($a['subject']) ?> · with <?= e($a['owner_name'] ?? '—') ?></p>
  </div>
  <a href="bid_registry_view.php?id=<?= (int) $a['registry_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Registry entry</a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <!-- Compliance checklist -->
    <form method="post" action="bid_analysis_save.php" class="f-panel" style="padding:1.15rem;">
      <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Compliance checklist</div>
      <div class="row g-2">
        <?php foreach ($checks as $k => $lbl): ?>
          <div class="col-md-6">
            <label class="d-flex align-items-center gap-2" style="font-size:.85rem;">
              <input type="checkbox" name="<?= $k ?>" value="1" <?= $cb($k) ?> <?= $editable ? '' : 'disabled' ?>>
              <?= e($lbl) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="row g-2 mt-1">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Date of publication</label>
          <input type="date" name="date_of_publication" class="form-control form-control-sm" value="<?= e($a['date_of_publication']) ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Bid validity (days)</label>
          <input type="number" name="bid_validity_days" class="form-control form-control-sm" value="<?= e($a['bid_validity_days']) ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Evaluation report date</label>
          <input type="date" name="evaluation_report_date" class="form-control form-control-sm" value="<?= e($a['evaluation_report_date']) ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Comment on IPDC</label>
          <textarea name="comment_on_ipdc" class="form-control form-control-sm" rows="2" <?= $editable ? '' : 'disabled' ?>><?= e($a['comment_on_ipdc']) ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Officer feedback / findings</label>
          <textarea name="officer_feedback" class="form-control form-control-sm" rows="3" <?= $editable ? '' : 'disabled' ?>><?= e($a['officer_feedback']) ?></textarea>
        </div>
      </div>
      <?php if ($editable): ?>
        <div class="text-end mt-3"><button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Save checklist</button></div>
      <?php else: ?>
        <p class="text-muted small mt-2 mb-0">Checklist is locked at this stage.</p>
      <?php endif; ?>
    </form>

    <!-- Evaluation (read summary) -->
    <?php
    $fin = []; foreach (db_all("SELECT * FROM es_bid_financial_eval WHERE analysis_id = ?", 'i', [$id]) as $f) $fin[(int) $f['bidder_id']] = $f;
    $tech = []; foreach (db_all("SELECT * FROM es_bid_technical_eval WHERE analysis_id = ?", 'i', [$id]) as $t) $tech[(int) $t['bidder_id']] = $t;
    $items = []; foreach (db_all("SELECT kind, item_number, body FROM es_bid_list_item WHERE analysis_id = ? ORDER BY kind, item_number, id", 'i', [$id]) as $it) $items[$it['kind']][] = $it;
    $kindLabels = ['technical_criteria' => 'Technical criteria', 'assessment_criteria' => 'Assessment criteria', 'bid_opening_observation' => 'Bid-opening observations', 'evaluation_observation' => 'Evaluation observations', 'recommendation' => 'Recommendations', 'post_qualification' => 'Post-qualification'];
    ?>
    <div class="f-panel mt-3" style="padding:1.15rem;">
      <div class="fw-bold mb-2 d-flex align-items-center">
        <i class="bi bi-people me-1"></i>Bidders &amp; evaluation
        <?php if ($editable): ?>
          <a href="bid_analysis_evaluate.php?id=<?= $id ?>" class="btn btn-sm btn-success ms-auto"><i class="bi bi-pencil-square me-1"></i>Edit evaluation</a>
        <?php endif; ?>
      </div>
      <?php if (!$bidders): ?>
        <div class="f-state" style="padding:1.5rem 1rem;"><i class="bi bi-people"></i><p>No bidders captured yet.<?= $editable ? ' Use “Edit evaluation”.' : '' ?></p></div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.83rem;">
          <thead><tr><th>#</th><th>Bidder</th><th>Read-out</th><th>Corrected</th><th>Rank</th><th>Tech</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($bidders as $b):
            $f = $fin[(int) $b['id']] ?? null; $t = $tech[(int) $b['id']] ?? null; ?>
            <tr>
              <td><?= (int) $b['bidder_number'] ?></td>
              <td class="fw-semibold"><?= e($b['name']) ?><?= !empty($f['preferred_bidder']) ? ' <span class="pill t-green">preferred</span>' : '' ?><?= !empty($f['is_msme']) ? ' <span class="pill t-sky">MSME</span>' : '' ?></td>
              <td><?= e($b['currency_code']) ?> <?= $b['read_out_price'] !== null ? number_format((float) $b['read_out_price'], 2) : '—' ?></td>
              <td><?= $f && $f['corrected_bid_price'] !== null ? number_format((float) $f['corrected_bid_price'], 2) : '—' ?></td>
              <td><?= $f['rank_position'] ?? '—' ?></td>
              <td><?php if ($t): ?><span class="pill <?= $t['result'] === 'pass' ? 't-green' : ($t['result'] === 'fail' ? 't-rose' : 't-neutral') ?>"><?= e($t['result']) ?><?= $t['score'] !== null ? ' · ' . rtrim(rtrim((string) $t['score'], '0'), '.') : '' ?></span><?php else: ?>—<?php endif; ?></td>
              <td><span class="pill <?= $b['bid_status'] === 'responsive' ? 't-green' : 't-rose' ?>"><?= e(str_replace('_', ' ', $b['bid_status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>

      <?php foreach ($kindLabels as $k => $lbl): if (empty($items[$k])) continue; ?>
        <div class="mt-3">
          <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;"><?= e($lbl) ?></div>
          <ol class="mb-0 ps-3" style="font-size:.85rem;">
            <?php foreach ($items[$k] as $it): ?><li><?= e($it['body']) ?></li><?php endforeach; ?>
          </ol>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <!-- Workflow -->
    <div class="f-panel" style="padding:1.15rem;">
      <div class="fw-bold mb-2"><i class="bi bi-signpost-split me-1"></i>Workflow</div>
      <?php if (in_array($a['stage'], ['approved', 'rejected'], true)): ?>
        <p class="mb-0"><span class="pill <?= $stageTint[$a['stage']] ?>"><?= e($a['stage']) ?></span>
          <?= $a['decided_at'] ? ' on ' . e(date('d M Y', strtotime($a['decided_at']))) : '' ?></p>
        <?php if (trim((string) $a['dg_feedback']) !== ''): ?><div class="mt-2 small" style="white-space:pre-wrap;"><strong>DG feedback:</strong> <?= e($a['dg_feedback']) ?></div><?php endif; ?>
      <?php elseif (!$actions): ?>
        <p class="text-muted mb-0 small">No action available to you at this stage. Currently with <strong><?= e($a['owner_name'] ?? '—') ?></strong>.</p>
      <?php else: ?>
        <?php foreach ($actions as $act): ?>
          <form method="post" action="bid_analysis_action.php" class="mb-3 pb-3 border-bottom">
            <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="<?= e($act['key']) ?>">
            <?php if ($act['to_role']): $cands = es_users_with_role($act['to_role']); ?>
              <label class="form-label small fw-semibold">Send to (<?= e($act['to_role']) ?>)</label>
              <select name="to_user_id" class="form-select form-select-sm mb-2" required>
                <option value="">— select —</option>
                <?php foreach ($cands as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?>
              </select>
              <?php if (!$cands): ?><p class="text-danger small">No users hold the "<?= e($act['to_role']) ?>" e-services role yet.</p><?php endif; ?>
            <?php endif; ?>
            <textarea name="comments" class="form-control form-control-sm mb-2" rows="2" placeholder="Comment (optional)"></textarea>
            <button class="btn btn-<?= e($act['btn']) ?> btn-sm w-100"><?= e($act['label']) ?></button>
          </form>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- PDE response letter -->
    <?php if ($canWriteLetter): ?>
      <div class="f-panel mt-3" style="padding:1.15rem;">
        <div class="fw-bold mb-2 d-flex align-items-center">
          <i class="bi bi-envelope-paper me-1"></i>Response letter to the PDE
          <?php if ($response): ?>
            <span class="pill <?= $response['published'] ? 't-green' : 't-neutral' ?> ms-2"><?= $response['published'] ? 'published' : 'draft' ?></span>
            <a href="response_letter.php?id=<?= (int) $response['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success ms-auto">
              <i class="bi bi-file-earmark-arrow-down me-1"></i>Download
            </a>
          <?php endif; ?>
        </div>
        <form method="post" action="pde_response_save.php">
          <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
          <input type="hidden" name="analysis_id" value="<?= $id ?>">
          <textarea name="body" class="form-control form-control-sm mb-2" rows="6"
                    placeholder="Findings and PPDA's decision to the procuring entity…"><?= e($response['body'] ?? '') ?></textarea>
          <div class="d-flex gap-2 justify-content-end">
            <button name="action" value="draft" class="btn btn-outline-secondary btn-sm">Save draft</button>
            <button name="action" value="publish" class="btn btn-success btn-sm">
              <i class="bi bi-send me-1"></i><?= !empty($response['published']) ? 'Re-publish' : 'Publish' ?>
            </button>
          </div>
        </form>
      </div>
    <?php elseif ($response): ?>
      <div class="f-panel mt-3" style="padding:1.15rem;">
        <div class="fw-bold mb-2"><i class="bi bi-envelope-paper me-1"></i>Response letter</div>
        <a href="response_letter.php?id=<?= (int) $response['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success">
          <i class="bi bi-file-earmark-arrow-down me-1"></i>Download response letter
        </a>
      </div>
    <?php endif; ?>

    <!-- Routing trail -->
    <div class="f-panel mt-3" style="padding:1.15rem;">
      <div class="fw-bold mb-2"><i class="bi bi-clock-history me-1"></i>Routing trail</div>
      <?php if (!$trail): ?><p class="text-muted small mb-0">No movements yet.</p><?php else: ?>
        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
          <?php foreach ($trail as $t): ?>
            <li class="mb-2 pb-2 border-bottom">
              <span class="pill t-neutral"><?= e($t['action']) ?></span>
              <span class="text-muted"><?= e($t['from_name'] ?? 'system') ?><?= $t['to_name'] ? ' → ' . e($t['to_name']) : '' ?></span>
              <div class="text-muted"><?= e(date('d M Y H:i', strtotime($t['ts_create']))) ?></div>
              <?php if (trim((string) $t['comments']) !== ''): ?><div style="white-space:pre-wrap;"><?= e($t['comments']) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Discussion -->
    <div class="f-panel mt-3" style="padding:1.15rem;">
      <div class="fw-bold mb-2"><i class="bi bi-chat-left-text me-1"></i>Discussion</div>
      <?php foreach ($messages as $g): ?>
        <div class="mb-2 pb-2 border-bottom" style="font-size:.83rem;">
          <strong><?= e($g['full_name'] ?? '—') ?></strong> <span class="text-muted"><?= e(date('d M H:i', strtotime($g['ts_create']))) ?></span>
          <div style="white-space:pre-wrap;"><?= e($g['body']) ?></div>
        </div>
      <?php endforeach; ?>
      <form method="post" action="bid_analysis_comment.php" class="mt-2">
        <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <textarea name="body" class="form-control form-control-sm mb-2" rows="2" placeholder="Add a comment…" required></textarea>
        <div class="text-end"><button class="btn btn-outline-secondary btn-sm">Post</button></div>
      </form>
    </div>
  </div>
</div>

<?php es_layout_foot(); ?>
