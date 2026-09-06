<?php
// external_letters_list.php

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/auth.php'; // establishes session user_id
require_once __DIR__ . '/db.php';   // gives you $conn (mysqli)

// 1) Must be logged in
$userId = intval($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success'=>false,'message'=>'Not authenticated']);
    exit;
}

// 2) Re‑check controlling‑officer from the database (never trust session alone)
$isOfficer = false;
if ($stmt = $conn->prepare("SELECT is_controlling_officer FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($flag);
    if ($stmt->fetch() && $flag == 1) {
        $isOfficer = true;
    }
    $stmt->close();
}

// 3) Build your query
if ($isOfficer) {
    // COs see every open letter
    $sql = <<<SQL
SELECT
  el.id,
  el.reference_number,
  el.title,
  el.received_from,
  el.received_date,
  el.status
FROM external_letters AS el
WHERE el.status != 'closed'
ORDER BY el.created_at DESC
SQL;
    $stmt = $conn->prepare($sql);
} else {
    // Everyone else sees letters they're assigned to, or letters they’ve delegated/from them
    $sql = <<<SQL
SELECT DISTINCT
  el.id,
  el.reference_number,
  el.title,
  el.received_from,
  el.received_date,
  el.status
FROM external_letters AS el
LEFT JOIN external_letter_delegation AS d1
  ON d1.letter_id = el.id
LEFT JOIN external_letter_delegation AS d2
  ON d2.letter_id = el.id
WHERE
  el.status != 'closed'
  AND (
    el.current_assignee = ?
    OR d1.delegated_by = ?
    OR d2.delegated_to = ?
  )
ORDER BY el.created_at DESC
SQL;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $userId, $userId, $userId);
}

// 4) Run it and collect rows
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    switch ($row['status']) {
        case 'pending':          $cls = 'secondary'; break;
        case 'assigned':         $cls = 'info';      break;
        case 'in_progress':      $cls = 'primary';   break;
        case 'report_submitted': $cls = 'success';   break;
        case 'closed':           $cls = 'dark';      break;
        default:                 $cls = 'warning';   break;
    }
    $data[] = [
      'id'               => (int)$row['id'],
      'reference_number' => $row['reference_number'],
      'title'            => $row['title'],
      'received_from'    => $row['received_from'],
      'received_date'    => $row['received_date'],
      'status'           => $row['status'],
      'status_label'     => ucfirst(str_replace('_',' ',$row['status'])),
      'badge_class'      => $cls,
    ];
}

$stmt->close();

// 5) And send it back
echo json_encode([
  'success' => true,
  'data'    => $data
]);
