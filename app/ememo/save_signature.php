<?php
require_once 'auth.php';
require_once 'db.php';

if (!isset($_POST['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'No image data received.']);
    exit;
}

$userId = $_SESSION['user_id'];
$imageData = $_POST['image'];

// Decode base64 image
$imageParts = explode(";base64,", $imageData);
$imageBase64 = base64_decode($imageParts[1]);
$filename = 'signatures/' . uniqid('sig_', true) . '.png';

// Save image to server
if (!file_put_contents($filename, $imageBase64)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save image.']);
    exit;
}

// Insert or update the latest signature
$conn->query("DELETE FROM signatures WHERE user_id = $userId"); // only one per user
$stmt = $conn->prepare("INSERT INTO signatures (user_id, image_path) VALUES (?, ?)");
$stmt->bind_param("is", $userId, $filename);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'image' => $filename]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB insert failed']);
}
