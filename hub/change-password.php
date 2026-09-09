<?php
// Turn on full error reporting so we see exactly what’s failing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Make sure the user really is logged in
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PPDA DIGITAL HUB – Account Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/theme.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0; padding: 0;
    }
    .login-wrapper {
      max-width: 500px;
      margin: 4rem auto;
      background: var(--surface);
      padding: 2rem;
      border-radius: var(--radius-md);
      border: 1px solid var(--border);
    }
    .login-logo-area {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .login-logo-area img { max-height: 60px; }
    .login-logo-area h2 { margin-top: 1rem; color: var(--text); }
    input[type="password"], input[type="file"] {
      width:100%; padding:.6rem; margin-bottom:1rem;
      border:1px solid var(--border); border-radius: var(--radius-sm); font-size:1rem;
    }
    .requirements { font-size:.9rem; margin-bottom:1rem; list-style:none; padding-left:0; }
    .requirements li { margin:.25rem 0; }
    .requirements li.valid   { color: var(--brand); }
    .requirements li.invalid { color: var(--danger); }
    #signature-pad {
      border:1px solid var(--border); border-radius: var(--radius-sm);
      width:100%; height:150px; touch-action:none;
      margin-bottom:1rem;
    }
    .sig-control { display:flex; gap:1rem; margin-bottom:1rem; }
    .sig-control button {
      padding:.5rem 1rem; border:none; background: var(--danger);
      color:#fff; border-radius: var(--radius-sm); cursor:pointer;
    }
    #submitBtn {
      width:100%; padding:.7rem; background: var(--brand);
      color:#fff; border:none; font-size:1rem;
      border-radius: var(--radius-sm); cursor:pointer;
    }
    #submitBtn:hover:not(:disabled) { background: var(--brand-dark); }
    #submitBtn:disabled { opacity:.6; cursor:not-allowed; }
    .error-box { color: var(--danger); margin-top:1rem; display:none; }
    .success-msg { color: var(--brand); text-align:center; margin-top:1rem; font-weight:bold; }
  </style>
</head>
<body>

<div class="login-wrapper">
  <div class="login-logo-area">
    <img src="logo.jpg" alt="PPDA Logo">
    <h2>Complete Your Setup</h2>
  </div>

  <form id="setupForm">
    <input type="password" id="new_password" placeholder="New Password" autocomplete="new-password" required>
    <ul class="requirements" id="passwordRequirements">
      <li id="req-length"  class="invalid">At least 8 characters</li>
      <li id="req-lower"   class="invalid">One lowercase letter</li>
      <li id="req-upper"   class="invalid">One uppercase letter</li>
      <li id="req-digit"   class="invalid">One digit</li>
      <li id="req-special" class="invalid">One special character</li>
    </ul>
    <input type="password" id="confirm_password" placeholder="Confirm Password" autocomplete="new-password" required>

    <label>Upload Signature (PNG/JPG):</label>
    <input type="file" id="sigFile" accept="image/png,image/jpeg">

    <hr>

    <label>Or Draw Signature:</label>
    <canvas id="signature-pad"></canvas>
    <div class="sig-control">
      <button type="button" id="clearSig">Clear</button>
    </div>

    <button type="button" id="submitBtn" disabled>Finish Setup</button>
    <div id="feedback" class="error-box"></div>
    <div id="successMsg" class="success-msg"></div>
  </form>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title">Confirm Setup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to update your password and signature?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmSubmit" type="button" class="btn btn-warning">Yes, Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
  const newPwd     = document.getElementById('new_password');
  const confirmPwd = document.getElementById('confirm_password');
  const submitBtn  = document.getElementById('submitBtn');
  const feedback   = document.getElementById('feedback');
  const successMsg = document.getElementById('successMsg');
  const reqs = {
    length:  document.getElementById('req-length'),
    lower:   document.getElementById('req-lower'),
    upper:   document.getElementById('req-upper'),
    digit:   document.getElementById('req-digit'),
    special: document.getElementById('req-special'),
  };

  function validatePassword(pwd) {
    return {
      length:  pwd.length >= 8,
      lower:   /[a-z]/.test(pwd),
      upper:   /[A-Z]/.test(pwd),
      digit:   /[0-9]/.test(pwd),
      special: /[^A-Za-z0-9]/.test(pwd),
    };
  }

  function updatePasswordUI() {
    const pwd = newPwd.value, tests = validatePassword(pwd);
    for (let k in tests) {
      reqs[k].classList.toggle('valid', tests[k]);
      reqs[k].classList.toggle('invalid',!tests[k]);
    }
    checkSubmitable();
  }

  newPwd.addEventListener('input', updatePasswordUI);
  confirmPwd.addEventListener('input', updatePasswordUI);

  // Signature pad
  const canvas = document.getElementById('signature-pad');
  const sigPad = new SignaturePad(canvas, { backgroundColor:'#fff' });
  document.getElementById('clearSig').onclick = () => { sigPad.clear(); checkSubmitable(); };

  const sigFile = document.getElementById('sigFile');
  sigFile.addEventListener('change', checkSubmitable);

  function checkSubmitable() {
    const pwdTests = Object.values(validatePassword(newPwd.value)).every(v=>v);
    const match    = newPwd.value && newPwd.value===confirmPwd.value;
    const hasFile  = sigFile.files.length>0;
    const hasDraw  = !sigPad.isEmpty();
    submitBtn.disabled = !(pwdTests && match && (hasFile||hasDraw));
    feedback.style.display = (!match && confirmPwd.value) ? 'block':'none';
    feedback.textContent = (!match && confirmPwd.value) ? 'Passwords do not match.':'';
  }

  const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
  submitBtn.addEventListener('click', ()=> {
    // show confirmation dialog
    confirmModal.show();
  });

  document.getElementById('confirmSubmit').onclick = async () => {
    confirmModal.hide();
    feedback.style.display = 'none';
    successMsg.textContent = '';

    const form = new FormData();
    form.append('new_password', newPwd.value);
    form.append('confirm_password', confirmPwd.value);

    // signature: file or drawn
    if (sigFile.files.length) {
      form.append('signature', sigFile.files[0]);
    } else {
      const dataURL = sigPad.toDataURL('image/png');
      const blob    = await (await fetch(dataURL)).blob();
      form.append('signature', blob, 'drawn.png');
    }

    const res = await fetch('change_password.php', { method:'POST', body:form });
    const data = await res.json().catch(()=>null);
    if (data?.status === 'success') {
      successMsg.textContent = '✅ Setup complete! Redirecting…';
      setTimeout(()=>window.location='index.php',1500);
    } else {
      feedback.style.display = 'block';
      feedback.textContent = data?.message || 'Unexpected error.';
    }
  };
</script>

</body>
</html>
