<?php
/** Printable / PDF export of a bid analysis — clean official document layout.
 *  bid_analysis_export.php?id=N[&print=1] */
require __DIR__ . '/inc/bootstrap.php';

if (!es_can('analysis.export')) {
    http_response_code(403); exit('Not authorised.');
}

global $conn;

$id = (int) ($_GET['id'] ?? 0);
$a  = db_one(
    "SELECT a.*, r.serial_no, r.subject, r.tender_number, r.ref_code_pde,
            r.channel, r.ts_create AS submitted_ts,
            p.name AS pde_name, m.name AS method_name, t.name AS rtype_name,
            o.full_name AS officer_name, c.full_name AS owner_name
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
       LEFT JOIN es_procurement_method m ON m.id = r.procurement_method_id
       LEFT JOIN es_review_type t ON t.id = r.review_type_id
       LEFT JOIN users o ON o.id = a.officer_id
       LEFT JOIN users c ON c.id = a.current_owner_id
      WHERE a.id = ?",
    'i', [$id]
);
if (!$a) { http_response_code(404); exit('Analysis not found.'); }

$lots    = db_all("SELECT * FROM es_bid_lot WHERE analysis_id = ? ORDER BY lot_number", 'i', [$id]);
$bidders = db_all("SELECT * FROM es_bid_bidder WHERE analysis_id = ? ORDER BY sort_order, bidder_number", 'i', [$id]);
$prelim = []; foreach (db_all("SELECT * FROM es_bid_prelim_eval    WHERE analysis_id = ?", 'i', [$id]) as $x) $prelim[(int) $x['bidder_id']] = $x;
$tech   = []; foreach (db_all("SELECT * FROM es_bid_technical_eval  WHERE analysis_id = ?", 'i', [$id]) as $x) $tech[(int) $x['bidder_id']]   = $x;
$fin    = []; foreach (db_all("SELECT * FROM es_bid_financial_eval  WHERE analysis_id = ?", 'i', [$id]) as $x) $fin[(int) $x['bidder_id']]    = $x;
$postq  = []; foreach (db_all("SELECT * FROM es_bid_postqual_eval   WHERE analysis_id = ?", 'i', [$id]) as $x) $postq[(int) $x['bidder_id']]  = $x;

$items = [];
foreach (db_all("SELECT kind, item_number, body FROM es_bid_list_item WHERE analysis_id = ? ORDER BY item_number, id", 'i', [$id]) as $it) {
    $items[$it['kind']][] = $it;
}
$trail = db_all(
    "SELECT t.action, t.from_stage, t.to_stage, t.comments, t.ts_create,
            f.full_name AS from_name, u.full_name AS to_name
       FROM es_bid_routing t
       LEFT JOIN users f ON f.id = t.from_user_id
       LEFT JOIN users u ON u.id = t.to_user_id
      WHERE t.analysis_id = ? ORDER BY t.id ASC",
    'i', [$id]
);
$response = db_one("SELECT body, published, published_at FROM es_pde_response WHERE analysis_id = ? ORDER BY id DESC LIMIT 1", 'i', [$id]);

$lotNo = [];
foreach ($lots as $l) $lotNo[(int) $l['id']] = (int) $l['lot_number'];

$checks = [
    'approved_proc_plan'         => 'Approved procurement plan in place',
    'approved_workplan'          => 'Approved annual work plan',
    'preferences_applied'        => 'Preference / reservation scheme applied',
    'publication_done'           => 'Bid notice published',
    'bid_opening_minutes_signed' => 'Bid opening minutes signed',
    'evaluation_report_signed'   => 'Evaluation report signed',
    'ipdc_minutes_signed'        => 'IPDC minutes signed',
    'all_bids_enclosed'          => 'All bids enclosed',
    'original_bid_enclosed'      => 'Original bid enclosed',
    'bids_still_valid'           => 'Bids still valid',
];
$outcomeText = [
    'pending' => 'Pending', 'compliant' => 'Compliant', 'non_compliant' => 'Non-compliant',
    'no_objection' => 'No objection granted', 'objection' => 'Objection raised',
];
$stageText = [
    'draft' => 'Officer draft', 'returned' => 'Returned to officer', 'supervisor_review' => 'Supervisory review',
    'director_review' => 'Director review', 'dg_review' => 'Director General review', 'board_review' => 'PPDA Board review',
    'approved' => 'Approved', 'rejected' => 'Rejected',
];
$fmtn = fn($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2);
$rp   = fn($r) => !$r ? '—' : ($r === 'fail' ? '<strong>fail</strong>' : e($r));
$d    = fn($ts) => $ts ? date('j F Y', strtotime($ts)) : '—';
$today = date('j F Y');

// the report title follows the review type (Pre-Award Review, Post Review,
// Single Source Approval, Advisory, …); neutral fallback when none is set.
$reviewName = trim((string) ($a['rtype_name'] ?? ''));
$docTitle = ($reviewName !== '' ? $reviewName : 'Procurement Review') . ' — Analysis Report';

$biddersByLot = [];
foreach ($bidders as $b) $biddersByLot[(int) ($b['lot_id'] ?? 0)][] = $b;
$lotIter = array_merge(array_keys($lotNo), [0]);

$listBlock = function (string $kind, string $title) use ($items) {
    $rows = $items[$kind] ?? [];
    if (!$rows) return;
    echo '<h3>' . htmlspecialchars($title, ENT_QUOTES) . '</h3><ol class="crit">';
    foreach ($rows as $it) echo '<li>' . htmlspecialchars($it['body'], ENT_QUOTES) . '</li>';
    echo '</ol>';
};

/* emit a key/value row only when the value is present */
$kvRow = function (string $label, $val, bool $raw = false) {
    if (is_string($val)) $val = trim($val);
    if ($val === null || $val === '' || $val === '—') return;
    echo '<tr><td>' . htmlspecialchars($label, ENT_QUOTES) . '</td><td>'
       . ($raw ? $val : htmlspecialchars((string) $val, ENT_QUOTES)) . '</td></tr>';
};

// which optional sections have any content
$anyCheck = false;
foreach (array_keys($checks) as $k) if (!empty($a[$k])) { $anyCheck = true; break; }
$hasChecklist = $anyCheck || trim((string) $a['comment_on_ipdc']) !== '';
$hasCriteria  = $items['assessment_criteria'] ?? $items['bid_opening_observation'] ?? $items['technical_criteria']
             ?? $items['evaluation_observation'] ?? $items['post_qualification'] ?? null;
$hasFindings  = trim((string) $a['officer_feedback']) !== '' || !empty($items['recommendation']);

// numbered section heading — only sections with content are rendered, so the
// numbers stay contiguous
$sn = 0;
$sec = function (string $title) use (&$sn) {
    $sn++;
    echo '<h2>' . $sn . '. ' . htmlspecialchars($title, ENT_QUOTES) . '</h2>';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= e($docTitle) ?> · <?= e($a['serial_no']) ?></title>
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9ecef; color: #1a1a1a;
      font-family: Georgia, "Times New Roman", serif; font-size: 11pt; line-height: 1.5; }
    .bar { position: sticky; top: 0; background: #fff; border-bottom: 1px solid #d9dde1;
      padding: .55rem 1rem; display: flex; gap: .5rem; font-family: system-ui, sans-serif; }
    .bar button, .bar a { font: inherit; font-size: .85rem; padding: .42rem .85rem; border-radius: 6px;
      border: 1px solid #ced4da; background: #fff; color: #212529; text-decoration: none; cursor: pointer; }
    .bar .primary { background: #2a8f2e; border-color: #2a8f2e; color: #fff; }

    .sheet { max-width: 850px; margin: 1.4rem auto; background: #fff; padding: 48px 56px; }
    @media screen { .sheet { box-shadow: 0 1px 10px rgba(0,0,0,.12); } }

    .hdr { text-align: center; }
    .hdr h1 { font-size: 14pt; margin: 0; letter-spacing: .3px; }
    .hdr p { margin: 2px 0 0; font-size: 9pt; color: #666; font-family: system-ui, sans-serif; }
    .doctitle { text-align: center; font-size: 12.5pt; font-weight: bold; text-transform: uppercase;
      letter-spacing: .6px; margin: 22px 0 2px; }
    .docref { text-align: center; font-size: 9pt; color: #666; font-family: system-ui, sans-serif;
      border-bottom: 1.5px solid #1a1a1a; padding-bottom: 14px; margin-bottom: 6px; }

    h2 { font-size: 11pt; text-transform: uppercase; letter-spacing: .5px; margin: 26px 0 8px;
      border-bottom: 1px solid #9aa0a6; padding-bottom: 3px; }
    h3 { font-size: 10pt; margin: 14px 0 4px; }
    p { margin: 4px 0 10px; }
    p.free { white-space: pre-wrap; text-align: justify; }

    table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; font-size: 9.5pt; }
    td, th { padding: 4px 12px 4px 0; text-align: left; vertical-align: top; border-bottom: 1px solid #e4e7ea; }
    th { border-bottom: 1.5px solid #1a1a1a; font-weight: bold; }
    table.kv td:first-child { width: 34%; font-weight: bold; }
    table.kv td:last-child, table.data td:last-child, table.data th:last-child { padding-right: 0; }

    ol.crit { margin: 4px 0 10px; padding-left: 22px; }
    ol.crit li { margin-bottom: 5px; }
    .lot { font-weight: bold; font-size: 9.5pt; margin: 14px 0 2px; }
    .muted { color: #777; }

    .sign { margin-top: 42px; display: flex; gap: 48px; }
    .sign .box { flex: 1; }
    .sign .line { border-top: 1px solid #1a1a1a; margin-top: 44px; padding-top: 4px;
      font-size: 9pt; font-family: system-ui, sans-serif; }

    @media print {
      body { background: #fff; font-size: 10.5pt; }
      .bar { display: none; }
      .sheet { margin: 0; max-width: none; padding: 0; }
      h2 { break-after: avoid; }
      tr, ol.crit, .sign, p.free { break-inside: avoid; }
      @page { margin: 18mm; }
    }
  </style>
</head>
<body>
  <div class="bar">
    <button class="primary" onclick="window.print()">Print / Save as PDF</button>
    <a href="bid_analysis_view.php?id=<?= $id ?>">Back to analysis</a>
  </div>

  <div class="sheet">
    <div class="hdr">
      <h1>Public Procurement and Disposal of Assets Authority</h1>
      <p>Private Bag 383, Lilongwe 3, Malawi &nbsp;·&nbsp; www.ppda.mw</p>
    </div>
    <div class="doctitle"><?= e($docTitle) ?></div>
    <div class="docref">Ref: <?= e($a['serial_no']) ?> &nbsp;·&nbsp; Generated <?= e($today) ?>
      &nbsp;·&nbsp; Status: <?= e($stageText[$a['stage']] ?? $a['stage']) ?></div>

    <?php $sec('Submission details'); ?>
    <table class="kv">
      <?php
      $kvRow('Procuring & Disposing Entity', $a['pde_name'] ?? '');
      $kvRow('Subject of the submission', $a['subject']);
      $kvRow('Serial number', $a['serial_no']);
      $kvRow('Tender number', $a['tender_number']);
      $kvRow('PDE reference', $a['ref_code_pde']);
      $kvRow('Procurement method', $a['method_name']);
      $kvRow('Type of review', $a['rtype_name']);
      $kvRow('Submission received', $a['submitted_ts'] ? $d($a['submitted_ts']) . ' (' . ucfirst($a['channel']) . ')' : '');
      $kvRow('Reviewing officer', $a['officer_name'] ?? '');
      $kvRow('Currently with', $a['owner_name'] ?? '');
      if ($a['publication_done'] || $a['date_of_publication'] || $a['publication_source']) {
          $kvRow('Publication', ($a['publication_done'] ? 'Yes' : 'No')
              . ($a['date_of_publication'] ? ', ' . $d($a['date_of_publication']) : '')
              . ($a['publication_source'] ? ' (' . $a['publication_source'] . ')' : ''));
      }
      $kvRow('Bid validity', $a['bid_validity_days'] ? (int) $a['bid_validity_days'] . ' days' : '');
      ?>
    </table>

    <?php if ($hasChecklist): $sec('Compliance checklist'); ?>
      <table class="data">
        <tr><th style="width:78%">Requirement</th><th>Confirmed</th></tr>
        <?php foreach ($checks as $k => $lbl): ?>
          <tr><td><?= e($lbl) ?></td><td><?= !empty($a[$k]) ? 'Yes' : 'No' ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php if (trim((string) $a['comment_on_ipdc']) !== ''): ?>
        <h3>Comment on the IPDC</h3><p class="free"><?= e($a['comment_on_ipdc']) ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($bidders): $sec('Bid evaluation'); ?>
    <?php foreach ($lotIter as $lid): if (empty($biddersByLot[$lid])) continue; ?>
      <?php if (count($lots) > 0): ?>
        <div class="lot"><?= $lid ? 'Lot ' . (int) $lotNo[$lid] : 'Not assigned to a lot' ?></div>
      <?php endif; ?>
      <table class="data">
        <tr>
          <th>#</th><th>Bidder</th><th>Read-out price</th><th>Preliminary</th>
          <th>Technical</th><th>Corrected price</th><th>Rank</th><th>Post-qual.</th>
        </tr>
        <?php foreach ($biddersByLot[$lid] as $b):
          $pe = $prelim[(int) $b['id']] ?? null; $t = $tech[(int) $b['id']] ?? null;
          $f  = $fin[(int) $b['id']] ?? null;   $pq = $postq[(int) $b['id']] ?? null; ?>
          <tr>
            <td><?= (int) $b['bidder_number'] ?></td>
            <td><?= e($b['name']) ?><?= !empty($f['preferred_bidder']) ? ' <span class="muted">(preferred)</span>' : '' ?><?= !empty($f['is_msme']) ? ' <span class="muted">(MSME)</span>' : '' ?></td>
            <td><?= e($b['currency_code']) ?> <?= $fmtn($b['read_out_price']) ?></td>
            <td><?= $rp($pe['result'] ?? null) ?></td>
            <td><?= $rp($t['result'] ?? null) ?><?= isset($t['score']) && $t['score'] !== null ? ' (' . rtrim(rtrim((string) $t['score'], '0'), '.') . ')' : '' ?></td>
            <td><?= $fmtn($f['corrected_bid_price'] ?? null) ?></td>
            <td><?= $f['rank_position'] ?? '—' ?></td>
            <td><?= $rp($pq['result'] ?? null) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endforeach; endif; ?>

    <?php if ($hasCriteria): $sec('Evaluation criteria & observations');
      $listBlock('assessment_criteria', 'Preliminary evaluation criteria');
      $listBlock('bid_opening_observation', 'Bid-opening observations');
      $listBlock('technical_criteria', 'Technical criteria');
      $listBlock('evaluation_observation', 'Evaluation observations');
      $listBlock('post_qualification', 'Post-qualification criteria');
    endif; ?>

    <?php if ($hasFindings): $sec('Officer findings & recommendations'); ?>
      <?php if (trim((string) $a['officer_feedback']) !== ''): ?>
        <h3>Officer feedback / findings</h3><p class="free"><?= e($a['officer_feedback']) ?></p>
      <?php endif; ?>
      <?php if (!empty($items['recommendation'])): ?>
        <h3>Recommendations</h3><ol class="crit">
          <?php foreach ($items['recommendation'] as $it): ?><li><?= e($it['body']) ?></li><?php endforeach; ?>
        </ol>
      <?php endif; ?>
    <?php endif; ?>

    <?php $sec('Outcome'); ?>
    <table class="kv">
      <?php
      $kvRow('Current stage', $stageText[$a['stage']] ?? $a['stage']);
      $kvRow('Recommendation / outcome', $a['final_outcome'] === 'pending' ? '' : ($outcomeText[$a['final_outcome']] ?? $a['final_outcome']));
      $kvRow('Submitted for review', $a['submitted_at'] ? $d($a['submitted_at']) : '');
      $kvRow('Decided', $a['decided_at'] ? $d($a['decided_at']) : '');
      ?>
    </table>
    <?php if (trim((string) $a['dg_feedback']) !== ''): ?>
      <h3>Director General's remarks</h3><p class="free"><?= e($a['dg_feedback']) ?></p>
    <?php endif; ?>
    <?php if ($response && $response['published']): ?>
      <h3>Response issued to the PDE<?= $response['published_at'] ? ', ' . e($d($response['published_at'])) : '' ?></h3>
      <p class="free"><?= e($response['body']) ?></p>
    <?php endif; ?>

    <?php if ($trail): $sec('Workflow trail'); ?>
      <table class="data">
        <tr><th>Date</th><th>Action</th><th>Stage</th><th>By</th><th>Comment</th></tr>
        <?php foreach ($trail as $tr): ?>
          <tr>
            <td><?= e(date('j M Y H:i', strtotime($tr['ts_create']))) ?></td>
            <td><?= e(ucfirst($tr['action'])) ?></td>
            <td><?= e(str_replace('_', ' ', (string) $tr['from_stage'])) ?> &rarr; <?= e(str_replace('_', ' ', (string) $tr['to_stage'])) ?></td>
            <td><?= e($tr['from_name'] ?? '—') ?><?= $tr['to_name'] ? ' &rarr; ' . e($tr['to_name']) : '' ?></td>
            <td><?= e($tr['comments'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <div class="sign">
      <div class="box"><div class="line">Reviewing officer — <?= e($a['officer_name'] ?? '') ?><br>Date: <?= e($today) ?></div></div>
      <div class="box"><div class="line">Reviewed / approved</div></div>
    </div>
  </div>

  <?php if (!empty($_GET['print'])): ?><script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script><?php endif; ?>
</body>
</html>
