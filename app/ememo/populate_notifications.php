<?php
// populate_notifications.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$userId = intval($_SESSION['user_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// *** ONE‑TIME: enforce uniqueness in the DB ***
// Run this in your MySQL console (or via a migration).
// ALTER TABLE notifications
//   ADD UNIQUE KEY ux_notifications_user_event_history (user_id, event_type, history_id);

try {
    $conn->begin_transaction();

    //
    // ─── LETTER INSTRUCTIONS ────────────────────────────────────────
    //
    $conn->query(<<<SQL
      INSERT IGNORE INTO notifications
        (user_id, event_type, object_id, history_id, related_id, message, url)
      SELECT
        tgt.id,
        'letter_instruction',
        et.letter_id,
        et.id AS history_id,
        src.id         AS related_id,
        CONCAT(src.full_name,
               ' instructed you on letter ',
               l.reference_number,
               ': “', et.instruction, '”'
        ) AS message,
        CONCAT('external_assigned.php?letter_id=', et.letter_id) AS url
      FROM external_letter_trail AS et
      JOIN external_letters        AS l   ON l.id = et.letter_id
      JOIN users                   AS src ON src.id = et.from_user_id
      JOIN users                   AS tgt ON tgt.id = et.to_user_id
      LEFT JOIN notifications      AS n
        ON n.history_id = et.id
       AND n.user_id    = tgt.id
      WHERE et.to_user_id IS NOT NULL
        AND n.id IS NULL
        AND tgt.id = {$userId}
    SQL
    );

    //
    // ─── LETTER COMMENTS ───────────────────────────────────────────
    //
    $conn->query(<<<SQL
      INSERT IGNORE INTO notifications
        (user_id, event_type, object_id, history_id, related_id, message, url)
      SELECT
        tgt.id,
        'letter_comment',
        c.letter_id,
        c.id AS history_id,
        src.id         AS related_id,
        CONCAT(src.full_name,
               ' commented on letter ',
               l.reference_number,
               ': “', c.comment, '”'
        ) AS message,
        CONCAT('external_assigned.php?letter_id=', c.letter_id) AS url
      FROM external_letter_comments AS c
      JOIN external_letters             AS l   ON l.id = c.letter_id
      JOIN users                        AS src ON src.id = c.user_id
      JOIN users                        AS tgt ON tgt.id = c.assigned_to
      LEFT JOIN notifications           AS n
        ON n.history_id = c.id
       AND n.user_id    = tgt.id
      WHERE c.assigned_to IS NOT NULL
        AND n.id IS NULL
        AND tgt.id = {$userId}
    SQL
    );

    //
    // ─── MEMO INSTRUCTIONS ──────────────────────────────────────────
    //
    $conn->query(<<<SQL
      INSERT IGNORE INTO notifications
        (user_id, event_type, object_id, history_id, related_id, message, url)
      SELECT
        tgt.id,
        'memo_instruction',
        mi.memo_id,
        mi.id AS history_id,
        src.id         AS related_id,
        CONCAT(src.full_name,
               ' instructed you on memo ',
               m.memo_id,
               ': “', mi.instruction, '”'
        ) AS message,
        CONCAT('index.php?module=my_memos&memo_id=', mi.memo_id) AS url
      FROM memo_instructions AS mi
      JOIN memos                AS m   ON m.id = mi.memo_id
      JOIN users               AS src ON src.id = mi.user_id
      JOIN users               AS tgt ON tgt.id = mi.recipient_id
      LEFT JOIN notifications  AS n
        ON n.history_id = mi.id
       AND n.user_id    = tgt.id
      WHERE n.id IS NULL
        AND tgt.id = {$userId}
    SQL
    );

    //
    // ─── MEMO CLARIFICATIONS ────────────────────────────────────────
    //
    $conn->query(<<<SQL
      INSERT IGNORE INTO notifications
        (user_id, event_type, object_id, history_id, related_id, message, url)
      SELECT
        tgt.id,
        'memo_clarification',
        mc.memo_id,
        mc.id AS history_id,
        src.id         AS related_id,
        CONCAT(src.full_name,
               ' requested clarification on memo ',
               m.memo_id,
               ': “', mc.clarification_comment, '”'
        ) AS message,
        CONCAT('index.php?module=my_memos&memo_id=', mc.memo_id) AS url
      FROM memo_clarifications AS mc
      JOIN memos                  AS m   ON m.id = mc.memo_id
      JOIN users                 AS src ON src.id = mc.user_id
      JOIN users                 AS tgt ON tgt.id = mc.requested_by
      LEFT JOIN notifications    AS n
        ON n.history_id = mc.id
       AND n.user_id    = tgt.id
      WHERE n.id IS NULL
        AND tgt.id = {$userId}
    SQL
    );

    //
    // ─── MEMO MOVEMENTS (forward/escalate/approve/etc.) ─────────────
    //
    $conn->query(<<<SQL
      INSERT IGNORE INTO notifications
        (user_id, event_type, object_id, history_id, related_id, message, url)
      SELECT
        tgt.id,
        CONCAT('memo_', LOWER(mt.action)),
        mt.memo_id,
        mt.id AS history_id,
        src.id         AS related_id,
        CONCAT(src.full_name,
               ' ', LOWER(mt.action),
               ' memo ', m.memo_id,
               IF(mt.comments<>'', CONCAT(' – “', mt.comments, '”'), '')
        ) AS message,
        CONCAT('index.php?module=my_memos&memo_id=', mt.memo_id) AS url
      FROM memo_movements AS mt
      JOIN memos               AS m   ON m.id = mt.memo_id
      JOIN users               AS src ON src.id = mt.from_user_id
      JOIN users               AS tgt ON tgt.id = mt.to_user_id
      LEFT JOIN notifications  AS n
        ON n.history_id = mt.id
       AND n.user_id    = tgt.id
      WHERE mt.to_user_id IS NOT NULL
        AND n.id IS NULL
        AND tgt.id = {$userId}
    SQL
    );

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Notifications populated']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'Populate failed: ' . $e->getMessage()
    ]);
}
