<?php
// external_assigned_list.php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// 1) Ensure logged in
$uid = intval($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2) Fetch letters delegated to this user that are still 'assigned'
$sql = "
  SELECT
    el.id,
    el.reference_number,
    el.title,
    el.received_from,
    el.received_date,
    el.status        AS status_label,
    CASE el.status
      WHEN 'assigned'  THEN 'warning'
      WHEN 'completed' THEN 'success'
      ELSE 'secondary'
    END               AS badge_class
  FROM external_letters AS el
  JOIN external_letter_delegation AS d
    ON d.letter_id = el.id
  WHERE d.delegated_to = ?
    AND el.status       = 'assigned'
  ORDER BY el.received_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();

// 3) Build and return JSON
$data = $res->fetch_all(MYSQLI_ASSOC);
echo json_encode([
    'success' => true,
    'data'    => $data
]);
