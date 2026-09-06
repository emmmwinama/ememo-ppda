<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $section_id = intval($_POST['section_id'] ?? 0);
    $position_id = intval($_POST['position_id'] ?? 0);

    if (!$full_name || !$username || !$email || !$password || !$role || !$department_id || !$section_id || !$position_id) {
        throw new Exception('All fields except phone number are required.');
    }

    // Validate role
    $allowed_roles = ['originator', 'endorser', 'approver', 'admin'];
    if (!in_array($role, $allowed_roles)) {
        throw new Exception('Invalid role selected.');
    }

    // Check for duplicate username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        throw new Exception('Username already taken.');
    }
    $stmt->close();

    // Hash password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("
        INSERT INTO users (full_name, username, email, phone_number, password, role, department_id, section_id, position_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssssssiii', $full_name, $username, $email, $phone_number, $hashed_password, $role, $department_id, $section_id, $position_id);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'User created successfully.']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
