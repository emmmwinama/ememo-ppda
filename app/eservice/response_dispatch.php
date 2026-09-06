<?php
/** Mark a published PDE response as sent (emailed / handed over). Moves it to
 *  the "Sent" tab on the responses worklist. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('response.dispatch');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_responses.php');
}

$rid    = (int) ($_POST['response_id'] ?? 0);
$method = in_array($_POST['method'] ?? '', ['email', 'manual'], true) ? $_POST['method'] : 'manual';

$r = db_one("SELECT id, published, sent_at FROM es_pde_response WHERE id = ?", 'i', [$rid]);
if (!$r || !$r['published']) { flash('Response not found or not published.', 'error'); redirect('bid_responses.php'); }

if ($r['sent_at']) {
    flash('This response is already marked as sent.', 'error');
    redirect('bid_responses.php?tab=sent');
}

$conn->begin_transaction();
try {
    $st = $conn->prepare("UPDATE es_pde_response SET sent_at = NOW(), sent_by = ?, sent_method = ? WHERE id = ? AND sent_at IS NULL");
    $st->bind_param('isi', $ES_UID, $method, $rid);
    $st->execute();
    $st->close();

    $st = $conn->prepare("INSERT INTO es_response_access (response_id, user_id, action) VALUES (?, ?, 'email')");
    $st->bind_param('ii', $rid, $ES_UID);
    $st->execute();
    $st->close();

    $conn->commit();
    flash($method === 'email' ? 'Marked as emailed to the PDE.' : 'Marked as sent to the PDE.', 'success');
} catch (Throwable $ex) {
    $conn->rollback();
    flash('Could not update: ' . $ex->getMessage(), 'error');
}
redirect('bid_responses.php?tab=sent');
