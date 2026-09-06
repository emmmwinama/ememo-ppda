<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $userId = $_SESSION['user_id'];
    $newPassword = trim($_POST['new_password'] ?? '');

    if (empty($newPassword) && empty($_POST['signature_canvas']) && empty($_FILES['signature_file'])) {
        throw new Exception('Nothing to update.');
    }

    // Handle password update
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            throw new Exception('Password must be at least 6 characters.');
        }
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed = 1 WHERE id = ?");
        $stmt->bind_param('si', $hashedPassword, $userId);
        $stmt->execute();
    }

    // Handle signature update
    if (!empty($_FILES['signature_file']['name']) || !empty($_POST['signature_canvas'])) {
        $signaturePath = 'uploads/signatures/user_' . $userId . '.png';

        // Uploaded file
        if (!empty($_FILES['signature_file']['name'])) {
            if (!is_dir('uploads/signatures')) {
                mkdir('uploads/signatures', 0777, true);
            }
            move_uploaded_file($_FILES['signature_file']['tmp_name'], $signaturePath);
        }
        // Drawn signature (canvas)
        else if (!empty($_POST['signature_canvas'])) {
            $base64 = explode(',', $_POST['signature_canvas'])[1];
            if (!is_dir('uploads/signatures')) {
                mkdir('uploads/signatures', 0777, true);
            }
            file_put_contents($signaturePath, base64_decode($base64));
        }

        // Insert/update signature record
        $stmt = $conn->prepare("SELECT id FROM signatures WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE signatures SET image_path = ? WHERE user_id = ?");
            $stmt->bind_param('si', $signaturePath, $userId);
        } else {
            $stmt = $conn->prepare("INSERT INTO signatures (user_id, image_path) VALUES (?, ?)");
            $stmt->bind_param('is', $userId, $signaturePath);
        }
        $stmt->execute();
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
