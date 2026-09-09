<?php
// login.php
session_start();
// Already logged in?
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PPDA SSO Portal – Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/theme.css" rel="stylesheet">
  <style>
    body, html { height:100%; font-family:'Segoe UI',sans-serif; }
    .card-custom { border-top:5px solid var(--brand); }
  </style>
</head>
<body>
  <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-lg card-custom" style="max-width:400px;width:100%">
      <div class="card-body py-5">
        <div class="text-center mb-4">
          <img src="logo.jpg" alt="PPDA Logo" class="img-fluid" style="max-width:120px;">
          <h3 class="fw-bold text-success mt-3">PPDA Single Sign-On</h3>
        </div>

        <div id="errorBox" class="alert alert-danger d-none"></div>

        <form id="loginForm" autocomplete="off">
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input id="username" name="username" type="text" class="form-control" required autofocus>
          </div>
          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input id="password" name="password" type="password" class="form-control" required>
          </div>
          <button id="loginBtn" type="submit" class="btn btn-ppda btn-lg w-100">
            <i class="bi bi-lock-fill me-2"></i>Login
          </button>
        </form>

        <div id="loginSpinner" class="text-center mt-3 d-none">
          <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>

        <footer class="text-center text-muted small mt-4">&copy; 2025 Public Procurement &amp; Disposal of Assets Authority</footer>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const form     = document.getElementById('loginForm');
  const btn      = document.getElementById('loginBtn');
  const spinner  = document.getElementById('loginSpinner');
  const errorBox = document.getElementById('errorBox');

  form.addEventListener('submit', async e => {
    e.preventDefault();

    errorBox.classList.add('d-none');
    btn.disabled = true;
    spinner.classList.remove('d-none');

    // Prepare form data
    const formData = new URLSearchParams();
    formData.append('username', form.username.value.trim());
    formData.append('password', form.password.value.trim());

    try {
      const res = await fetch('login_handler.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
      });

      const data = await res.json();

      if (data.success) {
        window.location.href = data.redirect || 'index.php';
      } else {
        throw new Error(data.message || 'Login failed');
      }

    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.remove('d-none');
    } finally {
      spinner.classList.add('d-none');
      btn.disabled = false;
    }
  });
</script>



</body>
</html>
