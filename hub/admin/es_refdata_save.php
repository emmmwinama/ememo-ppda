<?php
/** Administration — persist e-Services reference data (save / toggle). */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../app/eservice/inc/refdata.php';

global $conn;

$defs   = es_refdata_defs();
$entity = $_POST['entity'] ?? '';
$op     = $_POST['op'] ?? '';
if (!isset($defs[$entity])) { hub_flash('Unknown entity.', 'error'); redirect('es_refdata.php'); }
$def    = $defs[$entity];
$keyCol = $def['key'];
$keyIsStr = $keyCol === 'code';
$back   = 'es_refdata.php?entity=' . urlencode($entity);

$keyRaw = $_POST['key'] ?? '';
$keyVal = $keyIsStr ? (string) $keyRaw : (int) $keyRaw;
$bt = fn($x) => is_int($x) ? 'i' : (is_float($x) ? 'd' : 's');

if ($op === 'toggle') {
    if ($keyRaw === '') { hub_flash('Missing key.', 'error'); redirect($back); }
    $st = $conn->prepare("UPDATE `{$def['table']}` SET active = 1 - active WHERE `$keyCol` = ?");
    $st->bind_param($keyIsStr ? 's' : 'i', $keyVal);
    $st->execute();
    $st->close();
    hub_audit("Toggled e-Services $entity '$keyRaw'");
    hub_flash('Updated.', 'success');
    redirect($back);
}

// save (insert / update)
$data = [];
foreach ($def['fields'] as $name => $fld) {
    $val = trim((string) ($_POST[$name] ?? ''));
    if (!empty($fld['upper']))     $val = strtoupper($val);
    if (!empty($fld['maxlength'])) $val = mb_substr($val, 0, (int) $fld['maxlength']);
    if (!empty($fld['required']) && $val === '') {
        hub_flash($fld['label'] . ' is required.', 'error');
        redirect($back);
    }
    $data[$name] = ($fld['type'] ?? '') === 'number'
        ? ($val === '' ? 0 : (float) $val)
        : ($val === '' ? null : $val);
}
$data['active'] = !empty($_POST['active']) ? 1 : 0;

$isEdit = $keyRaw !== '' && db_one("SELECT 1 x FROM `{$def['table']}` WHERE `$keyCol` = ?", $keyIsStr ? 's' : 'i', [$keyVal]);

if ($isEdit) {
    unset($data[$keyCol]);
    if (!$data) { hub_flash('Nothing to update.', 'error'); redirect($back); }
    $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
    $types = ''; $args = [];
    foreach ($data as $x) { $types .= $bt($x); $args[] = $x; }
    $types .= $keyIsStr ? 's' : 'i'; $args[] = $keyVal;
    $st = $conn->prepare("UPDATE `{$def['table']}` SET $set WHERE `$keyCol` = ?");
    $st->bind_param($types, ...$args);
    $ok = $st->execute(); $err = $conn->error; $st->close();
    if ($ok) hub_audit("Updated e-Services $entity '$keyRaw'");
    hub_flash($ok ? 'Saved.' : "Save failed: $err", $ok ? 'success' : 'error');
    redirect($back);
}

$cols = array_keys($data);
$ph = implode(',', array_fill(0, count($cols), '?'));
$types = ''; $args = [];
foreach ($data as $x) { $types .= $bt($x); $args[] = $x; }
$st = $conn->prepare("INSERT INTO `{$def['table']}` (`" . implode('`,`', $cols) . "`) VALUES ($ph)");
$st->bind_param($types, ...$args);
$ok = $st->execute(); $err = $conn->error; $st->close();
if ($ok) hub_audit("Added e-Services $entity");
hub_flash($ok ? 'Added.' : "Could not add: $err", $ok ? 'success' : 'error');
redirect($back);
