<?php
// admin_user_manage.php — rendered inside index.php (header.php loads Bootstrap, Icons, theme.css)
require_once 'auth.php';
?>
<!-- SweetAlert2 (not provided by header.php) -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
  .admin-tabs { display: flex; gap: .5rem; margin-bottom: 1.2rem; border: none; }
  .admin-tabs .nav-link {
    border: 1px solid var(--border); background: var(--surface);
    border-radius: var(--radius-pill);
    padding: .35rem .95rem; font-size: .82rem; font-weight: 600; color: var(--muted);
  }
  .admin-tabs .nav-link:hover { color: var(--text); border-color: var(--border-strong, #cbd5e1); }
  .admin-tabs .nav-link.active { background: var(--brand); border-color: var(--brand); color: #fff; }
</style>

<div class="f-head">
  <h1 class="f-title">User Management</h1>
  <p class="f-subtitle">Manage users and permission groups</p>
</div>

<ul class="nav admin-tabs" id="adminTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-users" data-bs-toggle="tab" data-bs-target="#usersTab" type="button" role="tab">
      <i class="bi bi-people me-1"></i>Users
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-groups" data-bs-toggle="tab" data-bs-target="#groupsTab" type="button" role="tab">
      <i class="bi bi-diagram-3 me-1"></i>Groups
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="usersTab" role="tabpanel" aria-labelledby="tab-users">
    <?php include 'user_management.php'; ?>
  </div>
  <div class="tab-pane fade" id="groupsTab" role="tabpanel" aria-labelledby="tab-groups">
    <?php include 'group_management.php'; ?>
  </div>
</div>

<?php include 'shared_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="management_logic.js"></script>
