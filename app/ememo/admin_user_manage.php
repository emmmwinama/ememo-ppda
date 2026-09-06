<?php require_once 'auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin – User & Group Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & SweetAlert2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid mt-4">
  <h4 class="mb-4">Admin Management</h4>

  <!-- Nav Tabs -->
  <ul class="nav nav-tabs" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-users" data-bs-toggle="tab" data-bs-target="#usersTab" type="button" role="tab">Users</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-groups" data-bs-toggle="tab" data-bs-target="#groupsTab" type="button" role="tab">Groups</button>
    </li>
  </ul>

  <!-- Tab Content -->
  <div class="tab-content mt-3">
    <div class="tab-pane fade show active" id="usersTab" role="tabpanel" aria-labelledby="tab-users">
      <?php include 'user_management.php'; ?>
    </div>
    <div class="tab-pane fade" id="groupsTab" role="tabpanel" aria-labelledby="tab-groups">
      <?php include 'group_management.php'; ?>
    </div>
  </div>
</div>

<!-- Modals -->
<?php include 'shared_modals.php'; ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="management_logic.js"></script>
</body>
</html>
