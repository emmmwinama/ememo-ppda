<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    $users = [];

    // Fetch all users with full_name and id
    $query = "SELECT id, full_name FROM users ORDER BY full_name ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'users' => $users
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch users list.'
    ]);
}
?>
