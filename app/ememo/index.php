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

echo '<div class="container-fluid p-0">';
echo '<div class="row g-0 flex-nowrap">';

include 'sidebar.php'; // Left sidebar

echo '<div class="col main-content pt-4 px-5" style="min-width:0;">';

if (!$module) {
  include 'dashboard.php'; // Default page
} elseif (isset($moduleMap[$module])) {
  // Modules that render their own page header — skip the generic one.
  $ownsHeader = ['my_memos', 'inbox', 'endorsements', 'archive', 'profile', 'user_admin',
                 'closed_letters', 'dg_memos', 'upload_letter', 'letter_reception', 'my_letters'];
  if (!in_array($module, $ownsHeader, true)) {
    echo '<h4 class="text-success mb-4 text-capitalize">' . str_replace('_', ' ', $module) . '</h4>';
  }
  include $moduleMap[$module];
} else {
  // ✅ 404 Error Display
  echo '<div class="text-center mt-5">';
  echo '<h1 class="display-4 text-danger">404 - Module Not Found</h1>';
  echo '<p class="lead">The page you are looking for doesn\'t exist or has been moved.</p>';
  echo '<a href="index.php" class="btn btn-outline-success mt-3">Back to Dashboard</a>';
  echo '</div>';
}

echo '</div>'; // content
echo '</div>'; // row
echo '</div>'; // container

include 'footer.php';
?>
