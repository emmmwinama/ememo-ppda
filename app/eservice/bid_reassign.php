<?php
/** Reassign an allocated submission to a different technical officer.
 *  Records the move (from → to, who, why, when) on the submission's allocation trail
 *  and, if an analysis has started, on the analysis routing trail too. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('submission.allocate');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_allocations.php?tab=allocated');
}

$id     = (int) ($_POST['id'] ?? 0);
$newId  = (int) ($_POST['officer_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$back   = 'bid_allocations.php?tab=allocated';

$r = db_one("SELECT id, status, assigned_officer_id FROM es_bid_registry WHERE id = ?", 'i', [$id]);
if (!$r) { flash('Submission not found.', 'error'); redirect($back); }
if (!in_array($r['status'], ['assigned', 'in_analysis'], true)) {
    flash('Only an allocated submission that has not been completed can be reassigned.', 'error');
    redirect("bid_registry_view.php?id=$id");
}

$oldId = (int) $r['assigned_officer_id'];
if ($newId === $oldId) { flash('That is already the assigned officer.', 'error'); redirect($back); }
if ($reason === '')    { flash('Give a reason for the reassignment.', 'error'); redirect($back); }

$isOfficer = $newId && db_one(
    "SELECT 1 x FROM es_user_role WHERE user_id = ? AND role = 'officer' LIMIT 1", 'i', [$newId]
);
if (!$isOfficer) { flash('Pick a technical officer.', 'error'); redirect($back); }

$an = db_one("SELECT id, stage, current_owner_id FROM es_bid_analysis WHERE registry_id = ? ORDER BY id DESC LIMIT 1", 'i', [$id]);

$conn->begin_transaction();
try {
    $st = $conn->prepare(
        "UPDATE es_bid_registry
            SET assigned_officer_id = ?, allocated_by = ?, allocated_at = NOW()
          WHERE id = ?"
    );
    $st->bind_param('iii', $newId, $ES_UID, $id);
    $st->execute();
    $st->close();

    $st = $conn->prepare(
        "INSERT INTO es_bid_allocation (registry_id, action, from_officer_id, to_officer_id, by_user_id, reason)
         VALUES (?, 'reassign', ?, ?, ?, ?)"
    );
    $st->bind_param('iiiis', $id, $oldId, $newId, $ES_UID, $reason);
    $st->execute();
    $st->close();

    if ($an) {
        // officer of record always follows the reassignment
        $conn->query("UPDATE es_bid_analysis SET officer_id = $newId WHERE id = " . (int) $an['id']);
        // only hand over the live baton if the officer is the one currently holding it
        if ((int) $an['current_owner_id'] === $oldId) {
            $conn->query("UPDATE es_bid_analysis SET current_owner_id = $newId WHERE id = " . (int) $an['id']);
        }
        $fromName = db_one("SELECT full_name FROM users WHERE id = ?", 'i', [$oldId])['full_name'] ?? ('officer #' . $oldId);
        $note = 'Reassigned from ' . $fromName . ': ' . $reason;
        $st = $conn->prepare(
            "INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, from_stage, to_stage, comments)
             VALUES (?, ?, ?, 'assign', ?, ?, ?)"
        );
        $st->bind_param('iiisss', $an['id'], $ES_UID, $newId, $an['stage'], $an['stage'], $note);
        $st->execute();
        $st->close();
    }

    $conn->commit();
    flash('Reassigned. The move is recorded on the submission.', 'success');
} catch (Throwable $ex) {
    $conn->rollback();
    flash('Could not reassign: ' . $ex->getMessage(), 'error');
}
redirect($back);
