<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access.");
    }
    $userId = intval($_SESSION['user_id']);

    // Validate and sanitize input
    $letterId = isset($_POST['letter_id']) ? intval($_POST['letter_id']) : 0;
    $comment  = trim($_POST['comment'] ?? '');
    $report   = trim($_POST['report'] ?? '');

    if ($letterId <= 0 || empty($comment)) {
        throw new Exception("Missing required fields.");
    }

    // Start DB transaction
    $conn->begin_transaction();

    // 1) Insert into external_letter_comments
    $stmt = $conn->prepare("
      INSERT INTO external_letter_comments
        (letter_id, user_id, comment, created_at)
      VALUES (?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception("Prepare comment insert failed: " . $conn->error);
    $stmt->bind_param("iis", $letterId, $userId, $comment);
    if (!$stmt->execute()) throw new Exception("Comment insert failed: " . $stmt->error);
    $stmt->close();

    // 2) Insert into external_letter_actions
    $stmt = $conn->prepare("
      INSERT INTO external_letter_actions
        (letter_id, action_taken, submitted_by, submitted_at)
      VALUES (?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception("Prepare action insert failed: " . $conn->error);
    $stmt->bind_param("isi", $letterId, $report, $userId);
    if (!$stmt->execute()) throw new Exception("Action insert failed: " . $stmt->error);
    $actionId = $stmt->insert_id;
    $stmt->close();

    // 3) Optional file upload
    if (!empty($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/reports';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory.");
            }
        }

        $originalName = basename($_FILES['report_file']['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = "ltr{$letterId}_act{$actionId}_" . time() . '.' . $ext;
        $targetPath = "$uploadDir/$filename";

        if (!move_uploaded_file($_FILES['report_file']['tmp_name'], $targetPath)) {
            throw new Exception("Failed to move uploaded file.");
        }

        // Save relative path to DB
        $relativePath = "uploads/reports/$filename";
        $stmt = $conn->prepare("
          UPDATE external_letter_actions
             SET report_file_path = ?
           WHERE id = ?
        ");
        if (!$stmt) throw new Exception("Prepare update file path failed: " . $conn->error);
        $stmt->bind_param("si", $relativePath, $actionId);
        if (!$stmt->execute()) throw new Exception("Update file path failed: " . $stmt->error);
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
