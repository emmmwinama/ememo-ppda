<?php
// external_letter_history.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$letterId = intval($_GET['letter_id'] ?? 0);
if (!$letterId) {
    echo json_encode(['success'=>false,'message'=>'Missing letter ID']);
    exit;
}

$sql = "
  SELECT 
    'instruction'    AS type,
    t.instruction    AS text,
    t.created_at     AS when_happened,
    fu.full_name     AS by_user,
    pf.name          AS by_position,
    NULL             AS to_position,
    NULL             AS report_file_path
  FROM external_letter_trail t
  JOIN users fu ON t.from_user_id   = fu.id
  JOIN positions pf ON fu.position_id = pf.id
  WHERE t.letter_id = ?

  UNION ALL

  SELECT
    'delegation'     AS type,
    ''               AS text,
    d.created_at     AS when_happened,
    u1.full_name     AS by_user,
    p1.name          AS by_position,
    p2.name          AS to_position,
    NULL             AS report_file_path
  FROM external_letter_delegation d
  JOIN users u1 ON d.delegated_by     = u1.id
  JOIN positions p1 ON u1.position_id  = p1.id
  JOIN users u2 ON d.delegated_to     = u2.id
  JOIN positions p2 ON u2.position_id  = p2.id
  WHERE d.letter_id = ?

  UNION ALL

  SELECT
    'comment'        AS type,
    c.comment        AS text,
    c.created_at     AS when_happened,
    u.full_name      AS by_user,
    p.name           AS by_position,
    NULL             AS to_position,
    NULL             AS report_file_path
  FROM external_letter_comments c
  JOIN users u ON c.user_id         = u.id
  JOIN positions p ON u.position_id  = p.id
  WHERE c.letter_id = ?

  UNION ALL

  SELECT
    'action'         AS type,
    a.action_taken   AS text,
    a.submitted_at   AS when_happened,
    u.full_name      AS by_user,
    p.name           AS by_position,
    NULL             AS to_position,
    a.report_file_path
  FROM external_letter_actions a
  JOIN users u ON a.submitted_by     = u.id
  JOIN positions p ON u.position_id   = p.id
  WHERE a.letter_id = ?

  ORDER BY when_happened ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $letterId, $letterId, $letterId, $letterId);
$stmt->execute();
$res = $stmt->get_result();

$history = [];
while ($row = $res->fetch_assoc()) {
    // ensure key exists
    if (!isset($row['report_file_path'])) {
        $row['report_file_path'] = null;
    }
    $history[] = $row;
}

echo json_encode(['success'=>true, 'history'=>$history]);
