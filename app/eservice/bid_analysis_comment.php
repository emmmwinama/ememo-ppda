<?php
/** Bid analysis — post a discussion comment. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('analysis.comment');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_analysis.php');
}

$id   = (int) ($_POST['id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$a = db_one("SELECT id FROM es_bid_analysis WHERE id = ?", 'i', [$id]);

if ($a && $body !== '') {
    $st = $conn->prepare("INSERT INTO es_bid_message (analysis_id, user_id, body) VALUES (?,?,?)");
    $st->bind_param('iis', $id, $ES_UID, $body);
    $st->execute();
    $st->close();
} else {
    flash('Comment was empty.', 'error');
}
redirect("bid_analysis_view.php?id=$id");
