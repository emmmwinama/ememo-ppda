
<?php
header('Content-Type: application/json');
require 'auth.php'; require 'db.php';
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
// sanitize
$in = implode(',', array_map('intval', $ids));
if (!$in) return print json_encode([]);
$sql = "
  SELECT d.letter_id,
         u_from.full_name AS from_pos,
         u_to.full_name   AS to_pos,
         d.created_at AS when_happened
    FROM external_letter_delegation d
    JOIN users u_from ON u_from.id = d.delegated_by
    JOIN users u_to   ON u_to.id   = d.delegated_to
   WHERE d.letter_id IN ($in)
     AND d.status = 'pending'
   ORDER BY d.created_at DESC
";
$res = $conn->query($sql);
$out = [];
while ($row = $res->fetch_assoc()) {
  $lid = (int)$row['letter_id'];
  if (!isset($out[$lid])) {
    $out[$lid] = [
      'from' => $row['from_pos'],
      'to'   => $row['to_pos'],
      'when' => $row['when_happened']
    ];
  }
}
echo json_encode($out);
