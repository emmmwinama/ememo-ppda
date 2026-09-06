<?php
// external_assigned_process.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
// 1) Validate inputs
$userId   = intval($_SESSION['user_id'] ?? 0);
$letterId = intval($_POST['letter_id'] ?? 0);   // ← changed here
$comment  = trim($_POST['comment']     ?? '');
$report   = trim($_POST['report']      ?? '');

if (!$userId || !$letterId || $comment === '') {
    echo json_encode([
      'success' => false,
      'message' => 'Missing required fields (user, letter, or comment).'
    ]);
    exit;
}


try {
    $conn->begin_transaction();

    // 2) Insert into external_letter_comments
    $stmt = $conn->prepare("
      INSERT INTO external_letter_comments
        (letter_id, user_id, comment, created_at)
      VALUES (?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception("Prepare comments failed: " . $conn->error);
    $stmt->bind_param("iis", $letterId, $userId, $comment);
    if (!$stmt->execute()) throw new Exception("Execute comments failed: " . $stmt->error);
    $stmt->close();

    // 3) Insert into external_letter_actions, capture its ID
    $stmt = $conn->prepare("
      INSERT INTO external_letter_actions
        (letter_id, action_taken, submitted_by, submitted_at)
      VALUES (?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception("Prepare actions failed: " . $conn->error);
    $stmt->bind_param("isi", $letterId, $report, $userId);
    if (!$stmt->execute()) throw new Exception("Execute actions failed: " . $stmt->error);
    $actionId = $stmt->insert_id;
    $stmt->close();

    // 4) Handle optional file upload
    if (!empty($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/reports';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory: $uploadDir");
        }
        $ext      = pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION);
        $filename = "ltr{$letterId}_act{$actionId}_" . time() . ".{$ext}";
        $dest     = "$uploadDir/$filename";

        if (!move_uploaded_file($_FILES['report_file']['tmp_name'], $dest)) {
            throw new Exception("move_uploaded_file failed to $dest");
        }

        // update the action row with the file path
        $relPath = "uploads/reports/$filename";
        $stmt = $conn->prepare("
          UPDATE external_letter_actions
             SET report_file_path = ?
           WHERE id = ?
        ");
        if (!$stmt) throw new Exception("Prepare update action file failed: " . $conn->error);
        $stmt->bind_param("si", $relPath, $actionId);
        if (!$stmt->execute()) throw new Exception("Execute update action file failed: " . $stmt->error);
        $stmt->close();
    }

    // 5) Mark the delegation as completed
    $stmt = $conn->prepare("
      UPDATE external_letter_delegation
         SET status = 'completed'
       WHERE letter_id    = ?
         AND delegated_to = ?
         AND status       = 'pending'
    ");
    if (!$stmt) throw new Exception("Prepare delegation update failed: " . $conn->error);
    $stmt->bind_param("ii", $letterId, $userId);
    if (!$stmt->execute()) throw new Exception("Execute delegation update failed: " . $stmt->error);
    $stmt->close();

    // 6) Commit & respond
    $conn->commit();
    echo json_encode(['success'=>true]);
} catch (Exception $ex) {
    $conn->rollback();
    // expose the real error once, then remove for production
    echo json_encode([
      'success'=>false,
      'message'=>"Error: " . $ex->getMessage()
    ]);
}
