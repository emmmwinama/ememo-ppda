<?php
// update_clarification.php
require_once 'auth.php';  // sets $_SESSION['user_id']
require_once 'db.php';    // provides $conn (mysqli)

header('Content-Type: application/json');

// 1) Read and validate JSON payload
$input = json_decode(file_get_contents('php://input'), true);
if (
    !isset($input['id'], $input['comment']) ||
    !is_numeric($input['id']) ||
    trim($input['comment']) === ''
) {
    http_response_code(400);
    echo json_encode([
      'status'  => 'error',
      'message' => 'Invalid input.'
    ]);
    exit;
}

$clarId  = (int)$input['id'];
$comment = trim($input['comment']);
$userId  = $_SESSION['user_id'];

// 2) Verify ownership
$stmt = $conn->prepare("
    SELECT requested_by 
      FROM memo_clarifications 
     WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $clarId);
$stmt->execute();
$stmt->bind_result($requestedBy);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode([
      'status'  => 'error',
      'message' => 'Clarification not found.'
    ]);
    exit;
}
$stmt->close();

if ((int)$requestedBy !== $userId) {
    http_response_code(403);
    echo json_encode([
      'status'  => 'error',
      'message' => 'You are not authorized to edit this clarification.'
    ]);
    exit;
}

// 3) Perform the update (only the comment field)
$upd = $conn->prepare("
    UPDATE memo_clarifications
       SET clarification_comment = ?
     WHERE id = ?
");
$upd->bind_param('si', $comment, $clarId);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    echo json_encode(['status'=>'success']);
} else {
    http_response_code(500);
    echo json_encode([
      'status'  => 'error',
      'message' => 'Database error: ' . $conn->error
    ]);
}
