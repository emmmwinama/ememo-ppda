<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'memo_utils.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json');

try {
    $from_user_id = $_SESSION['user_id'] ?? null;
    if (!$from_user_id) throw new Exception("User not authenticated.");

    $memo_id = $_POST['memo_id'] ?? null;
    if (!$memo_id) throw new Exception("Memo ID is required.");

    $to_user_id = $_POST['to_id'] ?? null;
    $through_ids = array_filter(explode(',', $_POST['through_ids'] ?? ''));
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'Draft';
    $signature_type = $_POST['signature_type'] ?? 'saved';
    $signature_data = $_POST['signature_data'] ?? null;

    // Check ownership and status
    $stmt = $conn->prepare("SELECT id, originator_id, status FROM memos WHERE id = ?");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute(); $result = $stmt->get_result();
    $memo = $result->fetch_assoc();
    $stmt->close();

    if (!$memo || $memo['originator_id'] != $from_user_id) {
        throw new Exception("Not allowed to update this memo.");
    }
    if (strtolower($memo['status']) !== 'draft') {
        throw new Exception("Only draft memos can be updated.");
    }

    $meta = getUserMeta($conn, $from_user_id);
    $to_code = getUserPositionCode($conn, $to_user_id);
    $stage_id = determineStage($conn, $status, count($through_ids) > 0, $to_code);

    // Update memo core fields
    $stmt = $conn->prepare("
        UPDATE memos SET subject = ?, content = ?, to_user_id = ?, status = ?, current_stage_id = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("ssisii", $subject, $content, $to_user_id, $status, $stage_id, $memo_id);
    $stmt->execute();

    // Remove old through recipients and placeholders
    $conn->query("DELETE FROM memo_through_recipients WHERE memo_id = $memo_id");
    $conn->query("DELETE FROM memo_signatures WHERE memo_id = $memo_id AND role IN ('Endorser', 'Approver')");

    // Re-insert recipients
    foreach ($through_ids as $uid) {
        $stmt = $conn->prepare("INSERT INTO memo_through_recipients (memo_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $memo_id, $uid);
        $stmt->execute();
    }
    addSignaturePlaceholders($conn, $memo_id, $through_ids, 'Endorser', $stage_id);
    addSignaturePlaceholders($conn, $memo_id, [$to_user_id], 'Approver', $stage_id);

    // Update or apply originator signature
    if ($signature_type === 'new' && $signature_data) {
        $imgParts = explode(";base64,", $signature_data);
        if (count($imgParts) !== 2) throw new Exception('Invalid signature format');
        $imgBase64 = base64_decode($imgParts[1]);
        $signature_path = 'signatures/' . uniqid('sig_', true) . '.png';
        file_put_contents($signature_path, $imgBase64);
        chmod($signature_path, 0644);

        saveSignature($conn, $memo_id, $from_user_id, 'Originator', $stage_id, $signature_path, date('Y-m-d H:i:s'));
    }

    // Attachments (append new)
    if (isset($_FILES['attachments'])) {
        saveAttachments($conn, $memo_id, $_FILES['attachments']);
    }

    // Log trail update
    $action = strtolower($status) === 'draft' ? 'Draft' : 'Submitted';
    $action_type = strtolower($status) === 'draft' ? 'Save' : 'Submit';
    $comment = "Memo updated by " . $meta['name'];
    insertTrail($conn, $memo_id, $from_user_id, $stage_id, $action, $action_type, $comment);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log("Memo update error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
