<?php
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

try {
  switch ($type) {
    case 'departments':
      $result = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
      break;

    case 'sections':
      $dept_id = intval($_GET['department_id'] ?? 0);
      $result = $conn->query("SELECT id, name FROM sections WHERE department_id = $dept_id ORDER BY name ASC");
      break;

    case 'positions':
      $result = $conn->query("SELECT id, name FROM positions ORDER BY name ASC");
      break;

    case 'grades':
      $result = $conn->query("SELECT id, code FROM grades ORDER BY code ASC");
      break;

    default:
      throw new Exception("Invalid or missing type parameter.");
  }

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }

  echo json_encode($data);
} catch (Exception $e) {
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
