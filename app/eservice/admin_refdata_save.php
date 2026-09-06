<?php
/** Reference-data persist: save (insert/update) or toggle active. */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/refdata.php';
es_require_perm('refdata.manage');

global $conn;

$defs   = es_refdata_defs();
$entity = $_POST['entity'] ?? '';
$op     = $_POST['op'] ?? '';
if (!isset($defs[$entity])) { flash('Unknown entity.', 'error'); redirect('admin_refdata.php'); }
$def  = $defs[$entity];
$back = 'admin_refdata.php?entity=' . urlencode($entity);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request.', 'error');
    redirect($back);
}

$keyCol = $def['key'];
$keyIsStr = $keyCol === 'code';
$keyRaw = $_POST['key'] ?? '';
$keyVal = $keyIsStr ? (string) $keyRaw : (int) $keyRaw;

// ---- toggle active --------------------------------------------------
if ($op === 'toggle') {
    if ($keyRaw === '') { flash('Missing key.', 'error'); redirect($back); }
    $st = $conn->prepare("UPDATE `{$def['table']}` SET active = 1 - active WHERE `$keyCol` = ?");
    $st->bind_param($keyIsStr ? 's' : 'i', $keyVal);
    $st->execute();
    $st->close();
    flash('Updated.', 'success');
    redirect($back);
}

// ---- save (insert / update) --------------------------------------
$data = [];
foreach ($def['fields'] as $name => $fld) {
    $val = trim((string) ($_POST[$name] ?? ''));
    if (!empty($fld['upper'])) $val = strtoupper($val);
    if (!empty($fld['maxlength'])) $val = mb_substr($val, 0, (int) $fld['maxlength']);
    if (!empty($fld['required']) && $val === '') {
        flash($fld['label'] . ' is required.', 'error');
        redirect($back);
    }
    if (($fld['type'] ?? '') === 'number') {
        $data[$name] = $val === '' ? 0 : (float) $val;
    } else {
        $data[$name] = $val === '' ? null : $val;
    }
}
$data['active'] = !empty($_POST['active']) ? 1 : 0;

$isEdit = $keyRaw !== '' && db_one("SELECT 1 x FROM `{$def['table']}` WHERE `$keyCol` = ?", $keyIsStr ? 's' : 'i', [$keyVal]);

$bt = fn($x) => is_int($x) ? 'i' : (is_float($x) ? 'd' : 's');

if ($isEdit) {
    // don't rewrite a locked key column
    unset($data[$keyCol]);
    if (!$data) { flash('Nothing to update.', 'error'); redirect($back); }
    $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
    $types = ''; $args = [];
    foreach ($data as $x) { $types .= $bt($x); $args[] = $x; }
    $types .= $keyIsStr ? 's' : 'i'; $args[] = $keyVal;
    $st = $conn->prepare("UPDATE `{$def['table']}` SET $set WHERE `$keyCol` = ?");
    $st->bind_param($types, ...$args);
    $ok = $st->execute();
    $err = $conn->error;
    $st->close();
    flash($ok ? 'Saved.' : "Save failed: $err", $ok ? 'success' : 'error');
    redirect($back);
}

// insert
$cols = array_keys($data);
$ph = implode(',', array_fill(0, count($cols), '?'));
$types = ''; $args = [];
foreach ($data as $x) { $types .= $bt($x); $args[] = $x; }
$st = $conn->prepare("INSERT INTO `{$def['table']}` (`" . implode('`,`', $cols) . "`) VALUES ($ph)");
$st->bind_param($types, ...$args);
$ok = $st->execute();
$err = $conn->error;
$st->close();
flash($ok ? 'Added.' : "Could not add: $err", $ok ? 'success' : 'error');
redirect($back);
