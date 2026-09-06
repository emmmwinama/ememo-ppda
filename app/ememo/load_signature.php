<?php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$stmt = $conn->prepare("SELECT image_path FROM signatures WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result && file_exists($result['image_path'])) {
    echo json_encode(['image' => $result['image_path']]);
} else {
    echo json_encode(['image' => null]);
}
