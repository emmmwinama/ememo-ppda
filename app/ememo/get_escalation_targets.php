<?php
require 'db.php'; // Database connection
require 'auth.php'; // Authentication

$memoId = $_GET['memo_id'] ?? null;
if (!$memoId) {
  echo json_encode(['status' => 'error', 'message' => 'Missing memo_id']);
  exit;
}

// Prepare and execute the SQL statement
$stmt = $conn->prepare("
  SELECT u.id, u.full_name
  FROM users u
  INNER JOIN positions p ON u.position_id = p.id
  WHERE p.position_code <= 4
    AND u.id NOT IN (
      SELECT to_user_id FROM memo_movements WHERE memo_id = ?
    )
  ORDER BY u.full_name
");
$stmt->bind_param("i", $memoId);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

// Return the result as JSON
echo json_encode(['status' => 'success', 'users' => $users]);
?>
