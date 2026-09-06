<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password - eMemo System</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <style>
    body { font-family: sans-serif; }
    .login-wrapper { max-width: 400px; margin: 3rem auto; background: var(--surface); padding: 2rem; border-radius: var(--radius-md); border: 1px solid var(--border); }
    .login-logo-area { text-align: center; margin-bottom: 1rem; }
    .login-logo-area img { max-height: 60px; }
    .requirements { font-size: 0.9rem; margin-bottom: 1rem; }
    .requirements li { margin: .3rem 0; }
    .requirements li.valid { color: var(--brand); }
    .requirements li.invalid { color: var(--danger); }
    .error-box { color: var(--danger); margin-top: 1rem; }
    button:disabled { opacity: .6; cursor: not-allowed; }
    #submitBtn { background: var(--brand); border: none; color: #fff; border-radius: var(--radius-sm); }
    #submitBtn:hover:not(:disabled) { background: var(--brand-dark); }
  </style>
</head>
<body>

<div class="login-wrapper">
  <div class="login-logo-area">
    <img src="logo.png" alt="e-Memo Logo">
    <h2>Change Your Password</h2>
  </div>

  <form id="changePasswordForm">
    <div>
      <input type="password" id="new_password" placeholder="New Password" autocomplete="new-password" required style="width:100%;padding:.5rem;">
    </div>

    <ul class="requirements" id="passwordRequirements">
      <li id="req-length" class="invalid">8 or more characters</li>
      <li id="req-lower"  class="invalid">At least one lowercase letter</li>
      <li id="req-upper"  class="invalid">At least one uppercase letter</li>
      <li id="req-digit"  class="invalid">At least one digit</li>
      <li id="req-special"class="invalid">At least one special character (!@#$…)</li>
    </ul>

    <div>
      <input type="password" id="confirm_password" placeholder="Confirm Password" autocomplete="new-password" required style="width:100%;padding:.5rem;margin-bottom:1rem;">
    </div>

    <button type="submit" id="submitBtn" disabled style="width:100%;padding:.6rem;">Update Password</button>
    <div id="feedback" class="error-box" style="display:none;"></div>
  </form>
</div>

<script>
  const newPwd    = document.getElementById('new_password');
  const confirmPwd= document.getElementById('confirm_password');
  const submitBtn = document.getElementById('submitBtn');
  const feedback  = document.getElementById('feedback');

  // requirement elements
  const reqs = {
    length:  document.getElementById('req-length'),
    lower:   document.getElementById('req-lower'),
    upper:   document.getElementById('req-upper'),
    digit:   document.getElementById('req-digit'),
    special: document.getElementById('req-special'),
  };

  function validatePassword() {
    const pwd = newPwd.value;
    // tests
    const tests = {
      length:   pwd.length >= 8,
      lower:    /[a-z]/.test(pwd),
      upper:    /[A-Z]/.test(pwd),
      digit:    /[0-9]/.test(pwd),
      special:  /[^A-Za-z0-9]/.test(pwd),
    };
    // update UI
    for (const [k, ok] of Object.entries(tests)) {
      reqs[k].classList.toggle('valid', ok);
      reqs[k].classList.toggle('invalid', !ok);
    }
    return Object.values(tests).every(Boolean);
  }

  function checkFormReady() {
    const pwdOk = validatePassword();
    const match = newPwd.value && newPwd.value === confirmPwd.value;
    submitBtn.disabled = !(pwdOk && match);
    if (confirmPwd.value && !match) {
      feedback.style.display = 'block';
      feedback.textContent = 'Passwords do not match.';
    } else {
      feedback.style.display = 'none';
    }
  }

  newPwd.addEventListener('input',  () => { validatePassword(); checkFormReady(); });
  confirmPwd.addEventListener('input', checkFormReady);

  document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    feedback.style.display = 'none';

    const new_password     = newPwd.value;
    const confirm_password = confirmPwd.value;

    fetch('change_password.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ new_password, confirm_password })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        window.location.href = 'index.php';
      } else {
        feedback.style.display = 'block';
        feedback.textContent = data.message;
      }
    })
    .catch(() => {
      feedback.style.display = 'block';
      feedback.textContent = 'Network error, please try again.';
    });
  });
</script>

</body>
</html>
