<?php
// index.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PPDA Digital Hub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../assets/css/theme.css') ?: time() ?>" rel="stylesheet">
  <link href="../assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../assets/css/app.css') ?: time() ?>" rel="stylesheet">
  <style>
    html { height: 100%; }
    .navbar {
      background: var(--surface);
    }
    .navbar-brand img {
      height: 40px;
    }
    .welcome-card {
      background: var(--surface);
      border-left: 5px solid var(--brand);
      border-radius: var(--radius-md);
      border: 1px solid var(--border);
      padding: 2rem;
      margin-bottom: 2rem;
    }
    .app-card {
      transition: transform .15s;
    }
    .app-card:hover {
      transform: translateY(-3px);
    }
    .app-icon {
      font-size: 3rem;
      color: var(--brand);
      margin-bottom: 1rem;
    }
    .search-input {
      max-width: 400px;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        <img src="logo.jpg" alt="PPDA Digital Hub Logo">
      </a>
      <div class="ms-auto d-flex align-items-center">
        <span class="me-3 text-secondary">Hello, <strong><?= $user ?></strong></span>
        <button id="logoutBtn" class="btn btn-outline-secondary">Logout</button>
      </div>
    </div>
  </nav>

  <div class="container py-5">
    <!-- Welcome Card -->
    <div class="welcome-card text-center">
      <h2 class="fw-bold mb-2">Welcome to PPDA Digital Hub</h2>
      <p class="text-muted mb-4">Access all PPDA applications in one place.</p>
      <input id="searchApp" type="text" class="form-control mx-auto search-input" placeholder="Search applications…">
    </div>

    <!-- Application Grid -->
    <div id="appsGrid" class="row g-4">
      <!-- e-Memo -->
      <div class="col-sm-6 col-lg-4 app-item">
        <div class="card app-card h-100 text-center p-4">
          <i class="bi bi-file-earmark-text-fill app-icon"></i>
          <h5 class="card-title">e-Memo</h5>
          <p class="card-text text-muted">Internal communication &amp; approvals</p>
          <button class="btn btn-ppda mt-auto" onclick="launchApp('ememo')">Open</button>
        </div>
      </div>
      <!-- e-Service -->
      <div class="col-sm-6 col-lg-4 app-item">
        <div class="card app-card h-100 text-center p-4">
          <i class="bi bi-gear-fill app-icon"></i>
          <h5 class="card-title">e-Service</h5>
          <p class="card-text text-muted">Supplier management &amp; reviews</p>
          <button class="btn btn-ppda mt-auto" onclick="launchApp('eservice')">Open</button>
        </div>
      </div>
      <!-- Reports -->
      <div class="col-sm-6 col-lg-4 app-item">
        <div class="card app-card h-100 text-center p-4">
          <i class="bi bi-graph-up-arrow app-icon"></i>
          <h5 class="card-title">Reports</h5>
          <p class="card-text text-muted">Analytics &amp; dashboards</p>
          <button class="btn btn-ppda mt-auto" onclick="launchApp('reports')">Open</button>
        </div>
      </div>
      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
      <!-- Administration -->
      <div class="col-sm-6 col-lg-4 app-item">
        <div class="card app-card h-100 text-center p-4">
          <i class="bi bi-sliders app-icon"></i>
          <h5 class="card-title">Administration</h5>
          <p class="card-text text-muted">Users, roles, reference data &amp; security</p>
          <a class="btn btn-ppda mt-auto" href="admin/index.php">Open</a>
        </div>
      </div>
      <?php endif; ?>
      <!-- Add more apps here -->
    </div>
  </div>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Work out the deployment base from this page's own URL. The hub may be
    // reached as  <base>/ , <base>/index.php , or <base>/hub/index.php  (a root
    // .htaccess maps <base>/ -> hub/index.php), so strip any trailing file,
    // "/hub", and slash. Apps live at <base>/app/<app>/index.php.
    function hubBase() {
      var b = window.location.pathname;
      b = b.replace(/\/(hub\/)?[^\/?#]*\.[^\/?#]*$/, '');  // drop "/[hub/]file.ext"
      b = b.replace(/\/hub\/?$/, '');                       // drop trailing "/hub" or "/hub/"
      return b.replace(/\/$/, '');                          // drop trailing "/"
    }
    function launchApp(app) {
      window.location.href = hubBase() + '/app/' + encodeURIComponent(app) + '/index.php';
    }

    // Handle logout
    document.getElementById('logoutBtn').addEventListener('click', () => {
      fetch('logout.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) window.location.href = 'login.php';
      });
    });

    // Filter apps by search
    document.getElementById('searchApp').addEventListener('input', e => {
      const term = e.target.value.toLowerCase();
      document.querySelectorAll('.app-item').forEach(card => {
        const title = card.querySelector('.card-title').textContent.toLowerCase();
        card.style.display = title.includes(term) ? '' : 'none';
      });
    });
  </script>
</body>
</html>
