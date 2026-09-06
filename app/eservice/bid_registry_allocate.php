<?php
/** DG (or an allocator) assigns a checked submission to a technical officer. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('submission.allocate');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_registry.php');
}

$id        = (int) ($_POST['id'] ?? 0);
$officerId = (int) ($_POST['officer_id'] ?? 0);
$comment   = trim($_POST['comment'] ?? '');
$back      = ($_POST['from'] ?? '') === 'allocations' ? 'bid_allocations.php' : "bid_registry_view.php?id=$id";

$r = db_one("SELECT id, status FROM es_bid_registry WHERE id = ?", 'i', [$id]);
if (!$r) { flash('Submission not found.', 'error'); redirect('bid_registry.php'); }
if ($r['status'] !== 'pending_allocation') {
    flash('This submission is not awaiting allocation.', 'error');
    redirect($back);
}

$isOfficer = $officerId && db_one(
    "SELECT 1 x FROM es_user_role WHERE user_id = ? AND role = 'officer' LIMIT 1", 'i', [$officerId]
);
if (!$isOfficer) { flash('Pick a technical officer.', 'error'); redirect($back); }

$note = $comment !== '' ? $comment : null;

$conn->begin_transaction();
try {
    $st = $conn->prepare(
        "UPDATE es_bid_registry
            SET status = 'assigned', assigned_officer_id = ?, allocated_by = ?, allocated_at = NOW()
          WHERE id = ?"
    );
    $st->bind_param('iii', $officerId, $ES_UID, $id);
    $st->execute();
    $st->close();

    $st = $conn->prepare(
        "INSERT INTO es_bid_allocation (registry_id, action, to_officer_id, by_user_id, reason)
         VALUES (?, 'allocate', ?, ?, ?)"
    );
    $st->bind_param('iiis', $id, $officerId, $ES_UID, $note);
    $st->execute();
    $st->close();

    $conn->commit();
    flash('Allocated to the officer.', 'success');
} catch (Throwable $ex) {
    $conn->rollback();
    flash('Could not allocate: ' . $ex->getMessage(), 'error');
}
redirect($back);
