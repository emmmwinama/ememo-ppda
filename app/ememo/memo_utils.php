<?php

function getUserMeta($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT u.full_name, u.section_id, p.position_code 
        FROM users u 
        JOIN positions p ON u.position_id = p.id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($name, $section_id, $position_code);
    $stmt->fetch();
    $stmt->close();

    return [
        'name' => $name,
        'section_id' => $section_id,
        'position_code' => $position_code
    ];
}


function getUserPositionCode($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT p.position_code 
        FROM users u 
        JOIN positions p ON u.position_id = p.id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($code);
    $stmt->fetch();
    $stmt->close();
    return $code;
}


function getApprovalStageId($conn, $stage_name) {
    $stmt = $conn->prepare("SELECT id FROM approval_stages WHERE name = ?");
    $stmt->bind_param("s", $stage_name);
    $stmt->execute();
    $stmt->bind_result($id);
    $stmt->fetch();
    $stmt->close();
    return $id;
}

function determineStage($conn, $status, $has_through, $to_position_code) {
    $status = strtolower($status);
    if ($status === 'draft') {
        return getApprovalStageId($conn, 'Draft');
    }

    if ($has_through && $to_position_code == 1) {
        return getApprovalStageId($conn, 'Director Approval');
    } elseif (!$has_through && $to_position_code == 3) {
        return getApprovalStageId($conn, 'Director Approval');
    } elseif (!$has_through && $to_position_code == 1) {
        return getApprovalStageId($conn, 'DG Approval');
    }

    return getApprovalStageId($conn, 'Director Approval'); // default fallback
}

function insertTrail($conn, $memo_id, $user_id, $stage_id, $action, $action_type, $comment = null) {
    $stmt = $conn->prepare("INSERT INTO memo_trail (memo_id, user_id, stage_id, action, action_type, comment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisss", $memo_id, $user_id, $stage_id, $action, $action_type, $comment);
    $stmt->execute();
}

function saveSignature($conn, $memo_id, $user_id, $role, $stage_id, $signature_path = null, $signed_at = null) {
    $stmt = $conn->prepare("INSERT INTO memo_signatures (memo_id, user_id, role, stage_id, signature_path, signed_at) VALUES (?, ?, ?, ?, ?, ?)");
    // types: memo_id i, user_id i, role s, stage_id i, signature_path s, signed_at s
    $stmt->bind_param("iisiss", $memo_id, $user_id, $role, $stage_id, $signature_path, $signed_at);
    $stmt->execute();
}

function addSignaturePlaceholders($conn, $memo_id, $user_ids, $role, $stage_id) {
    foreach ($user_ids as $uid) {
        $stmt = $conn->prepare("INSERT INTO memo_signatures (memo_id, user_id, role, stage_id) VALUES (?, ?, ?, ?)");
        // types: memo_id i, user_id i, role s, stage_id i
        $stmt->bind_param("iisi", $memo_id, $uid, $role, $stage_id);
        $stmt->execute();
    }
}

function saveAttachments($conn, $memo_id, $files, $uploadDir = __DIR__ . '/uploads/') {
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'zip', 'rar'];
    $allowed_mime = [
        'image/jpeg', 'image/png', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/x-rar-compressed'
    ];

    foreach ($files['tmp_name'] as $i => $tmpName) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $original = basename($files['name'][$i]);
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $mime = mime_content_type($tmpName);

            if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
                throw new Exception("Invalid file type: $original");
            }

            $safeName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $original);
            $newName = uniqid('att_', true) . '_' . $safeName;
            $dest = $uploadDir . $newName;
            $webPath = 'uploads/' . $newName;

            if (move_uploaded_file($tmpName, $dest)) {
                chmod($dest, 0644);
                $stmt = $conn->prepare("INSERT INTO attachments (memo_id, file_name, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $memo_id, $original, $webPath);
                $stmt->execute();
            }
        }
    }
}
