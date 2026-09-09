<?php
/** Persist a submission (add / edit) plus its document attachments. */
require __DIR__ . '/inc/bootstrap.php';
es_require_role('registry', 'pde');

global $conn, $ES_UID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !es_csrf_check()) {
    flash('Invalid request — please try again.', 'error');
    redirect('bid_registry.php');
}

/** How many of the incoming docs[] look like real, acceptable uploads. */
function es_incoming_file_count(): int {
    if (empty($_FILES['docs']) || !is_array($_FILES['docs']['name'] ?? null)) return 0;
    $n = 0;
    foreach ($_FILES['docs']['error'] as $err) {
        if ($err === UPLOAD_ERR_OK) $n++;
    }
    return $n;
}

// ---- PDE withdraws a submission that is still awaiting the registry check ----
if (($_POST['op'] ?? '') === 'withdraw') {
    $wid  = (int) ($_POST['id'] ?? 0);
    $wpde = es_pde_scope();
    $wrow = $wid ? db_one("SELECT pde_id, status FROM es_bid_registry WHERE id = ?", 'i', [$wid]) : null;
    if (!$wrow || $wpde === null || $wpde <= 0
        || (int) $wrow['pde_id'] !== (int) $wpde || $wrow['status'] !== 'pending_registry') {
        flash('This submission can no longer be withdrawn.', 'error');
        redirect($wid ? "bid_registry_view.php?id=$wid" : 'bid_registry.php');
    }
    $st = $conn->prepare("UPDATE es_bid_registry SET status = 'withdrawn' WHERE id = ?");
    $st->bind_param('i', $wid);
    $st->execute();
    $st->close();
    flash('Submission withdrawn.', 'success');
    redirect("bid_registry_view.php?id=$wid");
}

const ES_ATT_MAX   = 20 * 1024 * 1024;
const ES_ATT_EXT   = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip'];

/** Move any uploaded docs[] into uploads/registry/<id>/ and record them. Returns [stored, skipped]. */
function es_store_uploads(int $regId): array {
    global $conn, $ES_UID;
    if (empty($_FILES['docs']) || !is_array($_FILES['docs']['name'])) return [0, 0];

    $dir = ES_UPLOAD_DIR . '/registry/' . $regId;
    $stored = 0; $skipped = 0;
    foreach ($_FILES['docs']['name'] as $i => $name) {
        if (($_FILES['docs']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $tmp  = $_FILES['docs']['tmp_name'][$i] ?? '';
        $size = (int) ($_FILES['docs']['size'][$i] ?? 0);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($_FILES['docs']['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)
            || $size <= 0 || $size > ES_ATT_MAX || !in_array($ext, ES_ATT_EXT, true)) {
            $skipped++; continue;
        }
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) { $skipped++; continue; }

        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME));
        $clean = trim($clean, '_.') ?: 'file';
        $fname = substr($clean, 0, 80) . '.' . $ext;
        $rel   = 'registry/' . $regId . '/' . bin2hex(random_bytes(6)) . '_' . $fname;
        if (!move_uploaded_file($tmp, ES_UPLOAD_DIR . '/' . $rel)) { $skipped++; continue; }

        $orig = mb_substr($name, 0, 240);
        $st = $conn->prepare(
            "INSERT INTO es_bid_attachment (registry_id, kind, file_path, original_name, uploaded_by)
             VALUES (?, 'submission', ?, ?, ?)"
        );
        $st->bind_param('issi', $regId, $rel, $orig, $ES_UID);
        $st->execute();
        $st->close();
        $stored++;
    }
    return [$stored, $skipped];
}

/** Remove attachments the user ticked (only ones on this submission). */
function es_remove_atts(int $regId): int {
    global $conn;
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['remove_att'] ?? []))));
    if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all("SELECT id, file_path FROM es_bid_attachment WHERE registry_id = ? AND id IN ($in)",
                   str_repeat('i', 1 + count($ids)), array_merge([$regId], $ids));
    foreach ($rows as $a) {
        $p = realpath(ES_UPLOAD_DIR . '/' . $a['file_path']);
        if ($p && str_starts_with($p, realpath(ES_UPLOAD_DIR)) && is_file($p)) @unlink($p);
    }
    if ($rows) {
        $delIds = implode(',', array_map(fn($a) => (int) $a['id'], $rows));
        $conn->query("DELETE FROM es_bid_attachment WHERE registry_id = " . $regId . " AND id IN ($delIds)");
    }
    return count($rows);
}

$pdeScope = es_pde_scope();
$isPde    = $pdeScope !== null && $pdeScope > 0;
if ($pdeScope === -1) { flash('Your PDE account is not linked to an entity.', 'error'); redirect('bid_registry.php'); }

$id      = (int) ($_POST['id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
if ($subject === '') { flash('Subject is required.', 'error'); redirect($id ? "bid_registry_form.php?id=$id" : 'bid_registry_form.php'); }

$nInt = fn($k) => ($_POST[$k] ?? '') !== '' ? (int) $_POST[$k] : null;
$nStr = fn($k) => trim($_POST[$k] ?? '') !== '' ? trim($_POST[$k]) : null;

$pde_id    = $isPde ? (int) $pdeScope : $nInt('pde_id');
$channel   = in_array($_POST['channel'] ?? '', ['physical', 'email', 'portal'], true) ? $_POST['channel'] : ($isPde ? 'portal' : 'physical');
$importance = in_array($_POST['importance'] ?? '', ['normal', 'high', 'urgent'], true) ? $_POST['importance'] : 'normal';
$tender    = $nStr('tender_number');
$refcode   = $nStr('ref_code_pde');
$method_id = $nInt('procurement_method_id');
$rtype_id  = $nInt('review_type_id');
$signed    = !empty($_POST['submission_signed']) ? 1 : 0;
$sign_date = $nStr('date_of_signing');
$in_plan   = !empty($_POST['in_procurement_plan']) ? 1 : 0;
$details   = $nStr('submission_details');

// accompanied-documents checklist -> canonical, ordered, comma-joined SET value
$accDocs   = implode(',', array_values(array_intersect(
    array_keys(es_accompanying_docs()),
    array_map('strval', (array) ($_POST['accompanied_documents'] ?? []))
))) ?: null;

if (!$isPde && !$pde_id) { flash('Choose the PDE.', 'error'); redirect('bid_registry_form.php'); }

// A PDE's first submission must carry at least one document.
if ($isPde && !$id && es_incoming_file_count() === 0) {
    flash('Attach at least one document — the submission pack is required.', 'error');
    redirect('bid_registry_form.php');
}

// ---------------------------------------------------------------- UPDATE
if ($id) {
    $row = db_one("SELECT pde_id, status, origin FROM es_bid_registry WHERE id = ?", 'i', [$id]);
    if (!$row) { flash('Submission not found.', 'error'); redirect('bid_registry.php'); }
    if ($isPde && ((int) $row['pde_id'] !== (int) $pdeScope || !in_array($row['status'], ['pending_registry', 'returned_to_pde'], true))) {
        flash('This submission can no longer be edited.', 'error'); redirect("bid_registry_view.php?id=$id");
    }
    if (!$isPde && !in_array($row['status'], ['pending_registry', 'pending_allocation', 'returned_to_pde'], true)) {
        flash('This submission can no longer be edited from here.', 'error'); redirect("bid_registry_view.php?id=$id");
    }
    // a PDE re-submitting a returned pack pushes it back to the registry
    $status = ($isPde && $row['status'] === 'returned_to_pde') ? 'pending_registry' : $row['status'];
    // registry keeps the PDE; PDE can't change it
    $pdeForUpdate = $isPde ? (int) $row['pde_id'] : ($pde_id ?: (int) $row['pde_id']);

    $st = $conn->prepare(
        "UPDATE es_bid_registry SET
           pde_id=?, tender_number=?, subject=?, submission_details=?, procurement_method_id=?,
           review_type_id=?, channel=?, importance=?, submission_signed=?, date_of_signing=?,
           ref_code_pde=?, in_procurement_plan=?, accompanied_documents=?, status=?
         WHERE id=?"
    );
    $vals = [$pdeForUpdate, $tender, $subject, $details, $method_id, $rtype_id, $channel,
             $importance, $signed, $sign_date, $refcode, $in_plan, $accDocs, $status, $id];
    $types = '';
    foreach ($vals as $x) $types .= is_int($x) ? 'i' : 's';
    $st->bind_param($types, ...$vals);
    $ok = $st->execute();
    $err = $conn->error;
    $st->close();

    if ($ok) {
        $removed = es_remove_atts($id);
        [$added, $skipped] = es_store_uploads($id);
        $msg = 'Submission updated.';
        if ($added)   $msg .= " $added file" . ($added === 1 ? '' : 's') . ' attached.';
        if ($removed) $msg .= " $removed removed.";
        if ($skipped) $msg .= " $skipped file" . ($skipped === 1 ? '' : 's') . ' rejected (type or size).';
        flash($msg, 'success');
    } else {
        flash("Update failed: $err", 'error');
    }
    redirect("bid_registry_view.php?id=$id");
}

// ---------------------------------------------------------------- INSERT
$year   = date('Y');
$seq    = (int) ($conn->query("SELECT COUNT(*) FROM es_bid_registry WHERE YEAR(ts_create) = $year")->fetch_row()[0] ?? 0) + 1;
$serial = sprintf('PPDA/BA/%s/%05d', $year, $seq);

$origin = $isPde ? 'pde' : 'registry';
$status = $isPde ? 'pending_registry' : 'pending_allocation';   // registry entries skip the check

$st = $conn->prepare(
    "INSERT INTO es_bid_registry
       (serial_no, pde_id, tender_number, subject, submission_details, procurement_method_id,
        review_type_id, channel, importance, submission_signed, date_of_signing, ref_code_pde,
        in_procurement_plan, accompanied_documents, origin, received_by, status)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$vals = [$serial, (int) $pde_id, $tender, $subject, $details, $method_id, $rtype_id, $channel,
         $importance, $signed, $sign_date, $refcode, $in_plan, $accDocs, $origin, $ES_UID, $status];
$types = '';
foreach ($vals as $x) $types .= is_int($x) ? 'i' : 's';
$st->bind_param($types, ...$vals);
$ok = $st->execute();
$err = $conn->error;
$newId = $conn->insert_id;
$st->close();

if ($ok) {
    [$added, $skipped] = es_store_uploads((int) $newId);
    $tail = $added ? " $added file" . ($added === 1 ? '' : 's') . ' attached.' : '';
    if ($skipped) $tail .= " $skipped file" . ($skipped === 1 ? '' : 's') . ' rejected (type or size).';
    flash(($isPde ? "Submitted as $serial — awaiting the PPDA registry check." : "Logged as $serial — awaiting allocation.") . $tail, 'success');
    redirect("bid_registry_view.php?id=$newId");
}
flash("Could not save: $err", 'error');
redirect('bid_registry_form.php');
