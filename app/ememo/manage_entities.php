<?php
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

try {
  $data = json_decode(file_get_contents('php://input'), true);
  $type = $data['type'] ?? '';

  if (!$type) {
    throw new Exception("Missing type parameter.");
  }

  switch ($type) {
    case 'department':
      $name = trim($data['name'] ?? '');
      if (!$name) throw new Exception("Department name required.");
      $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
      $stmt->bind_param('s', $name);
      break;

    case 'section':
      $name = trim($data['name'] ?? '');
      $department_id = intval($data['department_id'] ?? 0);
      if (!$name || !$department_id) throw new Exception("Section name and department required.");
      $stmt = $conn->prepare("INSERT INTO sections (name, department_id) VALUES (?, ?)");
      $stmt->bind_param('si', $name, $department_id);
      break;

    case 'position':
      $name = trim($data['name'] ?? '');
      $short_name = trim($data['short_name'] ?? '');
      $position_code = intval($data['position_code'] ?? 0);
      if (!$name || !$position_code) throw new Exception("Position name and grade required.");
      $stmt = $conn->prepare("INSERT INTO positions (position_code, name, short_name) VALUES (?, ?, ?)");
      $stmt->bind_param('iss', $position_code, $name, $short_name);
      break;

    case 'grade':
      $code = trim($data['code'] ?? '');
      if (!$code) throw new Exception("Grade code required.");
      $stmt = $conn->prepare("INSERT INTO grades (code) VALUES (?)");
      $stmt->bind_param('s', $code);
      break;

    case 'signature':
      $user_id = intval($data['user_id'] ?? 0);
      $image_path = trim($data['image_path'] ?? '');
      if (!$user_id || !$image_path) throw new Exception("User ID and image path required for signature.");

      // Check if signature already exists
      $check = $conn->prepare("SELECT id FROM signatures WHERE user_id = ?");
      $check->bind_param('i', $user_id);
      $check->execute();
      $result = $check->get_result();

      if ($result->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE signatures SET image_path = ? WHERE user_id = ?");
        $stmt->bind_param('si', $image_path, $user_id);
      } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO signatures (user_id, image_path) VALUES (?, ?)");
        $stmt->bind_param('is', $user_id, $image_path);
      }
      break;

    default:
      throw new Exception("Invalid type provided.");
  }

  $stmt->execute();
  echo json_encode(['status' => 'success', 'message' => ucfirst($type) . " saved successfully."]);

} catch (Exception $e) {
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
