<?php
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        throw new Exception('Missing user ID.');
    }

    $userId = intval($data['id']);
    if ($userId <= 0) {
        throw new Exception('Invalid user ID.');
    }

    // 1️⃣ Fetch current active status
    $stmt = $conn->prepare("SELECT active FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($currentStatus);
    
    if (!$stmt->fetch()) {
        throw new Exception('User not found.');
    }
    $stmt->close();

    // 2️⃣ Toggle the status
    $newStatus = $currentStatus ? 0 : 1;

    $updateStmt = $conn->prepare("UPDATE users SET active = ? WHERE id = ?");
    $updateStmt->bind_param('ii', $newStatus, $userId);
    $updateStmt->execute();
    $updateStmt->close();

    echo json_encode([
        'status' => 'success',
        'message' => 'User has been ' . ($newStatus ? 'activated' : 'deactivated') . '.',
        'newStatus' => $newStatus
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
