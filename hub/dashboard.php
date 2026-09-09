
<!-- ========================= index.php (Dashboard) ========================= -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PPDA SSO Portal - Dashboard</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/theme.css" rel="stylesheet">
  <style>
    body, html { font-family: 'Segoe UI', sans-serif; }
    .app-card { transition: transform .15s; }
    .app-card:hover { transform: translateY(-3px); }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="#"><img src="logo.jpg" alt="PPDA" style="height:32px;"></a>
      <span class="navbar-text ms-2 fw-semibold text-success">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
      <div class="ms-auto">
        <button id="logoutBtn" class="btn btn-outline-secondary btn-sm">Logout</button>
      </div>
    </div>
  </nav>
  <div class="container py-5">
    <h2 class="mb-4 text-secondary">Select an Application</h2>
    <div class="row g-4">
      <div class="col-sm-6 col-lg-4">
        <div class="card app-card h-100">
          <div class="card-body text-center">
            <i class="bi bi-file-earmark-text-fill fs-1 text-success mb-3"></i>
            <h5 class="card-title">e-Memo</h5>
            <p class="card-text text-muted">Internal communication & approvals</p>
            <button class="btn btn-ppda" onclick="launchApp('ememo')">Open</button>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card app-card h-100">
          <div class="card-body text-center">
            <i class="bi bi-basket-fill fs-1 text-success mb-3"></i>
            <h5 class="card-title">eService</h5>
            <p class="card-text text-muted">Manage suppliers & Reviews</p>
            <button class="btn btn-ppda" onclick="launchApp('procurement')">Open</button>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card app-card h-100">
          <div class="card-body text-center">
            <i class="bi bi-graph-up-arrow fs-1 text-success mb-3"></i>
            <h5 class="card-title">Reports</h5>
            <p class="card-text text-muted">Analytics & dashboards</p>
            <button class="btn btn-ppda" onclick="launchApp('reports')">Open</button>
          </div>
        </div>
      </div>
      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
      <div class="col-sm-6 col-lg-4">
        <div class="card app-card h-100">
          <div class="card-body text-center">
            <i class="bi bi-sliders fs-1 text-success mb-3"></i>
            <h5 class="card-title">Administration</h5>
            <p class="card-text text-muted">Users, roles, reference data & security</p>
            <a class="btn btn-ppda" href="admin/index.php">Open</a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function hubBase() {
      var b = window.location.pathname;
      b = b.replace(/\/(hub\/)?[^\/?#]*\.[^\/?#]*$/, '');
      b = b.replace(/\/hub\/?$/, '');
      return b.replace(/\/$/, '');
    }
    function launchApp(app) {
      window.location.href = hubBase() + '/app/' + encodeURIComponent(app) + '/index.php';
    }
    var _btn = document.getElementById('logoutBtn');
    if (_btn) _btn.addEventListener('click', () => {
      fetch(hubBase() + '/hub/logout.php', { method: 'POST', credentials: 'include' })
        .then(() => window.location.href = hubBase() + '/hub/login.php');
    });
  </script>
</body>
</html>