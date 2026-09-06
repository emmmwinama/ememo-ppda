<?php
require_once 'db.php';
require_once 'auth.php';

$q = trim($_GET['q'] ?? '');
if (!$q) exit;

$search = "%$q%";

// Search users
$stmtUser = $conn->prepare("
  SELECT u.id, u.full_name, p.name AS position_name, p.short_name
  FROM users u
  LEFT JOIN positions p ON u.position_id = p.id
  WHERE u.full_name LIKE ? OR p.name LIKE ? OR p.short_name LIKE ?
  LIMIT 10
");
$stmtUser->bind_param("sss", $search, $search, $search);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

// Search groups
$stmtGroup = $conn->prepare("
  SELECT id, name, description
  FROM groups
  WHERE name LIKE ? OR description LIKE ?
  LIMIT 10
");
$stmtGroup->bind_param("ss", $search, $search);
$stmtGroup->execute();
$resultGroup = $stmtGroup->get_result();

// Output users
if ($resultUser->num_rows > 0) {
    echo "<div class='list-group-item text-muted small bg-light'>Users</div>";
    while ($row = $resultUser->fetch_assoc()) {
        $position = $row['position_name'] ? "{$row['position_name']} ({$row['short_name']})" : 'No Position';
        echo "<button type='button' class='list-group-item list-group-item-action' 
                data-user-id='{$row['id']}' data-type='user'>
                {$row['full_name']} - $position
              </button>";
    }
}

// Output groups
if ($resultGroup->num_rows > 0) {
    echo "<div class='list-group-item text-muted small bg-light'>Groups</div>";
    while ($row = $resultGroup->fetch_assoc()) {
        $desc = $row['description'] ? " - " . htmlspecialchars($row['description']) : "";
        echo "<button type='button' class='list-group-item list-group-item-action' 
                data-group-id='{$row['id']}' data-type='group'>
                {$row['name']}{$desc}
              </button>";
    }
}

if ($resultUser->num_rows === 0 && $resultGroup->num_rows === 0) {
    echo "<div class='list-group-item disabled'>No matches found</div>";
}
?>
