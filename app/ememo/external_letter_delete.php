<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$id) {
  echo json_encode(['success' => false, 'message' => 'Invalid letter ID']);
  exit;
}

// Check if the letter exists and is pending
$stmt = $conn->prepare("SELECT id FROM external_letters WHERE id = ? AND status = 'pending'");
if (!$stmt) {
  echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
  exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'Letter not found or not deletable']);
  exit;
}

// Delete from external_letter_files
$stmtFiles = $conn->prepare("DELETE FROM external_letter_files WHERE letter_id = ?");
if (!$stmtFiles) {
  echo json_encode(['success' => false, 'message' => 'Prepare failed (files): ' . $conn->error]);
  exit;
}
$stmtFiles->bind_param("i", $id);
$stmtFiles->execute();

// Delete from external_letters
$stmtLetter = $conn->prepare("DELETE FROM external_letters WHERE id = ?");
if (!$stmtLetter) {
  echo json_encode(['success' => false, 'message' => 'Prepare failed (letter): ' . $conn->error]);
  exit;
}
$stmtLetter->bind_param("i", $id);
$stmtLetter->execute();

echo json_encode(['success' => true]);
