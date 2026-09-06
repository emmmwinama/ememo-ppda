<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
  $result = $conn->query("
    SELECT 
      id, 
      full_name, 
      username, 
      email, 
      role, 
      active 
    FROM users 
    ORDER BY full_name ASC
  ");

  $users = [];
  while ($row = $result->fetch_assoc()) {
    $users[] = $row;
  }

  echo json_encode($users);
} catch (Exception $e) {
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
