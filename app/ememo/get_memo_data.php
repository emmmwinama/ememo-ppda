<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$memo_id = intval($_GET['memo_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

$response = ['status' => 'error', 'message' => 'Invalid request.'];

if ($memo_id) {
    // 1) Fetch memo + stage
    $stmt = $conn->prepare("
        SELECT m.*, s.name AS stage
          FROM memos m
     LEFT JOIN approval_stages s ON m.current_stage_id = s.id
         WHERE m.id = ?
    ");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $memo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($memo) {
        $memo['date'] = $memo['created_at'];

        // 2) To-user (approver) + signature
        $to_user = [];
        if (!empty($memo['to_user_id'])) {
            $stmt = $conn->prepare("
                SELECT u.id, u.full_name AS name, p.name AS position
                  FROM users u
             LEFT JOIN positions p ON u.position_id = p.id
                 WHERE u.id = ?
            ");
            $stmt->bind_param("i", $memo['to_user_id']);
            $stmt->execute();
            $to_user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $sig = '';
            $stmt = $conn->prepare("
                SELECT signature_path
                  FROM memo_signatures
                 WHERE memo_id = ? AND user_id = ?
              ORDER BY signed_at DESC
                 LIMIT 1
            ");
            $stmt->bind_param("ii", $memo_id, $memo['to_user_id']);
            $stmt->execute();
            $stmt->bind_result($sig);
            $stmt->fetch();
            $stmt->close();

            if (empty($sig)) {
                $stmt = $conn->prepare("
                    SELECT image_path AS signature_path
                      FROM signatures
                     WHERE user_id = ?
                  ORDER BY created_at DESC
                     LIMIT 1
                ");
                $stmt->bind_param("i", $memo['to_user_id']);
                $stmt->execute();
                $stmt->bind_result($sig);
                $stmt->fetch();
                $stmt->close();
            }

            $to_user['signature_path'] = $sig;
        }

        // 3) Through users + signatures
        $stmt = $conn->prepare("
            SELECT u.id, u.full_name AS name, p.name AS position
              FROM memo_through_recipients mtr
              JOIN users u ON mtr.user_id = u.id
         LEFT JOIN positions p ON u.position_id = p.id
             WHERE mtr.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $through_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($through_users as &$tu) {
            $sig = '';
            $s1 = $conn->prepare("
                SELECT signature_path
                  FROM memo_signatures
                 WHERE memo_id = ? AND user_id = ?
              ORDER BY signed_at DESC
                 LIMIT 1
            ");
            $s1->bind_param("ii", $memo_id, $tu['id']);
            $s1->execute();
            $s1->bind_result($sig);
            $s1->fetch();
            $s1->close();

            if (empty($sig)) {
                $s2 = $conn->prepare("
                    SELECT image_path AS signature_path
                      FROM signatures
                     WHERE user_id = ?
                  ORDER BY created_at DESC
                     LIMIT 1
                ");
                $s2->bind_param("i", $tu['id']);
                $s2->execute();
                $s2->bind_result($sig);
                $s2->fetch();
                $s2->close();
            }

            $tu['signature_path'] = $sig;
        }
        unset($tu);

        // 4) Originator + signature
        $originator = [];
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
            $stmt->close();

            $sig = '';
            $stmt = $conn->prepare("
                SELECT signature_path
                  FROM memo_signatures
                 WHERE memo_id = ? AND user_id = ?
              ORDER BY signed_at DESC
                 LIMIT 1
            ");
            $stmt->bind_param("ii", $memo_id, $memo['originator_id']);
            $stmt->execute();
            $stmt->bind_result($sig);
            $stmt->fetch();
            $stmt->close();

            if (empty($sig)) {
                $stmt = $conn->prepare("
                    SELECT image_path AS signature_path
                      FROM signatures
                     WHERE user_id = ?
                  ORDER BY created_at DESC
                     LIMIT 1
                ");
                $stmt->bind_param("i", $memo['originator_id']);
                $stmt->execute();
                $stmt->bind_result($sig);
                $stmt->fetch();
                $stmt->close();
            }

            $originator['signature_path'] = $sig;
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
        $stmt->close();

        // 6) Through endorsements
        $stmt = $conn->prepare("
            SELECT mte.user_id, u.full_name AS name, p.name AS position,
                   mte.comment, mte.signature_path, mte.endorsed_at
              FROM memo_through_endorsements mte
              JOIN users u ON mte.user_id = u.id
         LEFT JOIN positions p ON u.position_id = p.id
             WHERE mte.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $raw_endorsements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $through_endorsements = [];
        foreach ($raw_endorsements as $e) {
            if (empty($e['signature_path'])) {
                $sigStmt = $conn->prepare("
                    SELECT image_path AS signature_path
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

        // 7) Memo comments
        $stmt = $conn->prepare("
            SELECT mc.user_id, u.full_name AS name, p.name AS position,
                   mc.comment, mc.stage, mc.timestamp
              FROM memo_comments mc
              JOIN users u ON mc.user_id = u.id
         LEFT JOIN positions p ON u.position_id = p.id
             WHERE mc.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $memo_comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($memo_comments as &$c) {
            $sigStmt = $conn->prepare("
                SELECT image_path AS signature_path
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
        unset($c);

        // 8) Movements
        $stmt = $conn->prepare("
            SELECT mm.from_user_id, fu.full_name AS from_name, p1.name AS from_position,
                   mm.to_user_id, tu.full_name AS to_name, p2.name AS to_position,
                   mm.action, mm.comments, mm.timestamp
              FROM memo_movements mm
         LEFT JOIN users fu ON mm.from_user_id = fu.id
         LEFT JOIN positions p1 ON fu.position_id = p1.id
         LEFT JOIN users tu ON mm.to_user_id = tu.id
         LEFT JOIN positions p2 ON tu.position_id = p2.id
             WHERE mm.memo_id = ?
          ORDER BY mm.timestamp ASC
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $memo_movements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($memo_movements as &$mv) {
            $sig = '';
            $s1 = $conn->prepare("
                SELECT signature_path
                  FROM memo_signatures
                 WHERE memo_id = ? AND user_id = ?
              ORDER BY signed_at DESC
                 LIMIT 1
            ");
            $s1->bind_param("ii", $memo_id, $mv['from_user_id']);
            $s1->execute();
            $s1->bind_result($sig);
            $s1->fetch();
            $s1->close();

            if (empty($sig)) {
                $s2 = $conn->prepare("
                    SELECT image_path AS signature_path
                      FROM signatures
                     WHERE user_id = ?
                  ORDER BY created_at DESC
                     LIMIT 1
                ");
                $s2->bind_param("i", $mv['from_user_id']);
                $s2->execute();
                $s2->bind_result($sig);
                $s2->fetch();
                $s2->close();
            }
            $mv['signature_path'] = $sig;
        }
        unset($mv);

        // 9) Clarifications
        $stmt = $conn->prepare("
            SELECT mc.id, mc.memo_id, mc.requested_by,
                   rb.full_name AS requested_by_name, p1.name AS requested_by_position,
                   mc.user_id, u.full_name AS user_name, p2.name AS user_position,
                   mc.clarification_comment, mc.response_comment,
                   mc.responded_at, mc.status, mc.requested_at
              FROM memo_clarifications mc
              JOIN users u  ON mc.user_id = u.id
         LEFT JOIN positions p2 ON u.position_id = p2.id
              JOIN users rb ON mc.requested_by = rb.id
         LEFT JOIN positions p1 ON rb.position_id = p1.id
             WHERE mc.memo_id = ?
        ");
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $clarifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($clarifications as &$cl) {
            foreach (['user_id','requested_by'] as $key) {
                $sigStmt = $conn->prepare("
                    SELECT image_path AS signature_path
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
        unset($cl);

// 10) Instructions
$stmt = $conn->prepare("
    SELECT
      mi.instruction,
      mi.created_at,

      -- recipient’s position
      rp.name AS recipient_position,

      -- sender’s position
      sp.name AS sender_position,

      -- sender’s latest signature (if any)
      COALESCE(
        (SELECT signature_path
           FROM memo_signatures ms
          WHERE ms.memo_id    = mi.memo_id
            AND ms.user_id    = mi.user_id
          ORDER BY ms.signed_at DESC
          LIMIT 1),
        (SELECT s.image_path
           FROM signatures s
          WHERE s.user_id = mi.user_id
          ORDER BY s.created_at DESC
          LIMIT 1)
      ) AS signature_path

    FROM memo_instructions mi

    -- join recipient user for their position
    JOIN users ru
      ON ru.id = mi.recipient_id
    LEFT JOIN positions rp
      ON ru.position_id = rp.id

    -- join sender user for their position
    JOIN users su
      ON su.id = mi.user_id
    LEFT JOIN positions sp
      ON su.position_id = sp.id

   WHERE mi.memo_id = ?
   ORDER BY mi.created_at ASC
");
$stmt->bind_param("i", $memo_id);
$stmt->execute();
$memo_instructions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


        // 11) Role checks
        $stmt = $conn->prepare("
            SELECT 1 FROM memo_through_recipients WHERE memo_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $memo_id, $user_id);
        $stmt->execute();
        $stmt->store_result();
        $current_user_is_recipient = $stmt->num_rows > 0;
        $stmt->close();

        $current_user_is_approver = (
            !empty($memo['to_user_id']) &&
            $memo['to_user_id'] === $user_id
        );

        // 12) Draft letter + recipients
        $letter = null; $letter_id = 0;
        $lit = $conn->prepare("
            SELECT id, letter_date, letter_ref_no, letter_subject, letter_content
              FROM memo_outgoing_letters
             WHERE memo_id = ?
          ORDER BY id DESC LIMIT 1
        ");
        $lit->bind_param("i", $memo_id);
        $lit->execute();
        if ($row = $lit->get_result()->fetch_assoc()) {
            $letter_id = $row['id'];
            $letter = [
                'letter_date'    => $row['letter_date'],
                'letter_ref_no'  => $row['letter_ref_no'],
                'letter_subject' => $row['letter_subject'],
                'letter_content' => $row['letter_content'],
                'recipients'     => []
            ];
        }
        $lit->close();

        if ($letter_id) {
            $rpt = $conn->prepare("
                SELECT position, address
                  FROM memo_outgoing_letter_recipients
                 WHERE outgoing_letter_id = ?
              ORDER BY sort_order ASC
            ");
            $rpt->bind_param("i", $letter_id);
            $rpt->execute();
            $rows = $rpt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as $r) {
                $letter['recipients'][] = $r;
            }
            $rpt->close();
        }

        // 13) DG comment
        $dg_comment = null;
        if (!empty($memo['to_user_id'])) {
            $stmt = $conn->prepare("
                SELECT comment, timestamp
                  FROM memo_comments
                 WHERE memo_id = ? AND user_id = ?
              ORDER BY timestamp DESC LIMIT 1
            ");
            $stmt->bind_param("ii", $memo_id, $memo['to_user_id']);
            $stmt->execute();
            $dg_comment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        // Assemble response
        $response = [
            'status'                    => 'success',
            'memo'                      => $memo,
            'to_user'                   => $to_user,
            'through_users'             => $through_users,
            'originator'                => $originator,
            'originator_signature'      => $originator['signature_path'] ?? '',
            'attachments'               => $attachments,
            'through_endorsements'      => $through_endorsements,
            'memo_comments'             => $memo_comments,
            'memo_movements'            => $memo_movements,
            'memo_clarifications'       => $clarifications,
            'memo_instructions'         => $memo_instructions,
            'outgoing_letter'           => $letter,
            'dg_comment'                => $dg_comment,
            'current_user_id'           => $user_id,
            'current_user_is_recipient' => $current_user_is_recipient,
            'current_user_is_approver'  => $current_user_is_approver
        ];
    } else {
        $response['message'] = 'Memo not found.';
    }
}

echo json_encode($response);
