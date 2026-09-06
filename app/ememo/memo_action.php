<?php

session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'memo_utils.php';
require_once 'MemoNotifier.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Parse input & session
$data      = json_decode(file_get_contents('php://input'), true);
$userId    = $_SESSION['user_id'] ?? 0;
$memoId    = intval($data['memo_id']   ?? 0);
$action    = strtolower(trim($data['action'] ?? ''));
$comment   = trim($data['comment']      ?? '');
$clarId    = intval($data['clarification_id'] ?? 0);
$targetId  = isset($data['target_id'])   ? intval($data['target_id'])   : null;
$targetIds = is_array($data['target_ids'])? array_map('intval',$data['target_ids']) : [];

if (!$memoId || !$userId || !$action) {
    echo json_encode(['status'=>'error','message'=>'Missing required parameters.']);
    exit;
}

// fetch public memo ref
$stmt = $conn->prepare("SELECT memo_id FROM memos WHERE id = ?");
$stmt->bind_param('i', $memoId);
$stmt->execute();
$stmt->bind_result($memoRef);
$stmt->fetch();
$stmt->close();

$conn->begin_transaction();
$notifier = new MemoNotifier($conn, __DIR__ . '/ppdaememo-b656edc602b5.json');
$actorMeta = getUserMeta($conn, $userId);
$actorName = $actorMeta['name'] ?? 'User';

try {
    //
    // BRANCH 1: Instruction (Clarification)
    //
//
// BRANCH X: Instruction (Consult / Clarification)
//
if ($action === 'instruction') {
    if ($comment === '') {
        throw new Exception('Instruction cannot be empty.');
    }
    if (empty($targetIds)) {
        throw new Exception('Instruction requires at least one recipient.');
    }

    // 1) Insert one instruction per mentioned user
    $stmt = $conn->prepare("
        INSERT INTO memo_instructions
          (memo_id, user_id, recipient_id, instruction, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    foreach ($targetIds as $rid) {
        $stmt->bind_param('iiis', $memoId, $userId, $rid, $comment);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();

    // 2) Notify each recipient
    $notifier = new MemoNotifier($conn, __DIR__ . '/ppdaememo-b656edc602b5.json');
    $notifier->notify(
        $memoId,
        $memoRef,
        'Instruction Added',
        $userId,
        $actorName,
        $targetIds,
        ['message' => $comment]
    );

    echo json_encode(['status' => 'success', 'message' => 'Instruction sent.']);
    exit;
}



    //
    // BRANCH 2: Forward
    //
    if ($action === 'forward') {
        $forwardType = ucfirst(strtolower($comment));
        $trailLabel  = "Forward ({$forwardType})";
        $moveComment = "Please handle as {$forwardType} priority.";

        $stmtTrail = $conn->prepare("
            INSERT INTO memo_trail (memo_id, user_id, action, action_type, comment)
            VALUES (?, ?, 'Forward', ?, ?)
        ");
        $stmtMove  = $conn->prepare("
            INSERT INTO memo_movements (memo_id, from_user_id, to_user_id, action, comments)
            VALUES (?, ?, ?, 'Forwarded ({$forwardType})', ?)
        ");
        foreach ($targetIds as $toId) {
            $stmtTrail->bind_param('iiss', $memoId, $userId, $trailLabel, $moveComment);
            $stmtTrail->execute();
            $stmtMove->bind_param('iiis', $memoId, $userId, $toId, $moveComment);
            $stmtMove->execute();
        }
        $stmtTrail->close();
        $stmtMove->close();
        $conn->commit();

        $notifier->notify(
            $memoId,
            $memoRef,
            "Memo Forwarded",
            $userId,
            $actorName,
            $targetIds,
            ['note' => $moveComment]
        );

        echo json_encode(['status'=>'success','message'=>'Memo forwarded.']);
        exit;
    }

    //
    // BRANCH 2.5: Seek Clarification
    //
    if ($action === 'seek_clarification') {
        if (empty($comment) || empty($targetIds)) {
            throw new Exception('Clarification requires both a message and at least one user.');
        }
        $stmt = $conn->prepare("
            INSERT INTO memo_clarifications
              (memo_id, user_id, requested_by, clarification_comment, status, requested_at)
            VALUES (?, ?, ?, ?, 'Pending', NOW())
        ");
        foreach ($targetIds as $uid) {
            $stmt->bind_param('iiis', $memoId, $uid, $userId, $comment);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();

        $notifier->notify(
            $memoId,
            $memoRef,
            'Clarification Requested',
            $userId,
            $actorName,
            $targetIds,
            ['message' => $comment]
        );

        echo json_encode(['status' => 'success', 'message' => 'Clarification request sent.']);
        exit;
    }

    //
    // BRANCH X: Respond to Clarification
    //
    if ($action === 'respond_clarification') {
        if (!$clarId || $comment === '') {
            throw new Exception('Clarification response requires both an ID and a message.');
        }
        // update clarification
        $r = $conn->prepare("
            UPDATE memo_clarifications
               SET response_comment = ?, responded_at = NOW(), status = 'Responded'
             WHERE id = ?
        ");
        $r->bind_param('si', $comment, $clarId);
        $r->execute();
        $r->close();

        // fetch original requester
        $q = $conn->prepare("
            SELECT requested_by
              FROM memo_clarifications
             WHERE id = ?
        ");
        $q->bind_param('i', $clarId);
        $q->execute();
        $q->bind_result($requesterId);
        $q->fetch();
        $q->close();

        $conn->commit();

        $notifier->notify(
            $memoId,
            $memoRef,
            'Clarification Responded',
            $userId,
            $actorName,
            [$requesterId],
            ['response' => $comment]
        );

        echo json_encode(['status'=>'success','message'=>'Clarification response saved.']);
        exit;
    }

    //
    // BRANCH 2.6: Escalate / Consult
    //
if ($action === 'escalate') {
    if (empty($comment) || empty($targetIds)) {
        throw new Exception('Escalation requires both a message and at least one user.');
    }
    $newStatus = 'Escalated';

    // 1) Update each through‐recipient row
    $uStmt = $conn->prepare("
        UPDATE memo_through_recipients
           SET endorsement_status = ?
         WHERE memo_id = ? AND user_id = ?
    ");
    foreach ($targetIds as $uid) {
        $uStmt->bind_param('sii', $newStatus, $memoId, $uid);
        $uStmt->execute();
    }
    $uStmt->close();

    // 2) Insert into trail + movements
    $tStmt = $conn->prepare("
        INSERT INTO memo_trail
          (memo_id, user_id, action, action_type, comment)
        VALUES (?, ?, ?, 'Escalate', ?)
    ");
    $mStmt = $conn->prepare("
        INSERT INTO memo_movements
          (memo_id, from_user_id, to_user_id, action, comments)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($targetIds as $toId) {
        $tStmt->bind_param('iiss', $memoId, $userId, $newStatus, $comment);
        $tStmt->execute();
        $mStmt->bind_param('iiiss', $memoId, $userId, $toId, $newStatus, $comment);
        $mStmt->execute();
    }
    $tStmt->close();
    $mStmt->close();

    // 3) **NO LONGER** update the memo’s overall status:
    //     // $stmt = $conn->prepare("UPDATE memos SET status = ? WHERE id = ?");
    //     // $stmt->bind_param('si', $newStatus, $memoId);
    //     // $stmt->execute();
    //     // $stmt->close();

    $conn->commit();

    $notifier->notify(
        $memoId,
        $memoRef,
        'Memo Consulted',
        $userId,
        $actorName,
        $targetIds,
        ['message' => $comment]
    );

    echo json_encode(['status'=>'success','message'=>'Consult recorded.']);
    exit;
}

//
// BRANCH 3: Endorse/Approve/Reject/Return
//
$map = [
    'endorse' => ['new'=>'Under Review','trail'=>'Endorse','label'=>'Memo Endorsed'],
    'approve' => ['new'=>'Approved','trail'=>'Approve','label'=>'Memo Approved'],
    'reject'  => ['new'=>'Rejected','trail'=>'Reject','label'=>'Memo Rejected'],
    'return'  => ['new'=>'Returned','trail'=>'Return','label'=>'Memo Returned'],
];
if (!isset($map[$action])) {
    throw new Exception('Invalid action.');
}
$cfg = $map[$action];

// 1) Update this recipient’s status
$stmt = $conn->prepare("
    UPDATE memo_through_recipients
       SET endorsement_status = ?
     WHERE memo_id = ? AND user_id = ?
");
$stmt->bind_param('sii', $cfg['new'], $memoId, $userId);
$stmt->execute();
$stmt->close();

// 2) Insert into trail
$stmt = $conn->prepare("
    INSERT INTO memo_trail (memo_id, user_id, action, action_type, comment)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param('iisss', $memoId, $userId, $cfg['new'], $cfg['trail'], $comment);
$stmt->execute();
$stmt->close();

// 3) Movement
$toUser = null;
$stmt = $conn->prepare("
    INSERT INTO memo_movements (memo_id, from_user_id, to_user_id, action, comments)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param('iiiss', $memoId, $userId, $toUser, $cfg['new'], $comment);
$stmt->execute();
$stmt->close();

// ─────────── APPROVE SPECIAL HANDLER ───────────
if ($action === 'approve') {
	
	$stmt = $conn->prepare("
        UPDATE memos
           SET status = ?
         WHERE id = ?
    ");
    $stmt->bind_param('si', $cfg['new'], $memoId);
    $stmt->execute();
    $stmt->close();
    // a) commit
    $conn->commit();

    // b) pick next recipient
    $recipients = [];
    $row = $conn->query("
        SELECT user_id
          FROM memo_through_recipients
         WHERE memo_id = {$memoId}
           AND endorsement_status = 'Pending'
         ORDER BY id ASC
         LIMIT 1
    ")->fetch_assoc();
    if ($row) {
        $recipients[] = intval($row['user_id']);
    } else {
        $r = $conn->query("
            SELECT to_user_id
              FROM memos
             WHERE id = {$memoId}
        ")->fetch_assoc();
        $recipients[] = intval($r['to_user_id']);
    }

    // c) notify
    $notifier->notify(
        $memoId,
        $memoRef,
        $cfg['label'],   // “Memo Approved”
        $userId,
        $actorName,
        $recipients,
        ['comment' => $comment, 'status' => $cfg['new']]
    );

    // d) finish
    echo json_encode([
        'status'  => 'success',
        'message' => 'Memo approved.'
    ]);
    exit;
}
// ───────── END APPROVE SPECIAL ─────────

//
// BRANCH X: Return to originator
//
if ($action === 'return') {
    if (empty($comment)) {
        throw new Exception('Return requires a comment.');
    }

    // 1) Get originator
    $ownerStmt = $conn->prepare("SELECT originator_id FROM memos WHERE id = ?");
    $ownerStmt->bind_param('i', $memoId);
    $ownerStmt->execute();
    $ownerStmt->bind_result($originatorId);
    $ownerStmt->fetch();
    $ownerStmt->close();

    // 2) Fetch all already-endorsed users (excluding returner)
    $endorsedUsers = [];
    $stmt = $conn->prepare("
        SELECT mtr.user_id, u.full_name
          FROM memo_through_recipients mtr
          JOIN users u ON mtr.user_id = u.id
         WHERE mtr.memo_id = ? AND mtr.endorsement_status = 'Endorsed' AND mtr.user_id != ?
    ");
    $stmt->bind_param('ii', $memoId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $endorsedUsers[] = $row;
    }
    $stmt->close();

    // 3) Build rollback comment and insert trail (System-level)
    $rollbackNames = '';
    if (!empty($endorsedUsers)) {
        $rollbackNames = implode(', ', array_column($endorsedUsers, 'full_name'));
        $rollbackComment = "Rolled back endorsements for: $rollbackNames";

        $stmt = $conn->prepare("
            INSERT INTO memo_trail (memo_id, user_id, action, action_type, comment)
            VALUES (?, ?, 'Rolled Back', 'System', ?)
        ");
        $stmt->bind_param('iis', $memoId, $userId, $rollbackComment);
        $stmt->execute();
        $stmt->close();
    }

    // 4) Add return trail with context
    $returnComment = !empty($rollbackNames)
        ? "Returned memo and rolled back endorsements for: $rollbackNames. Reason: $comment"
        : $comment;

    $stmt = $conn->prepare("
        INSERT INTO memo_trail (memo_id, user_id, action, action_type, comment)
        VALUES (?, ?, 'Returned', 'Return', ?)
    ");
    $stmt->bind_param('iis', $memoId, $userId, $returnComment);
    $stmt->execute();
    $stmt->close();

    // 5) Insert movement to originator
    $stmt = $conn->prepare("
        INSERT INTO memo_movements (memo_id, from_user_id, to_user_id, action, comments)
        VALUES (?, ?, ?, 'Returned', ?)
    ");
    $stmt->bind_param('iiis', $memoId, $userId, $originatorId, $returnComment);
    $stmt->execute();
    $stmt->close();

    // 6) Reset all endorsement statuses to Pending
    $stmt = $conn->prepare("
        UPDATE memo_through_recipients
           SET endorsement_status = 'Pending'
         WHERE memo_id = ?
    ");
    $stmt->bind_param('i', $memoId);
    $stmt->execute();
    $stmt->close();

    // 7) Delete all endorsement records
    $stmt = $conn->prepare("
        DELETE FROM memo_through_endorsements
         WHERE memo_id = ?
    ");
    $stmt->bind_param('i', $memoId);
    $stmt->execute();
    $stmt->close();

    // 8) Update memo status to 'Returned'
    $stmt = $conn->prepare("
        UPDATE memos
           SET status = 'Returned'
         WHERE id = ?
    ");
    $stmt->bind_param('i', $memoId);
    $stmt->execute();
    $stmt->close();

    // 9) Commit the changes
    $conn->commit();

    // 10) Notify the originator
    $notifier->notify(
        $memoId,
        $memoRef,
        'Memo Returned',
        $userId,
        $actorName,
        [$originatorId],
        ['comment' => $returnComment]
    );

    echo json_encode(['status' => 'success', 'message' => 'Memo returned and previous endorsements rolled back.']);
    exit;
}




// … your existing “reject” block would follow here, similarly unchanged …


//
// BRANCH Y: Reject to originator
//
if ($action === 'reject') {
    if (empty($comment)) {
        throw new Exception('Rejection requires a reason.');
    }

    // 1) Trail
    $stmt = $conn->prepare("
        INSERT INTO memo_trail
          (memo_id, user_id, action, action_type, comment)
        VALUES (?, ?, 'Rejected', 'Reject', ?)
    ");
    $stmt->bind_param('iis', $memoId, $userId, $comment);
    $stmt->execute();
    $stmt->close();

    // 2) Movement back to originator
    $ownerStmt = $conn->prepare("
        SELECT originator_id
          FROM memos
         WHERE id = ?
    ");
    $ownerStmt->bind_param('i', $memoId);
    $ownerStmt->execute();
    $ownerStmt->bind_result($originatorId);
    $ownerStmt->fetch();
    $ownerStmt->close();

    $stmt = $conn->prepare("
        INSERT INTO memo_movements
          (memo_id, from_user_id, to_user_id, action, comments)
        VALUES (?, ?, ?, 'Rejected', ?)
    ");
    $stmt->bind_param('iiis', $memoId, $userId, $originatorId, $comment);
    $stmt->execute();
    $stmt->close();

    // 3) Update status
    $stmt = $conn->prepare("
        UPDATE memos
           SET status = 'Rejected'
         WHERE id = ?
    ");
    $stmt->bind_param('i', $memoId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // 4) Notify originator
    $notifier->notify(
        $memoId,
        $memoRef,
        'Memo Rejected',
        $userId,
        $actorName,
        [$originatorId],
        ['comment' => $comment]
    );

    echo json_encode(['status'=>'success','message'=>'Memo rejected.']);
    exit;
}


// 4) If endorse, record endorsement and update recipient status
if ($action === 'endorse') {
    // 4a) mark this through‐recipient as Endorsed
    $stmt = $conn->prepare("
        UPDATE memo_through_recipients
           SET endorsement_status = 'Endorsed'
         WHERE memo_id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $memoId, $userId);
    $stmt->execute();
    $stmt->close();

    // 4b) insert into memo_through_endorsements
    $stmt = $conn->prepare("
        INSERT INTO memo_through_endorsements
            (memo_id, user_id, comment, endorsed_at)
        VALUES (?,       ?,       ?,       NOW())
    ");
    $stmt->bind_param('iis', $memoId, $userId, $comment);
    $stmt->execute();
    $stmt->close();

    // 5) Decide overall memo.status
    //    Count recipients still Pending
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
          FROM memo_through_recipients 
         WHERE memo_id = ? 
           AND endorsement_status = 'Pending'
    ");
    $stmt->bind_param('i', $memoId);
    $stmt->execute();
    $stmt->bind_result($pendingCount);
    $stmt->fetch();
    $stmt->close();

    // If none are left pending → Endorsed; otherwise Under Review
    $newStatus = $pendingCount === 0 ? 'Endorsed' : 'Under Review';

    $stmt = $conn->prepare("
        UPDATE memos
           SET status = ?
         WHERE id = ?
    ");
    $stmt->bind_param('si', $newStatus, $memoId);
    $stmt->execute();
    $stmt->close();
	
	$statusToSet = $newStatus;

    $conn->commit();

    // 6) Determine who to notify next
$recipients = [];

// 6a) Try to find the next “Pending” through-recipient
$row = $conn->query("
    SELECT user_id
      FROM memo_through_recipients
     WHERE memo_id = {$memoId}
       AND endorsement_status = 'Pending'
     ORDER BY id ASC
     LIMIT 1
")->fetch_assoc();

if ($row) {
    // still someone left to endorse
    $recipients[] = intval($row['user_id']);
} else {
    // all through-recipients done → notify the final approver
    $rec = $conn->query("
        SELECT to_user_id
          FROM memos
         WHERE id = {$memoId}
    ")->fetch_assoc();
    $recipients[] = intval($rec['to_user_id']);
}

// 7) Send the notification
$notifier->notify(
    $memoId,
    $memoRef,
    $cfg['label'],      // e.g. “Memo Endorsed”
    $userId,
    $actorName,
    $recipients,
    ['comment' => $comment, 'status' => $statusToSet]
);

    echo json_encode(['status'=>'success','message'=> ucfirst($action).' recorded.']);
}
}
catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
