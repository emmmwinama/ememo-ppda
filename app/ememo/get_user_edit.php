<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin access required.']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, full_name, username, email, phone_number, role,
           department_id, section_id, position_id,
           active, is_controlling_officer, is_secretary
    FROM users
    WHERE id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

echo json_encode(['status' => 'success', 'user' => $user]);
