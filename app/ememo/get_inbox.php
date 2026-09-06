<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['status'=>'error','message'=>'User not authenticated.']);
    exit;
}

try {
    $flat = [];

    // — Step 1: Get your group IDs —
    $groupStmt = $conn->prepare("SELECT group_id FROM group_members WHERE user_id = ?");
    $groupStmt->bind_param("i", $userId);
    $groupStmt->execute();
    $groupResult = $groupStmt->get_result();
    $groupIds = [];
    while ($row = $groupResult->fetch_assoc()) {
        $groupIds[] = (int)$row['group_id'];
    }

// — Step 2: only memos with pending clarifications for me —
$clarSql = "
  SELECT
    m.id,
    m.memo_id,
    m.subject,
    -- since we're only pulling pending MC rows, this will always be 'Escalated'
    'Escalated' AS status,
    m.created_at,
    -- have I already seen it?
    EXISTS(
      SELECT 1
        FROM inbox_views iv
       WHERE iv.user_id   = ?
         AND iv.memo_id   = m.id
         AND iv.memo_type = 'Workflow'
    ) AS viewed,
    -- how many pending clarifications are outstanding for me?
    COUNT(mc.id) AS pending_count

  FROM memo_clarifications mc
  JOIN memos m
    ON mc.memo_id = m.id

  WHERE
    mc.status = 'Pending'                   -- only still-pending clarifications
    AND (mc.user_id    = ?                  -- assigned to me
         OR mc.requested_by = ?)            -- or I requested them
    AND m.status IN (
      'Submitted','Under Review','Endorsed',
      'Approved','Rejected','Returned','Finalized'
    )

  GROUP BY
    m.id, m.memo_id, m.subject, m.created_at
";

$clarStmt = $conn->prepare($clarSql);
// bind userId for the EXISTS() check, then again for mc.user_id and mc.requested_by
$clarStmt->bind_param("iii", $userId, $userId, $userId);
$clarStmt->execute();

$flat = [];
foreach ($clarStmt->get_result() as $m) {
    $m['viewed']   = (bool)$m['viewed'];
    $m['type']     = 'Workflow Memo';
    // because every row here has at least one pending clarification
    $m['is_main']  = false;
    $flat[] = $m;
}


    // — Step 3: Workflows awaiting your final sign-off —
    $finalSql = "
      SELECT m.id, m.memo_id, m.subject, m.status, m.created_at,
        EXISTS(
          SELECT 1 
            FROM inbox_views iv 
           WHERE iv.user_id=? 
             AND iv.memo_id=m.id 
             AND iv.memo_type='Workflow'
        ) AS viewed
      FROM memos m
      LEFT JOIN memo_through_endorsements e
        ON e.memo_id=m.id AND e.user_id=?
      WHERE m.to_user_id=? 
        AND m.status='Endorsed' 
        AND e.id IS NULL
      ORDER BY m.created_at DESC
    ";
    $finalStmt = $conn->prepare($finalSql);
    $finalStmt->bind_param("iii", $userId, $userId, $userId);
    $finalStmt->execute();
    foreach ($finalStmt->get_result() as $m) {
        $m['viewed']  = (bool)$m['viewed'];
        $m['type']    = 'Workflow Memo';
        $m['is_main'] = true;
        $flat[] = $m;
    }

    // — Step 4: Other final-state workflows assigned to you —
    $otherSql = "
      SELECT m.id, m.memo_id, m.subject, m.status, m.created_at,
        EXISTS(
          SELECT 1 
            FROM inbox_views iv 
           WHERE iv.user_id=? 
             AND iv.memo_id=m.id 
             AND iv.memo_type='Workflow'
        ) AS viewed
      FROM memos m
      WHERE m.to_user_id=? 
        AND m.status IN('Approved','Returned','Rejected')
    ";
    $otherStmt = $conn->prepare($otherSql);
    $otherStmt->bind_param("ii", $userId, $userId);
    $otherStmt->execute();
    foreach ($otherStmt->get_result() as $m) {
        $m['viewed'] = (bool)$m['viewed'];
        $m['type']   = 'Workflow Memo';
        $flat[] = $m;
    }

    // — Step 5: Direct memos sent to you (Submitted) —
    $groupClause = '';
    if (!empty($groupIds)) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $groupClause  = " OR (dmr.recipient_type='group' AND dmr.recipient_id IN ($placeholders))";
    }
    $directSql = "
      SELECT DISTINCT dm.id,
             dm.reference_number AS memo_id,
             dm.subject,
             dm.status,
             dm.created_at,
             EXISTS(
               SELECT 1 
                 FROM inbox_views iv 
                WHERE iv.user_id=? 
                  AND iv.memo_id=dm.id 
                  AND iv.memo_type='Direct'
             ) AS viewed
      FROM direct_memos dm
      LEFT JOIN direct_memo_recipients dmr ON dm.id = dmr.direct_memo_id
      WHERE dm.status = 'Submitted'
        AND (
          dm.send_all = 1
          OR (dmr.recipient_type='user'  AND dmr.recipient_id = ?)
          $groupClause
        )
    ";
    $params = array_merge([$userId, $userId], $groupIds);
    $types  = 'ii' . str_repeat('i', count($groupIds));
    $dirStmt = $conn->prepare($directSql);
    $dirStmt->bind_param($types, ...$params);
    $dirStmt->execute();
    foreach ($dirStmt->get_result() as $m) {
        $m['viewed']  = (bool)$m['viewed'];
        $m['type']    = 'Direct Memo';
        $m['is_main'] = true;
        $flat[] = $m;
    }

    // — Step 6: Memos forwarded to you —
    $forwardSql = "
      SELECT DISTINCT
        m.id,
        m.memo_id,
        m.subject,
        m.status,
        m.created_at,
        EXISTS(
          SELECT 1
            FROM inbox_views iv
           WHERE iv.user_id=? 
             AND iv.memo_id=m.id 
             AND iv.memo_type='Workflow'
        ) AS viewed,
        mm.comments AS forward_type
      FROM memo_movements mm
      JOIN memos m ON m.id = mm.memo_id
     WHERE mm.action LIKE 'Forwarded%'
        AND mm.to_user_id = ?
      ORDER BY mm.timestamp DESC
    ";
    $forwardStmt = $conn->prepare($forwardSql);
    $forwardStmt->bind_param("ii", $userId, $userId);
    $forwardStmt->execute();
    foreach ($forwardStmt->get_result() as $m) {
        $m['viewed'] = (bool)$m['viewed'];
        $m['type']   = 'Workflow Memo';
        // show “Forwarded: General” or “Forwarded: Payment”
        $m['status'] = 'Forwarded: ' . ucfirst($m['forward_type']);
        $flat[] = $m;
    }
	
	
	// — Step 2.5: Memos escalated to you —
$escSql = "
  SELECT 
    m.id,
    m.memo_id,
    m.subject,
    m.status,
    m.created_at,
    EXISTS(
      SELECT 1 
        FROM inbox_views iv
       WHERE iv.user_id = ?
         AND iv.memo_id = m.id
         AND iv.memo_type = 'Workflow'
    ) AS viewed
  FROM memo_movements mm
  JOIN memos m 
    ON m.id = mm.memo_id
  WHERE mm.to_user_id = ?
    AND mm.action = 'Escalated'
  ORDER BY mm.timestamp DESC
";
$escStmt = $conn->prepare($escSql);
$escStmt->bind_param("ii", $userId, $userId);
$escStmt->execute();
foreach ($escStmt->get_result() as $m) {
    $m['viewed']  = (bool)$m['viewed'];
    $m['type']    = 'Workflow Memo';
    $m['status']  = 'Escalated';    // force the status key
    $m['is_main'] = false;          // not part of “main”
    $flat[] = $m;
}


    // — Group into tabs & dedupe —
    $tabs = [
      'main'      => [],
      'escalated' => [],
      'pending'   => [],
      'approved'  => [],
      'returned'  => [],
      'rejected'  => [],
      'forwarded' => []
    ];
    $seen = [];

    foreach ($flat as $m) {
        // dedupe
        if (in_array($m['id'], $seen, true)) {
            continue;
        }
        $seen[] = $m['id'];

        $statusKey = strtolower($m['status']);

        // forwarded entries go here
        if (strpos($statusKey, 'forwarded:') === 0) {
            $tabs['forwarded'][] = $m;
            continue;
        }

        // existing routing logic
        if ($statusKey === 'escalated') {
            $tabs['escalated'][] = $m;
            continue;
        }
        if (($m['type'] === 'Workflow Memo' && $statusKey === 'endorsed')
         ||($m['type'] === 'Direct Memo'   && $statusKey === 'submitted')) {
            $tabs['main'][] = $m;
            continue;
        }
        if ($m['type'] === 'Workflow Memo' && in_array($statusKey, ['submitted','under review'], true)) {
            $tabs['pending'][] = $m;
            continue;
        }
        if ($statusKey === 'approved') {
            $tabs['approved'][] = $m;
            continue;
        }
        if ($statusKey === 'returned') {
            $tabs['returned'][] = $m;
            continue;
        }
        if ($statusKey === 'rejected') {
            $tabs['rejected'][] = $m;
            continue;
        }
    }

    echo json_encode(['status'=>'success','data'=>$tabs]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Server error: '.$e->getMessage()]);
}
