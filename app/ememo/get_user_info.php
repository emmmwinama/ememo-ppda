<?php
require 'auth.php';
require 'db.php'; // Assumes $conn is your active MySQLi connection

header('Content-Type: application/json');

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
  echo json_encode(['status' => 'error', 'message' => 'User ID is required.']);
  exit;
}

$sql = "
  SELECT 
    u.id, u.full_name, p.name AS position, s.image_path AS signature_path
  FROM users u
  LEFT JOIN positions p ON u.position_id = p.id
  LEFT JOIN (
    SELECT user_id, image_path
    FROM signatures
    WHERE id IN (
      SELECT MAX(id) FROM signatures GROUP BY user_id
    )
  ) s ON u.id = s.user_id
  WHERE u.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
  echo json_encode(['status' => 'success', 'user' => $user]);
} else {
  echo json_encode(['status' => 'error', 'message' => 'User not found.']);
}
