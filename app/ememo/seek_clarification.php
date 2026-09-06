<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'memo_utils.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated.');
    }

    $user_id = $_SESSION['user_id'];

    $rawData = file_get_contents('php://input');
    if (!$rawData) {
        throw new Exception('No input data received.');
    }

    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input.');
    }

    $memo_id = intval($data['memo_id'] ?? 0);
    $message = trim($data['message'] ?? '');
    $user_ids = $data['user_ids'] ?? [];

    if (!$memo_id || empty($message) || !is_array($user_ids) || count($user_ids) === 0) {
        throw new Exception('Missing required fields.');
    }

    // 🔍 Resolve mentioned user full names
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $types = str_repeat('i', count($user_ids));
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$user_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    $userNames = [];
    while ($row = $result->fetch_assoc()) {
        $userNames[] = $row['full_name'];
    }

    // 📝 Insert clarification entries
    $clarStmt = $conn->prepare("
        INSERT INTO memo_clarifications (memo_id, requested_by, user_id, clarification_comment)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($user_ids as $to_user_id) {
        $clarStmt->bind_param("iiis", $memo_id, $user_id, $to_user_id, $message);
        $clarStmt->execute();
    }

    // 📜 Insert trail — tied to DG Approval stage (id = 3)
    $stageId = 3;
    $action = 'Returned'; // enum value from `memo_trail.action`
    $actionType = 'Return'; // enum value from `memo_trail.action_type`

    $trailComment = "Clarification requested to: " . implode(', ', $userNames) . "\n\nMessage:\n" . $message;

    insertTrail($conn, $memo_id, $user_id, $stageId, $action, $actionType, $trailComment);

    echo json_encode(['status' => 'success', 'message' => 'Clarification request sent successfully.']);
} catch (Exception $e) {
    error_log("Seek Clarification Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
