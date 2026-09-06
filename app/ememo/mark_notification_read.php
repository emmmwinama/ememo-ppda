<?php
// mark_notification_read.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/auth.php';  // ensures $_SESSION['user_id']
require_once __DIR__ . '/db.php';    // provides $conn

$userId = intval($_SESSION['user_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$noteId = intval($data['id'] ?? 0);
if (!$noteId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
    exit;
}

try {
    // Ensure the notification belongs to this user
    $stmt = $conn->prepare("SELECT user_id FROM notifications WHERE id = ?");
    $stmt->bind_param('i', $noteId);
    $stmt->execute();
    $stmt->bind_result($ownerId);
    if (!$stmt->fetch() || $ownerId !== $userId) {
        // either not found or not theirs
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
    $stmt->close();

    // Mark as read
    $stmt = $conn->prepare("
        UPDATE notifications
           SET is_read = 1,
               read_at = NOW()
         WHERE id = ?
    ");
    $stmt->bind_param('i', $noteId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} catch (mysqli_sql_exception $e) {
    error_log("mark_notification_read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
