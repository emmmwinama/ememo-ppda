<?php
require_once 'auth.php'; // 👈 protect the page
?>
<?php
require_once 'auth.php';
$error = $_GET['error'] ?? '';
?>
<?php if ($error): ?>
  <div class="alert alert-warning">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Signature - eMemo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
<div class="login-wrapper">
    <div class="login-logo-area">
        <img src="logo.png" alt="e-Memo Logo" class="logo-large">
        <h1>e-Memo System</h1>
    </div>

    <div class="login-container">
        <form id="signatureForm" class="login-form" enctype="multipart/form-data">
            <h2>Upload Signature</h2>
            <div id="errorBox" class="error-box" style="display: none;"></div>

            <input type="file" name="signature" accept="image/*" required>
            <button type="submit">Upload and Proceed</button>
        </form>
    </div>
</div>

<script>
document.getElementById('signatureForm').onsubmit = function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('upload_signature.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(response => {
        if (response.status === 'success') {
            window.location.href = 'index.php';
        } else {
            document.getElementById('errorBox').style.display = 'block';
            document.getElementById('errorBox').innerText = response.message;
        }
    });
};
</script>
</body>
</html>
