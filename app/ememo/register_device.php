<?php
// register_device.php

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// Accept either set of names
$userId = trim($_POST['user']      ?? $_POST['user_id']   ?? '');
$token  = trim($_POST['token']     ?? $_POST['fcm_token'] ?? '');

if (!$userId || !$token) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing user or token'
    ]);
    exit;
}

// 1) Check if a record already exists for this user
$stmt = $conn->prepare("SELECT id FROM user_devices WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($existingId);

if ($stmt->fetch()) {
    // 2a) If found, update that row
    $stmt->close();
    $upd = $conn->prepare("
        UPDATE user_devices
           SET fcm_token = ?
         WHERE id = ?
    ");
    $upd->bind_param("si", $token, $existingId);
    $upd->execute();
    $upd->close();
} else {
    // 2b) Otherwise, insert a new row
    $stmt->close();
    $ins = $conn->prepare("
        INSERT INTO user_devices (user_id, fcm_token, created_at)
        VALUES (?, ?, NOW())
    ");
    $ins->bind_param("is", $userId, $token);
    $ins->execute();
    $ins->close();
}

echo json_encode(['success' => true]);
