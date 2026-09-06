<?php
require_once 'auth.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT image_path FROM signatures WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result && file_exists($result['image_path'])) {
    echo json_encode(['status' => 'success', 'path' => $result['image_path']]);
} else {
    echo json_encode(['status' => 'error']);
}
?>
