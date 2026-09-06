<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/FCMService.php';

define('SERVICE_ACCOUNT_FILE', __DIR__ . '/ppdaememo-b656edc602b5.json');
define('TEST_USER_ID', 215);

header('Content-Type: application/json');

// Step 1: Fetch tokens
$uid = TEST_USER_ID;
$stmt = $conn->prepare("SELECT fcm_token FROM user_devices WHERE user_id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();

$tokens = [];
while ($row = $res->fetch_assoc()) {
    $tokens[] = $row['fcm_token'];
}
$stmt->close();

if (empty($tokens)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => "No tokens found for user {$uid}"]);
    exit;
}

// Step 2: Send Notification
try {
    $fcm = new FCMService(SERVICE_ACCOUNT_FILE);
    $response = $fcm->sendNotification(
        $tokens,
        'New Memo',
        'A new memo has been issued',
        ['memo_id' => 123, 'type' => 'memo']
    );

    echo json_encode(['success' => true, 'results' => $response], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
