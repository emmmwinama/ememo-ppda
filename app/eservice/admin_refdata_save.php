<?php
/**
 * Moved — administration is now the unified console at hub/admin/.
 * This stub keeps old links and bookmarks working.
 */
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ../../hub/admin/es_refdata.php' . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
