<?php
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

try {
    if (!isset($_FILES['signature'])) {
        throw new Exception("No file uploaded.");
    }

    $file = $_FILES['signature'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload error.");
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $path = "uploads/signatures/user_" . $_SESSION['user_id'] . "." . $ext;
    move_uploaded_file($file['tmp_name'], $path);

    $stmt = $conn->prepare("REPLACE INTO signatures (user_id, image_path) VALUES (?, ?)");
    $stmt->bind_param('is', $_SESSION['user_id'], $path);
    $stmt->execute();

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
