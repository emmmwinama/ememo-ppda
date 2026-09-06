<?php require_once 'auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PPDA DIGITAL HUB – First Login Setup</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* … your existing styles … */
    #signature-pad { border: 1px solid #ccc; border-radius:5px; }
    .sig-control { display:flex; gap:1rem; margin-bottom:1rem; }
    .sig-control button { padding:0.5rem 1rem; }
    .file-upload { margin-bottom:1rem; }
  </style>
</head>
<body>

<div class="login-wrapper">
  <div class="login-logo-area">
    <img src="logo.jpg" alt="PPDA Logo">
    <h2>Setup Your Account</h2>
  </div>

  <form id="setupForm">
    <!-- Password fields + live requirements -->
    <input type="password" id="new_password" placeholder="New Password" required>
    <ul class="requirements" id="passwordRequirements">
      <!-- … same list as before … -->
    </ul>
    <input type="password" id="confirm_password" placeholder="Confirm Password" required>

    <!-- Signature: file upload OR draw -->
    <div class="file-upload">
      <label>Upload Signature (PNG/JPG):</label><br>
      <input type="file" id="sigFile" accept="image/png,image/jpeg">
    </div>

    <div>
      <label>Or Draw Signature:</label>
      <canvas id="signature-pad" width=400 height=150></canvas>
      <div class="sig-control">
        <button type="button" id="clearSig">Clear</button>
      </div>
    </div>

    <button type="submit" id="submitBtn" disabled>Complete Setup</button>

    <div id="feedback" class="error-box"></div>
    <div id="successMsg" class="success-msg"></div>
  </form>
</div>

<!-- Signature Pad library (https://github.com/szimek/signature_pad) -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
  // … password validation code from before … (validatePassword, updateUI) …

  // Signature pad setup
  const canvas = document.getElementById('signature-pad');
  const sigPad = new SignaturePad(canvas, { backgroundColor: '#fff' });
  document.getElementById('clearSig').onclick = () => sigPad.clear();

  // Enable submit only when:
  // 1) password & confirm valid AND
  // 2) either a file is chosen OR canvas is non-empty
  function updateSubmitState() {
    const pwdOk = !submitBtn.disabled; // reuse your existing logic
    const hasFile = !!sigFile.files.length;
    const hasDraw = !sigPad.isEmpty();
    submitBtn.disabled = !(pwdOk && (hasFile || hasDraw));
  }

  newPwd.addEventListener('input', updateSubmitState);
  confirmPwd.addEventListener('input', updateSubmitState);
  const sigFile = document.getElementById('sigFile');
  sigFile.addEventListener('change', updateSubmitState);
  canvas.addEventListener('mouseup', updateSubmitState);
  canvas.addEventListener('touchend', updateSubmitState);

  document.getElementById('setupForm').addEventListener('submit', async e => {
    e.preventDefault();
    feedback.style.display = 'none';
    successMsg.textContent = '';

    const form = new FormData();
    form.append('new_password', newPwd.value);
    form.append('confirm_password', confirmPwd.value);

    // Prefer uploaded file, else use drawn
    if (sigFile.files.length) {
      form.append('signature', sigFile.files[0]);
    } else {
      // convert dataURL to blob
      const dataURL = sigPad.toDataURL('image/png');
      const blob = await (await fetch(dataURL)).blob();
      form.append('signature', blob, 'drawn.png');
    }

    fetch('change_password.php', {
      method: 'POST',
      body: form
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        successMsg.textContent = '✅ Setup complete! Redirecting…';
        setTimeout(() => window.location.href = 'index.php', 1500);
      } else {
        feedback.style.display = 'block';
        feedback.textContent = data.message || 'Error.';
      }
    })
    .catch(() => {
      feedback.style.display = 'block';
      feedback.textContent = 'Network error.';
    });
  });
</script>
</body>
</html>
