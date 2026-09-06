<?php
require_once 'db.php';
require_once 'auth.php';
header('Content-Type: application/json');

$subject = $_POST['subject'] ?? '';
$reference_number = $_POST['reference_number'] ?? '';
$content = $_POST['content'] ?? '';
$memo_type = $_POST['memo_type'] ?? 'CIRCULAR';
$status = $_POST['status'] ?? 'Submitted';
$from_user_id = $_SESSION['user_id'] ?? 0;
$date = $_POST['date'] ?? date('Y-m-d');
$send_all = isset($_POST['send_all']) ? 1 : 0;
$signature_data = $_POST['signature_data'] ?? '';

// Generate memo ID
$memo_id = uniqid('memo_');

// Fallback to saved signature
if (empty($signature_data)) {
    $stmt = $conn->prepare("SELECT image_path FROM signatures WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $from_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $path = $row['image_path'];
        $signature_data = $path ? file_get_contents($path) : '';
    }
}

// Insert memo
$stmt = $conn->prepare("INSERT INTO direct_memos (memo_id, reference_number, subject, content, from_user_id, memo_type, created_at, status, signature_data, send_all)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssissssi", $memo_id, $reference_number, $subject, $content, $from_user_id, $memo_type, $date, $status, $signature_data, $send_all);
$stmt->execute();
$memo_id_db = $stmt->insert_id;

// Save recipients
function insert_recipients($conn, $memo_id_db, $type, $ids, $role) {
    if (empty($ids)) return;
    $stmt = $conn->prepare("INSERT INTO direct_memo_recipients (direct_memo_id, recipient_type, recipient_id, recipient_role) VALUES (?, ?, ?, ?)");
    foreach (explode(',', $ids) as $id) {
        $id = trim($id);
        if ($id !== '') {
            $stmt->bind_param("isis", $memo_id_db, $type, $id, $role);
            $stmt->execute();
        }
    }
}

// Insert TO and CC recipients
insert_recipients($conn, $memo_id_db, 'user', $_POST['to_user_ids'] ?? '', 'To');
insert_recipients($conn, $memo_id_db, 'group', $_POST['to_group_ids'] ?? '', 'To');
insert_recipients($conn, $memo_id_db, 'user', $_POST['cc_user_ids'] ?? '', 'CC');
insert_recipients($conn, $memo_id_db, 'group', $_POST['cc_group_ids'] ?? '', 'CC');

// Handle attachments
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['tmp_name'])) {
    foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpPath) {
        $original = $_FILES['attachments']['name'][$index];
        if (!is_uploaded_file($tmpPath)) continue;

        $newFile = uniqid('file_') . '_' . basename($original);
        $dest = $uploadDir . $newFile;

        if (move_uploaded_file($tmpPath, $dest)) {
            $filePath = 'uploads/' . $newFile;
            $stmt = $conn->prepare("INSERT INTO direct_memo_attachments (memo_id, file_name, file_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $memo_id_db, $original, $filePath);
            $stmt->execute();
        }
    }
}

echo json_encode(['status' => 'success', 'message' => 'Memo submitted successfully']);
?>
