<?php
require_once 'auth.php';
require 'db.php';

// Only show memos you forwarded or that were forwarded to you
$userId = $_SESSION['user_id'];

// Fetch forwarded records from your movements table
$sql = "
  SELECT
    m.id AS memo_id,
    m.subject,
    mm.to_user_id,
    mm.comments AS forward_type,
    mm.timestamp
  FROM memo_movements mm
  JOIN memos m ON m.id = mm.memo_id
  WHERE mm.action LIKE 'Forwarded:%'
    AND (mm.from_user_id = ? OR mm.to_user_id = ?)
  ORDER BY mm.timestamp DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$res = $stmt->get_result();
?>

<div class="card">
  <div class="card-header bg-success text-white">
    <h5 class="mb-0">Forwarded Memos</h5>
  </div>
  <div class="card-body p-0">
    <?php if ($res->num_rows): ?>
      <table class="table table-striped mb-0">
        <thead class="table-success">
          <tr>
            <th>Memo #</th>
            <th>Subject</th>
            <th>Type</th>
            <th>To / From</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($row = $res->fetch_assoc()): ?>
          <?php
            // determine direction
            $otherId = ($row['to_user_id'] === $userId)
              ? $row['memo_id'] // you were the recipient
              : $row['to_user_id']; // you forwarded to someone
            // fetch the other user's name
            $uStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
            $uStmt->bind_param("i", $otherId);
            $uStmt->execute();
            $uStmt->bind_result($otherName);
            $uStmt->fetch();
            $uStmt->close();
          ?>
          <tr>
            <td><a href="view_memo.php?memo_id=<?= $row['memo_id'] ?>">
              <?= htmlspecialchars($row['memo_id']) ?></a></td>
            <td><?= htmlspecialchars($row['subject']) ?></td>
            <td><?= htmlspecialchars($row['forward_type']) ?></td>
            <td><?= htmlspecialchars($otherName) ?></td>
            <td><?= date('Y-m-d H:i', strtotime($row['timestamp'])) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="p-3 mb-0 text-muted">No forwarded memos found.</p>
    <?php endif; ?>
  </div>
</div>
