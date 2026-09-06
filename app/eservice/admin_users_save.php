<?php
/** Admin — persist a user's e-Services role set. */
require __DIR__ . '/inc/bootstrap.php';
es_require_perm('users.manage');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect('admin_users.php');
}

$uid   = (int) ($_POST['uid'] ?? 0);
$want  = array_values(array_intersect(
    (array) ($_POST['roles'] ?? []),
    array_column(db_all("SELECT role_key FROM es_role"), 'role_key')
));
$pdeId = ($_POST['pde_id'] ?? '') !== '' ? (int) $_POST['pde_id'] : null;

if (!db_one("SELECT 1 x FROM users WHERE id = ?", 'i', [$uid])) {
    flash('User not found.', 'error');
    redirect('admin_users.php');
}
if (in_array('pde', $want, true) && !$pdeId) {
    flash('Choose which PDE this user represents.', 'error');
    redirect('admin_users.php');
}
// don't let an admin strip their own admin rights (lock-out guard)
if ($uid === $ES_UID && es_has_role('admin') && !in_array('admin', $want, true)) {
    flash("You can't remove your own Administrator role.", 'error');
    redirect('admin_users.php');
}

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM es_user_role WHERE user_id = $uid");
    if ($want) {
        $st = $conn->prepare("INSERT INTO es_user_role (user_id, role, pde_id) VALUES (?, ?, ?)");
        foreach ($want as $role) {
            $pv = $role === 'pde' ? $pdeId : null;
            $st->bind_param('isi', $uid, $role, $pv);
            $st->execute();
        }
        $st->close();
    }
    $conn->commit();
    flash('Roles updated.', 'success');
} catch (Throwable $e) {
    $conn->rollback();
    flash('Could not save: ' . $e->getMessage(), 'error');
}
redirect('admin_users.php');
