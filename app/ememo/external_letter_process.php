<?php
// external_letter_process.php

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/auth.php';   // sets $_SESSION['is_controlling_officer'], user_id
require_once __DIR__ . '/db.php';     // provides $conn (mysqli)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Permission check
if (empty($_SESSION['is_controlling_officer'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

// 2) Gather & validate inputs
$letterId    = intval($_POST['id'] ?? 0);
$rawAssignees= $_POST['assignees'] ?? [];
$instruction = trim($_POST['instruction'] ?? '');
$dueDate     = trim($_POST['due_date'] ?? '');
$authorId    = intval($_SESSION['user_id'] ?? 0);

if ($letterId <= 0) {
    echo json_encode(['success'=>false,'message'=>'Missing or invalid letter ID.']);
    exit;
}
if (!is_array($rawAssignees) || count($rawAssignees) === 0) {
    echo json_encode(['success'=>false,'message'=>'Select at least one assignee.']);
    exit;
}
if ($instruction === '') {
    echo json_encode(['success'=>false,'message'=>'Instruction text is required.']);
    exit;
}

// 3) Clean & dedupe assignees
$assignees = array_filter(
    array_unique(array_map('intval', $rawAssignees)),
    function($v){ return $v > 0; }
);
if (empty($assignees)) {
    echo json_encode(['success'=>false,'message'=>'No valid assignees selected.']);
    exit;
}

try {
    // 4) Begin transaction
    $conn->begin_transaction();

    // 5) Insert one instruction record (to_user_id=NULL)
    $stmt = $conn->prepare("
      INSERT INTO external_letter_trail
        (letter_id, from_user_id, to_user_id, instruction, due_date, status, created_at)
      VALUES (?, ?, NULL, ?, ?, 'pending', NOW())
    ");
    $due = $dueDate !== '' ? $dueDate : null;
    $stmt->bind_param("iiss", $letterId, $authorId, $instruction, $due);
    $stmt->execute();
    $stmt->close();

    // 6) Loop each assignee → delegation only
    $firstAssignee = null;
    $dStmt = $conn->prepare("
      INSERT INTO external_letter_delegation
        (letter_id, delegated_by, delegated_to, status, created_at)
      VALUES (?, ?, ?, 'pending', NOW())
    ");
    foreach ($assignees as $aid) {
        if ($firstAssignee === null) {
            $firstAssignee = $aid;
        }
        $dStmt->bind_param("iii", $letterId, $authorId, $aid);
        $dStmt->execute();
    }
    $dStmt->close();

    // 7) Update letter’s status & current assignee (first one)
    if ($firstAssignee !== null) {
        $uStmt = $conn->prepare("
          UPDATE external_letters
             SET status           = 'assigned',
                 current_assignee = ?
           WHERE id = ?
        ");
        $uStmt->bind_param("ii", $firstAssignee, $letterId);
        $uStmt->execute();
        $uStmt->close();
    }

    // 8) Commit
    $conn->commit();
    echo json_encode(['success'=>true]);

} catch (\mysqli_sql_exception $e) {
    // 9) Rollback + log
    $conn->rollback();
    error_log("external_letter_process error: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error. Please try again.']);
}
