<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

$memo_id  = intval($_GET['memo_id']  ?? 0);
$user_id  = $_SESSION['user_id']   ?? 0;
$response = ['status' => 'error', 'message' => 'Invalid request.'];

if ($memo_id) {
    //
    // 1) Fetch the memo itself + stage name
    //
    $stmt = $conn->prepare("
        SELECT m.*, s.name AS stage
          FROM memos m
     LEFT JOIN approval_stages s ON m.current_stage_id = s.id
         WHERE m.id = ?
    ");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $memo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$memo) {
        echo json_encode(['status'=>'error','message'=>'Memo not found.']);
        exit;
    }

    //
    // 2) TO user (only id and name)
    //
    $to_user = null;
    if (!empty($memo['to_user_id'])) {
        $stmt = $conn->prepare("
            SELECT id, full_name AS name
              FROM users
             WHERE id = ?
        ");
        $stmt->bind_param("i", $memo['to_user_id']);
        $stmt->execute();
        $to_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    //
    // 3) THROUGH users (only id and name)
    //
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name AS name
          FROM memo_through_recipients mtr
          JOIN users u ON mtr.user_id = u.id
         WHERE mtr.memo_id = ?
    ");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $through_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    //
    // 4) Attachments
    //
    $stmt = $conn->prepare("
        SELECT id, file_name, file_path
          FROM attachments
         WHERE memo_id = ?
    ");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    //
    // 5) Originator signature
    //
    $originator_signature = null;
    $stmt = $conn->prepare("
        SELECT signature_path
          FROM memo_signatures
         WHERE memo_id = ? AND user_id = ?
         ORDER BY signed_at DESC
         LIMIT 1
    ");
    $stmt->bind_param("ii", $memo_id, $memo['originator_id']);
    $stmt->execute();
    $stmt->bind_result($sig_path);
    if ($stmt->fetch()) {
        $originator_signature = $sig_path;
    }
    $stmt->close();

    //
    // 6) Outgoing‐letter draft
    //
    $outgoing_letter = null;
    $stmt = $conn->prepare("
        SELECT
          id               AS letter_id,
          letter_date,
          letter_ref_no,
          letter_subject,
          letter_content,
          letter_status,
          drafted_by,
          signed_by,
          signed_at,
          letter_signature
        FROM memo_outgoing_letters
       WHERE memo_id = ?
    ");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $ol = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($ol) {
        // fetch its recipients
        $stmt = $conn->prepare("
            SELECT position, address
              FROM memo_outgoing_letter_recipients
             WHERE outgoing_letter_id = ?
             ORDER BY sort_order ASC
        ");
        $stmt->bind_param("i", $ol['letter_id']);
        $stmt->execute();
        $recs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $ol['recipients']   = $recs;
        $outgoing_letter    = $ol;
    }

    //
    // 7) Build response
    //
    $response = [
        'status'               => 'success',
        'memo'                 => $memo,
        'to_user'              => $to_user,
        'through_users'        => $through_users,
        'attachments'          => $attachments,
        'originator_signature' => $originator_signature,
        'outgoing_letter'      => $outgoing_letter,
        'current_user_id'      => $user_id
    ];
}

echo json_encode($response);
