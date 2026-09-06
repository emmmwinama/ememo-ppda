<?php
// external_letter_view.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$letterId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($letterId <= 0) {
    echo json_encode(array('success' => false, 'message' => 'Invalid letter ID'));
    exit;
}

// 1) Letter metadata
$stmt = $conn->prepare("
  SELECT reference_number, title, description, received_from, received_date, status
    FROM external_letters
   WHERE id = ?
");
$stmt->bind_param('i', $letterId);
$stmt->execute();
$stmt->bind_result($ref, $title, $desc, $from, $date, $status);
if (!$stmt->fetch()) {
    echo json_encode(array('success' => false, 'message' => 'Letter not found'));
    exit;
}
$stmt->close();

// 2) Attached files
$files = array();
$fstmt = $conn->prepare("
  SELECT file_name, file_path
    FROM external_letter_files
   WHERE letter_id = ?
");
$fstmt->bind_param('i', $letterId);
$fstmt->execute();
$fres = $fstmt->get_result();
while ($row = $fres->fetch_assoc()) {
    $files[] = $row;
}
$fstmt->close();

// 3) Full history (instructions + delegations)
$history = array();
$hstmt = $conn->prepare("
  SELECT
    'instruction'        AS entry_type,
    t.instruction        AS text,
    t.created_at         AS when_happened,
    u1.full_name         AS from_position,
    COALESCE(u2.full_name, '') AS to_position
  FROM external_letter_trail AS t
  LEFT JOIN users AS u1 ON u1.id = t.from_user_id
  LEFT JOIN users AS u2 ON u2.id = t.to_user_id
  WHERE t.letter_id = ?

  UNION ALL

  SELECT
    'delegation'         AS entry_type,
    ''                   AS text,
    d.created_at         AS when_happened,
    u1.full_name         AS from_position,
    u2.full_name         AS to_position
  FROM external_letter_delegation AS d
  LEFT JOIN users AS u1 ON u1.id = d.delegated_by
  LEFT JOIN users AS u2 ON u2.id = d.delegated_to
  WHERE d.letter_id = ?

  ORDER BY when_happened
");
$hstmt->bind_param('ii', $letterId, $letterId);
$hstmt->execute();
$hres = $hstmt->get_result();
while ($row = $hres->fetch_assoc()) {
    $history[] = $row;
}
$hstmt->close();

// 4) Return JSON
echo json_encode(array(
    'success' => true,
    'data'    => array(
        'reference_number' => $ref,
        'title'            => $title,
        'description'      => $desc,
        'received_from'    => $from,
        'received_date'    => $date,
        'status'           => $status,
    ),
    'files'   => $files,
    'history' => $history,
));
