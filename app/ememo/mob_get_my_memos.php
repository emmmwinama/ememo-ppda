<?php
require_once 'db.php';
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_POST['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

try {
    $payload = [];

    // 1️⃣ Originated Workflow Memos
    $stmt = $conn->prepare("
        SELECT 
            m.id, m.memo_id, m.subject, m.status, m.created_at,
            COALESCE(s.name, 'N/A') AS stage,
            'workflow' AS memo_type
        FROM memos m
        LEFT JOIN approval_stages s ON m.current_stage_id = s.id
        WHERE m.originator_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $payload['workflow'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2️⃣ Direct Inbox Memos
    $stmt = $conn->prepare("
        SELECT 
            i.memo_id AS id,
            m.memo_id,
            m.subject,
            m.status,
            m.created_at,
            'direct' AS memo_type
        FROM inbox i
        JOIN memos m ON i.memo_id = m.id
        WHERE i.user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $payload['direct'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3️⃣ Memos where user is in THROUGH list
    $stmt = $conn->prepare("
        SELECT 
            m.id, m.memo_id, m.subject, m.status, m.created_at,
            'through' AS memo_type
        FROM memo_through_recipients t
        JOIN memos m ON t.memo_id = m.id
        WHERE t.user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $payload['through'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 4️⃣ Memos Assigned as Approver (To User)
    $stmt = $conn->prepare("
        SELECT 
            m.id, m.memo_id, m.subject, m.status, m.created_at,
            'pending_approval' AS memo_type
        FROM memos m
        WHERE m.to_user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $payload['pending'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 5️⃣ Returned Memos (Optional: Only latest action returned)
    $stmt = $conn->prepare("
        SELECT 
            DISTINCT m.id, m.memo_id, m.subject, m.status, m.created_at,
            'returned' AS memo_type
        FROM memos m
        JOIN memo_trail t ON t.memo_id = m.id
        WHERE t.user_id = ? AND t.action = 'Returned'
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $payload['returned'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $payload
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
