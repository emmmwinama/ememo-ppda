<?php
// Legacy landing page — the Digital Hub landing is now hub/index.php only.
// Kept as a redirect so old links / bookmarks still work.
session_start();
header('Location: ' . (empty($_SESSION['user_id']) ? 'login.php' : 'index.php'), true, 302);
exit;
