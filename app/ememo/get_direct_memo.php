<?php
require_once 'db.php';
require_once 'auth.php';
header('Content-Type: application/json');

$memoId = $_GET['memo_id'] ?? null;

if (!$memoId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

try {
    // Fetch memo details
    $stmt = $conn->prepare("
        SELECT dm.id, dm.memo_id, dm.reference_number, dm.subject, dm.content, dm.status, dm.created_at, dm.memo_type,
               u.full_name AS originator_name, p.name AS originator_position, dm.signature_data
        FROM direct_memos dm
        LEFT JOIN users u ON dm.from_user_id = u.id
        LEFT JOIN positions p ON u.position_id = p.id
        WHERE dm.id = ?
    ");
    $stmt->bind_param("i", $memoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $memo = $result->fetch_assoc();

    if (!$memo) {
        echo json_encode(['status' => 'error', 'message' => 'Memo not found.']);
        exit;
    }

    // Fetch recipients
    $stmt = $conn->prepare("
        SELECT dmr.recipient_type, dmr.recipient_id, dmr.recipient_role,
               u.full_name, pos.name AS position_name, g.name AS group_name
        FROM direct_memo_recipients dmr
        LEFT JOIN users u ON dmr.recipient_type = 'user' AND dmr.recipient_id = u.id
        LEFT JOIN positions pos ON u.position_id = pos.id
        LEFT JOIN groups g ON dmr.recipient_type = 'group' AND dmr.recipient_id = g.id
        WHERE dmr.direct_memo_id = ?
    ");
    $stmt->bind_param("i", $memoId);
    $stmt->execute();
    $recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Separate recipients into 'To' and 'CC'
    $toRecipients = [];
    $ccRecipients = [];

    foreach ($recipients as $recipient) {
        $recipientInfo = [
            'type' => $recipient['recipient_type'],
            'id' => $recipient['recipient_id'],
            'name' => $recipient['recipient_type'] === 'user' ? $recipient['full_name'] : $recipient['group_name'],
            'position' => $recipient['recipient_type'] === 'user' ? $recipient['position_name'] : null
        ];

        if ($recipient['recipient_role'] === 'To') {
            $toRecipients[] = $recipientInfo;
        } elseif ($recipient['recipient_role'] === 'CC') {
            $ccRecipients[] = $recipientInfo;
        }
    }

    $memo['to_recipients'] = $toRecipients;
    $memo['cc_recipients'] = $ccRecipients;

    // Fetch attachments
    $stmt = $conn->prepare("
        SELECT file_name, file_path
        FROM direct_memo_attachments
        WHERE memo_id = ?
    ");
    $stmt->bind_param("i", $memoId);
    $stmt->execute();
    $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $memo['attachments'] = $attachments;

  

    // Fetch views
    $stmt = $conn->prepare("
        SELECT u.full_name, dv.viewed_at
        FROM memo_views dv
        JOIN users u ON dv.user_id = u.id
        WHERE dv.memo_id = ?
        ORDER BY dv.viewed_at ASC
    ");
    $stmt->bind_param("i", $memoId);
    $stmt->execute();
    $views = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $memo['views'] = $views;

    echo json_encode(['status' => 'success', 'data' => $memo]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
