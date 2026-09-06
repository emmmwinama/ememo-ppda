<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            m.id, 
            m.memo_id, 
            m.subject, 
            m.status AS memo_status, -- ✅ get the memo status
            r.endorsement_status, 
            COALESCE(s.name, 'N/A') AS stage, 
            u.full_name AS sender_name,
            (SELECT comment FROM memo_trail 
             WHERE memo_id = m.id AND action = 'Returned' 
             ORDER BY action_time DESC 
             LIMIT 1) AS return_comment -- ✅ get the return comment if exists
        FROM memo_through_recipients r
        INNER JOIN memos m ON r.memo_id = m.id
        INNER JOIN users u ON m.originator_id = u.id
        LEFT JOIN approval_stages s ON m.current_stage_id = s.id
        WHERE r.user_id = ? 
          AND m.status != 'Draft'
        ORDER BY m.created_at DESC
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $all = [];
    while ($row = $result->fetch_assoc()) {
        $all[] = [
            'id' => (int)$row['id'],
            'memo_id' => $row['memo_id'],
            'subject' => $row['subject'],
            'memo_status' => $row['memo_status'], // ✅
            'endorsement_status' => $row['endorsement_status'],
            'stage' => $row['stage'],
            'sender_name' => $row['sender_name'],
            'return_comment' => $row['return_comment'], // ✅
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $all]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $e->getMessage()]);
}
?>
