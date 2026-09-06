<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// read & sanitize pagination params
$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = max(1, min(50, intval($_GET['per_page'] ?? 10)));
$offset   = ($page - 1) * $per_page;

// get total count
$countRes = $conn->query("SELECT COUNT(*) FROM external_letters WHERE status='closed'");
$total    = $countRes->fetch_row()[0];

// fetch page of closed letters, newest first
$stmt = $conn->prepare("
  SELECT id, reference_number, title, received_from, received_date, status
    FROM external_letters
   WHERE status = 'closed'
   ORDER BY received_date DESC
   LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
      'id'                => (int)$row['id'],
      'reference_number'  => $row['reference_number'],
      'title'             => $row['title'],
      'received_from'     => $row['received_from'],
      'received_date'     => $row['received_date'],
      'status_label'      => ucfirst(str_replace('_',' ',$row['status'])),
      'badge_class'       => 'dark',
    ];
}

echo json_encode([
  'success'      => true,
  'data'         => $data,
  'page'         => $page,
  'per_page'     => $per_page,
  'total_items'  => $total,
  'total_pages'  => ceil($total / $per_page),
]);
