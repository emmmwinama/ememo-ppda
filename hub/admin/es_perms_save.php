<?php
/** Administration — persist an e-Services role's permission bundle. */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_guard.php';

global $conn;

$key = $_POST['role_key'] ?? '';
if (!db_one("SELECT 1 x FROM es_role WHERE role_key = ?", 's', [$key])) {
    hub_flash('Role not found.', 'error');
    redirect('es_roles.php');
}

$valid = array_column(db_all("SELECT perm_key FROM es_permission"), 'perm_key');
$want  = array_values(array_intersect((array) ($_POST['perms'] ?? []), $valid));

$conn->begin_transaction();
try {
    $st = $conn->prepare("DELETE FROM es_role_permission WHERE role_key = ?");
    $st->bind_param('s', $key);
    $st->execute();
    $st->close();
    if ($want) {
        $st = $conn->prepare("INSERT INTO es_role_permission (role_key, perm_key) VALUES (?, ?)");
        foreach ($want as $pk) { $st->bind_param('ss', $key, $pk); $st->execute(); }
        $st->close();
    }
    $conn->commit();
    hub_audit("Set permissions for e-Services role '$key' (" . count($want) . ' permission(s))');
    hub_flash('Permissions updated.', 'success');
} catch (Throwable $e) {
    $conn->rollback();
    hub_flash('Could not save: ' . $e->getMessage(), 'error');
}
redirect('es_roles.php?role=' . urlencode($key));
