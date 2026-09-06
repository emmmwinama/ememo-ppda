<?php
require_once 'db.php';
require_once 'auth.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$stmt = $conn->prepare("
    SELECT u.full_name, p.name AS position 
    FROM users u 
    LEFT JOIN positions p ON u.position_id = p.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'full_name' => $data['full_name'] ?? '',
    'position' => $data['position'] ?? ''
]);
