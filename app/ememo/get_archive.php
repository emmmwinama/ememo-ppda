<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'Not authenticated.']);
    exit;
}

// 0) Fetch current user's department
$stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userDept);
$stmt->fetch();
$stmt->close();

// If they don’t have a department, fall back to 0
$userDept = $userDept ?: 0;

// 1) Main archive query
$sql = "
  SELECT m.id,
         m.memo_id,
         m.subject,
         m.created_at
    FROM memos m
   WHERE m.status = 'Approved'
     AND (
       m.originator_id = ?
       OR m.to_user_id   = ?
       OR EXISTS(
            SELECT 1
              FROM memo_through_recipients r
             WHERE r.memo_id = m.id
               AND r.user_id = ?
          )
       OR EXISTS(
            SELECT 1
              FROM memo_through_endorsements e
             WHERE e.memo_id  = m.id
               AND e.user_id  = ?
          )
       OR EXISTS(
            SELECT 1
              FROM memo_movements mm
             WHERE mm.memo_id    = m.id
               AND mm.action    LIKE 'Forwarded:%'
               AND mm.to_user_id = ?
          )
       OR EXISTS(
            SELECT 1
              FROM memo_movements mm2
             WHERE mm2.memo_id    = m.id
               AND mm2.action    = 'Escalated'
               AND (mm2.from_user_id = ? OR mm2.to_user_id = ?)
          )
       OR EXISTS(
            SELECT 1
              FROM memo_clarifications c
             WHERE c.memo_id       = m.id
               AND (c.requested_by = ? OR c.user_id = ?)
          )
       OR (
            -- same department as the originator
            (SELECT department_id FROM users WHERE id = m.originator_id) = ?
          )
     )
   ORDER BY m.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'iiiiiiiiii',
    $userId,    // originator
    $userId,    // final approver
    $userId,    // through-recipient
    $userId,    // endorser
    $userId,    // forwarded-to
    $userId,    // escalator (from)
    $userId,    // escalator (to)
    $userId,    // clarification requester
    $userId,    // clarification responder
    $userDept   // same-department check
);
$stmt->execute();
$memos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'status' => 'success',
    'data'   => $memos
]);
