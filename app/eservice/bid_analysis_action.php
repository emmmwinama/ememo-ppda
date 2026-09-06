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
$comment = trim($_POST['comments'] ?? '');

$a = db_one("SELECT * FROM es_bid_analysis WHERE id = ?", 'i', [$id]);
if (!$a) { flash('Analysis not found.', 'error'); redirect('bid_analysis.php'); }

// ── Archive / un-archive — park a review without a decision. Available at any
//    stage of review; the analysis keeps its stage and still counts as active. ──
if ($action === 'archive' || $action === 'unarchive') {
    if (in_array($a['stage'], ['approved', 'rejected'], true)) {
        flash('A finalised review cannot be archived here.', 'error');
        redirect("bid_analysis_view.php?id=$id");
    }
    if (!es_can('analysis.archive')) {
        flash('You do not have permission to archive reviews.', 'error');
        redirect("bid_analysis_view.php?id=$id");
    }
    $inOfficerStage = in_array($a['stage'], ['draft', 'returned'], true);
    if ($inOfficerStage && !es_has_role('dg') && (int) $a['officer_id'] !== $ES_UID) {
        flash('Only the assigned technical officer can archive this draft.', 'error');
        redirect("bid_analysis_view.php?id=$id");
    }
    if ($action === 'archive' && $comment === '') {
        flash('Give a reason for archiving this submission.', 'error');
        redirect("bid_analysis_view.php?id=$id");
    }

    $conn->begin_transaction();
    try {
        if ($action === 'archive') {
            $st = $conn->prepare("UPDATE es_bid_analysis
                SET archived = 1, archived_at = NOW(), archived_by = ?, archived_reason = ?, current_owner_id = NULL
                WHERE id = ?");
            $st->bind_param('isi', $ES_UID, $comment, $id);
        } else {
            $st = $conn->prepare("UPDATE es_bid_analysis
                SET archived = 0, archived_at = NULL, archived_by = NULL, archived_reason = NULL WHERE id = ?");
            $st->bind_param('i', $id);
        }
        $st->execute();
        $st->close();

        $nu = null; $stg = $a['stage']; $cmt = $comment !== '' ? $comment : null;
        $st = $conn->prepare("INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, from_stage, to_stage, comments)
                              VALUES (?,?,?,?,?,?,?)");
        $st->bind_param('iiissss', $id, $ES_UID, $nu, $action, $stg, $stg, $cmt);
        $st->execute();
        $st->close();

        $conn->commit();
        flash($action === 'archive' ? 'Submission archived — it stays on the books as active work.' : 'Submission taken out of the archive.', 'success');
    } catch (Throwable $ex) {
        $conn->rollback();
        flash('Archive update failed: ' . $ex->getMessage(), 'error');
    }
    redirect("bid_analysis_view.php?id=$id");
}

// Who may action an analysis at each stage. A forward step hands it to the next
// stage's shared queue (no single owner); a return hands it back to the
// technical officer who wrote it.
$stageRole = [
    'draft'             => 'officer',
    'returned'          => 'officer',
    'supervisor_review' => 'supervisor',
    'director_review'   => 'director',
    'dg_review'         => 'dg',
];
$need = $stageRole[$a['stage']] ?? null;
if (!$need || !es_has_role($need)) {
    flash('You cannot action this analysis at its current stage.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}
if ($need === 'officer' && (int) $a['officer_id'] !== $ES_UID) {
    flash('Only the assigned technical officer can submit this analysis.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}

// Transition table: action => [requiredStage, requiredRole, newStage, ownerRule, routingAction]
//   ownerRule: 'pool'   -> next stage's shared queue (current_owner_id = NULL)
//              'officer' -> back to the technical officer
//              'self'    -> stays with the DG who finalised it
$T = [
    'submit'          => ['draft',             'officer',    'supervisor_review', 'pool',    'submit'],
    'submit_returned' => ['returned',          'officer',    'supervisor_review', 'pool',    'submit'],
    'submit_director' => ['supervisor_review', 'supervisor', 'director_review',   'pool',    'submit'],
    'submit_dg'       => ['director_review',   'director',   'dg_review',         'pool',    'submit'],
    'submit_board'    => ['dg_review',         'dg',         'board_review',      'pool',    'submit'],
    'return_reviewer' => [$a['stage'],         $need,        'returned',          'officer', 'return'],
    'approve'         => ['dg_review',         'dg',         'approved',          'self',    'approve'],
    'reject'          => ['dg_review',         'dg',         'rejected',          'self',    'reject'],
];

// 'submit' from a returned analysis is still a submit
if ($action === 'submit' && $a['stage'] === 'returned') $action = 'submit_returned';
if (!isset($T[$action])) { flash('Unknown workflow action.', 'error'); redirect("bid_analysis_view.php?id=$id"); }

[$needStage, $needRole, $newStage, $ownerRule, $rAction] = $T[$action];
if ($a['stage'] !== $needStage || !es_has_role($needRole)) {
    flash('That action is not available at this stage.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}
// granular permission behind each transition
$needPerm = [
    'submit'          => 'analysis.submit',
    'submit_returned' => 'analysis.submit',
    'submit_director' => 'analysis.review_supervisor',
    'submit_dg'       => 'analysis.review_director',
    'submit_board'    => 'analysis.review_dg',
    'approve'         => 'analysis.review_dg',
    'reject'          => 'analysis.review_dg',
    'return_reviewer' => $a['stage'] === 'supervisor_review' ? 'analysis.review_supervisor' : 'analysis.review_director',
][$action] ?? null;
if ($needPerm && !es_can($needPerm)) {
    flash('You do not have permission for this workflow step.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}
// a return must carry an explanation — that comment is what unlocks the officer's edit
if ($rAction === 'return' && $comment === '') {
    flash('Add a comment saying what needs to change before returning it.', 'error');
    redirect("bid_analysis_view.php?id=$id");
}

// Resolve the new owner
$newOwner = match ($ownerRule) {
    'self'    => $ES_UID,
    'officer' => (int) $a['officer_id'],
    default   => null,               // 'pool'
};

$conn->begin_transaction();
try {
    $fromStage = $a['stage'];
    $decidedSql = in_array($newStage, ['approved', 'rejected'], true) ? ', decided_at = NOW()' : '';
    $submittedSql = ($newStage === 'supervisor_review' && $fromStage !== 'supervisor_review') ? ', submitted_at = COALESCE(submitted_at, NOW())' : '';

    // DG feedback lands in dg_feedback; everyone else's comment goes to routing only
    $dgFbSql = '';
    if (in_array($action, ['approve', 'reject'], true) && $comment !== '') {
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
    $msg = match ($action) {
        'approve'         => 'No-objection granted — the review is finalised.',
        'reject'          => 'No-objection withheld — the review is finalised.',
        'submit_board'    => 'Sent to the PPDA Board for determination.',
        'return_reviewer' => 'Returned to the technical officer with your comment.',
        default           => 'Submitted — the analysis is now in ' . str_replace('_', ' ', $newStage) . '.',
    };
    flash($msg, 'success');
} catch (Throwable $ex) {
    $conn->rollback();
    flash('Workflow update failed: ' . $ex->getMessage(), 'error');
}
redirect("bid_analysis_view.php?id=$id");
