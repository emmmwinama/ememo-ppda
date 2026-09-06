<?php
/** Bid analysis — evaluation editor: lots, bidders, financial & technical
 *  evaluation, criteria / observations / recommendations. Officer-only, draft. */
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

$editable = ((int) $a['current_owner_id'] === $ES_UID) && in_array($a['stage'], ['draft', 'returned'], true);
if (!$editable) {
    flash('The evaluation can only be edited by its officer while the analysis is a draft.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}

$lots    = db_all("SELECT * FROM es_bid_lot WHERE analysis_id = ? ORDER BY lot_number", 'i', [$id]);
$bidders = db_all("SELECT * FROM es_bid_bidder WHERE analysis_id = ? ORDER BY sort_order, bidder_number", 'i', [$id]);
$fin = []; foreach (db_all("SELECT * FROM es_bid_financial_eval WHERE analysis_id = ?", 'i', [$id]) as $f) $fin[(int) $f['bidder_id']] = $f;
$tech = []; foreach (db_all("SELECT * FROM es_bid_technical_eval WHERE analysis_id = ?", 'i', [$id]) as $t) $tech[(int) $t['bidder_id']] = $t;
$items = []; foreach (db_all("SELECT * FROM es_bid_list_item WHERE analysis_id = ? ORDER BY kind, item_number, id", 'i', [$id]) as $it) $items[$it['kind']][] = $it;

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

$listKinds = [
    'technical_criteria'      => 'Technical criteria',
    'assessment_criteria'     => 'Assessment criteria',
    'bid_opening_observation' => 'Bid-opening observations',
    'evaluation_observation'  => 'Evaluation observations',
    'recommendation'          => 'Recommendations',
    'post_qualification'      => 'Post-qualification criteria',
];

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
  .ev-tech  { grid-template-columns: 1.6fr 130px 110px 1fr 90px; min-width: 640px; }
  .ev-grid  { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: .55rem; }
  .ev-fin   { padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }
  .ev-fin:last-child { border-bottom: 0; }
  .ev-list .form-label { margin-bottom: .15rem; font-size: .72rem; color: var(--muted); }
</style>

<div class="f-head f-head-row">
  <div>
    <h1 class="f-title">Evaluation · <?= e($a['serial_no']) ?></h1>
    <p class="f-subtitle"><?= e($a['subject']) ?></p>
  </div>
  <a href="bid_analysis_view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to analysis</a>
</div>

<!-- ── Lots ─────────────────────────────────────────────────────────── -->
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

<!-- ── Bidders ──────────────────────────────────────────────────────── -->
<div class="f-panel">
  <div class="f-panel-head"><i class="bi bi-people"></i> Bidders &amp; read-out prices</div>
  <div class="ev-scroll"><div class="ev-list">
    <div class="ev-head ev-bid"><span>#</span><span>Name</span><span>Lot</span><span>Ccy</span><span>Read-out price</span><span>Status</span><span>Remarks</span><span></span></div>
    <?php
    $lotOptions = function ($sel) use ($lots) {
        $o = '<option value="">—</option>';
        foreach ($lots as $l) $o .= '<option value="' . (int) $l['id'] . '"' . ((int) $sel === (int) $l['id'] ? ' selected' : '') . '>Lot ' . (int) $l['lot_number'] . '</option>';
        return $o;
    };
    foreach ($bidders as $b): ?>
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

<?php if ($bidders): ?>
<!-- ── Financial evaluation ─────────────────────────────────────────── -->
<div class="f-panel">
  <div class="f-panel-head"><i class="bi bi-cash-stack"></i> Financial evaluation</div>
  <?php foreach ($bidders as $b): $f = $fin[(int) $b['id']] ?? []; ?>
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
</div>

<!-- ── Technical evaluation ─────────────────────────────────────────── -->
<div class="f-panel">
  <div class="f-panel-head"><i class="bi bi-clipboard-check"></i> Technical evaluation</div>
  <div class="ev-scroll"><div class="ev-list">
    <div class="ev-head ev-tech"><span>Bidder</span><span>Result</span><span>Score</span><span>Remarks</span><span></span></div>
    <?php foreach ($bidders as $b): $t = $tech[(int) $b['id']] ?? []; ?>
      <form method="post" action="bid_eval_save.php" class="ev-row ev-tech">
        <?= $hidden('technical') ?><input type="hidden" name="bidder_id" value="<?= (int) $b['id'] ?>">
        <span class="fw-semibold"><?= (int) $b['bidder_number'] ?>. <?= e($b['name']) ?></span>
        <select name="result" class="form-select form-select-sm">
          <?php foreach (['pending', 'pass', 'fail'] as $r): ?><option value="<?= $r ?>" <?= ($t['result'] ?? 'pending') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
        </select>
        <input type="number" step="0.01" name="score" class="form-control form-control-sm" value="<?= e($t['score'] ?? '') ?>">
        <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($t['remarks'] ?? '') ?>">
        <span class="btns"><button class="btn btn-sm btn-outline-secondary">Save</button></span>
      </form>
    <?php endforeach; ?>
  </div></div>
</div>
<?php endif; ?>

<!-- ── Criteria / observations / recommendations ───────────────────── -->
<?php foreach ($listKinds as $kind => $label): ?>
  <div class="f-panel">
    <div class="f-panel-head"><i class="bi bi-list-ul"></i> <?= e($label) ?></div>
    <div class="f-panel-body">
      <?php foreach ($items[$kind] ?? [] as $it): ?>
        <form method="post" action="bid_eval_save.php" class="d-flex gap-2 align-items-start mb-2">
          <?= $hidden('list_item') ?>
          <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
          <input type="number" name="item_number" class="form-control form-control-sm" style="width:70px;" value="<?= (int) $it['item_number'] ?>">
          <textarea name="body" class="form-control form-control-sm" rows="2"><?= e($it['body']) ?></textarea>
          <button class="btn btn-sm btn-outline-secondary">Save</button>
          <button name="op" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove?')"><i class="bi bi-trash"></i></button>
        </form>
      <?php endforeach; ?>
      <form method="post" action="bid_eval_save.php" class="d-flex gap-2 align-items-start">
        <?= $hidden('list_item') ?><input type="hidden" name="kind" value="<?= e($kind) ?>">
        <input type="number" name="item_number" class="form-control form-control-sm" style="width:70px;" value="<?= count($items[$kind] ?? []) + 1 ?>">
        <textarea name="body" class="form-control form-control-sm" rows="2" placeholder="Add <?= e(strtolower($label)) ?>…"></textarea>
        <button class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php es_layout_foot(); ?>
