<?php
require_once 'db.php';
require_once 'auth.php';

$status = $_GET['status'] ?? 'Submitted';
$from_user_id = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("SELECT id, reference_number, subject, memo_type, created_at FROM direct_memos WHERE from_user_id = ? AND status = ? ORDER BY created_at DESC");
$stmt->bind_param("is", $from_user_id, $status);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<div class="f-panel table-responsive"><table class="table table-borderless f-table align-middle mb-0">';
    echo '<thead><tr><th>Reference</th><th>Subject</th><th>Type</th><th>Date</th><th>Action</th></tr></thead><tbody>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td class="font-monospace">' . htmlspecialchars($row['reference_number']) . '</td>';
        echo '<td class="fw-semibold">' . htmlspecialchars($row['subject']) . '</td>';
        echo '<td class="text-muted">' . htmlspecialchars($row['memo_type']) . '</td>';
        echo '<td class="text-muted">' . htmlspecialchars($row['created_at']) . '</td>';
        echo '<td><a href="index.php?module=dg_memos&id=' . (int)$row['id'] . '" class="btn btn-sm btn-outline-success"><i class="bi bi-pencil me-1"></i>Edit</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
} else {
    $label = strtolower(htmlspecialchars($status));
    echo '<div class="f-state"><i class="bi bi-megaphone"></i><p>No ' . $label . ' broadcasts yet.</p></div>';
}
?>
