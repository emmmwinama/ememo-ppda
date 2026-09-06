<?php
/** Stream a submission document (es_bid_attachment). Registry / review roles only;
 *  PDE users may only fetch files on their own entity's submissions. */
require __DIR__ . '/inc/bootstrap.php';

if (!es_has_role('registry', 'pde', 'allocator', 'officer', 'supervisor', 'director', 'dg', 'board')) {
    http_response_code(403); exit('Not authorised.');
}

global $conn;

$id = (int) ($_GET['id'] ?? 0);
$a  = db_one(
    "SELECT a.file_path, a.original_name, r.pde_id
       FROM es_bid_attachment a
       JOIN es_bid_registry r ON r.id = a.registry_id
      WHERE a.id = ?",
    'i', [$id]
);
if (!$a) { http_response_code(404); exit('File not found.'); }

$scope = es_pde_scope();
if ($scope !== null && (int) $a['pde_id'] !== (int) $scope) {
    http_response_code(403); exit('This document belongs to another entity.');
}

$base = realpath(ES_UPLOAD_DIR);
$path = realpath(ES_UPLOAD_DIR . '/' . $a['file_path']);
if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404); exit('File missing on disk.');
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
