<?php
/** Bid analysis — the review workspace: checklist + workflow + trail + thread. */
require __DIR__ . '/inc/layout.php';
es_require_role('officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

$id = (int) ($_GET['id'] ?? 0);
$a  = db_one(
    "SELECT a.*, r.serial_no, r.subject, r.tender_number, r.id AS registry_id,
            p.name AS pde_name, m.name AS method_name,
            o.full_name AS officer_name, c.full_name AS owner_name, ab.full_name AS archived_by_name
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN users o ON o.id = a.officer_id
       LEFT JOIN users c ON c.id = a.current_owner_id
       LEFT JOIN users ab ON ab.id = a.archived_by
      WHERE a.id = ?",
    'i', [$id]
);
if (!$a) { http_response_code(404); es_layout_head('Not found'); echo '<div class="f-state is-error"><i class="bi bi-question-circle"></i><p>Analysis not found.</p></div>'; es_layout_foot(); exit; }

// editing is for the assigned officer, and only while the analysis is a draft
// or has been returned to them with comments — never once it is up the chain.
$editable = ((int) $a['officer_id'] === $ES_UID) && es_can('analysis.evaluate')
            && in_array($a['stage'], ['draft', 'returned'], true) && empty($a['archived']);

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
// the officer drafts the response letter with the analysis; supervisor / director /
// DG / board revise it as it moves up. Permission is the gate — a published letter
// stays visible (download only) to viewers without the permission.
$canWriteLetter   = es_can('response.write');
$canPublishLetter = es_can('response.publish');

// board (group) review
$boardMode  = ($a['stage'] === 'board_review' && es_has_role('board'));
$boardVotes = $a['stage'] === 'board_review'
    ? db_all("SELECT v.decision, v.comment, v.user_id, u.full_name
                FROM es_bid_board_vote v JOIN users u ON u.id = v.user_id
               WHERE v.analysis_id = ? ORDER BY v.id", 'i', [$id])
    : [];
$boardTotal = (int) (db_one("SELECT COUNT(*) c FROM es_user_role WHERE role = 'board'")['c'] ?? 0);
$myVote     = null;
foreach ($boardVotes as $v) if ((int) $v['user_id'] === $ES_UID) $myVote = $v['decision'];

// Archiving — parking a review without a decision. Available at every review
// stage to that stage's role (and to the DG at any stage). Archived work keeps
// its stage and still counts as active.
$archRole   = ['draft' => 'officer', 'returned' => 'officer', 'supervisor_review' => 'supervisor',
               'director_review' => 'director', 'dg_review' => 'dg', 'board_review' => 'board'][$a['stage']] ?? null;
$archAllowed = !in_array($a['stage'], ['approved', 'rejected'], true)
             && es_can('analysis.archive')
             && !($archRole === 'officer' && !es_has_role('dg') && (int) $a['officer_id'] !== $ES_UID);
$canArchive   = $archAllowed && empty($a['archived']);
$canUnarchive = $archAllowed && !empty($a['archived']);

// Workflow actions available now — one shared comment box, one button per destination.
$actions = [];
if (!empty($a['archived'])) {
    // no forward/return actions while parked
} elseif (in_array($a['stage'], ['draft', 'returned'], true) && (int) $a['officer_id'] === $ES_UID && es_has_role('officer')) {
    $actions[] = ['key' => 'submit', 'label' => 'Submit to supervisor', 'btn' => 'success'];
} elseif ($a['stage'] === 'supervisor_review' && es_has_role('supervisor')) {
    $actions[] = ['key' => 'submit_director', 'label' => 'Submit to director', 'btn' => 'success'];
    $actions[] = ['key' => 'return_reviewer', 'label' => 'Return to reviewer', 'btn' => 'outline-danger'];
} elseif ($a['stage'] === 'director_review' && es_has_role('director')) {
    $actions[] = ['key' => 'submit_dg', 'label' => 'Submit to DG', 'btn' => 'success'];
    $actions[] = ['key' => 'return_reviewer', 'label' => 'Return to reviewer', 'btn' => 'outline-danger'];
} elseif ($a['stage'] === 'dg_review' && es_has_role('dg')) {
    $actions[] = ['key' => 'approve', 'label' => 'Grant no-objection', 'btn' => 'success'];
    $actions[] = ['key' => 'reject', 'label' => 'Withhold no-objection', 'btn' => 'danger'];
    $actions[] = ['key' => 'submit_board', 'label' => 'Send to the board', 'btn' => 'primary'];
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

// keep the nav item for the queue this analysis is being viewed from lit
$navKey = match (true) {
    $a['stage'] === 'supervisor_review' && es_has_role('supervisor') => 'rev_supervisor',
    $a['stage'] === 'director_review'   && es_has_role('director')   => 'rev_director',
    $a['stage'] === 'dg_review'         && es_has_role('dg')         => 'rev_dg',
    $a['stage'] === 'board_review'      && es_has_role('board')      => 'rev_board',
    default => 'analysis',
};
es_layout_head('Analysis · ' . $a['serial_no'], $navKey);
?>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title"><?= e($a['serial_no']) ?> <span style="vertical-align:middle;"><?= es_status_badge($a["stage"]) ?></span></h1>
    <p class="f-subtitle"><?= e($a['subject']) ?> · <?= $a['owner_name'] ? 'with ' . e($a['owner_name']) : 'in the ' . e(str_replace('_', ' ', $a['stage'])) . ' queue' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (es_can('analysis.export')): ?>
      <a href="bid_analysis_export.php?id=<?= $id ?>&amp;print=1" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Export analysis</a>
    <?php endif; ?>
    <?= es_submission_button((int) $a['registry_id'], $a['serial_no']) ?>
  </div>
</div>

<style>
  @media (min-width: 992px) {
    .row.g-3 { align-items: flex-start; }
    .an-side {
      position: sticky;
      top: 72px;                        /* clears the 56px sticky top bar */
      align-self: flex-start;
      max-height: calc(100vh - 88px);
      overflow-y: auto;
      overscroll-behavior: contain;
      padding-right: .25rem;
    }
    .an-side::-webkit-scrollbar { width: 8px; }
    .an-side::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
  }
  tr.an-lotrow td {
    background: var(--bg); font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; font-size: .7rem; color: var(--muted);
    border-top: 1px solid var(--border); padding-top: .5rem; padding-bottom: .5rem;
  }
</style>
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
    $prelim = []; foreach (db_all("SELECT * FROM es_bid_prelim_eval WHERE analysis_id = ?", 'i', [$id]) as $x) $prelim[(int) $x['bidder_id']] = $x;
    $postq  = []; foreach (db_all("SELECT * FROM es_bid_postqual_eval WHERE analysis_id = ?", 'i', [$id]) as $x) $postq[(int) $x['bidder_id']] = $x;
    $lotNumById = []; foreach (db_all("SELECT id, lot_number FROM es_bid_lot WHERE analysis_id = ?", 'i', [$id]) as $l) $lotNumById[(int) $l['id']] = (int) $l['lot_number'];
    $items = []; foreach (db_all("SELECT kind, item_number, body FROM es_bid_list_item WHERE analysis_id = ? ORDER BY kind, item_number, id", 'i', [$id]) as $it) $items[$it['kind']][] = $it;
    // recommendations get their own editable panel below; the rest render read-only here
    $kindLabels = ['technical_criteria' => 'Technical criteria', 'assessment_criteria' => 'Preliminary evaluation criteria', 'bid_opening_observation' => 'Bid-opening observations', 'evaluation_observation' => 'Evaluation observations', 'post_qualification' => 'Post-qualification criteria'];
    $pfPill = fn($res) => $res ? '<span class="pill ' . ($res === 'pass' ? 't-green' : ($res === 'fail' ? 't-rose' : 't-neutral')) . '">' . e($res) . '</span>' : '—';
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
        <?php
        $bByLot = [];
        foreach ($bidders as $b) $bByLot[(int) ($b['lot_id'] ?? 0)][] = $b;
        $lotSeq = array_keys($lotNumById);
        if (isset($bByLot[0])) $lotSeq[] = 0;
        $showLotRows = count($lotNumById) > 0;
        ?>
        <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.83rem;">
          <thead><tr><th>#</th><th>Bidder</th><th>Read-out</th><th>Prelim</th><th>Tech</th><th>Corrected</th><th>Rank</th><th>Post-qual</th></tr></thead>
          <tbody>
          <?php foreach ($lotSeq as $lid): if (empty($bByLot[$lid])) continue; ?>
            <?php if ($showLotRows): ?>
              <tr class="an-lotrow"><td colspan="8"><?= $lid ? 'Lot ' . (int) $lotNumById[$lid] : 'Not assigned to a lot' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($bByLot[$lid] as $b):
              $f = $fin[(int) $b['id']] ?? null; $t = $tech[(int) $b['id']] ?? null;
              $pe = $prelim[(int) $b['id']] ?? null; $pq = $postq[(int) $b['id']] ?? null; ?>
              <tr>
                <td><?= (int) $b['bidder_number'] ?></td>
                <td class="fw-semibold"><?= e($b['name']) ?><?= !empty($f['preferred_bidder']) ? ' <span class="pill t-green">preferred</span>' : '' ?><?= !empty($f['is_msme']) ? ' <span class="pill t-sky">MSME</span>' : '' ?></td>
                <td><?= e($b['currency_code']) ?> <?= $b['read_out_price'] !== null ? number_format((float) $b['read_out_price'], 2) : '—' ?></td>
                <td><?= $pfPill($pe['result'] ?? null) ?></td>
                <td><?php if ($t): ?><span class="pill <?= $t['result'] === 'pass' ? 't-green' : ($t['result'] === 'fail' ? 't-rose' : 't-neutral') ?>"><?= e($t['result']) ?><?= $t['score'] !== null ? ' · ' . rtrim(rtrim((string) $t['score'], '0'), '.') : '' ?></span><?php else: ?>—<?php endif; ?></td>
                <td><?= $f && $f['corrected_bid_price'] !== null ? number_format((float) $f['corrected_bid_price'], 2) : '—' ?></td>
                <td><?= $f['rank_position'] ?? '—' ?></td>
                <td><?= $pfPill($pq['result'] ?? null) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>

      <?php $firstKind = true; foreach ($kindLabels as $k => $lbl): if (empty($items[$k])) continue; ?>
        <div class="ev-crit<?= $firstKind ? ' ev-crit--first' : '' ?>">
          <div class="ev-crit-head"><?= e($lbl) ?> <span class="ev-crit-count"><?= count($items[$k]) ?></span></div>
          <ol class="ev-crit-list">
            <?php foreach ($items[$k] as $it): ?><li><?= e($it['body']) ?></li><?php endforeach; ?>
          </ol>
        </div>
        <?php $firstKind = false; endforeach; ?>
    </div>

    <style>
      .ev-crit { margin-top: 1.4rem; padding-top: 1.2rem; border-top: 1px solid var(--border); }
      .ev-crit--first { margin-top: 1.5rem; }
      .ev-crit-head { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
        color: var(--muted); display: flex; align-items: center; gap: .5rem; margin-bottom: .7rem; }
      .ev-crit-count { display: inline-flex; align-items: center; justify-content: center; min-width: 1.3rem;
        padding: 0 .35rem; height: 1.3rem; border-radius: 999px; background: var(--bg); border: 1px solid var(--border);
        font-size: .68rem; color: var(--text); }
      .ev-crit-list { margin: 0; padding-left: 1.25rem; font-size: .88rem; line-height: 1.5; }
      .ev-crit-list li { margin-bottom: .55rem; padding-left: .25rem; }
      .ev-crit-list li:last-child { margin-bottom: 0; }
    </style>

    <!-- Recommendations (add without a reload; saved items list with edit/delete) -->
    <?php $recs = $items['recommendation'] ?? []; ?>
    <div class="f-panel mt-3" style="padding:1.15rem;" id="recPanel"
         data-aid="<?= $id ?>" data-csrf="<?= e(es_csrf_token()) ?>" data-editable="<?= $editable ? '1' : '0' ?>">
      <div class="fw-bold mb-2"><i class="bi bi-lightbulb me-1"></i>Recommendations</div>
      <ol class="rec-list" id="recList">
        <?php foreach ($recs as $it): ?>
          <li data-id="<?= (int) $it['id'] ?>">
            <span class="rec-body"><?= e($it['body']) ?></span>
            <?php if ($editable): ?>
              <span class="rec-ctl">
                <button type="button" data-rec-edit title="Edit"><i class="bi bi-pencil"></i></button>
                <button type="button" data-rec-del title="Delete"><i class="bi bi-trash"></i></button>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <p class="text-muted small mb-0 rec-empty <?= $recs ? 'd-none' : '' ?>">No recommendations recorded<?= $editable ? ' yet.' : '.' ?></p>
      <?php if ($editable): ?>
        <form id="recAdd" class="d-flex gap-2 align-items-start mt-2">
          <textarea class="form-control form-control-sm" rows="2" placeholder="Add a recommendation…" required></textarea>
          <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-plus-lg"></i></button>
        </form>
      <?php endif; ?>
    </div>

    <style>
      .rec-list { list-style: decimal; margin: 0; padding-left: 1.4rem; font-size: .88rem; line-height: 1.5; }
      .rec-list li { margin-bottom: .6rem; }
      .rec-list li:last-child { margin-bottom: 0; }
      .rec-list .rec-body { display: inline; }
      .rec-list .rec-ctl { white-space: nowrap; margin-left: .4rem; }
      .rec-list .rec-ctl button { border: 0; background: none; color: var(--muted); padding: 0 .2rem; font-size: .82rem; cursor: pointer; }
      .rec-list .rec-ctl button:hover { color: var(--text); }
      .rec-list li.is-editing .rec-body, .rec-list li.is-editing .rec-ctl { display: none; }
      .rec-edit { display: flex; gap: .5rem; align-items: flex-start; margin-top: .2rem; }
    </style>
    <script>
    (function () {
      var p = document.getElementById('recPanel'); if (!p || p.dataset.editable !== '1') return;
      var aid = p.dataset.aid, csrf = p.dataset.csrf;
      function toast(m, t) { (window.esToast || function () {})(m, t); }
      var list = document.getElementById('recList'), empty = p.querySelector('.rec-empty'), addForm = document.getElementById('recAdd');

      function post(extra) {
        var fd = new FormData();
        fd.append('_csrf', csrf); fd.append('analysis_id', aid);
        fd.append('entity', 'list_item'); fd.append('kind', 'recommendation');
        Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        return fetch('bid_eval_save.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd })
          .then(function (r) { return r.json(); });
      }
      function ctlHtml() {
        return '<span class="rec-ctl"><button type="button" data-rec-edit title="Edit"><i class="bi bi-pencil"></i></button>'
             + '<button type="button" data-rec-del title="Delete"><i class="bi bi-trash"></i></button></span>';
      }
      function addRow(id, body) {
        var li = document.createElement('li');
        li.dataset.id = id;
        li.innerHTML = '<span class="rec-body"></span>' + ctlHtml();
        li.querySelector('.rec-body').textContent = body;
        list.appendChild(li);
        empty.classList.add('d-none');
      }

      if (addForm) addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var ta = addForm.querySelector('textarea'), body = ta.value.trim();
        if (!body) return;
        var btn = addForm.querySelector('button'); btn.disabled = true;
        post({ op: 'save', item_number: list.children.length + 1, body: body })
          .then(function (res) {
            btn.disabled = false;
            if (res.ok) { addRow(res.id, res.body); ta.value = ''; ta.focus(); toast('Recommendation added.', 'success'); }
            else toast(res.error || 'Could not save.', 'error');
          }).catch(function () { btn.disabled = false; toast('Network error.', 'error'); });
      });

      list.addEventListener('click', function (e) {
        var li = e.target.closest('li'); if (!li) return;
        var id = li.dataset.id;

        if (e.target.closest('[data-rec-del]')) {
          if (!confirm('Remove this recommendation?')) return;
          post({ op: 'delete', item_id: id }).then(function (res) {
            if (res.ok) { li.remove(); if (!list.children.length) empty.classList.remove('d-none'); toast('Recommendation removed.', 'success'); }
            else toast(res.error || 'Could not delete.', 'error');
          });
          return;
        }
        if (e.target.closest('[data-rec-edit]')) {
          if (li.classList.contains('is-editing')) return;
          li.classList.add('is-editing');
          var cur = li.querySelector('.rec-body').textContent;
          var box = document.createElement('div');
          box.className = 'rec-edit';
          box.innerHTML = '<textarea class="form-control form-control-sm" rows="2"></textarea>'
                        + '<button class="btn btn-sm btn-success" data-rec-save>Save</button>'
                        + '<button class="btn btn-sm btn-link text-decoration-none" data-rec-cancel>Cancel</button>';
          box.querySelector('textarea').value = cur;
          li.appendChild(box);
          box.querySelector('textarea').focus();
          box.querySelector('[data-rec-cancel]').addEventListener('click', function () { box.remove(); li.classList.remove('is-editing'); });
          box.querySelector('[data-rec-save]').addEventListener('click', function () {
            var body = box.querySelector('textarea').value.trim(); if (!body) return;
            var idx = Array.prototype.indexOf.call(list.children, li) + 1;
            post({ op: 'save', item_id: id, item_number: idx, body: body }).then(function (res) {
              if (res.ok) { li.querySelector('.rec-body').textContent = res.body; box.remove(); li.classList.remove('is-editing'); toast('Recommendation updated.', 'success'); }
              else toast(res.error || 'Could not save.', 'error');
            });
          });
        }
      });
    })();
    </script>
  </div>

  <div class="col-lg-5 an-side">
    <!-- Workflow -->
    <div class="f-panel" style="padding:1.15rem;">
      <div class="fw-bold mb-2"><i class="bi bi-signpost-split me-1"></i>Workflow</div>
      <?php if (!empty($a['archived'])): ?>
        <div class="rec-note is-return" style="background:#fff7ed;border-color:#fed7aa;">
          <i class="bi bi-archive me-1"></i><strong>Archived</strong>
          <?= $a['archived_at'] ? ' on ' . e(date('d M Y', strtotime($a['archived_at']))) : '' ?>
          <?= $a['archived_by_name'] ? ' by ' . e($a['archived_by_name']) : '' ?>
          <?php if (trim((string) $a['archived_reason']) !== ''): ?>
            <div class="mt-1" style="white-space:pre-wrap;"><?= e($a['archived_reason']) ?></div>
          <?php endif; ?>
          <div class="text-muted mt-1" style="font-size:.8rem;">Stage held at <em><?= e(str_replace('_', ' ', $a['stage'])) ?></em>. Still counts as active work.</div>
        </div>
        <?php if ($canUnarchive): ?>
          <form method="post" action="bid_analysis_action.php" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <textarea name="comments" class="form-control form-control-sm mb-2" rows="2" placeholder="Note (optional)"></textarea>
            <button name="action" value="unarchive" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-box-arrow-up me-1"></i>Take out of the archive</button>
          </form>
        <?php endif; ?>

      <?php elseif (in_array($a['stage'], ['approved', 'rejected'], true)): ?>
        <p class="mb-0"><span class="pill <?= $stageTint[$a['stage']] ?>"><?= e($a['stage']) ?></span>
          <?= $a['decided_at'] ? ' on ' . e(date('d M Y', strtotime($a['decided_at']))) : '' ?></p>
        <?php if (trim((string) $a['dg_feedback']) !== ''): ?><div class="mt-2 small" style="white-space:pre-wrap;"><strong>DG feedback:</strong> <?= e($a['dg_feedback']) ?></div><?php endif; ?>

      <?php elseif ($a['stage'] === 'board_review'):
        $vA = 0; $vR = 0; foreach ($boardVotes as $v) { $v['decision'] === 'approve' ? $vA++ : $vR++; }
        $need = intdiv($boardTotal, 2) + 1; ?>
        <p class="small mb-2">Group review — <strong><?= $boardTotal ?></strong> board member<?= $boardTotal === 1 ? '' : 's' ?>,
          <strong><?= $need ?></strong> to decide. Approve ends the review; otherwise it returns to the DG.</p>
        <div class="d-flex gap-2 mb-2">
          <span class="es-badge" style="background:#e6f4e6;color:#1f6b23"><i class="bi bi-hand-thumbs-up"></i><?= $vA ?> approve</span>
          <span class="es-badge" style="background:#fdecee;color:#be123c"><i class="bi bi-hand-thumbs-down"></i><?= $vR ?> return</span>
          <span class="es-badge es-badge-status"><?= $vA + $vR ?>/<?= $boardTotal ?> voted</span>
        </div>
        <?php if ($boardVotes): ?>
          <ul class="list-unstyled mb-2" style="font-size:.82rem;">
            <?php foreach ($boardVotes as $v): ?>
              <li class="mb-1"><i class="bi bi-<?= $v['decision'] === 'approve' ? 'check-circle text-success' : 'arrow-counterclockwise text-danger' ?> me-1"></i>
                <strong><?= e($v['full_name']) ?></strong> — <?= e($v['decision']) ?><?php if (trim((string) $v['comment']) !== ''): ?>: <span class="text-muted"><?= e($v['comment']) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($boardMode): ?>
          <form method="post" action="bid_board_vote.php" enctype="multipart/form-data" class="pt-2 border-top">
            <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label class="form-label small fw-semibold mb-1"><?= $myVote ? 'Change your vote' : 'Your vote' ?></label>
            <div class="d-flex gap-3 mb-2" style="font-size:.86rem;">
              <label class="d-flex align-items-center gap-1"><input type="radio" name="decision" value="approve" <?= $myVote === 'approve' ? 'checked' : '' ?> required> Approve</label>
              <label class="d-flex align-items-center gap-1"><input type="radio" name="decision" value="return" <?= $myVote === 'return' ? 'checked' : '' ?>> Return to DG</label>
            </div>
            <textarea name="comment" class="form-control form-control-sm mb-2" rows="2" placeholder="Comment (required to return)"></textarea>
            <input type="file" name="docs[]" class="form-control form-control-sm mb-2" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip">
            <button class="btn btn-primary btn-sm w-100"><i class="bi bi-check2-square me-1"></i><?= $myVote ? 'Update vote' : 'Cast vote' ?></button>
          </form>
        <?php else: ?>
          <p class="text-muted small mb-0">Awaiting the Board's determination.</p>
        <?php endif; ?>

      <?php elseif (!$actions): ?>
        <p class="text-muted mb-0 small">No action available to you at this stage.<?= $a['owner_name'] ? ' Currently with <strong>' . e($a['owner_name']) . '</strong>.' : '' ?></p>
      <?php else: ?>
        <form method="post" action="bid_analysis_action.php">
          <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <label class="form-label small fw-semibold">Comment</label>
          <textarea name="comments" class="form-control form-control-sm mb-2" rows="3" placeholder="Add a note for the trail — required when returning."></textarea>
          <div class="d-grid gap-2">
            <?php foreach ($actions as $act): ?>
              <button name="action" value="<?= e($act['key']) ?>" class="btn btn-<?= e($act['btn']) ?> btn-sm"><?= e($act['label']) ?></button>
            <?php endforeach; ?>
          </div>
        </form>
      <?php endif; ?>

      <?php if ($canArchive): ?>
        <details class="mt-3 pt-3 border-top">
          <summary class="small fw-semibold text-muted" style="cursor:pointer;"><i class="bi bi-archive me-1"></i>Archive this submission</summary>
          <form method="post" action="bid_analysis_action.php" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <textarea name="comments" class="form-control form-control-sm mb-2" rows="2" placeholder="Reason for archiving — required" required></textarea>
            <button name="action" value="archive" class="btn btn-outline-secondary btn-sm w-100">Archive — close without a decision</button>
          </form>
        </details>
      <?php endif; ?>
    </div>

    <!-- PDE response letter -->
    <?php if ($canWriteLetter || $response): ?>
      <?php $letterForm = static function () use ($id, $response, $canPublishLetter) { ?>
        <form method="post" action="pde_response_save.php" data-resp-form <?= $response ? 'hidden' : '' ?>>
          <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
          <input type="hidden" name="analysis_id" value="<?= $id ?>">
          <textarea name="body" class="form-control form-control-sm mb-2" rows="8"
                    placeholder="Findings and PPDA's decision to the procuring entity…"><?= e($response['body'] ?? '') ?></textarea>
          <div class="d-flex gap-2 justify-content-end">
            <?php if ($response): ?><button type="button" class="btn btn-link btn-sm text-decoration-none" data-resp-cancel>Cancel</button><?php endif; ?>
            <button name="action" value="draft" class="btn btn-outline-secondary btn-sm">Save draft</button>
            <?php if ($canPublishLetter): ?>
              <button name="action" value="publish" class="btn btn-success btn-sm">
                <i class="bi bi-send me-1"></i><?= !empty($response['published']) ? 'Re-publish' : 'Publish' ?>
              </button>
            <?php endif; ?>
          </div>
        </form>
      <?php }; ?>

      <div class="f-panel mt-3" style="padding:1.15rem;" id="respPanel">
        <div class="fw-bold mb-2 d-flex align-items-center flex-wrap gap-2">
          <span><i class="bi bi-envelope-paper me-1"></i>Response letter to the PDE</span>
          <?php if ($response): ?><span class="pill <?= $response['published'] ? 't-green' : 't-neutral' ?>"><?= $response['published'] ? 'published' : 'draft' ?></span><?php endif; ?>
          <?php if ($response): ?>
            <div class="ms-auto d-flex gap-2">
              <a href="response_letter.php?id=<?= (int) $response['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>
              <a href="response_letter.php?id=<?= (int) $response['id'] ?>&amp;format=pdf" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
              <?php if ($canWriteLetter): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-resp-edit><i class="bi bi-pencil me-1"></i>Edit</button><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($response): ?>
          <div data-resp-view style="white-space:pre-wrap; font-size:.88rem; max-height:340px; overflow:auto; padding:.7rem .85rem; background:var(--bg); border:1px solid var(--border); border-radius:8px;"><?= e($response['body']) ?></div>
        <?php endif; ?>

        <?php if ($canWriteLetter) $letterForm(); ?>
      </div>

      <?php if ($response && $canWriteLetter): ?>
        <script>
        (function () {
          var p = document.getElementById('respPanel'); if (!p) return;
          var v = p.querySelector('[data-resp-view]'), f = p.querySelector('[data-resp-form]');
          var e = p.querySelector('[data-resp-edit]'), c = p.querySelector('[data-resp-cancel]');
          if (e) e.addEventListener('click', function () { v.hidden = true; f.hidden = false; e.hidden = true; });
          if (c) c.addEventListener('click', function () { v.hidden = false; f.hidden = true; if (e) e.hidden = false; });
        })();
        </script>
      <?php endif; ?>
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
