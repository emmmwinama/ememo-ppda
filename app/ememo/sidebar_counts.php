<?php
// sidebar_counts.php
error_reporting(E_ALL);
ini_set('display_errors','1');
ini_set('display_startup_errors','1');

session_start();
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$userId       = (int)($_SESSION['user_id'] ?? 0);
$userEmail    = trim($_SESSION['email'] ?? '');
$isController = !empty($_SESSION['is_controlling_officer']);

// If not logged-in, return zeros
if (!$userId) {
  echo json_encode([
    'approvals'        => 0,
    'endorsements'     => 0,
    'direct'           => 0,
    'workflow'         => 0,
    'my_memos'         => 0,
    'my_letters'       => 0,
    'letter_reception' => 0,
    'unclosed_issues'  => 0,
    'smart_forms'      => 0,
    'dg_memos'         => 0
  ]);
  exit;
}

try {
  // Ensure $conn exists (compatible with your db.php)
  if (!isset($conn) || !($conn instanceof mysqli)) {
    if (function_exists('db_connect')) {
      $conn = db_connect();
    } else {
      throw new Exception('DB connection not initialized');
    }
  }

  // --- approvals ---
  $stmt = $conn->prepare("
    SELECT COUNT(*)
      FROM memo_approvals
     WHERE approver_id = ?
       AND decision = 'Pending'
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $stmt->bind_result($approvalCount);
  $stmt->fetch();
  $stmt->close();

  // --- endorsements ---
  $stmt = $conn->prepare("
    SELECT COUNT(*)
      FROM memo_through_recipients
     WHERE user_id = ?
       AND (endorsement_status IS NULL OR endorsement_status = 'Pending')
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $stmt->bind_result($endorsementCount);
  $stmt->fetch();
  $stmt->close();

  // --- direct memos (unseen) ---
  $stmt = $conn->prepare("
    SELECT COUNT(DISTINCT dm.id)
      FROM direct_memos dm
 LEFT JOIN direct_memo_recipients dmr
        ON dm.id = dmr.direct_memo_id
 LEFT JOIN inbox_views iv
        ON iv.memo_id   = dm.id
       AND iv.user_id   = ?
       AND iv.memo_type = 'Direct'
     WHERE dm.status = 'Submitted'
       AND (
              dm.send_all = 1
           OR (dmr.recipient_type = 'user'  AND dmr.recipient_id  = ?)
           OR (dmr.recipient_type = 'group' AND dmr.recipient_id IN (
                 SELECT group_id FROM group_members WHERE user_id = ?
             ))
       )
       AND iv.id IS NULL
  ");
  $stmt->bind_param("iii", $userId, $userId, $userId);
  $stmt->execute();
  $stmt->bind_result($directCount);
  $stmt->fetch();
  $stmt->close();

  // --- workflow memos (unseen) ---
  $stmt = $conn->prepare("
    SELECT COUNT(*)
      FROM memos m
 LEFT JOIN inbox_views iv
        ON iv.memo_id   = m.id
       AND iv.user_id   = ?
       AND iv.memo_type = 'Workflow'
     WHERE m.to_user_id = ?
       AND m.status IN ('Endorsed', 'Escalated')
       AND iv.id IS NULL
  ");
  $stmt->bind_param("ii", $userId, $userId);
  $stmt->execute();
  $stmt->bind_result($workflowCount);
  $stmt->fetch();
  $stmt->close();

  // --- my memos (originated) ---
  $stmt = $conn->prepare("
    SELECT COUNT(*)
      FROM memos
     WHERE originator_id = ?
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $stmt->bind_result($myMemosCount);
  $stmt->fetch();
  $stmt->close();

  // --- my_letters (assigned inbox) ---
  $stmt = $conn->prepare("
    SELECT COUNT(DISTINCT d.letter_id)
      FROM external_letter_delegation AS d
      JOIN external_letters         AS l ON l.id = d.letter_id
     WHERE d.delegated_to = ?
       AND d.status       = 'pending'
       AND l.status      != 'closed'
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $stmt->bind_result($assignedInboxCount);
  $stmt->fetch();
  $stmt->close();

  // --- letter_reception (DG inbox) ---
  $dgCount = 0;
  if ($isController) {
    $stmt = $conn->prepare("
      SELECT COUNT(*)
        FROM external_letters
       WHERE status = 'Pending'
    ");
    $stmt->execute();
    $stmt->bind_result($dgCount);
    $stmt->fetch();
    $stmt->close();
  }

  // --- unclosed issues (pending clarifications or awaiting action) ---
  $stmt = $conn->prepare("
SELECT COUNT(DISTINCT m.id)
  FROM memos m
 LEFT JOIN memo_clarifications c
        ON c.memo_id = m.id
       AND c.user_id = ?
       AND c.status = 'Pending'
       AND (c.response_comment IS NULL OR TRIM(c.response_comment) = '')
 WHERE (
          (m.status = 'Endorsed' AND m.to_user_id = ?)
       OR c.id IS NOT NULL
       )
   AND m.status NOT IN ('Approved', 'Rejected', 'Returned', 'Finalized');

  ");
  $stmt->bind_param("ii", $userId, $userId);
  $stmt->execute();
  $stmt->bind_result($unclosedIssuesCount);
  $stmt->fetch();
  $stmt->close();

  // --- smart_forms (pending signatures for this user) ---
  // Counts any pending signer rows where user is assigned directly,
  // or via external email match, and instance is still active.
  $smartFormsCount = 0;
  $stmt = $conn->prepare("
    SELECT COUNT(*)
      FROM form_signers fs
      JOIN form_instances fi ON fi.id = fs.instance_id
     WHERE fs.status = 'pending'
       AND fi.status IN ('in_progress','awaiting_sign')
       AND (
              fs.user_id = ?
           OR (fs.email IS NOT NULL AND fs.email <> '' AND fs.email = ?)
           )
  ");
  $stmt->bind_param("is", $userId, $userEmail);
  $stmt->execute();
  $stmt->bind_result($smartFormsCount);
  $stmt->fetch();
  $stmt->close();

  // --- dg_memos (optional; keep UI happy). Replace with real logic later. ---
  $dgMemosCount = 0;

  echo json_encode([
    'approvals'        => (int)$approvalCount,
    'endorsements'     => (int)$endorsementCount,
    'direct'           => (int)$directCount,
    'workflow'         => (int)$workflowCount,
    'my_memos'         => (int)$myMemosCount,
    'my_letters'       => (int)$assignedInboxCount,
    'letter_reception' => (int)$dgCount,
    'unclosed_issues'  => (int)$unclosedIssuesCount,
    'smart_forms'      => (int)$smartFormsCount,
    'dg_memos'         => (int)$dgMemosCount
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'Query failed: '.$e->getMessage()]);
}
