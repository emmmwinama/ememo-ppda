<?php
require_once 'db.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$memo_id = intval($_GET['memo_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

$response = ['status' => 'error', 'message' => 'Invalid request.'];

if ($memo_id) {
    // 1) Fetch the memo and its current stage
    $stmt = $conn->prepare("
        SELECT m.*, s.name AS stage
          FROM memos m
     LEFT JOIN approval_stages s ON m.current_stage_id = s.id
         WHERE m.id = ?
    ");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $memo = $stmt->get_result()->fetch_assoc();

    if ($memo) {
        $memo['date'] = $memo['created_at'];

        // 2) Fetch To User (Approver)
        $to_user = null;
        if (!empty($memo['to_user_id'])) {
            $stmt = $conn->prepare("
                SELECT p.name AS position
                  FROM users u
             LEFT JOIN positions p ON u.position_id = p.id
                 WHERE u.id = ?
            ");
            $stmt->bind_param("i", $memo['to_user_id']);
            $stmt->execute();
            $to_user = $stmt->get_result()->fetch_assoc();
        }

        // 3) Fetch Through Users
        $stmt = $conn->prepare("
            SELECT u.id, p.name AS position
              FROM memo_through_recipients mtr
              JOIN users u ON mtr.user_id = u.id
         LEFT JOIN positions p ON u.position_id = p.id
             WHERE mtr.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $through_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // 4) Fetch Originator Info & Signature
        $originator = null;
        $originator_signature = '';
        if (!empty($memo['originator_id'])) {
            $stmt = $conn->prepare("
                SELECT u.id, u.full_name AS name, p.name AS position
                  FROM users u
             LEFT JOIN positions p ON u.position_id = p.id
                 WHERE u.id = ?
            ");
            $stmt->bind_param("i", $memo['originator_id']);
            $stmt->execute();
            $originator = $stmt->get_result()->fetch_assoc();

            // First look in memo_signatures
            $stmt = $conn->prepare("
                SELECT signature_path
                  FROM memo_signatures
                 WHERE memo_id = ? AND user_id = ?
              ORDER BY signed_at DESC
                 LIMIT 1
            ");
            $stmt->bind_param("ii", $memo_id, $memo['originator_id']);
            $stmt->execute();
            $stmt->bind_result($originator_signature);
            $stmt->fetch();
            $stmt->close();

            // Fallback to default user signature
            if (empty($originator_signature)) {
                $stmt = $conn->prepare("
                    SELECT image_path
                      FROM signatures
                     WHERE user_id = ?
                  ORDER BY created_at DESC
                     LIMIT 1
                ");
                $stmt->bind_param("i", $memo['originator_id']);
                $stmt->execute();
                $stmt->bind_result($default_sig);
                if ($stmt->fetch()) {
                    $originator_signature = $default_sig;
                }
                $stmt->close();
            }
        }

        // 5) Attachments
        $stmt = $conn->prepare("
            SELECT id, file_name, file_path
              FROM attachments
             WHERE memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // 6) Through Endorsements
        $stmt = $conn->prepare("
            SELECT mte.user_id,
                   u.full_name AS name,
                   p.name AS position,
                   mte.comment,
                   mte.signature_path,
                   mte.endorsed_at
              FROM memo_through_endorsements mte
              JOIN users u ON mte.user_id = u.id
         LEFT JOIN positions p ON u.position_id = p.id
             WHERE mte.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $raw_endorsements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $through_endorsements = [];
        foreach ($raw_endorsements as $e) {
            if (empty($e['signature_path'])) {
                $sigStmt = $conn->prepare("
                    SELECT image_path
                      FROM signatures
                     WHERE user_id = ?
                  ORDER BY created_at DESC
                     LIMIT 1
                ");
                $sigStmt->bind_param("i", $e['user_id']);
                $sigStmt->execute();
                $sigStmt->bind_result($fallback);
                if ($sigStmt->fetch()) {
                    $e['signature_path'] = $fallback;
                }
                $sigStmt->close();
            }
            $through_endorsements[] = $e;
        }

        // 7) Memo Comments (Approvals)
        $stmt = $conn->prepare("
            SELECT mc.user_id,
                   u.full_name AS name,
                   p.name AS position,
                   mc.comment,
                   mc.stage,
                   mc.timestamp
              FROM memo_comments mc
              JOIN users u ON mc.user_id = u.id
         LEFT JOIN positions p ON u.position_id = p.id
             WHERE mc.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $memo_comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($memo_comments as &$c) {
            $sigStmt = $conn->prepare("
                SELECT image_path
                  FROM signatures
                 WHERE user_id = ?
              ORDER BY created_at DESC
                 LIMIT 1
            ");
            $sigStmt->bind_param("i", $c['user_id']);
            $sigStmt->execute();
            $sigStmt->bind_result($sig);
            if ($sigStmt->fetch()) {
                $c['signature_path'] = $sig;
            }
            $sigStmt->close();
        }

        // 8) Movements
        $stmt = $conn->prepare("
            SELECT mm.from_user_id,
                   fu.full_name AS from_name,
                   mm.to_user_id,
                   tu.full_name AS to_name,
                   mm.action,
                   mm.comments,
                   mm.timestamp
              FROM memo_movements mm
         LEFT JOIN users fu ON mm.from_user_id = fu.id
         LEFT JOIN users tu ON mm.to_user_id   = tu.id
             WHERE mm.memo_id = ?
          ORDER BY mm.timestamp ASC
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $memo_movements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // 9) Clarifications
        $stmt = $conn->prepare("
            SELECT mc.id,
                   mc.memo_id,
                   mc.requested_by,
                   rb.full_name AS requested_by_name,
                   p1.name     AS requested_by_position,
                   mc.user_id,
                   u.full_name AS user_name,
                   p2.name     AS user_position,
                   mc.clarification_comment,
                   mc.response_comment,
                   mc.responded_at,
                   mc.status,
                   mc.requested_at
              FROM memo_clarifications mc
              JOIN users u ON mc.user_id       = u.id
         LEFT JOIN positions p2 ON u.position_id       = p2.id
              JOIN users rb ON mc.requested_by = rb.id
         LEFT JOIN positions p1 ON rb.position_id      = p1.id
             WHERE mc.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $clarifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($clarifications as &$cl) {
            foreach (['user_id', 'requested_by'] as $key) {
                $sigStmt = $conn->prepare("
                    SELECT image_path
                      FROM signatures
                     WHERE user_id = ?
                  ORDER BY created_at DESC
                     LIMIT 1
                ");
                $sigStmt->bind_param("i", $cl[$key]);
                $sigStmt->execute();
                $sigStmt->bind_result($sig);
                if ($sigStmt->fetch()) {
                    $cl[$key . '_signature'] = $sig;
                }
                $sigStmt->close();
            }
        }

        // 10) Director General Comment
        $dg_comment = null;
        if (!empty($memo['to_user_id'])) {
            $stmt = $conn->prepare("
                SELECT mc.comment, mc.timestamp
                  FROM memo_comments mc
                 WHERE mc.memo_id = ?
                   AND mc.user_id = ?
              ORDER BY mc.timestamp DESC
                 LIMIT 1
            ");
            $stmt->bind_param("ii", $memo_id, $memo['to_user_id']);
            $stmt->execute();
            $dg_comment = $stmt->get_result()->fetch_assoc();
        }

        // ── NEW: Fetch Instructions ──
// ── NEW: Fetch Instructions with position ──
$stmt = $conn->prepare("
    SELECT 
      mi.instruction,
      mi.created_at,
      p.name       AS position,
      (
        SELECT image_path
          FROM signatures
         WHERE user_id = u.id
      ORDER BY created_at DESC
         LIMIT 1
      ) AS signature_path
    FROM memo_instructions mi
    JOIN users u     ON mi.user_id     = u.id
    LEFT JOIN positions p ON u.position_id = p.id
    WHERE mi.memo_id = ?
    ORDER BY mi.created_at ASC
");
$stmt->bind_param('i', $memo_id);
$stmt->execute();
$memo_instructions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);




        // 11) Role Checks
        $stmt = $conn->prepare("
            SELECT 1
              FROM memo_through_recipients
             WHERE memo_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $memo_id, $user_id);
        $stmt->execute();
        $stmt->store_result();
        $current_user_is_recipient = $stmt->num_rows > 0;
        $stmt->free_result();

        $current_user_is_approver = (
            !empty($memo['to_user_id'])
            && $memo['to_user_id'] == $user_id
        );

        // Build response
        $response = [
            'status'                     => 'success',
            'memo'                       => $memo,
            'to_user'                    => $to_user,
            'through_users'              => $through_users,
            'originator'                 => $originator,
            'originator_signature'       => $originator_signature,
            'attachments'                => $attachments,
            'through_endorsements'       => $through_endorsements,
            'memo_comments'              => $memo_comments,
            'memo_movements'             => $memo_movements,
            'memo_clarifications'        => $clarifications,
            'memo_instructions'          => $memo_instructions,    // NEW
            'dg_comment'                 => $dg_comment,
            'current_user_id'            => $user_id,
            'current_user_is_recipient'  => $current_user_is_recipient,
            'current_user_is_approver'   => $current_user_is_approver
        ];
    } else {
        $response['message'] = 'Memo not found.';
    }
}

echo json_encode($response);
