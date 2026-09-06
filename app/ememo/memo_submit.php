<?php
// memo_create.php

session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'memo_utils.php';
require_once 'MemoNotifier.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated.');
    }

    // 1) Inputs & status/action logic
    $fromUserId       = $_SESSION['user_id'];
    $toUserId         = intval($_POST['to_id'] ?? 0);
    $memoRef          = trim($_POST['memo_id'] ?? '');
    $throughIds       = isset($_POST['through_ids'])
                          ? array_filter(explode(',', $_POST['through_ids']))
                          : [];
    $commType         = trim($_POST['communication_type'] ?? '');
    $subject          = trim($_POST['subject'] ?? '');
    $content          = trim($_POST['content'] ?? '');
    $incomingStatus   = $_POST['status'] ?? 'Draft';  // 'Draft' or 'Submitted'
    $sigType          = $_POST['signature_type'] ?? 'new';
    $sigData          = $_POST['signature_data'] ?? null;

    if (!$memoRef || !$toUserId || !$commType || !$subject || !$content) {
        throw new Exception('Please fill in all required fields.');
    }

    // Determine status/action
    if ($incomingStatus === 'Submitted') {
        if (count($throughIds) === 0) {
            $status     = 'Endorsed';
            $action     = 'Endorse';
            $eventLabel = 'Memo Endorsed';
        } else {
            $status     = 'Submitted';
            $action     = 'Submit';
            $eventLabel = 'Memo Submitted';
        }
    } else {
        $status     = 'Draft';
        $action     = 'Save';
        $eventLabel = 'Memo Saved';
    }

    // 2) Meta & stage
    $meta      = getUserMeta($conn, $fromUserId);
    $actorName = $meta['name'] ?? 'User';
    $toCode    = getUserPositionCode($conn, $toUserId);
    $stageId   = determineStage($conn, $status, count($throughIds) > 0, $toCode);

    // 3) Insert memo
    $stmt = $conn->prepare("
        INSERT INTO memos (
            memo_id, communication_type, subject, content,
            section_id, originator_id, from_user_id, to_user_id,
            status, current_stage_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssiiiisi",
        $memoRef, $commType, $subject, $content,
        $meta['section_id'], $fromUserId, $fromUserId,
        $toUserId, $status, $stageId
    );
    $stmt->execute();
    $memoDbId = $stmt->insert_id;
    $stmt->close();

    // 4) Signature handling (unchanged)…
    if ($sigType === 'new' && $sigData) {
        list(, $b64) = explode(';base64,', $sigData);
        $img     = base64_decode($b64);
        $sigPath = 'signatures/' . uniqid('sig_', true) . '.png';
        file_put_contents($sigPath, $img);
        chmod($sigPath, 0644);
    } else {
        $sigStmt = $conn->prepare("
            SELECT image_path FROM signatures
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $sigStmt->bind_param("i", $fromUserId);
        $sigStmt->execute();
        $sigStmt->bind_result($sigPath);
        $sigStmt->fetch();
        $sigStmt->close();
    }
    saveSignature($conn, $memoDbId, $fromUserId, 'Originator', $stageId, $sigPath, date('Y-m-d H:i:s'));

    // 5) Endorsers placeholders
    foreach ($throughIds as $uid) {
        $ins = $conn->prepare("
            INSERT IGNORE INTO memo_through_recipients (memo_id, user_id)
            VALUES (?, ?)
        ");
        $ins->bind_param("ii", $memoDbId, $uid);
        $ins->execute();
        $ins->close();
    }
    addSignaturePlaceholders($conn, $memoDbId, $throughIds, 'Endorser', $stageId);
	
	
// 5.1) Draft-letter storage (if the user drafted one)
if (!empty($_POST['letter_content']) && trim($_POST['letter_content']) !== '') {
    // Gather letter fields
    $letterDate    = trim($_POST['letter_date'] ?? '');
    if ($letterDate === '') {
        $letterDate = date('Y-m-d');            // column is DATE NOT NULL
    }
    $letterRefNo   = trim($_POST['letter_ref_no']  ?? '');
    $letterSubject = trim($_POST['letter_subject'] ?? '');
    $letterHtml    = trim($_POST['letter_content']);

    // Insert into memo_outgoing_letters
    $lit = $conn->prepare("
        INSERT INTO memo_outgoing_letters
          (memo_id,
           letter_date,
           letter_ref_no,
           letter_subject,
           letter_content,
           letter_status,
           drafted_by,
           letter_signature)
        VALUES (?, ?, ?, ?, ?, 'Draft', ?, ?)
    ");
    $lit->bind_param(
        "issssis",
        $memoDbId,       // i: memo_id
        $letterDate,     // s: letter_date
        $letterRefNo,    // s: letter_ref_no
        $letterSubject,  // s: letter_subject
        $letterHtml,     // s: letter_content
        $fromUserId,     // i: drafted_by
        $sigPath         // s: letter_signature
    );
    $lit->execute();

    // grab the PK of what we just inserted
    $letterId = $lit->insert_id;
    $lit->close();

    // Insert each physical recipient
    if (
        !empty($_POST['letter_recipients']['position'])
        && count($_POST['letter_recipients']['position'])
           === count($_POST['letter_recipients']['address'])
    ) {
        $rpt = $conn->prepare("
            INSERT INTO memo_outgoing_letter_recipients
              (outgoing_letter_id, position, address, sort_order)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($_POST['letter_recipients']['position'] as $i => $pos) {
            $position = trim($pos);
            $address  = trim($_POST['letter_recipients']['address'][$i]);
            $sort     = $i + 1;

            if ($position !== '' && $address !== '') {
                $rpt->bind_param(
                    "issi",
                    $letterId,  // i: outgoing_letter_id
                    $position,  // s: position
                    $address,   // s: address
                    $sort       // i: sort_order
                );
                $rpt->execute();
            }
        }
        $rpt->close();
    }
}




    // 6) Approver placeholder
    addSignaturePlaceholders($conn, $memoDbId, [$toUserId], 'Approver', $stageId);

    // 7) Trail, movement & push notification
    if ($status !== 'Draft') {
        $comment = "{$eventLabel} by {$actorName}";
        insertTrail($conn, $memoDbId, $fromUserId, $stageId, $status, $action, $comment);

        // Determine notify target
        $notifyUserId = count($throughIds) ? intval($throughIds[0]) : $toUserId;

        // Record movement
        $mv = $conn->prepare("
            INSERT INTO memo_movements
                (memo_id, from_user_id, to_user_id, action, comments)
            VALUES (?, ?, ?, ?, ?)
        ");
        $mv->bind_param("iiiss", $memoDbId, $fromUserId, $notifyUserId, $status, $comment);
        $mv->execute();
        $mv->close();

        // Push notification
        $notifier = new MemoNotifier($conn, __DIR__ . '/ppdaememo-b656edc602b5.json');
       $notifier->notify(
           $memoDbId,         // internal PK
           $memoRef,          // public memo number
           $eventLabel,       // clear event label
           $fromUserId,       // actor
           $actorName,        // actor name
           [$notifyUserId],   // recipients
           [
               'action'   => $action,
               'status'   => $status,
               'stage_id' => (string)$stageId,
               'subject'  => $subject
           ]
       );
    }

    // 8) Attachments
    if (!empty($_FILES['attachments'])) {
        saveAttachments($conn, $memoDbId, $_FILES['attachments']);
    }

    echo json_encode(['status' => 'success']);
}
catch (Exception $e) {
    error_log("Memo create error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
