<?php
require_once 'db.php'; // your db connection
require_once 'auth.php'; // authentication check

if (!isset($_GET['memo_id'])) {
  echo json_encode(['status' => 'error', 'message' => 'No memo id']);
  exit;
}

$memo_id = intval($_GET['memo_id']);

$stmt = $pdo->prepare('SELECT * FROM memos WHERE id = ?');
$stmt->execute([$memo_id]);
$memo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($memo) {
  echo json_encode(['status' => 'success', 'memo' => $memo]);
} else {
  echo json_encode(['status' => 'error', 'message' => 'Memo not found']);
}
