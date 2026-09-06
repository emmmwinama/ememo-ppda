<?php
require_once 'db.php';
require_once 'auth.php';

$status = $_GET['status'] ?? 'Submitted';
$from_user_id = $_SESSION['user_id'] ?? 0;

// Prepare SQL statement
$stmt = $conn->prepare("SELECT id, reference_number, subject, memo_type, created_at FROM direct_memos WHERE from_user_id = ? AND status = ? ORDER BY created_at DESC");
$stmt->bind_param("is", $from_user_id, $status);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML table rows
if ($result->num_rows > 0) {
    echo '<table class="table table-bordered">';
    echo '<thead><tr><th>Reference</th><th>Subject</th><th>Type</th><th>Date</th><th>Action</th></tr></thead><tbody>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['reference_number']) . '</td>';
        echo '<td>' . htmlspecialchars($row['subject']) . '</td>';
        echo '<td>' . htmlspecialchars($row['memo_type']) . '</td>';
        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
        echo '<td><a href="index.php?module=dg_memos&id=' . $row['id'] . '" class="btn btn-sm btn-primary">Edit</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p class="text-muted">No memos found.</p>';
}
?>
