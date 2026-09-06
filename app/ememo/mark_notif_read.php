<?php
require 'auth.php';
require 'db.php';

$userId   = (int)$_SESSION['user_id'];
$itemType = $_POST['item_type']; // 'letter' or 'memo'
$itemId   = (int)$_POST['item_id'];

$sql = "
  INSERT INTO item_views (user_id,item_type,item_id,last_viewed_at)
  VALUES (?,?,?,NOW())
  ON DUPLICATE KEY UPDATE last_viewed_at = NOW()
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isi", $userId, $itemType, $itemId);
$stmt->execute();
echo json_encode(['success'=>true]);
