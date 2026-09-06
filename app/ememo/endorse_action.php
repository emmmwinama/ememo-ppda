<?php
require 'db.php';
require 'auth.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

$memoId = (int)($data['memo_id'] ?? 0);
$action = $data['action'] ?? '';
$comment = trim($data['comment'] ?? '');
$targetId = (int)($data['target_id'] ?? 0); // used for escalate
$userId = $_SESSION['user_id'] ?? 0;

if (!$memoId || !$userId || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

$conn->begin_transaction();

try {
    $actionTypes = [
        'endorse' => ['status' => 'Under Review', 'trail_type' => 'Endorse'],
        'reject'  => ['status' => 'Rejected', 'trail_type' => 'Reject'],
        'return'  => ['status' => 'Returned', 'trail_type' => 'Return'],
        'escalate' => ['status' => 'Escalated', 'trail_type' => 'Escalate'],
    ];

    if (!isset($actionTypes[$action])) {
        throw new Exception("Invalid action.");
    }

    $status = $actionTypes[$action]['status'];
    $trailType = $actionTypes[$action]['trail_type'];

    // Update memo status
    $stmt = $conn->prepare("UPDATE memos SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $memoId);
    $stmt->execute();

    // Log in memo trail
    $stmt = $conn->prepare("INSERT INTO memo_trail (memo_id, user_id, action, action_type, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $memoId, $userId, $status, $trailType, $comment);
    $stmt->execute();

    // Record movement
    $stmt = $conn->prepare("INSERT INTO memo_movements (memo_id, from_user_id, to_user_id, action, comments) VALUES (?, ?, ?, ?, ?)");
    $toUser = $action === 'escalate' ? $targetId : null;
    $stmt->bind_param("iiiss", $memoId, $userId, $toUser, $status, $comment);
    $stmt->execute();

    // Handle endorsement logic
    if ($action === 'endorse') {
        $stmt = $conn->prepare("INSERT INTO memo_through_endorsements (memo_id, user_id, comment, endorsed_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $memoId, $userId, $comment);
        $stmt->execute();

        // Check if all recipients have endorsed
        $stmt = $conn->prepare("SELECT COUNT(*) FROM memo_through_recipients WHERE memo_id = ?");
        $stmt->bind_param("i", $memoId);
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch(); $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) FROM memo_through_endorsements WHERE memo_id = ?");
        $stmt->bind_param("i", $memoId);
        $stmt->execute();
        $stmt->bind_result($done);
        $stmt->fetch(); $stmt->close();

        if ($done >= $total) {
            $stmt = $conn->prepare("UPDATE memos SET status = 'Endorsed' WHERE id = ?");
            $stmt->bind_param("i", $memoId);
            $stmt->execute();
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => ucfirst($action) . ' action recorded.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
