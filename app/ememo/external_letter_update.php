<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid request method']);
  exit;
}

$id = intval($_POST['id'] ?? 0);
$reference_number = trim($_POST['reference_number'] ?? '');
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$received_from = trim($_POST['received_from'] ?? '');
$received_date = $_POST['received_date'] ?? '';

if (!$id || !$reference_number || !$title || !$received_from || !$received_date) {
  echo json_encode(['success' => false, 'message' => 'Missing required fields']);
  exit;
}

// Update main letter record
$updateStmt = $conn->prepare("UPDATE external_letters SET reference_number=?, title=?, description=?, received_from=?, received_date=? WHERE id=?");
$updateStmt->bind_param("sssssi", $reference_number, $title, $description, $received_from, $received_date, $id);

if (!$updateStmt->execute()) {
  echo json_encode(['success' => false, 'message' => 'Database update failed']);
  exit;
}

// Handle new file uploads (note the correct key: 'new_files')
if (!empty($_FILES['new_files']['name'][0])) {
  $uploadDir = 'uploads/';
  foreach ($_FILES['new_files']['name'] as $index => $name) {
    $tmpName = $_FILES['new_files']['tmp_name'][$index];
    $uniqueName = uniqid() . '_' . basename($name);
    $destination = $uploadDir . $uniqueName;

    if (move_uploaded_file($tmpName, $destination)) {
      $stmt = $conn->prepare("INSERT INTO external_letter_files (letter_id, file_path, file_name) VALUES (?, ?, ?)");
      $stmt->bind_param("iss", $id, $destination, $name);
      $stmt->execute();
    }
  }
}

echo json_encode(['success' => true, 'message' => 'Letter updated successfully']);
