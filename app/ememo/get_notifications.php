<?php
// get_notifications.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$userId = intval($_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success'=>false,'message'=>'Not authenticated']);
    exit;
}

try {
    // fetch unread notifications
    $sql = <<<SQL
    SELECT
      n.id,
      n.event_type,
      n.object_id,
      n.history_id,
      n.message,
      n.url,
      n.created_at,
      u_from.full_name AS from_name,
      u_to.full_name   AS to_name
    FROM notifications AS n
    JOIN users        AS u_to   ON u_to.id   = n.user_id
    LEFT JOIN users   AS u_from ON u_from.id = n.related_id
    WHERE n.user_id = ?
      AND n.is_read = 0
    ORDER BY n.created_at DESC
    LIMIT 50
    SQL;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $notes = [];
    while ($row = $res->fetch_assoc()) {
        $notes[] = [
            'id'         => (int)$row['id'],
            'event_type' => $row['event_type'],
            'object_id'  => (int)$row['object_id'],
            'history_id' => (int)$row['history_id'],
            'message'    => $row['message'],
            'url'        => $row['url'],
            'created_at' => $row['created_at'],
            'from_name'  => $row['from_name'] ?? 'System',
            'to_name'    => $row['to_name'],
        ];
    }
    echo json_encode([
        'success'       => true,
        'notifications' => $notes,
        'unread_count'  => count($notes),
    ]);
} catch (\Exception $e) {
    error_log("get_notifications error: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error']);
}
