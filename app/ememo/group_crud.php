<?php
require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'create_group') {
  $name = trim($data['name'] ?? '');
  $description = trim($data['description'] ?? '');
  if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Group name is required']);
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO groups (name, description) VALUES (?, ?)");
  $stmt->bind_param('ss', $name, $description);
  $stmt->execute();
  echo json_encode(['status' => 'success']);
}

elseif ($action === 'add_member') {
  $group_id = intval($data['group_id']);
  $user_id = intval($data['user_id']);
  $stmt = $conn->prepare("REPLACE INTO group_members (group_id, user_id) VALUES (?, ?)");
  $stmt->bind_param('ii', $group_id, $user_id);
  $stmt->execute();
  echo json_encode(['status' => 'success']);
}

elseif ($action === 'remove_member') {
  $group_id = intval($data['group_id']);
  $user_id = intval($data['user_id']);
  $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
  $stmt->bind_param('ii', $group_id, $user_id);
  $stmt->execute();
  echo json_encode(['status' => 'success']);
}

else {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
