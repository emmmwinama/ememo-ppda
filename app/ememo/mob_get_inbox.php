<?php
require 'db.php';
header('Content-Type: application/json');

// read JSON POST body
$payload = json_decode(file_get_contents('php://input'), true);
$userId  = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;

if (!$userId) {
    echo json_encode(['status'=>'error','message'=>'User not authenticated.']);
    exit;
}

try {
    $flat     = [];
    $groupIds = [];

    //
    // ─── STEP 1: GET GROUP IDS ──────────────────────────────────────────────
    //
    $g = $conn->prepare("SELECT group_id FROM group_members WHERE user_id = ?");
    $g->bind_param("i", $userId);
    $g->execute();
    $res = $g->get_result();
    while ($row = $res->fetch_assoc()) {
        $groupIds[] = (int)$row['group_id'];
    }
    $res->free();
    $g->close();

    //
    // ─── STEP 2: WORKFLOW VIA CLARIFICATIONS ─────────────────────────────────
    //
    $clarSql = "
      SELECT DISTINCT m.id, m.memo_id, m.subject, m.status, m.created_at,
        EXISTS(
          SELECT 1 FROM inbox_views iv
           WHERE iv.user_id=? AND iv.memo_id=m.id AND iv.memo_type='Workflow'
        ) AS viewed,
        (SELECT COUNT(*) FROM memo_clarifications mc2
           WHERE mc2.memo_id=m.id
             AND mc2.status='Pending'
             AND (mc2.requested_by=? OR mc2.user_id=?)
        ) AS pending_count
      FROM memo_clarifications mc
      JOIN memos m ON mc.memo_id=m.id
      WHERE (mc.requested_by=? OR mc.user_id=?)
        AND m.status IN(
          'Submitted','Under Review','Endorsed','Approved','Rejected','Returned','Finalized'
        )
    ";
    $c = $conn->prepare($clarSql);
    $c->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId);
    $c->execute();
    $res = $c->get_result();
    while ($m = $res->fetch_assoc()) {
        $m['viewed']  = (bool)$m['viewed'];
        $m['type']    = 'Workflow Memo';
        $m['is_main'] = ($m['pending_count'] == 0);
        if ($m['pending_count'] > 0) {
            $m['status']  = 'Escalated';
            $m['is_main'] = false;
        }
        $flat[] = $m;
    }
    $res->free();
    $c->close();

    //
    // ─── STEP 3: FINAL SIGN‐OFF WORKFLOWS ────────────────────────────────────
    //
    $finalSql = "
      SELECT m.id, m.memo_id, m.subject, m.status, m.created_at,
        EXISTS(
          SELECT 1 FROM inbox_views iv
           WHERE iv.user_id=? AND iv.memo_id=m.id AND iv.memo_type='Workflow'
        ) AS viewed
      FROM memos m
      LEFT JOIN memo_through_endorsements e
        ON e.memo_id=m.id AND e.user_id=?
      WHERE m.to_user_id=? AND m.status='Endorsed' AND e.id IS NULL
    ";
    $f = $conn->prepare($finalSql);
    $f->bind_param("iii", $userId, $userId, $userId);
    $f->execute();
    $res = $f->get_result();
    while ($m = $res->fetch_assoc()) {
        $m['viewed']  = (bool)$m['viewed'];
        $m['type']    = 'Workflow Memo';
        $m['is_main'] = true;
        $flat[] = $m;
    }
    $res->free();
    $f->close();

    //
    // ─── STEP 4: OTHER FINALIZED WORKFLOWS ──────────────────────────────────
    //
    $otherSql = "
      SELECT m.id, m.memo_id, m.subject, m.status, m.created_at,
        EXISTS(
          SELECT 1 FROM inbox_views iv
           WHERE iv.user_id=? AND iv.memo_id=m.id AND iv.memo_type='Workflow'
        ) AS viewed
      FROM memos m
      WHERE m.to_user_id=? AND m.status IN('Approved','Returned','Rejected')
    ";
    $o = $conn->prepare($otherSql);
    $o->bind_param("ii", $userId, $userId);
    $o->execute();
    $res = $o->get_result();
    while ($m = $res->fetch_assoc()) {
        $m['viewed'] = (bool)$m['viewed'];
        $m['type']   = 'Workflow Memo';
        $flat[] = $m;
    }
    $res->free();
    $o->close();

    //
    // ─── STEP 5: DIRECT MEMOS ────────────────────────────────────────────────
    //
    $groupClause = "";
    if (!empty($groupIds)) {
        $ph = implode(',', array_fill(0, count($groupIds), '?'));
        $groupClause = " OR (dmr.recipient_type='group' AND dmr.recipient_id IN ($ph))";
    }
    $directSql = "
      SELECT DISTINCT dm.id,
             dm.reference_number AS memo_id,
             dm.subject,
             dm.status,
             dm.created_at,
             EXISTS(
               SELECT 1 FROM inbox_views iv
                WHERE iv.user_id=? AND iv.memo_id=dm.id AND iv.memo_type='Direct'
             ) AS viewed
      FROM direct_memos dm
      LEFT JOIN direct_memo_recipients dmr ON dm.id=dmr.direct_memo_id
      WHERE dm.status='Submitted'
        AND (
          dm.send_all=1
          OR (dmr.recipient_type='user' AND dmr.recipient_id=?)
          $groupClause
        )
    ";
    $stmt = $conn->prepare($directSql);
    $types = 'ii' . str_repeat('i', count($groupIds));
    $params = array_merge([$userId, $userId], $groupIds);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($m = $res->fetch_assoc()) {
        $m['viewed']  = (bool)$m['viewed'];
        $m['type']    = 'Direct Memo';
        $m['is_main'] = true;
        $flat[] = $m;
    }
    $res->free();
    $stmt->close();

    //
    // ─── STEP 6: FORWARDED MEMOS ────────────────────────────────────────────
    //
    $fwSql = "
      SELECT DISTINCT m.id,m.memo_id,m.subject,m.status,m.created_at,
        EXISTS(
          SELECT 1 FROM inbox_views iv
           WHERE iv.user_id=? AND iv.memo_id=m.id AND iv.memo_type='Workflow'
        ) AS viewed,
        mm.comments AS forward_type
      FROM memo_movements mm
      JOIN memos m ON m.id=mm.memo_id
      WHERE mm.action LIKE 'Forwarded:%' AND mm.to_user_id=?
    ";
    $fw = $conn->prepare($fwSql);
    $fw->bind_param("ii", $userId, $userId);
    $fw->execute();
    $res = $fw->get_result();
    while ($m = $res->fetch_assoc()) {
        $m['viewed'] = (bool)$m['viewed'];
        $m['type']   = 'Workflow Memo';
        $m['status'] = 'Forwarded: ' . ucfirst($m['forward_type']);
        $flat[] = $m;
    }
    $res->free();
    $fw->close();

    //
    // ─── FINAL: BUCKET, DEDUPE, SORT ─────────────────────────────────────────
    //
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
        if (in_array($m['id'], $seen, true)) continue;
        $seen[] = $m['id'];
        $key = strtolower($m['status']);

        if (strpos($key,'forwarded:')===0) {
            $tabs['forwarded'][] = $m;
            continue;
        }
        if ($key==='escalated') {
            $tabs['escalated'][] = $m;
            continue;
        }
        if (($m['type']==='Workflow Memo' && $key==='endorsed')
         ||($m['type']==='Direct Memo'   && $key==='submitted')) {
            $tabs['main'][] = $m;
            continue;
        }
        if ($m['type']==='Workflow Memo'
         && in_array($key,['submitted','under review'],true)) {
            $tabs['pending'][] = $m;
            continue;
        }
        if ($key==='approved') {
            $tabs['approved'][] = $m;
            continue;
        }
        if ($key==='returned') {
            $tabs['returned'][] = $m;
            continue;
        }
        if ($key==='rejected') {
            $tabs['rejected'][] = $m;
            continue;
        }
    }

    // --- Now sort each bucket: newest (by created_at, then by id) at the top ---
    foreach ($tabs as &$list) {
        usort($list, function($a, $b) {
            // compare timestamps
            $cmp = strcmp($b['created_at'], $a['created_at']);
            if ($cmp !== 0) return $cmp;
            // tie-break: higher id first
            return $b['id'] - $a['id'];
        });
    }
    unset($list);

    echo json_encode(['status'=>'success','data'=>$tabs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
      'status'=>'error',
      'message'=>'Server error: '.$e->getMessage()
    ]);
}
