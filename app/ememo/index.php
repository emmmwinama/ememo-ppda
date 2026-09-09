<?php
require_once 'auth.php';
include 'header.php';

$module = $_GET['module'] ?? '';

// ✅ Register all module files here
$moduleMap = [
  'create_memo'      => 'memo_create.php',
  'my_memos'         => 'memo_my.php',
  'endorsements'     => 'memo_endorsements.php',
  'inbox'            => 'inbox.php',
  'profile'          => 'user_profile.php',
  'user_admin'       => 'admin_user_manage.php',
  'dg_memos'         => 'dg_memos.php',
  'archive'          => 'memo_archive.php',

  // ✅ NEW MODULE: External Letters
   'upload_letter'    => 'external_upload.php',
  'letter_reception' => 'external_inbox.php',
  'my_letters'       => 'external_assigned.php',
  'closed_letters'   => 'external_closed.php',
];

// header.php has already opened <main class="es-content"> for the shared shell.
if (!$module) {
  include 'dashboard.php'; // Default page
} elseif (isset($moduleMap[$module])) {
  // Modules that render their own page header — skip the generic one.
  $ownsHeader = ['my_memos', 'inbox', 'endorsements', 'archive', 'profile', 'user_admin',
                 'closed_letters', 'dg_memos', 'upload_letter', 'letter_reception', 'my_letters'];
  if (!in_array($module, $ownsHeader, true)) {
    echo '<div class="f-head"><h1 class="f-title text-capitalize">' . str_replace('_', ' ', $module) . '</h1></div>';
  }
  include $moduleMap[$module];
} else {
  echo '<div class="f-state is-error"><i class="bi bi-question-circle"></i>'
     . '<p>The page you are looking for doesn\'t exist or has been moved.</p>'
     . '<p class="mt-2"><a href="index.php" class="btn btn-sm btn-outline-secondary">Back to dashboard</a></p></div>';
}

include 'footer.php';
?>
