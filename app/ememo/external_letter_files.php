<?php
// external_letter_files.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_once 'db.php'; // your mysqli $conn

$letterId = intval($_GET['letter_id'] ?? 0);
if ($letterId < 1) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Invalid letter ID']);
    exit;
}

// Fetch stored metadata
$stmt = $conn->prepare("
    SELECT id, file_name, file_path
    FROM external_letter_files
    WHERE letter_id = ?
    ORDER BY id
");
$stmt->bind_param("i", $letterId);

if (! $stmt->execute()) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Failed to fetch files']);
    exit;
}

$result = $stmt->get_result();
$files = [];

while ($row = $result->fetch_assoc()) {
    // Compute file size in KB if file exists
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $row['file_path'];
    if (is_file($fullPath)) {
        $sizeKb = round(filesize($fullPath) / 1024, 1);
    } else {
        $sizeKb = 0;
    }
    $files[] = [
        'id'        => $row['id'],
        'file_name' => $row['file_name'],
        'file_path' => $row['file_path'],
        'size_kb'   => $sizeKb
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'data'    => $files
]);
