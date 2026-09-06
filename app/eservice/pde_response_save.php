<?php
/** Save / publish the PDE response letter for an analysis. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('response.write');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('bid_analysis.php');
}

$aid    = (int) ($_POST['analysis_id'] ?? 0);
$body   = trim($_POST['body'] ?? '');
$action = ($_POST['action'] ?? '') === 'publish' ? 'publish' : 'draft';
$from   = $_POST['from'] ?? '';
$back   = match ($from) {
    'responses' => 'bid_responses.php?tab=' . ($action === 'publish' ? 'published' : 'unpublished'),
    'dgreview'  => 'bid_review.php?role=dg&tab=' . rawurlencode($_POST['dg_tab'] ?? 'inbox'),
    default     => "bid_analysis_view.php?id=$aid",
};

if ($action === 'publish' && !es_can('response.publish')) {
    flash('You can draft the letter, but publishing it needs the response.publish permission.', 'error');
    redirect($back);
}

$a = db_one("SELECT id FROM es_bid_analysis WHERE id = ?", 'i', [$aid]);
if (!$a) { flash('Analysis not found.', 'error'); redirect('bid_analysis.php'); }
if ($body === '') { flash('The letter body is empty.', 'error'); redirect($back); }

$existing  = db_one("SELECT id FROM es_pde_response WHERE analysis_id = ? ORDER BY id DESC LIMIT 1", 'i', [$aid]);
$published = $action === 'publish' ? 1 : 0;

if ($existing) {
    $st = $conn->prepare(
        "UPDATE es_pde_response
            SET body = ?, published = ?,
                published_at = IF(? = 1, COALESCE(published_at, NOW()), published_at)
          WHERE id = ?"
    );
    $st->bind_param('siii', $body, $published, $published, $existing['id']);
    $st->execute();
    $st->close();
    $rid = (int) $existing['id'];
} else {
    $st = $conn->prepare(
        "INSERT INTO es_pde_response (analysis_id, body, published, published_at, created_by)
         VALUES (?, ?, ?, IF(? = 1, NOW(), NULL), ?)"
    );
    $st->bind_param('isiii', $aid, $body, $published, $published, $ES_UID);
    $st->execute();
    $rid = (int) $conn->insert_id;
    $st->close();
}

flash($published ? 'Response letter published.' : 'Response letter saved as draft.', 'success');
redirect($back);
