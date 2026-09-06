<?php
// update_memo.php

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
    $user_id      = $_SESSION['user_id'];
    $memo_id      = intval($_POST['memo_id_hidden'] ?? 0);
    $memo_ref     = trim($_POST['memo_id'] ?? '');
    $action_input = $_POST['action'] ?? 'save';  // 'save' or 'submit'

    if (!$memo_id) {
        throw new Exception('Missing memo_id.');
    }

    // Collect form fields
    $commType     = $_POST['communication_type'] ?? 'Memorandum';
    $subject      = trim($_POST['subject'] ?? '');
    $content      = trim($_POST['content'] ?? '');
    $to_user_id   = intval($_POST['to_user_id'] ?? 0);
    $through_ids  = array_filter(explode(',', $_POST['through_user_ids'] ?? ''));
    $signature    = $_POST['signature_data'] ?? null;
    $date         = $_POST['date'] ?? date('Y-m-d');

    if (!$subject || !$content || !$to_user_id) {
        throw new Exception('Please complete all required fields.');
    }

    // Load actor info
    $meta      = getUserMeta($conn, $user_id);
    $actorName = $meta['name'] ?? 'User';

    // Determine status/action and event label
    if ($action_input === 'submit') {
        if (count($through_ids) === 0) {
            $status     = 'Endorsed';
            $action     = 'Endorse';
            $eventLabel = 'Memo Endorsed';
        } else {
            $status     = 'Submitted';
            $action     = 'Submit';
            $eventLabel = 'Memo Submitted';
        }
        $to_code  = getUserPositionCode($conn, $to_user_id);
        $stage_id = determineStage($conn, $status, !empty($through_ids), $to_code);
    } else {
        // saving draft: leave stage unchanged
        $status     = 'Draft';
        $action     = 'Save';
        $eventLabel = 'Draft Saved';
        $stage_id   = $conn
            ->query("SELECT current_stage_id FROM memos WHERE id = {$memo_id}")
            ->fetch_assoc()['current_stage_id'];
    }

    // 1) UPDATE MEMO row
    if ($action_input === 'submit') {
        $upd = $conn->prepare("
            UPDATE memos
               SET communication_type = ?,
                   subject            = ?,
                   content            = ?,
                   memo_id            = ?,
                   date               = ?,
                   to_user_id         = ?,
                   status             = ?,
                   current_stage_id   = ?
             WHERE id = ?
        ");
        $upd->bind_param(
            "sssssisii",
            $commType,
            $subject,
            $content,
            $memo_ref,
            $date,
            $to_user_id,
            $status,
            $stage_id,
            $memo_id
        );
    } else {
        $upd = $conn->prepare("
            UPDATE memos
               SET communication_type = ?,
                   subject            = ?,
                   content            = ?,
                   memo_id            = ?,
                   date               = ?,
                   to_user_id         = ?
             WHERE id = ?
        ");
        $upd->bind_param(
            "sssssii",
            $commType,
            $subject,
            $content,
            $memo_ref,
            $date,
            $to_user_id,
            $memo_id
        );
    }
    $upd->execute();
    $upd->close();

    // 2) THROUGH recipients (insert if missing)
    foreach ($through_ids as $uid) {
        $chk = $conn->prepare("
            SELECT 1 FROM memo_through_recipients
             WHERE memo_id = ? AND user_id = ?
        ");
        $chk->bind_param("ii", $memo_id, $uid);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) {
            $ins = $conn->prepare("
                INSERT INTO memo_through_recipients (memo_id, user_id)
                VALUES (?, ?)
            ");
            $ins->bind_param("ii", $memo_id, $uid);
            $ins->execute();
            $ins->close();
        }
        $chk->close();
    }

    // 3) New attachments
    if (!empty($_FILES['attachments']['name'])) {
        foreach ($_FILES['attachments']['name'] as $k => $fn) {
            if (is_uploaded_file($_FILES['attachments']['tmp_name'][$k])) {
                $dest = 'uploads/' . uniqid('att_', true) . '_' . basename($fn);
                move_uploaded_file($_FILES['attachments']['tmp_name'][$k], $dest);
                $ins = $conn->prepare("
                    INSERT INTO attachments (memo_id, file_name, file_path)
                    VALUES (?, ?, ?)
                ");
                $ins->bind_param("iss", $memo_id, $fn, $dest);
                $ins->execute();
                $ins->close();
            }
        }
    }

    // 4) Signature update
    if ($signature) {
        list(, $b64) = explode(';base64,', $signature);
        $img     = base64_decode($b64);
        $sigPath = 'signatures/' . uniqid('sig_', true) . '.png';
        file_put_contents($sigPath, $img);
        chmod($sigPath, 0644);
        saveSignature(
            $conn, $memo_id, $user_id,
            'Originator', $stage_id,
            $sigPath, date('Y-m-d H:i:s')
        );
    }
	
	    // Fetch and trim letter details
$letterDate    = trim($_POST['letter_date']    ?? '');
$letterRefNo   = trim($_POST['letter_ref_no']  ?? '');
$letterSubject = trim($_POST['letter_subject'] ?? '');
$letterContent = trim($_POST['letter_content'] ?? '');

// Execute only if letter content and essential fields are provided
if (!empty($letterContent) && !empty($letterDate) && !empty($letterRefNo)) {

    // 1) Insert or update the outgoing letter
    $up = $conn->prepare("
      INSERT INTO memo_outgoing_letters
        (memo_id, letter_date, letter_ref_no, letter_subject, letter_content)
      VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        letter_date     = VALUES(letter_date),
        letter_ref_no   = VALUES(letter_ref_no),
        letter_subject  = VALUES(letter_subject),
        letter_content  = VALUES(letter_content)
    ");
    $up->bind_param(
      "issss",
      $memo_id,
      $letterDate,
      $letterRefNo,
      $letterSubject,
      $letterContent
    );
    $up->execute();
    $up->close();

    // 2) Fetch outgoing_letter_id
    $res = $conn->prepare("
      SELECT id 
      FROM memo_outgoing_letters 
      WHERE memo_id = ?
    ");
    $res->bind_param("i", $memo_id);
    $res->execute();
    $res->bind_result($outgoing_letter_id);
    if (!$res->fetch()) {
      // If fetch fails, handle gracefully
      $res->close();
      die(json_encode(['status' => 'error', 'message' => 'Failed to fetch letter ID.']));
    }
    $res->close();

    // 3) Delete old recipients
    $del = $conn->prepare("
      DELETE FROM memo_outgoing_letter_recipients 
      WHERE outgoing_letter_id = ?
    ");
    $del->bind_param("i", $outgoing_letter_id);
    $del->execute();
    $del->close();

    // 4) Insert current recipients
    $positions = $_POST['letter_recipients']['position'] ?? [];
    $addresses = $_POST['letter_recipients']['address']  ?? [];

    if (!empty($positions)) {
      $ins = $conn->prepare("
        INSERT INTO memo_outgoing_letter_recipients
          (outgoing_letter_id, position, address, sort_order)
        VALUES (?, ?, ?, ?)
      ");

      foreach ($positions as $i => $pos) {
        $addr = $addresses[$i] ?? '';
        $ins->bind_param(
          "issi",
          $outgoing_letter_id,
          $pos,
          $addr,
          $i
        );
        $ins->execute();
      }
      $ins->close();
    }

}


    // 5) ON SUBMIT: trail + movement + push
    if ($action_input === 'submit') {
        $comment = "{$eventLabel} by {$actorName}";
        insertTrail($conn, $memo_id, $user_id, $stage_id, $status, $action, $comment);

        $dest_uid = !empty($through_ids) ? intval($through_ids[0]) : $to_user_id;
        $mv = $conn->prepare("
            INSERT INTO memo_movements
                (memo_id, from_user_id, to_user_id, action, comments)
            VALUES (?, ?, ?, ?, ?)
        ");
        $mv->bind_param("iiiss", $memo_id, $user_id, $dest_uid, $status, $comment);
        $mv->execute();
        $mv->close();

       // Push notification
        $notifier = new MemoNotifier($conn, __DIR__ . '/ppdaememo-b656edc602b5.json');
        $notifier->notify(
           $memo_id,           // internal ID
           $memo_ref,          // public ref
           $eventLabel,        // e.g. "Memo Submitted"
           $user_id,           // actor
           $actorName,         // actor name
           [$dest_uid],        // recipients
            [                   // extra data
                'action'   => $action,
            'status'   => $status,
               'stage_id' => (string)$stage_id,
               'subject'  => $subject
            ]
        );
    }

    echo json_encode(['status' => 'success']);
}
catch (Exception $e) {
    error_log("Memo update error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
