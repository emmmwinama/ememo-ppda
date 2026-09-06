<?php
/** Create a draft bid analysis from a registry entry. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('analysis.start');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_registry.php');
}

$regId = (int) ($_POST['registry_id'] ?? 0);
$reg = db_one("SELECT id, status, assigned_officer_id FROM es_bid_registry WHERE id = ?", 'i', [$regId]);
if (!$reg) { flash('Submission not found.', 'error'); redirect('bid_registry.php'); }
if ($reg['status'] !== 'assigned') {
    flash('This submission has not been allocated for analysis yet.', 'error');
    redirect("bid_registry_view.php?id=$regId");
}
if ((int) $reg['assigned_officer_id'] !== $ES_UID && !es_can('submission.allocate')) {
    flash('This submission is allocated to another officer.', 'error');
    redirect("bid_registry_view.php?id=$regId");
}

$existing = db_one("SELECT id FROM es_bid_analysis WHERE registry_id = ?", 'i', [$regId]);
if ($existing) { redirect('bid_analysis_view.php?id=' . (int) $existing['id']); }

$conn->begin_transaction();
try {
    $st = $conn->prepare(
        "INSERT INTO es_bid_analysis (registry_id, officer_id, stage, current_owner_id)
         VALUES (?, ?, 'draft', ?)"
    );
    $st->bind_param('iii', $regId, $ES_UID, $ES_UID);
    $st->execute();
    $aid = $conn->insert_id;
    $st->close();

    $conn->query("UPDATE es_bid_registry SET status = 'in_analysis' WHERE id = $regId AND status = 'assigned'");

    $st = $conn->prepare(
        "INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, to_stage, comments)
         VALUES (?, ?, ?, 'assign', 'draft', 'Analysis started')"
    );
    $st->bind_param('iii', $aid, $ES_UID, $ES_UID);
    $st->execute();
    $st->close();

    $conn->commit();
    flash('Analysis created — you can now fill in the review.', 'success');
    redirect("bid_analysis_view.php?id=$aid");
} catch (Throwable $ex) {
    $conn->rollback();
    flash('Could not start analysis: ' . $ex->getMessage(), 'error');
    redirect("bid_registry_view.php?id=$regId");
}
