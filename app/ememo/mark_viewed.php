<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
$data = json_decode(file_get_contents('php://input'), true);

$memoId = (int)($data['memo_id'] ?? 0);
$type = $data['type'] ?? '';

if (!$userId || !$memoId || !in_array($type, ['Direct Memo', 'Workflow Memo'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

try {
    // Check if already viewed
    $stmt = $conn->prepare("SELECT 1 FROM memo_views WHERE memo_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $memoId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Insert view
        $stmt = $conn->prepare("INSERT INTO memo_views (memo_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $memoId, $userId);
        $stmt->execute();
    }

    echo json_encode(['status' => 'success', 'message' => 'View recorded.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
