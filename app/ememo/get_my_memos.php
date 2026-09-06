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
            m.status, 
            COALESCE(s.name, 'N/A') AS stage, 
            m.created_at,

            -- Fetch return comment if returned
            (SELECT t.comment 
             FROM memo_trail t
             WHERE t.memo_id = m.id 
               AND t.action = 'Returned'
             ORDER BY t.action_time DESC 
             LIMIT 1
            ) AS return_comment,

            -- Fetch return time
            (SELECT t.action_time
             FROM memo_trail t
             WHERE t.memo_id = m.id
               AND t.action = 'Returned'
             ORDER BY t.action_time DESC
             LIMIT 1
            ) AS return_time,

            -- Fetch who returned
            (SELECT u.full_name
             FROM memo_trail t
             JOIN users u ON u.id = t.user_id
             WHERE t.memo_id = m.id
               AND t.action = 'Returned'
             ORDER BY t.action_time DESC
             LIMIT 1
            ) AS returned_by

        FROM memos m
        LEFT JOIN approval_stages s ON m.current_stage_id = s.id
        WHERE m.originator_id = ?
        ORDER BY m.created_at DESC
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $memos = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $memos]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
