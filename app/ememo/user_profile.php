<?php
// user_profile.php — rendered inside index.php (header.php loads Bootstrap, Icons, theme.css)
require_once 'auth.php';
?>
<style>
  .profile-wrap { max-width: 640px; }
  .profile-form .form-label { font-weight: 600; font-size: .85rem; color: var(--text); }
  .profile-form .form-text { font-size: .8rem; }
  #signatureCanvas {
    width: 100%; height: 150px;
    border: 1px dashed var(--border-strong, #cbd5e1);
    border-radius: var(--radius-md); background: var(--surface);
  }
  .sig-preview { max-width: 300px; border: 1px solid var(--border); border-radius: var(--radius-md); padding: .5rem; background: var(--surface); }
</style>

<div class="profile-wrap">
  <div class="f-head">
    <h1 class="f-title">My Profile</h1>
    <p class="f-subtitle">Update your password and signature</p>
  </div>

  <div id="alertBox"></div>

  <form id="profileForm" class="f-card profile-form" enctype="multipart/form-data" style="gap:1.25rem;">

    <div>
      <label class="form-label">Change password <span class="text-muted fw-normal">(optional)</span></label>
      <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Enter a new password if changing">
      <div id="passwordStrength" class="mt-2"></div>
    </div>

    <div>
      <label class="form-label">Upload signature <span class="text-muted fw-normal">(optional)</span></label>
      <input type="file" name="signature_file" accept="image/*" class="form-control">
      <div class="form-text">…or draw one below.</div>
    </div>

    <div>
      <label class="form-label">Draw signature <span class="text-muted fw-normal">(optional)</span></label>
      <canvas id="signatureCanvas"></canvas>
      <div class="mt-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
          <i class="bi bi-eraser me-1"></i>Clear canvas
        </button>
      </div>
    </div>

    <div class="progress" style="height: 18px; display: none;" id="progressWrapper">
      <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%;">Uploading…</div>
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save changes</button>
    </div>
  </form>

  <div class="mt-4">
    <label class="form-label fw-semibold">Current signature</label><br>
    <img id="currentSignature" src="" alt="No signature on file" class="sig-preview mt-1">
  </div>
</div>

<div class="position-fixed top-0 end-0 p-3" style="z-index:1055">
  <div id="toastMessage" class="toast align-items-center text-white bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastBody"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
<script>
(() => {
  const signaturePad = new SignaturePad(document.getElementById('signatureCanvas'));
  document.getElementById('clearSignature').onclick = () => signaturePad.clear();

  const policyRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

  document.getElementById('newPassword').addEventListener('input', function () {
    const pwd = this.value;
    const bar = document.getElementById('passwordStrength');
    if (!pwd) { bar.innerHTML = ''; return; }
    let count = 0;
    if (/[A-Z]/.test(pwd)) count++;
    if (/[a-z]/.test(pwd)) count++;
    if (/\d/.test(pwd)) count++;
    if (/[^A-Za-z0-9]/.test(pwd)) count++;
    if (pwd.length >= 8) count++;

    let cls = 'bg-danger', txt = 'Too weak', pulse = '';
    if (count === 5) { cls = 'bg-success'; txt = 'Strong'; pulse = 'badge-pulse'; }
    else if (count >= 3) { cls = 'bg-warning text-dark'; txt = 'Medium'; }
    bar.innerHTML = `<span class="badge ${cls} ${pulse}">${txt} password</span>`;
  });

  function showToast(msg, type = 'success') {
    const t = document.getElementById('toastMessage');
    t.className = `toast align-items-center text-white bg-${type} border-0`;
    document.getElementById('toastBody').textContent = msg;
    new bootstrap.Toast(t).show();
  }

  function loadSignature() {
    fetch('get_user_signature.php')
      .then(r => r.json())
      .then(d => {
        document.getElementById('currentSignature').src =
          (d.status === 'success' && d.path) ? d.path + '?' + Date.now() : '';
      });
  }
  loadSignature();

  document.getElementById('profileForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const alertBox = document.getElementById('alertBox');
    alertBox.innerHTML = '';

    const pwd = document.getElementById('newPassword').value.trim();
    if (pwd && !policyRegex.test(pwd)) {
      alertBox.innerHTML = `<div class="alert alert-danger">Password must be ≥8 characters and include uppercase, lowercase, a number and a special character.</div>`;
      return;
    }

    const formData = new FormData(this);
    if (!signaturePad.isEmpty()) formData.append('signature_canvas', signaturePad.toDataURL());

    const wrapper = document.getElementById('progressWrapper');
    const bar     = document.getElementById('progressBar');
    wrapper.style.display = 'block';
    bar.style.width = '20%';

    fetch('update_profile.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(d => {
        bar.style.width = '80%';
        if (d.status === 'success') { showToast('Profile updated successfully.', 'success'); loadSignature(); }
        else { showToast('Error: ' + d.message, 'danger'); }
        bar.style.width = '100%';
        setTimeout(() => wrapper.style.display = 'none', 800);
      })
      .catch(() => {
        wrapper.style.display = 'none';
        showToast('Network error. Please try again.', 'danger');
      });
  });
})();
</script>
