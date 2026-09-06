<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized.');
    }

    $memo_id = intval($_POST['memo_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);

    if (!$memo_id || !$user_id) {
        throw new Exception('Missing memo_id or user_id.');
    }

    // Delete the through recipient
    $stmt = $conn->prepare("DELETE FROM memo_through_recipients WHERE memo_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $memo_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception('No record deleted. Maybe already removed?');
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
