<?php
/** Registry completeness check on a PDE-uploaded submission: approve or return. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('submission.registry_check');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_registry.php');
}

$id      = (int) ($_POST['id'] ?? 0);
$action  = $_POST['action'] ?? '';
$comment = trim($_POST['comment'] ?? '');

$r = db_one("SELECT id, status FROM es_bid_registry WHERE id = ?", 'i', [$id]);
if (!$r) { flash('Submission not found.', 'error'); redirect('bid_registry.php'); }
if ($r['status'] !== 'pending_registry') {
    flash('This submission is not awaiting a registry check.', 'error');
    redirect("bid_registry_view.php?id=$id");
}

if ($action === 'approve') {
    $st = $conn->prepare(
        "UPDATE es_bid_registry
            SET status = 'pending_allocation', registry_checked_by = ?, registry_checked_at = NOW(), registry_comment = ?
          WHERE id = ?"
    );
    $c = $comment !== '' ? $comment : null;
    $st->bind_param('isi', $ES_UID, $c, $id);
    $st->execute();
    $st->close();
    flash('Checked and sent for allocation.', 'success');
} elseif ($action === 'return') {
    if ($comment === '') { flash('Say what is missing when returning to the PDE.', 'error'); redirect("bid_registry_view.php?id=$id"); }
    $st = $conn->prepare(
        "UPDATE es_bid_registry
            SET status = 'returned_to_pde', registry_checked_by = ?, registry_checked_at = NOW(), registry_comment = ?
          WHERE id = ?"
    );
    $st->bind_param('isi', $ES_UID, $comment, $id);
    $st->execute();
    $st->close();
    flash('Returned to the PDE.', 'success');
} else {
    flash('Unknown action.', 'error');
}
redirect("bid_registry_view.php?id=$id");
