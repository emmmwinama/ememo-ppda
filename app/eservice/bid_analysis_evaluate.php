<?php
/** Bid analysis — evaluation editor. Officer-only, while draft / returned.
 *  Order: lots -> bidders -> preliminary -> technical -> financial -> post-qualification.
 *  Bidders flow forward by result: pass preliminary -> technical; pass technical ->
 *  financial; preferred bidder -> post-qualification. Recommendations live on the
 *  analysis page. */
require __DIR__ . '/inc/layout.php';
es_require_perm('analysis.evaluate');

global $conn, $ES_UID;

$id = (int) ($_GET['id'] ?? 0);
$a  = db_one(
    "SELECT a.*, r.serial_no, r.subject FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id WHERE a.id = ?",
    'i', [$id]
);
if (!$a) { http_response_code(404); es_layout_head('Not found'); echo '<div class="f-state is-error"><i class="bi bi-question-circle"></i><p>Analysis not found.</p></div>'; es_layout_foot(); exit; }

$editable = ((int) $a['officer_id'] === $ES_UID) && in_array($a['stage'], ['draft', 'returned'], true);
if (!$editable) {
    flash('You can only edit an evaluation assigned to you while it is a draft or returned to you.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}

$lots    = db_all("SELECT * FROM es_bid_lot WHERE analysis_id = ? ORDER BY lot_number", 'i', [$id]);
$bidders = db_all("SELECT * FROM es_bid_bidder WHERE analysis_id = ? ORDER BY sort_order, bidder_number", 'i', [$id]);
$prelim = []; foreach (db_all("SELECT * FROM es_bid_prelim_eval    WHERE analysis_id = ?", 'i', [$id]) as $x) $prelim[(int) $x['bidder_id']] = $x;
$tech   = []; foreach (db_all("SELECT * FROM es_bid_technical_eval  WHERE analysis_id = ?", 'i', [$id]) as $x) $tech[(int) $x['bidder_id']]   = $x;
$fin    = []; foreach (db_all("SELECT * FROM es_bid_financial_eval  WHERE analysis_id = ?", 'i', [$id]) as $x) $fin[(int) $x['bidder_id']]    = $x;
$postq  = []; foreach (db_all("SELECT * FROM es_bid_postqual_eval   WHERE analysis_id = ?", 'i', [$id]) as $x) $postq[(int) $x['bidder_id']]  = $x;

// criteria / observations, keyed [kind][lot_id]  (lot_id 0 = not tied to a lot)
$items = [];
foreach (db_all("SELECT * FROM es_bid_list_item WHERE analysis_id = ? ORDER BY item_number, id", 'i', [$id]) as $it) {
    $items[$it['kind']][(int) ($it['lot_id'] ?? 0)][] = $it;
}

$lotsById = [];
foreach ($lots as $l) $lotsById[(int) $l['id']] = $l;
$biddersByLot = [];
foreach ($bidders as $b) $biddersByLot[(int) ($b['lot_id'] ?? 0)][] = $b;
$hasLots = count($lots) > 0;

// lot buckets to iterate: real lots (by number), then a "no lot" bucket if used
$lotIterAll = array_keys($lotsById);

$currencies = array_column(db_all("SELECT code FROM es_currency WHERE active = 1 ORDER BY code"), 'code');
$csrf = e(es_csrf_token());

$hidden = fn(string $entity) =>
      '<input type="hidden" name="_csrf" value="' . $csrf . '">'
    . '<input type="hidden" name="analysis_id" value="' . $id . '">'
    . '<input type="hidden" name="entity" value="' . e($entity) . '">'
    . '<input type="hidden" name="op" value="save">';

$curSelect = function (string $name, ?string $sel) use ($currencies) {
    $o = '<option value="">—</option>';
    foreach ($currencies as $c) $o .= '<option' . ($c === $sel ? ' selected' : '') . '>' . e($c) . '</option>';
    return "<select name=\"$name\" class=\"form-select form-select-sm\">$o</select>";
};
$lotOptions = function ($sel) use ($lots) {
    $o = '<option value="">—</option>';
    foreach ($lots as $l) $o .= '<option value="' . (int) $l['id'] . '"' . ((int) $sel === (int) $l['id'] ? ' selected' : '') . '>Lot ' . (int) $l['lot_number'] . '</option>';
    return $o;
};
$lotName = fn($lid) => $lid
    ? 'Lot ' . (int) $lotsById[$lid]['lot_number'] . ' — ' . e($lotsById[$lid]['name'])
    : 'Not assigned to a lot';

/* one pass/fail evaluation section (preliminary, technical, post-qualification) */
$resultSection = function (string $title, string $icon, string $entity, array $eligible, array $map, string $emptyMsg, bool $withScore = false)
                 use ($id, $hidden, $biddersByLot, $lotIterAll, $hasLots, $lotName) {
    // regroup only the eligible bidders by lot
    $byLot = [];
    foreach ($eligible as $b) $byLot[(int) ($b['lot_id'] ?? 0)][] = $b;
    ?>
  <div class="f-panel">
    <div class="f-panel-head"><i class="bi <?= e($icon) ?>"></i> <?= e($title) ?></div>
    <?php if (!$eligible): ?>
      <div class="f-panel-body"><p class="text-muted small mb-0"><?= e($emptyMsg) ?></p></div>
    <?php else: ?>
      <div class="ev-scroll"><div class="ev-list">
        <div class="ev-head <?= $withScore ? 'ev-tech' : 'ev-res' ?>">
          <span>Bidder</span><span>Result</span><?php if ($withScore): ?><span>Score</span><?php endif; ?><span>Remarks</span><span></span>
        </div>
        <?php foreach (array_merge($lotIterAll, [0]) as $lid): if (empty($byLot[$lid])) continue; ?>
          <?php if ($hasLots): ?><div class="ev-lot-head"><?= $lotName($lid) ?></div><?php endif; ?>
          <?php foreach ($byLot[$lid] as $b): $r = $map[(int) $b['id']] ?? []; ?>
            <form method="post" action="bid_eval_save.php" class="ev-row <?= $withScore ? 'ev-tech' : 'ev-res' ?>">
              <?= $hidden($entity) ?><input type="hidden" name="bidder_id" value="<?= (int) $b['id'] ?>">
              <span class="fw-semibold"><?= (int) $b['bidder_number'] ?>. <?= e($b['name']) ?></span>
              <select name="result" class="form-select form-select-sm">
                <?php foreach (['pending', 'pass', 'fail'] as $o): ?><option value="<?= $o ?>" <?= ($r['result'] ?? 'pending') === $o ? 'selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
              </select>
              <?php if ($withScore): ?>
                <input type="number" step="0.01" name="score" class="form-control form-control-sm" value="<?= e($r['score'] ?? '') ?>">
              <?php endif; ?>
              <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($r['remarks'] ?? '') ?>">
              <span class="btns"><button class="btn btn-sm btn-outline-secondary">Save</button></span>
            </form>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div></div>
    <?php endif; ?>
  </div>
    <?php
};

/* one criteria / observation list section; $perLot groups it by lot */
$listSection = function (string $kind, string $label, bool $perLot, string $help = '')
               use ($id, $hidden, $items, $lots, $lotsById, $lotIterAll, $hasLots, $lotName) {
    $render = function ($lid) use ($kind, $label, $hidden, $items) {
        $rows = $items[$kind][$lid] ?? [];
        foreach ($rows as $it): ?>
          <form method="post" action="bid_eval_save.php" class="d-flex gap-2 align-items-start mb-2">
            <?= $hidden('list_item') ?>
            <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
            <input type="hidden" name="lot_id" value="<?= (int) $lid ?>">
            <input type="number" name="item_number" class="form-control form-control-sm" style="width:64px;" value="<?= (int) $it['item_number'] ?>">
            <textarea name="body" class="form-control form-control-sm" rows="2"><?= e($it['body']) ?></textarea>
            <button class="btn btn-sm btn-outline-secondary">Save</button>
            <button name="op" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove?')"><i class="bi bi-trash"></i></button>
          </form>
        <?php endforeach; ?>
        <form method="post" action="bid_eval_save.php" class="d-flex gap-2 align-items-start">
          <?= $hidden('list_item') ?><input type="hidden" name="kind" value="<?= e($kind) ?>">
          <input type="hidden" name="lot_id" value="<?= (int) $lid ?>">
          <input type="number" name="item_number" class="form-control form-control-sm" style="width:64px;" value="<?= count($rows) + 1 ?>">
          <textarea name="body" class="form-control form-control-sm" rows="2" placeholder="Add <?= e(strtolower($label)) ?>…"></textarea>
          <button class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i></button>
        </form>
        <?php
    };
    ?>
  <div class="f-panel">
    <div class="f-panel-head"><i class="bi bi-list-ul"></i> <?= e($label) ?><?php if ($help): ?><span class="text-muted fw-normal ms-1"><?= e($help) ?></span><?php endif; ?></div>
    <div class="f-panel-body">
      <?php if ($perLot && $hasLots): ?>
        <?php foreach ($lotIterAll as $lid): ?>
          <div class="ev-lot-head" style="margin:0 -1.4rem .6rem; border-top:1px solid var(--border);"><?= $lotName($lid) ?></div>
          <?php $render($lid); ?>
        <?php endforeach; ?>
        <?php if (!empty($items[$kind][0])): ?>
          <div class="ev-lot-head" style="margin:.6rem -1.4rem .6rem; border-top:1px solid var(--border);"><?= $lotName(0) ?></div>
          <?php $render(0); ?>
        <?php endif; ?>
      <?php else: ?>
        <?php $render(0); ?>
      <?php endif; ?>
    </div>
  </div>
    <?php
};

// eligibility chains
$passedPrelim = array_values(array_filter($bidders, fn($b) => ($prelim[(int) $b['id']]['result'] ?? '') === 'pass'));
$passedTech   = array_values(array_filter($bidders, fn($b) => ($tech[(int) $b['id']]['result'] ?? '') === 'pass'));
$preferred    = array_values(array_filter($bidders, fn($b) => !empty($fin[(int) $b['id']]['preferred_bidder'])));

es_layout_head('Evaluate · ' . $a['serial_no'], 'analysis');
?>
<style>
  .ev-scroll { overflow-x: auto; }
  .ev-list { display: flex; flex-direction: column; font-size: .85rem; }
  .ev-head, .ev-row { display: grid; gap: .5rem; align-items: center; padding: .55rem 1.4rem; }
  .ev-head { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: var(--bg); border-bottom: 1px solid var(--border); }
  .ev-row { border-bottom: 1px solid var(--border); }
  .ev-row:last-child { border-bottom: 0; }
  .ev-row:hover { background: rgba(0,0,0,.015); }
  .ev-row.is-add { background: var(--bg); }
  .ev-row .btns { display: flex; gap: .35rem; justify-content: flex-end; }
  .ev-lots  { grid-template-columns: 80px 1fr 160px; }
  .ev-bid   { grid-template-columns: 60px 1.5fr 90px 80px 1fr 130px 1fr 120px; min-width: 900px; }
  .ev-tech  { grid-template-columns: 1.6fr 120px 100px 1fr 90px; min-width: 640px; }
  .ev-res   { grid-template-columns: 1.6fr 120px 1fr 90px; min-width: 560px; }
  .ev-grid  { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: .55rem; }
  .ev-fin   { padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }
  .ev-fin:last-child { border-bottom: 0; }
  .ev-lot-head { padding: .55rem 1.4rem; background: var(--bg); border-bottom: 1px solid var(--border);
    font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
  .ev-list .form-label { margin-bottom: .15rem; font-size: .72rem; color: var(--muted); }
  .ev-flow { font-size: .82rem; color: var(--muted); margin: -.4rem 0 1rem; }
</style>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title">Evaluation · <?= e($a['serial_no']) ?></h1>
    <p class="f-subtitle"><?= e($a['subject']) ?></p>
  </div>
  <a href="bid_analysis_view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to analysis</a>
</div>

<p class="ev-flow"><i class="bi bi-arrow-right-circle me-1"></i>Bidders that <strong>pass preliminary</strong> move to technical; those that <strong>pass technical</strong> move to financial; the <strong>preferred bidder</strong> moves to post-qualification.</p>

<!-- ── 1. Lots ─────────────────────────────────────────────────────── -->
<div class="f-panel">
  <div class="f-panel-head"><i class="bi bi-box"></i> Lots <span class="text-muted fw-normal ms-1">optional — leave empty for a single-lot procurement</span></div>
  <div class="ev-scroll"><div class="ev-list">
    <div class="ev-head ev-lots"><span>No.</span><span>Name</span><span></span></div>
    <?php foreach ($lots as $l): ?>
      <form method="post" action="bid_eval_save.php" class="ev-row ev-lots">
        <?= $hidden('lot') ?><input type="hidden" name="lot_id" value="<?= (int) $l['id'] ?>">
        <input type="number" name="lot_number" class="form-control form-control-sm" value="<?= (int) $l['lot_number'] ?>" required>
        <input type="text" name="name" class="form-control form-control-sm" value="<?= e($l['name']) ?>" required>
        <span class="btns">
          <button class="btn btn-sm btn-outline-secondary">Save</button>
          <button name="op" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this lot?')"><i class="bi bi-trash"></i></button>
        </span>
      </form>
    <?php endforeach; ?>
    <form method="post" action="bid_eval_save.php" class="ev-row ev-lots is-add">
      <?= $hidden('lot') ?>
      <input type="number" name="lot_number" class="form-control form-control-sm" value="<?= count($lots) + 1 ?>">
      <input type="text" name="name" class="form-control form-control-sm" placeholder="New lot name…">
      <span class="btns"><button class="btn btn-sm btn-success"><i class="bi bi-plus-lg me-1"></i>Add lot</button></span>
    </form>
  </div></div>
</div>

<!-- ── 2. Bidders & read-out prices (by lot) ───────────────────────── -->
<div class="f-panel">
  <div class="f-panel-head"><i class="bi bi-people"></i> Bidders &amp; read-out prices</div>
  <div class="ev-scroll"><div class="ev-list">
    <div class="ev-head ev-bid"><span>#</span><span>Name</span><span>Lot</span><span>Ccy</span><span>Read-out price</span><span>Status</span><span>Remarks</span><span></span></div>
    <?php foreach (array_merge($lotIterAll, [0]) as $lid): if (empty($biddersByLot[$lid]) && !($lid === 0 && !$hasLots)) continue; ?>
      <?php if ($hasLots): ?><div class="ev-lot-head"><?= $lotName($lid) ?></div><?php endif; ?>
      <?php foreach ($biddersByLot[$lid] ?? [] as $b): ?>
        <form method="post" action="bid_eval_save.php" class="ev-row ev-bid">
          <?= $hidden('bidder') ?><input type="hidden" name="bidder_id" value="<?= (int) $b['id'] ?>">
          <input type="number" name="bidder_number" class="form-control form-control-sm" value="<?= (int) $b['bidder_number'] ?>" required>
          <input type="text" name="name" class="form-control form-control-sm" value="<?= e($b['name']) ?>" required>
          <select name="lot_id" class="form-select form-select-sm"><?= $lotOptions($b['lot_id']) ?></select>
          <?= $curSelect('currency_code', $b['currency_code']) ?>
          <input type="number" step="0.01" name="read_out_price" class="form-control form-control-sm" value="<?= $b['read_out_price'] !== null ? e($b['read_out_price']) : '' ?>">
          <select name="bid_status" class="form-select form-select-sm">
            <?php foreach (['responsive', 'non_responsive', 'rejected', 'withdrawn'] as $s): ?>
              <option value="<?= $s ?>" <?= $b['bid_status'] === $s ? 'selected' : '' ?>><?= str_replace('_', ' ', $s) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($b['remarks']) ?>">
          <span class="btns">
            <button class="btn btn-sm btn-outline-secondary">Save</button>
            <button name="op" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this bidder and its evaluations?')"><i class="bi bi-trash"></i></button>
          </span>
        </form>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <form method="post" action="bid_eval_save.php" class="ev-row ev-bid is-add">
      <?= $hidden('bidder') ?>
      <input type="number" name="bidder_number" class="form-control form-control-sm" value="<?= count($bidders) + 1 ?>">
      <input type="text" name="name" class="form-control form-control-sm" placeholder="Bidder name…">
      <select name="lot_id" class="form-select form-select-sm"><?= $lotOptions(0) ?></select>
      <?= $curSelect('currency_code', $currencies[0] ?? null) ?>
      <input type="number" step="0.01" name="read_out_price" class="form-control form-control-sm">
      <select name="bid_status" class="form-select form-select-sm">
        <?php foreach (['responsive', 'non_responsive', 'rejected', 'withdrawn'] as $s): ?><option value="<?= $s ?>"><?= str_replace('_', ' ', $s) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="remarks" class="form-control form-control-sm">
      <span class="btns"><button class="btn btn-sm btn-success"><i class="bi bi-plus-lg me-1"></i>Add</button></span>
    </form>
  </div></div>
</div>

<?php
// ── 3. Preliminary evaluation criteria ──────────────────────────────
$listSection('assessment_criteria', 'Preliminary evaluation criteria', true);

// ── 4. Bid-opening observations ────────────────────────────────────
$listSection('bid_opening_observation', 'Bid-opening observations', false);

// ── 5. Preliminary evaluation ─────────────────────────────────────
$resultSection('Preliminary evaluation', 'bi-funnel', 'preliminary', $bidders, $prelim,
    'Add bidders above, then evaluate them here.');

// ── 6. Technical criteria ─────────────────────────────────────────
$listSection('technical_criteria', 'Technical criteria', true);

// ── 7. Technical evaluation (bidders that passed preliminary) ──────
$resultSection('Technical evaluation', 'bi-clipboard-check', 'technical', $passedPrelim, $tech,
    'No bidder has passed preliminary evaluation yet.', true);
?>

<?php if ($passedTech): ?>
<!-- ── 8. Financial evaluation (bidders that passed technical) ─────── -->
<div class="f-panel">
  <div class="f-panel-head"><i class="bi bi-cash-stack"></i> Financial evaluation</div>
  <?php
  $finByLot = [];
  foreach ($passedTech as $b) $finByLot[(int) ($b['lot_id'] ?? 0)][] = $b;
  foreach (array_merge($lotIterAll, [0]) as $lid): if (empty($finByLot[$lid])) continue; ?>
    <?php if ($hasLots): ?><div class="ev-lot-head"><?= $lotName($lid) ?></div><?php endif; ?>
    <?php foreach ($finByLot[$lid] as $b): $f = $fin[(int) $b['id']] ?? []; ?>
    <form method="post" action="bid_eval_save.php" class="ev-fin">
      <?= $hidden('financial') ?><input type="hidden" name="bidder_id" value="<?= (int) $b['id'] ?>">
      <div class="fw-semibold mb-2"><?= (int) $b['bidder_number'] ?>. <?= e($b['name']) ?></div>
      <div class="ev-grid">
        <div><label class="form-label">Currency</label><?= $curSelect('currency_code', $f['currency_code'] ?? $b['currency_code']) ?></div>
        <div><label class="form-label">Bid price</label><input type="number" step="0.01" name="bid_price" class="form-control form-control-sm" value="<?= e($f['bid_price'] ?? $b['read_out_price']) ?>"></div>
        <div><label class="form-label">Computation errors</label><input type="number" step="0.01" name="computation_errors" class="form-control form-control-sm" value="<?= e($f['computation_errors'] ?? '0') ?>"></div>
        <div><label class="form-label">Corrected price</label><input type="number" step="0.01" name="corrected_bid_price" class="form-control form-control-sm" value="<?= e($f['corrected_bid_price'] ?? '') ?>"></div>
        <div><label class="form-label">Exchange rate</label><input type="number" step="0.000001" name="exchange_rate" class="form-control form-control-sm" value="<?= e($f['exchange_rate'] ?? '1') ?>"></div>
        <div><label class="form-label">Price after preferences</label><input type="number" step="0.01" name="price_after_preferences" class="form-control form-control-sm" value="<?= e($f['price_after_preferences'] ?? '') ?>"></div>
        <div><label class="form-label">Rank</label><input type="number" name="rank_position" class="form-control form-control-sm" value="<?= e($f['rank_position'] ?? '') ?>"></div>
        <div class="d-flex align-items-end gap-3">
          <label class="d-flex align-items-center gap-1"><input type="checkbox" name="preferred_bidder" value="1" <?= !empty($f['preferred_bidder']) ? 'checked' : '' ?>> Preferred</label>
          <label class="d-flex align-items-center gap-1"><input type="checkbox" name="is_msme" value="1" <?= !empty($f['is_msme']) ? 'checked' : '' ?>> MSME</label>
        </div>
      </div>
      <div class="mt-2 d-flex gap-2 align-items-end">
        <div class="flex-grow-1"><label class="form-label">Reasons for corrections</label><input type="text" name="reasons_errors" class="form-control form-control-sm" value="<?= e($f['reasons_errors'] ?? '') ?>"></div>
        <button class="btn btn-sm btn-outline-secondary">Save</button>
      </div>
    </form>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="f-panel"><div class="f-panel-head"><i class="bi bi-cash-stack"></i> Financial evaluation</div>
  <div class="f-panel-body"><p class="text-muted small mb-0">No bidder has passed technical evaluation yet.</p></div></div>
<?php endif; ?>

<?php
// ── 9. Evaluation observations ────────────────────────────────────
$listSection('evaluation_observation', 'Evaluation observations', false);

// ── 10. Post-qualification criteria ──────────────────────────────
$listSection('post_qualification', 'Post-qualification criteria', true);

// ── 11. Post-qualification evaluation (the preferred bidder) ──────
$resultSection('Post-qualification evaluation', 'bi-patch-check', 'postqual', $preferred, $postq,
    'Tick a preferred bidder in financial evaluation to post-qualify them.');
?>

<div class="text-center my-4">
  <a href="bid_analysis_view.php?id=<?= $id ?>" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Done — back to analysis</a>
</div>

<?php es_layout_foot(); ?>
