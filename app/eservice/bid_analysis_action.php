<?php
/** Bid analysis — workflow transitions (submit / endorse / return / approve / reject). */
require __DIR__ . '/inc/bootstrap.php';
es_require_role('officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_analysis.php');
}

$id      = (int) ($_POST['id'] ?? 0);
$action  = $_POST['action'] ?? '';
$toUser  = ($_POST['to_user_id'] ?? '') !== '' ? (int) $_POST['to_user_id'] : null;
$comment = trim($_POST['comments'] ?? '');

$a = db_one("SELECT * FROM es_bid_analysis WHERE id = ?", 'i', [$id]);
if (!$a) { flash('Analysis not found.', 'error'); redirect('bid_analysis.php'); }

$isOwner = ((int) $a['current_owner_id'] === $ES_UID);
if (!$isOwner) { flash('This analysis is not currently assigned to you.', 'error'); redirect("bid_analysis_view.php?id=$id"); }

// Transition table: action => [requiredStage, requiredRole, newStage, ownerFrom ('picked'|'officer'|'self'), routingAction]
$T = [
    'submit'             => ['draft',             'officer',    'supervisor_review', 'picked', 'submit'],
    'submit_returned'    => ['returned',          'officer',    'supervisor_review', 'picked', 'submit'],
    'endorse_director'   => ['supervisor_review', 'supervisor', 'director_review',   'picked', 'endorse'],
    'return_officer'     => ['supervisor_review', 'supervisor', 'returned',          'officer', 'return'],
    'endorse_dg'         => ['director_review',   'director',   'dg_review',         'picked', 'endorse'],
    'return_supervisor'  => ['director_review',   'director',   'supervisor_review', 'prev_sup', 'return'],
    'approve'            => ['dg_review',          'dg',         'approved',          'self',   'approve'],
    'reject'             => ['dg_review',          'dg',         'rejected',          'self',   'reject'],
    'return_director'    => ['dg_review',          'dg',         'director_review',   'prev_dir', 'return'],
];

// allow 'submit' from either draft or returned
if ($action === 'submit' && $a['stage'] === 'returned') $action = 'submit_returned';
if (!isset($T[$action])) { flash('Unknown workflow action.', 'error'); redirect("bid_analysis_view.php?id=$id"); }

[$needStage, $needRole, $newStage, $ownerRule, $rAction] = $T[$action];
if ($a['stage'] !== $needStage || !es_has_role($needRole)) {
    flash('That action is not available at this stage.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}

// Resolve the new owner
$newOwner = null;
switch ($ownerRule) {
    case 'self':    $newOwner = $ES_UID; break;
    case 'officer': $newOwner = (int) $a['officer_id']; break;
    case 'picked':
        if (!$toUser) { flash('Choose who to send it to.', 'error'); redirect("bid_analysis_view.php?id=$id"); }
        $newOwner = $toUser;
        break;
    case 'prev_sup': // last supervisor who endorsed
    case 'prev_dir':
        $wantAct = 'endorse';
        $prev = db_one(
            "SELECT from_user_id FROM es_bid_routing
              WHERE analysis_id = ? AND action = 'endorse' AND from_stage = ?
              ORDER BY id DESC LIMIT 1",
            'is',
            [$id, $ownerRule === 'prev_sup' ? 'supervisor_review' : 'director_review']
        );
        $newOwner = $prev['from_user_id'] ?? (int) $a['officer_id'];
        break;
}

$conn->begin_transaction();
try {
    $fromStage = $a['stage'];
    $decidedSql = in_array($newStage, ['approved', 'rejected'], true) ? ', decided_at = NOW()' : '';
    $submittedSql = ($newStage === 'supervisor_review' && $fromStage !== 'supervisor_review') ? ', submitted_at = COALESCE(submitted_at, NOW())' : '';

    // DG feedback lands in dg_feedback; everyone else's comment goes to routing only
    $dgFbSql = '';
    if (in_array($action, ['approve', 'reject', 'return_director'], true) && $comment !== '') {
        $dgFbSql = ', dg_feedback = ?';
    }

    $sql = "UPDATE es_bid_analysis SET stage = ?, current_owner_id = ?" . $decidedSql . $submittedSql . $dgFbSql;
    $sql .= ", final_outcome = " . ($newStage === 'approved' ? "'no_objection'" : ($newStage === 'rejected' ? "'objection'" : "final_outcome"));
    $sql .= " WHERE id = ?";

    $st = $conn->prepare($sql);
    if ($dgFbSql) {
        $st->bind_param('sisi', $newStage, $newOwner, $comment, $id);
    } else {
        $st->bind_param('sii', $newStage, $newOwner, $id);
    }
    $st->execute();
    $st->close();

    $st = $conn->prepare(
        "INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, from_stage, to_stage, comments)
         VALUES (?,?,?,?,?,?,?)"
    );
    $cmt = $comment !== '' ? $comment : null;
    $st->bind_param('iiisss' . 's', $id, $ES_UID, $newOwner, $rAction, $fromStage, $newStage, $cmt);
    $st->execute();
    $st->close();

    if (in_array($newStage, ['approved', 'rejected'], true)) {
        $conn->query("UPDATE es_bid_registry SET status = 'completed' WHERE id = " . (int) $a['registry_id']);
    }

    $conn->commit();
    flash('Done — analysis is now "' . str_replace('_', ' ', $newStage) . '".', 'success');
} catch (Throwable $ex) {
    $conn->rollback();
    flash('Workflow update failed: ' . $ex->getMessage(), 'error');
}
redirect("bid_analysis_view.php?id=$id");
