<?php
// memo_action.php

session_start();
require_once 'db.php';
require_once 'memo_utils.php';
require_once 'MemoNotifier.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Parse input & session
$data   = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'] ?? 0;

$memoId    = intval($data['memo_id'] ?? 0);
$action    = strtolower(trim($data['action'] ?? ''));
$comment   = trim($data['comment'] ?? '');
$targetId  = isset($data['target_id']) ? intval($data['target_id']) : null;
$targetIds = is_array($data['target_ids']) ? array_map('intval', $data['target_ids']) : [];

if (!$memoId || !$userId || !$action) {
    echo json_encode(['status'=>'error','message'=>'Missing required parameters.']);
    exit;
}

// 2) Fetch public memo number
$stmt = $conn->prepare("SELECT memo_id FROM memos WHERE id = ?");
$stmt->bind_param('i', $memoId);
$stmt->execute();
$stmt->bind_result($memoRef);
$stmt->fetch();
$stmt->close();

$conn->begin_transaction();

try {
    $actorMeta = getUserMeta($conn, $userId);
    $actorName = $actorMeta['name'] ?? 'User';

    //
    // BRANCH 1: Instruction
    //
    if ($action === 'instruction') {
        if ($comment === '') {
            throw new Exception('Instruction cannot be empty.');
        }
        $stmt = $conn->prepare("
            INSERT INTO memo_instructions (memo_id, user_id, instruction, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param('iis', $memoId, $userId, $comment);
        $stmt->execute();
        $stmt->close();
        $conn->commit();

        // notify originator
        $ownerStmt = $conn->prepare("SELECT originator_id FROM memos WHERE id = ?");
        $ownerStmt->bind_param('i', $memoId);
        $ownerStmt->execute();
        $ownerStmt->bind_result($originatorId);
        $ownerStmt->fetch();
        $ownerStmt->close();

        $notifier = new MemoNotifier($conn, __DIR__ . '/ppdaememo-b656edc602b5.json');
        $notifier->notify(
            $memoId,
            $memoRef,
            'Clarification Requested',
            $userId,
            $actorName,
            [$originatorId],
            ['message' => $comment]
        );

        echo json_encode(['status'=>'success','message'=>'Instruction saved.']);
        exit;
    }

    //
    // BRANCH 2: Forward
    //
    if ($action === 'forward') {
        $forwardType = ucfirst(strtolower($comment));
        $trailLabel  = "Forward ({$forwardType})";
        $moveAction  = "Forwarded ({$forwardType})";
        $moveComment = $message = "Please handle as {$forwardType} priority.";

        $stmtTrail = $conn->prepare("
            INSERT INTO memo_trail (memo_id, user_id, action, action_type, comment)
            VALUES (?, ?, 'Forward', ?, ?)
        ");
        $stmtMove = $conn->prepare("
            INSERT INTO memo_movements (memo_id, from_user_id, to_user_id, action, comments)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($targetIds as $toId) {
            $stmtTrail->bind_param('iiss', $memoId, $userId, $trailLabel, $moveComment);
            $stmtTrail->execute();
            $stmtMove->bind_param('iiiss', $memoId, $userId, $toId, $moveAction, $moveComment);
            $stmtMove->execute();
        }
        $stmtTrail->close();
        $stmtMove->close();
        $conn->commit();

        $notifier = new MemoNotifier($conn, __DIR__ . '/ppdaememo-b656edc602b5.json');
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
    // BRANCH 3: Endorse/Approve/Reject/Return/Escalate
    //
    $map = [
        'endorse'  => ['new'=>'Under Review','trail'=>'Endorse','label'=>'Memo Endorsed'],
        'approve'  => ['new'=>'Approved','trail'=>'Approve','label'=>'Memo Approved'],
        'reject'   => ['new'=>'Rejected','trail'=>'Reject','label'=>'Memo Rejected'],
        'return'   => ['new'=>'Returned','trail'=>'Return','label'=>'Memo Returned'],
        'escalate' => ['new'=>'Escalated','trail'=>'Escalate','label'=>'Memo Escalated'],
    ];
    if (!isset($map[$action])) {
        throw new Exception('Invalid action.');
    }
    $cfg = $map[$action];

    // update endorsement_status
    $stmt = $conn->prepare("
        UPDATE memo_through_recipients
           SET endorsement_status = ?
         WHERE memo_id = ? AND user_id = ?
    ");
    $stmt->bind_param('sii', $cfg['new'], $memoId, $userId);
    $stmt->execute();
    $stmt->close();

    // insert trail
    $stmt = $conn->prepare("
        INSERT INTO memo_trail (memo_id, user_id, action, action_type, comment)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iisss', $memoId, $userId, $cfg['new'], $cfg['trail'], $comment);
    $stmt->execute();
    $stmt->close();

    // record movement
    $toUser = ($action === 'escalate') ? $targetId : null;
    $stmt = $conn->prepare("
        INSERT INTO memo_movements (memo_id, from_user_id, to_user_id, action, comments)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiiss', $memoId, $userId, $toUser, $cfg['new'], $comment);
    $stmt->execute();
    $stmt->close();

    // endorsement-specific logic
    if ($action === 'endorse') {
        $stmt = $conn->prepare("
            INSERT INTO memo_through_endorsements (memo_id, user_id, comment, endorsed_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param('iis', $memoId, $userId, $comment);
        $stmt->execute();
        $stmt->close();
    }

    // update memo status
    $stmt = $conn->prepare("UPDATE memos SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $cfg['new'], $memoId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // determine next recipients
    $recipients = [];
    if ($action === 'escalate' && $toUser) {
        $recipients[] = $toUser;
    } else {
        $row = $conn->query("
            SELECT user_id FROM memo_through_recipients
            WHERE memo_id = {$memoId} AND endorsement_status IS NULL
            LIMIT 1
        ")->fetch_assoc();
        $recipients[] = $row ? intval($row['user_id']) : intval($conn->query("SELECT to_user_id FROM memos WHERE id = {$memoId}")->fetch_assoc()['to_user_id']);
    }

    // send clear notification
    $notifier = new MemoNotifier($conn, __DIR__ . '/ppdaememo-b656edc602b5.json');
    $notifier->notify(
        $memoId,
        $memoRef,
        $cfg['label'],
        $userId,
        $actorName,
        $recipients,
        ['comment' => $comment]
    );

    echo json_encode(['status'=>'success','message'=> ucfirst($action).' recorded.']);
}
catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
