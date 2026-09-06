<?php
// external_letter_redelegate.php
header('Content-Type: application/json');
if (session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__.'/auth.php';   // sets $_SESSION['is_controlling_officer'], user_id
require_once __DIR__.'/db.php';     // provides $conn (mysqli)



// parse JSON body, if any
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// gather params from JSON or form‑encoded
$letterId     = intval($input['letter_id']     ?? $_POST['letter_id']     ?? 0);
$rawAssignees = $input['delegate_to']           ?? $_POST['delegate_to']   ?? [];
$instruction  = trim($input['instruction']      ?? $_POST['instruction']    ?? '');
$authorId     = intval($_SESSION['user_id']     ?? 0);

// basic validation
if ($letterId <= 0 || !is_array($rawAssignees) || empty($rawAssignees) || $instruction === '') {
    echo json_encode(['success'=>false,'message'=>'Missing required fields.']);
    exit;
}

// clean & dedupe
$assignees = array_filter(
    array_unique(array_map('intval', $rawAssignees)),
    fn($v) => $v > 0
);
if (empty($assignees)) {
    echo json_encode(['success'=>false,'message'=>'No valid assignees.']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1) Trail instruction
    $stmt = $conn->prepare("
      INSERT INTO external_letter_trail
        (letter_id, from_user_id, instruction, created_at)
      VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $letterId, $authorId, $instruction);
    $stmt->execute();
    $stmt->close();

    // 2) Redelegate old pending
    $upd = $conn->prepare("
      UPDATE external_letter_delegation
         SET status = 'redelegated'
       WHERE letter_id = ? AND status = 'pending'
    ");
    $upd->bind_param("i", $letterId);
    $upd->execute();
    $upd->close();

    // 3) Insert new delegations
    $dins  = $conn->prepare("
      INSERT INTO external_letter_delegation
        (letter_id, delegated_by, delegated_to, status, created_at)
      VALUES (?, ?, ?, 'pending', NOW())
    ");
    $first = null;
    foreach ($assignees as $aid) {
        if ($first === null) $first = $aid;
        $dins->bind_param("iii", $letterId, $authorId, $aid);
        $dins->execute();
    }
    $dins->close();

    // 4) Update letter’s current assignee
    if ($first !== null) {
        $ust = $conn->prepare("
          UPDATE external_letters
             SET status           = 'assigned',
                 current_assignee = ?
           WHERE id = ?
        ");
        $ust->bind_param("ii", $first, $letterId);
        $ust->execute();
        $ust->close();
    }

    $conn->commit();
    echo json_encode(['success'=>true]);
} catch (\mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("redelegate error: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error. Please try again.']);
}
