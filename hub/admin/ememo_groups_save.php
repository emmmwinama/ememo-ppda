<?php
/** Administration — persist e-Memo groups + membership. */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_guard.php';

global $conn;

$op = $_POST['op'] ?? '';
$gid = (int) ($_POST['group_id'] ?? 0);
$back = 'ememo_groups.php' . ($gid ? '?g=' . $gid : '');

try {
    switch ($op) {
        case 'create_group':
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if ($name === '') throw new RuntimeException('Group name is required.');
            $st = $conn->prepare("INSERT INTO `groups` (name, description) VALUES (?, ?)");
            $st->bind_param('ss', $name, $desc);
            $st->execute();
            $gid = (int) $conn->insert_id;
            $st->close();
            hub_audit("Created e-Memo group '$name' (#$gid)");
            hub_flash('Group created.', 'success');
            $back = 'ememo_groups.php?g=' . $gid;
            break;

        case 'delete_group':
            $conn->query("DELETE FROM group_members WHERE group_id = " . $gid);
            $st = $conn->prepare("DELETE FROM `groups` WHERE id = ?");
            $st->bind_param('i', $gid);
            $st->execute();
            $st->close();
            hub_audit("Deleted e-Memo group #$gid");
            hub_flash('Group deleted.', 'success');
            $back = 'ememo_groups.php';
            break;

        case 'add_member':
            $uid = (int) ($_POST['user_id'] ?? 0);
            if (!$gid || !$uid) throw new RuntimeException('Pick a user.');
            $st = $conn->prepare("REPLACE INTO group_members (group_id, user_id) VALUES (?, ?)");
            $st->bind_param('ii', $gid, $uid);
            $st->execute();
            $st->close();
            hub_audit("Added user #$uid to e-Memo group #$gid");
            hub_flash('Member added.', 'success');
            break;

        case 'remove_member':
            $uid = (int) ($_POST['user_id'] ?? 0);
            $st = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
            $st->bind_param('ii', $gid, $uid);
            $st->execute();
            $st->close();
            hub_audit("Removed user #$uid from e-Memo group #$gid");
            hub_flash('Member removed.', 'success');
            break;

        default:
            hub_flash('Unknown action.', 'error');
    }
} catch (Throwable $e) {
    hub_flash($e instanceof RuntimeException ? $e->getMessage() : 'Could not complete that action.', 'error');
}
redirect($back);
