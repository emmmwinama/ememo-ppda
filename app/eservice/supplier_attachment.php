<?php
/** Stream a supplier document (es_supplier_attachment). Internal roles only. */
require __DIR__ . '/inc/bootstrap.php';

if (!es_has_role('registry', 'officer', 'supervisor', 'director', 'dg', 'board') && !es_can('supplier.view')) {
    http_response_code(403);
    exit('Not authorised.');
}

global $conn;

$id = (int) ($_GET['id'] ?? 0);
$a  = db_one("SELECT file_path, original_name FROM es_supplier_attachment WHERE id = ?", 'i', [$id]);
if (!$a) { http_response_code(404); exit('File not found.'); }

$base = realpath(ES_UPLOAD_DIR);
$path = realpath(ES_UPLOAD_DIR . '/' . $a['file_path']);
if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('File missing on disk (legacy documents are not stored on this server).');
}

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = [
    'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip' => 'application/zip',
][$ext] ?? 'application/octet-stream';

$name = preg_replace('/[\r\n"]+/', '', $a['original_name'] ?: basename($path));
$disp = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $disp . '; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
