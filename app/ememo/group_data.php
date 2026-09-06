<?php
require_once 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'groups') {
  $result = $conn->query("SELECT id, name, description FROM groups ORDER BY name");
  echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}

elseif ($action === 'members' && isset($_GET['group_id'])) {
  $group_id = intval($_GET['group_id']);
  $stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.username, u.email
    FROM group_members gm
    JOIN users u ON u.id = gm.user_id
    WHERE gm.group_id = ?
  ");
  $stmt->bind_param('i', $group_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  echo json_encode($res);
}

else {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid or missing parameters.']);
}
