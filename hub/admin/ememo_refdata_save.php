<?php
/** Administration — persist e-Memo reference data. */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_guard.php';

global $conn;

$tables = ['departments' => 'departments', 'sections' => 'sections', 'positions' => 'positions', 'grades' => 'grades'];
$entity = $_POST['entity'] ?? '';
$op     = $_POST['op'] ?? '';
if (!isset($tables[$entity])) { hub_flash('Unknown entity.', 'error'); redirect('ememo_refdata.php'); }
$tbl  = $tables[$entity];
$back = 'ememo_refdata.php?entity=' . urlencode($entity);

if ($op === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $st = $conn->prepare("DELETE FROM `$tbl` WHERE id = ?");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        hub_audit("Deleted $entity #$id");
        hub_flash('Deleted.', 'success');
    } catch (Throwable $e) {
        hub_flash('Could not delete — the record is still referenced.', 'error');
    }
    redirect($back);
}

// ---- save (insert / update) --------------------------------------------
$id = (int) ($_POST['id'] ?? 0);
$data = [];
try {
    if ($entity === 'grades') {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') throw new RuntimeException('Grade code is required.');
        $data = ['code' => $code];
    } elseif ($entity === 'positions') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new RuntimeException('Position name is required.');
        $data = [
            'name'          => $name,
            'short_name'    => trim($_POST['short_name'] ?? ''),
            'position_code' => ($_POST['position_code'] ?? '') !== '' ? (int) $_POST['position_code'] : 0,
        ];
    } elseif ($entity === 'sections') {
        $name = trim($_POST['name'] ?? '');
        $dept = (int) ($_POST['department_id'] ?? 0);
        if ($name === '' || !$dept) throw new RuntimeException('Section name and department are required.');
        $data = ['name' => $name, 'department_id' => $dept];
    } else { // departments
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new RuntimeException('Department name is required.');
        $data = ['name' => $name];
    }

    $bt = fn($x) => is_int($x) ? 'i' : 's';
    if ($id) {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $types = ''; $args = [];
        foreach ($data as $x) { $types .= $bt($x); $args[] = $x; }
        $types .= 'i'; $args[] = $id;
        $st = $conn->prepare("UPDATE `$tbl` SET $set WHERE id = ?");
        $st->bind_param($types, ...$args);
        $st->execute();
        $st->close();
        hub_audit("Updated $entity #$id");
        hub_flash('Saved.', 'success');
    } else {
        $cols = array_keys($data);
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $types = ''; $args = [];
        foreach ($data as $x) { $types .= $bt($x); $args[] = $x; }
        $st = $conn->prepare("INSERT INTO `$tbl` (`" . implode('`,`', $cols) . "`) VALUES ($ph)");
        $st->bind_param($types, ...$args);
        $st->execute();
        $newId = (int) $conn->insert_id;
        $st->close();
        hub_audit("Added $entity #$newId ({$data[array_key_first($data)]})");
        hub_flash('Added.', 'success');
    }
} catch (Throwable $e) {
    hub_flash($e instanceof RuntimeException ? $e->getMessage() : 'Could not save (duplicate or invalid value).', 'error');
}
redirect($back);
