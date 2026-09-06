<?php
// Turn on full error reporting so we see exactly what’s failing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();
require_once 'auth.php';   // ensures $_SESSION['user_id']
require_once 'db.php';     // provides $conn

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(401);
    exit(json_encode(['status'=>'error','message'=>'Not authenticated.']));
}

// 1) Validate passwords
$new  = $_POST['new_password']     ?? '';
$conf = $_POST['confirm_password'] ?? '';
if (!$new || !$conf || $new !== $conf) {
    http_response_code(400);
    exit(json_encode([
        'status'=>'error',
        'message'=>'Passwords missing or do not match.'
    ]));
}

// 2) Handle signature upload
if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode([
        'status'=>'error',
        'message'=>'Signature is required.'
    ]));
}

$file = $_FILES['signature'];
$info = @getimagesize($file['tmp_name']);
if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG])) {
    http_response_code(400);
    exit(json_encode([
        'status'=>'error',
        'message'=>'Signature must be a PNG or JPEG image.'
    ]));
}

$ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';

// 3) Determine upload directory one level up into app/ememo
$uploadDir = dirname(__DIR__) . '/app/ememo/uploads/signatures';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename    = "user_{$userId}_" . time() . ".{$ext}";
$destination = "{$uploadDir}/{$filename}";

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    exit(json_encode([
        'status'=>'error',
        'message'=>'Failed to save signature file.'
    ]));
}

// Store relative path for main app
$imagePath = 'uploads/signatures/' . $filename;

// 4) Upsert into signatures table
// Try UPDATE first
$updSig = $conn->prepare("
    UPDATE signatures
       SET image_path = ?, created_at = NOW()
     WHERE user_id = ?
");
$updSig->bind_param("si", $imagePath, $userId);
$updSig->execute();

if ($updSig->affected_rows === 0) {
    // No existing row → INSERT
    $insSig = $conn->prepare("
        INSERT INTO signatures (user_id, image_path, created_at)
        VALUES (?, ?, NOW())
    ");
    $insSig->bind_param("is", $userId, $imagePath);
    if (!$insSig->execute()) {
        http_response_code(500);
        exit(json_encode([
            'status'=>'error',
            'message'=>'Failed to record signature in database.'
        ]));
    }
    $insSig->close();
}
$updSig->close();

// 5) Update user password and clear password_changed flag
$hash = password_hash($new, PASSWORD_DEFAULT);
$upd  = $conn->prepare("
    UPDATE users
       SET password = ?, password_changed = 1
     WHERE id = ?
");
$upd->bind_param("si", $hash, $userId);

if ($upd->execute()) {
    echo json_encode([
        'status'=>'success',
        'message'=>'Password changed and signature saved successfully.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status'=>'error',
        'message'=>'Failed to update password.'
    ]);
}
$upd->close();
