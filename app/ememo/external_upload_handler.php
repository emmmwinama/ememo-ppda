<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

$requiredFields = ['reference_number', 'title', 'received_from', 'received_date'];

foreach ($requiredFields as $field) {
  if (empty($_POST[$field])) {
    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
    exit;
  }
}

$ref       = trim($_POST['reference_number']);
$title     = trim($_POST['title']);
$desc      = trim($_POST['description'] ?? '');
$from      = trim($_POST['received_from']);
$date      = $_POST['received_date'];
$userId    = $_SESSION['user_id'] ?? 0;
$uploadDir = 'uploads/letters/' . date('Ymd') . '/';

if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0775, true);
}

$conn->begin_transaction();

try {
  // Insert main letter
  $stmt = $conn->prepare("INSERT INTO external_letters 
    (reference_number, title, description, received_from, received_date, created_by) 
    VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("sssssi", $ref, $title, $desc, $from, $date, $userId);
  $stmt->execute();

  $letterId = $stmt->insert_id;

  // Handle files
  if (!empty($_FILES['files'])) {
    foreach ($_FILES['files']['tmp_name'] as $index => $tmpPath) {
      if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) continue;

      $originalName = basename($_FILES['files']['name'][$index]);
      $safeName     = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
      $targetPath   = $uploadDir . $safeName;

      if (move_uploaded_file($tmpPath, $targetPath)) {
        $stmtFile = $conn->prepare("INSERT INTO external_letter_files (letter_id, file_path, file_name) VALUES (?, ?, ?)");
        $stmtFile->bind_param("iss", $letterId, $targetPath, $originalName);
        $stmtFile->execute();
      }
    }
  }

  $conn->commit();
  echo json_encode(['success' => true]);

} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
