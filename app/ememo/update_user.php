<?php
require_once 'auth.php';   // also runs csrf_verify() on POST
require_once 'db.php';

header('Content-Type: application/json');

$httpError = 400;
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        $httpError = 403;
        throw new Exception('Admin access required.');
    }

    $id           = intval($_POST['id'] ?? 0);
    $full_name    = trim($_POST['full_name'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $role         = trim($_POST['role'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $department_id = ($_POST['department_id'] ?? '') !== '' ? intval($_POST['department_id']) : null;
    $section_id    = ($_POST['section_id'] ?? '')    !== '' ? intval($_POST['section_id'])    : null;
    $position_id   = ($_POST['position_id'] ?? '')   !== '' ? intval($_POST['position_id'])   : null;
    $active   = !empty($_POST['active']) ? 1 : 0;
    $is_co    = !empty($_POST['is_controlling_officer']) ? 1 : 0;
    $is_sec   = !empty($_POST['is_secretary']) ? 1 : 0;

    if ($id <= 0)                                    throw new Exception('Invalid user ID.');
    if (!$full_name || !$username || !$email || !$role) {
        throw new Exception('Name, username, email and role are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address.');

    $allowed_roles = ['originator', 'endorser', 'approver', 'admin'];
    if (!in_array($role, $allowed_roles, true))     throw new Exception('Invalid role selected.');

    // Username must stay unique (excluding this user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
    $stmt->bind_param('si', $username, $id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) throw new Exception('Username already taken.');
    $stmt->close();

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users SET
              full_name = ?, username = ?, email = ?, phone_number = ?, role = ?,
              department_id = ?, section_id = ?, position_id = ?,
              active = ?, is_controlling_officer = ?, is_secretary = ?,
              password = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            'sssssiiiiiisi',
            $full_name, $username, $email, $phone_number, $role,
            $department_id, $section_id, $position_id,
            $active, $is_co, $is_sec,
            $hash, $id
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE users SET
              full_name = ?, username = ?, email = ?, phone_number = ?, role = ?,
              department_id = ?, section_id = ?, position_id = ?,
              active = ?, is_controlling_officer = ?, is_secretary = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            'sssssiiiiiii',
            $full_name, $username, $email, $phone_number, $role,
            $department_id, $section_id, $position_id,
            $active, $is_co, $is_sec,
            $id
        );
    }

    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'User updated successfully.']);
} catch (Exception $e) {
    http_response_code($httpError);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
