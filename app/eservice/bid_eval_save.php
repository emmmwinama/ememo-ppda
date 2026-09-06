<?php
/** Persist one evaluation row: lot | bidder | financial | technical | list_item. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('analysis.evaluate');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_analysis.php');
}

$aid    = (int) ($_POST['analysis_id'] ?? 0);
$entity = $_POST['entity'] ?? '';
$op     = $_POST['op'] === 'delete' ? 'delete' : 'save';
$back   = "bid_analysis_evaluate.php?id=$aid";

$a = db_one("SELECT id, stage, current_owner_id FROM es_bid_analysis WHERE id = ?", 'i', [$aid]);
if (!$a) { flash('Analysis not found.', 'error'); redirect('bid_analysis.php'); }
if ((int) $a['current_owner_id'] !== $ES_UID || !in_array($a['stage'], ['draft', 'returned'], true)) {
    flash('This analysis can no longer be edited.', 'error');
    redirect("bid_analysis_view.php?id=$aid");
}

$nInt   = fn($k) => ($_POST[$k] ?? '') !== '' ? (int) $_POST[$k] : null;
$nStr   = fn($k) => trim($_POST[$k] ?? '') !== '' ? trim($_POST[$k]) : null;
$nFloat = fn($k) => ($_POST[$k] ?? '') !== '' ? (float) $_POST[$k] : null;
$fail   = function (string $m) use ($back) { flash($m, 'error'); redirect($back); };
$ok     = function (string $m) use ($back) { flash($m, 'success'); redirect($back); };

// helper: make sure a bidder belongs to this analysis
$bidderOk = fn($bid) => $bid && db_one("SELECT id FROM es_bid_bidder WHERE id = ? AND analysis_id = ?", 'ii', [(int) $bid, $aid]);

switch ($entity) {

// ------------------------------------------------------------------ LOT
case 'lot':
    if ($op === 'delete') {
        $conn->query("DELETE FROM es_bid_lot WHERE id = " . (int) ($_POST['lot_id'] ?? 0) . " AND analysis_id = $aid");
        $ok('Lot removed.');
    }
    $num  = (int) ($_POST['lot_number'] ?? 0);
    $name = $nStr('name');
    if (!$num || !$name) $fail('Lot number and name are required.');
    $lid = (int) ($_POST['lot_id'] ?? 0);
    if ($lid) {
        $st = $conn->prepare("UPDATE es_bid_lot SET lot_number = ?, name = ? WHERE id = ? AND analysis_id = ?");
        $st->bind_param('isii', $num, $name, $lid, $aid);
    } else {
        $st = $conn->prepare("INSERT INTO es_bid_lot (analysis_id, lot_number, name) VALUES (?,?,?)");
        $st->bind_param('iis', $aid, $num, $name);
    }
    $st->execute(); $st->close();
    $ok('Lot saved.');

// --------------------------------------------------------------- BIDDER
case 'bidder':
    if ($op === 'delete') {
        $conn->query("DELETE FROM es_bid_bidder WHERE id = " . (int) ($_POST['bidder_id'] ?? 0) . " AND analysis_id = $aid");
        $ok('Bidder removed (its evaluations too).');
    }
    $name = $nStr('name');
    $bnum = (int) ($_POST['bidder_number'] ?? 0);
    if (!$name || !$bnum) $fail('Bidder number and name are required.');
    $lot  = $nInt('lot_id');
    if ($lot && !db_one("SELECT id FROM es_bid_lot WHERE id = ? AND analysis_id = ?", 'ii', [$lot, $aid])) $lot = null;
    $cur  = $nStr('currency_code');
    $price = $nFloat('read_out_price');
    $bstat = in_array($_POST['bid_status'] ?? '', ['responsive', 'non_responsive', 'rejected', 'withdrawn'], true) ? $_POST['bid_status'] : 'responsive';
    $reason = $nStr('reason_rejection');
    $remarks = $nStr('remarks');
    $sort  = $nInt('sort_order');
    $bid   = (int) ($_POST['bidder_id'] ?? 0);
    if ($bid) {
        // lot i, bidder_number i, name s, currency s, read_out_price d, bid_status s,
        // reason s, remarks s, sort_order i, bidder_id i, analysis_id i
        $st = $conn->prepare(
            "UPDATE es_bid_bidder SET lot_id=?, bidder_number=?, name=?, currency_code=?, read_out_price=?,
                    bid_status=?, reason_rejection=?, remarks=?, sort_order=?
             WHERE id=? AND analysis_id=?"
        );
        $st->bind_param('iissdsssiii', $lot, $bnum, $name, $cur, $price, $bstat, $reason, $remarks, $sort, $bid, $aid);
    } else {
        // analysis_id i, lot i, bidder_number i, name s, currency s, read_out_price d,
        // bid_status s, reason s, remarks s, sort_order i
        $st = $conn->prepare(
            "INSERT INTO es_bid_bidder
               (analysis_id, lot_id, bidder_number, name, currency_code, read_out_price, bid_status, reason_rejection, remarks, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $st->bind_param('iiissdsssi', $aid, $lot, $bnum, $name, $cur, $price, $bstat, $reason, $remarks, $sort);
    }
    $st->execute(); $st->close();
    $ok('Bidder saved.');

// ------------------------------------------------------ FINANCIAL / TECHNICAL
case 'financial':
case 'technical':
    $bidderId = (int) ($_POST['bidder_id'] ?? 0);
    if (!$bidderOk($bidderId)) $fail('Unknown bidder.');
    $table = $entity === 'financial' ? 'es_bid_financial_eval' : 'es_bid_technical_eval';
    $existing = db_one("SELECT id FROM `$table` WHERE analysis_id = ? AND bidder_id = ? LIMIT 1", 'ii', [$aid, $bidderId]);

    if ($entity === 'financial') {
        $d = [
            'currency_code'           => $nStr('currency_code'),
            'bid_price'               => $nFloat('bid_price'),
            'computation_errors'      => $nFloat('computation_errors') ?? 0.0,
            'corrected_bid_price'     => $nFloat('corrected_bid_price'),
            'exchange_rate'           => $nFloat('exchange_rate') ?: 1.0,
            'price_after_preferences' => $nFloat('price_after_preferences'),
            'rank_position'           => $nInt('rank_position'),
            'preferred_bidder'        => !empty($_POST['preferred_bidder']) ? 1 : 0,
            'is_msme'                 => !empty($_POST['is_msme']) ? 1 : 0,
            'reasons_errors'          => $nStr('reasons_errors'),
        ];
    } else {
        $d = [
            'result'        => in_array($_POST['result'] ?? '', ['pass', 'fail', 'pending'], true) ? $_POST['result'] : 'pending',
            'score'         => $nFloat('score'),
            'currency_code' => $nStr('currency_code'),
            'bid_price'     => $nFloat('bid_price'),
            'remarks'       => $nStr('remarks'),
        ];
    }

    $cols = array_keys($d);
    $vals = array_values($d);
    $bt   = '';
    foreach ($vals as $x) $bt .= is_int($x) ? 'i' : (is_float($x) ? 'd' : 's');

    if ($existing) {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
        $vals[] = (int) $existing['id']; $bt .= 'i';
        $st = $conn->prepare("UPDATE `$table` SET $set WHERE id = ?");
    } else {
        $ph = implode(',', array_fill(0, count($cols), '?'));
        array_unshift($vals, $bidderId); array_unshift($vals, $aid); $bt = 'ii' . $bt;
        $st = $conn->prepare("INSERT INTO `$table` (analysis_id, bidder_id, `" . implode('`,`', $cols) . "`) VALUES (?,?,$ph)");
    }
    $st->bind_param($bt, ...$vals);
    $st->execute(); $st->close();
    $ok(ucfirst($entity) . ' evaluation saved.');

// ------------------------------------------------------------ LIST ITEM
case 'list_item':
    $kinds = ['technical_criteria', 'assessment_criteria', 'bid_opening_observation', 'evaluation_observation', 'recommendation', 'post_qualification'];
    $kind  = in_array($_POST['kind'] ?? '', $kinds, true) ? $_POST['kind'] : null;
    if (!$kind) $fail('Unknown list kind.');
    if ($op === 'delete') {
        $conn->query("DELETE FROM es_bid_list_item WHERE id = " . (int) ($_POST['item_id'] ?? 0) . " AND analysis_id = $aid");
        $ok('Item removed.');
    }
    $body = $nStr('body');
    if (!$body) $fail('The item text is empty.');
    $num  = (int) ($_POST['item_number'] ?? 0) ?: 1;
    $iid  = (int) ($_POST['item_id'] ?? 0);
    if ($iid) {
        $st = $conn->prepare("UPDATE es_bid_list_item SET item_number = ?, body = ? WHERE id = ? AND analysis_id = ? AND kind = ?");
        $st->bind_param('isiis', $num, $body, $iid, $aid, $kind);
    } else {
        $st = $conn->prepare("INSERT INTO es_bid_list_item (analysis_id, kind, item_number, body) VALUES (?,?,?,?)");
        $st->bind_param('isis', $aid, $kind, $num, $body);
    }
    $st->execute(); $st->close();
    $ok('Item saved.');

default:
    $fail('Unknown section.');
}
