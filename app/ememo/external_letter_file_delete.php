<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid request method']);
  exit;
}

$fileId = intval($_POST['file_id'] ?? 0); // ✅ corrected: 'file_id' not 'id'
if (!$fileId) {
  echo json_encode(['success' => false, 'message' => 'Missing or invalid file ID']);
  exit;
}

// Fetch the file path from DB
$stmt = $conn->prepare("SELECT file_path FROM external_letter_files WHERE id = ?");
if (!$stmt) {
  echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
  exit;
}
$stmt->bind_param("i", $fileId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'File not found']);
  exit;
}
$file = $result->fetch_assoc();
$filePath = $file['file_path'];

// Delete from file system
if (file_exists($filePath)) {
  unlink($filePath);
}

// Delete from DB
$delStmt = $conn->prepare("DELETE FROM external_letter_files WHERE id = ?");
if (!$delStmt) {
  echo json_encode(['success' => false, 'message' => 'Delete prepare failed: ' . $conn->error]);
  exit;
}
$delStmt->bind_param("i", $fileId);
if ($delStmt->execute()) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to delete from database']);
}
