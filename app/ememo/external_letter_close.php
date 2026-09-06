<?php
// external_letter_close.php

session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// 1) Only controlling officer may close
if (empty($_SESSION['is_controlling_officer'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$userId   = intval($_SESSION['user_id']);
$letterId = intval($_POST['id'] ?? 0);
$comment  = trim($_POST['comment']  ?? '');

if (!$letterId) {
    echo json_encode(['success'=>false,'message'=>'Missing letter ID']);
    exit;
}

$conn->begin_transaction();

try {
    // 2) Record the closing comment as an action
    $stmt = $conn->prepare("
      INSERT INTO external_letter_actions
        (letter_id, action_taken, submitted_by, submitted_at)
      VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("isi", $letterId, $comment, $userId);
    $stmt->execute();
    $stmt->close();

    // ✅ 2b) ALSO record it as a comment
    if (!empty($comment)) {
        $stmt = $conn->prepare("
          INSERT INTO external_letter_comments
            (letter_id, user_id, comment, created_at)
          VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $letterId, $userId, $comment);
        $stmt->execute();
        $stmt->close();
    }

    // 3) Handle uploaded file (optional)
    if (!empty($_FILES['close_file']['name']) && $_FILES['close_file']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['close_file'];
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uploadDir = __DIR__ . '/uploads/reports';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = "close_{$letterId}_" . time() . ".$ext";
        $dest     = "$uploadDir/$filename";
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception("Failed to move uploaded file");
        }
        $relPath = "uploads/reports/$filename";

        // insert into report files table
        $stmt = $conn->prepare("
          INSERT INTO external_letter_report_files
            (letter_id, user_id, file_path)
          VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $letterId, $userId, $relPath);
        $stmt->execute();
        $stmt->close();
    }

    // 4) Finally, mark letter as closed
    $stmt = $conn->prepare("
      UPDATE external_letters
         SET status = 'closed'
       WHERE id = ?
    ");
    $stmt->bind_param("i", $letterId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success'=>true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
