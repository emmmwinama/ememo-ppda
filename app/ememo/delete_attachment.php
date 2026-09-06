<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized.');
    }

    $attachment_id = intval($_POST['attachment_id'] ?? 0);

    if (!$attachment_id) {
        throw new Exception('Missing attachment_id.');
    }

    // Fetch attachment info first
    $stmt = $conn->prepare("SELECT file_path FROM attachments WHERE id = ?");
    $stmt->bind_param("i", $attachment_id);
    $stmt->execute();
    $stmt->bind_result($file_path);
    if (!$stmt->fetch()) {
        throw new Exception('Attachment not found.');
    }
    $stmt->close();

    // Delete the file from the server
    if ($file_path && file_exists($file_path)) {
        unlink($file_path); // Delete physical file
    }

    // Delete the record from the database
    $stmt = $conn->prepare("DELETE FROM attachments WHERE id = ?");
    $stmt->bind_param("i", $attachment_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception('Failed to delete attachment record.');
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
