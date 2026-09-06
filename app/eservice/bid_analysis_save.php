<?php
/** Bid analysis — save the compliance checklist (officer, draft/returned only). */
require __DIR__ . '/inc/bootstrap.php';
es_require_role('officer');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_analysis.php');
}

$id = (int) ($_POST['id'] ?? 0);
$a  = db_one("SELECT id, stage, current_owner_id FROM es_bid_analysis WHERE id = ?", 'i', [$id]);
if (!$a) { flash('Analysis not found.', 'error'); redirect('bid_analysis.php'); }
if ((int) $a['current_owner_id'] !== $ES_UID || !in_array($a['stage'], ['draft', 'returned'], true)) {
    flash('You cannot edit this analysis right now.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}

$boolCols = [
    'approved_proc_plan', 'approved_workplan', 'preferences_applied', 'publication_done',
    'bid_opening_minutes_signed', 'evaluation_report_signed', 'ipdc_minutes_signed',
    'all_bids_enclosed', 'original_bid_enclosed', 'bids_still_valid',
];
$nStr = fn($k) => trim($_POST[$k] ?? '') !== '' ? trim($_POST[$k]) : null;

$sets = [];
$types = '';
$args = [];
foreach ($boolCols as $c) {
    $sets[] = "`$c` = ?";
    $types .= 'i';
    $args[] = !empty($_POST[$c]) ? 1 : 0;
}
foreach (['date_of_publication', 'evaluation_report_date', 'comment_on_ipdc', 'officer_feedback'] as $c) {
    $sets[] = "`$c` = ?";
    $types .= 's';
    $args[] = $nStr($c);
}
$sets[] = "bid_validity_days = ?";
$types .= 'i';
$args[] = ($_POST['bid_validity_days'] ?? '') !== '' ? (int) $_POST['bid_validity_days'] : null;

$args[] = $id;
$types .= 'i';

$st = $conn->prepare("UPDATE es_bid_analysis SET " . implode(', ', $sets) . " WHERE id = ?");
$st->bind_param($types, ...$args);
$ok = $st->execute();
$err = $conn->error;
$st->close();

flash($ok ? 'Checklist saved.' : "Save failed: $err", $ok ? 'success' : 'error');
redirect("bid_analysis_view.php?id=$id");
