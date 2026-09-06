<?php
/** Cast (or change) a board member's vote on an analysis, attach optional
 *  comment / files, and decide once a majority of the Board has voted. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('analysis.review_board');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_board.php');
}

$id       = (int) ($_POST['id'] ?? 0);
$decision = ($_POST['decision'] ?? '') === 'return' ? 'return' : (($_POST['decision'] ?? '') === 'approve' ? 'approve' : '');
$comment  = trim($_POST['comment'] ?? '');
$back     = "bid_analysis_view.php?id=$id";

$a = db_one("SELECT id, registry_id, stage FROM es_bid_analysis WHERE id = ?", 'i', [$id]);
if (!$a) { flash('Analysis not found.', 'error'); redirect('bid_board.php'); }
if ($a['stage'] !== 'board_review') { flash('This analysis is not with the Board.', 'error'); redirect($back); }
if ($decision === '') { flash('Choose approve or return.', 'error'); redirect($back); }
if ($decision === 'return' && $comment === '') { flash('A return needs a comment for the DG.', 'error'); redirect($back); }

// ---- record the vote (one per member; re-voting overwrites) ------------
$st = $conn->prepare(
    "INSERT INTO es_bid_board_vote (analysis_id, user_id, decision, comment)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE decision = VALUES(decision), comment = VALUES(comment)"
);
$c = $comment !== '' ? $comment : null;
$st->bind_param('iiss', $id, $ES_UID, $decision, $c);
$st->execute();
$st->close();

// ---- optional supporting files -> analysis attachments ----------------
$added = 0;
if (!empty($_FILES['docs']) && is_array($_FILES['docs']['name'])) {
    $ext_ok = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip'];
    $dir = ES_UPLOAD_DIR . '/board/' . $id;
    foreach ($_FILES['docs']['name'] as $i => $name) {
        if (($_FILES['docs']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $tmp = $_FILES['docs']['tmp_name'][$i];
        $sz  = (int) $_FILES['docs']['size'][$i];
        $x   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!is_uploaded_file($tmp) || $sz <= 0 || $sz > 20 * 1024 * 1024 || !in_array($x, $ext_ok, true)) continue;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) continue;
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME));
        $rel   = 'board/' . $id . '/' . bin2hex(random_bytes(6)) . '_' . substr(trim($clean, '_.') ?: 'file', 0, 80) . '.' . $x;
        if (!move_uploaded_file($tmp, ES_UPLOAD_DIR . '/' . $rel)) continue;
        $orig = mb_substr($name, 0, 240);
        $s2 = $conn->prepare("INSERT INTO es_bid_attachment (analysis_id, kind, file_path, original_name, uploaded_by) VALUES (?, 'board', ?, ?, ?)");
        $s2->bind_param('issi', $id, $rel, $orig, $ES_UID);
        $s2->execute();
        $s2->close();
        $added++;
    }
}

// ---- tally: does the Board have a decision yet? ----------------------
$boardTotal = (int) (db_one("SELECT COUNT(*) c FROM es_user_role WHERE role = 'board'")['c'] ?? 0);
$need = intdiv($boardTotal, 2) + 1;                       // strict majority of all members
$vA = (int) (db_one("SELECT COUNT(*) c FROM es_bid_board_vote WHERE analysis_id = ? AND decision = 'approve'", 'i', [$id])['c'] ?? 0);
$vR = (int) (db_one("SELECT COUNT(*) c FROM es_bid_board_vote WHERE analysis_id = ? AND decision = 'return'",  'i', [$id])['c'] ?? 0);

$outcome = null;
if ($boardTotal > 0 && $vA >= $need)                 $outcome = 'approved';
elseif ($boardTotal > 0 && $vR >= $need)             $outcome = 'return';
elseif ($vA + $vR >= $boardTotal && $boardTotal > 0) $outcome = $vA > $vR ? 'approved' : 'return';   // everyone voted

if ($outcome === null) {
    flash('Vote recorded — ' . $vA . ' approve / ' . $vR . ' return so far.' . ($added ? " $added file(s) attached." : ''), 'success');
    redirect($back);
}

// ---- the Board has decided -----------------------------------------
$votes = db_all(
    "SELECT u.full_name, v.decision, v.comment FROM es_bid_board_vote v
       JOIN users u ON u.id = v.user_id WHERE v.analysis_id = ? ORDER BY v.id",
    'i', [$id]
);
$lines = [];
foreach ($votes as $v) {
    $lines[] = '• ' . $v['full_name'] . ' — ' . strtoupper($v['decision'])
             . (trim((string) $v['comment']) !== '' ? ': ' . $v['comment'] : '');
}
$summary = 'Board determination (' . $vA . ' approve / ' . $vR . ' return):' . "\n" . implode("\n", $lines);

// the DG who sent it to the Board (fallback: any dg-role user)
$dgRow = db_one(
    "SELECT from_user_id FROM es_bid_routing
      WHERE analysis_id = ? AND to_stage = 'board_review' ORDER BY id DESC LIMIT 1", 'i', [$id]
);
$dgUid = (int) ($dgRow['from_user_id']
        ?? (db_one("SELECT MIN(user_id) u FROM es_user_role WHERE role = 'dg'")['u'] ?? 0)) ?: null;

$conn->begin_transaction();
try {
    if ($outcome === 'approved') {
        $conn->query("UPDATE es_bid_analysis SET stage = 'approved', current_owner_id = NULL,
                        final_outcome = 'no_objection', decided_at = NOW() WHERE id = $id");
        $conn->query("UPDATE es_bid_registry SET status = 'completed' WHERE id = " . (int) $a['registry_id']);
        $st = $conn->prepare(
            "INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, from_stage, to_stage, comments)
             VALUES (?, ?, NULL, 'approve', 'board_review', 'approved', ?)"
        );
        $st->bind_param('iis', $id, $ES_UID, $summary);
        $st->execute();
        $st->close();
        $msg = 'Board approved — the review is finalised.';
    } else {
        $st = $conn->prepare(
            "UPDATE es_bid_analysis SET stage = 'dg_review', current_owner_id = ?,
                dg_feedback = ? WHERE id = ?"
        );
        $st->bind_param('isi', $dgUid, $summary, $id);
        $st->execute();
        $st->close();
        $st = $conn->prepare(
            "INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, from_stage, to_stage, comments)
             VALUES (?, ?, ?, 'return', 'board_review', 'dg_review', ?)"
        );
        $st->bind_param('iiis', $id, $ES_UID, $dgUid, $summary);
        $st->execute();
        $st->close();
        $msg = 'Board did not approve — returned to the DG with the members\' comments.';
    }
    // clear the votes so a re-referral to the Board starts fresh
    $conn->query("DELETE FROM es_bid_board_vote WHERE analysis_id = $id");
    $conn->commit();
    flash($msg, 'success');
} catch (Throwable $ex) {
    $conn->rollback();
    flash('Could not finalise the Board decision: ' . $ex->getMessage(), 'error');
}
redirect($back);
