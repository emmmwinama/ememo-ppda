<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
$data   = json_decode(file_get_contents('php://input'), true);
$memoId = (int)($data['memo_id'] ?? 0);
$type   = $data['memo_type'] ?? '';

if (!$userId || !$memoId || !in_array($type, ['Workflow', 'Direct'])) {
    echo json_encode(['status'=>'error','message'=>'Invalid input.']);
    exit;
}

try {
    // 1) inbox_views
    $stmt = $conn->prepare("
        INSERT INTO inbox_views (user_id, memo_id, memo_type)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iis", $userId, $memoId, $type);
    $stmt->execute();

    // 2) memo_views
    $stmt2 = $conn->prepare("
        INSERT IGNORE INTO memo_views (memo_id, user_id)
        VALUES (?, ?)
    ");
    $stmt2->bind_param("ii", $memoId, $userId);
    $stmt2->execute();

    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Server error: '.$e->getMessage()]);
}
