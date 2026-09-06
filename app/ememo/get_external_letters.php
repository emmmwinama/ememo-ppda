<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;

$isSecretary = false;

// Check if user is secretary
$secCheck = $conn->prepare("SELECT is_secretary FROM users WHERE id = ?");
$secCheck->bind_param("i", $userId);
$secCheck->execute();
$secCheck->bind_result($secFlag);
if ($secCheck->fetch() && $secFlag == 1) {
  $isSecretary = true;
}
$secCheck->close();

// Build query
if ($isSecretary) {
  $sql = "SELECT id, reference_number, title, received_from, received_date, status 
          FROM external_letters 
          ORDER BY created_at DESC";
} else {
  $sql = "SELECT id, reference_number, title, received_from, received_date, status 
          FROM external_letters 
          WHERE current_assignee = $userId 
          ORDER BY created_at DESC";
}

$result = $conn->query($sql);
$data = [];

while ($row = $result->fetch_assoc()) {
  $statusClass = match($row['status']) {
    'pending'             => 'secondary',
    'assigned'            => 'info',
    'delegation_pending'  => 'warning',
    'in_progress'         => 'primary',
    'report_submitted'    => 'success',
    'closed'              => 'dark',
    default               => 'light'
  };

  $data[] = [
    'id'                => $row['id'],
    'reference_number' => $row['reference_number'],
    'title'            => $row['title'],
    'received_from'    => $row['received_from'],
    'received_date'    => $row['received_date'],
    'status'           => $row['status'],
    'status_label'     => ucfirst(str_replace('_', ' ', $row['status'])),
    'badge_class'      => $statusClass
  ];
}

echo json_encode(['success' => true, 'data' => $data]);
