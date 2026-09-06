<?php require_once 'auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update My Profile</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    /* Pulse animation for strong password badge */
    @keyframes pulse {
      0%   { transform: scale(1);   opacity: 1;   }
      50%  { transform: scale(1.1); opacity: 0.7; }
      100% { transform: scale(1);   opacity: 1;   }
    }
    .badge-pulse { animation: pulse 1.5s infinite; }
  </style>
</head>
<body class="p-4">

<div class="container" style="max-width: 700px;">
  <h4 class="mb-4">Update My Profile</h4>
  <div id="alertBox"></div>
  <form id="profileForm" class="card shadow-sm p-4 border-0" enctype="multipart/form-data">
    
    <!-- Password Change -->
    <div class="mb-4">
      <label class="form-label">Change Password (optional)</label>
      <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Enter new password if changing">
      <div id="passwordStrength" class="mt-2"></div>
    </div>

    <!-- Signature Upload -->
    <div class="mb-4">
      <label class="form-label">Upload Signature (optional)</label>
      <input type="file" name="signature_file" accept="image/*" class="form-control mb-2">
      <small class="text-muted">Or draw below 👇</small>
    </div>

    <!-- Signature Pad -->
    <div class="mb-4">
      <label class="form-label">Draw Signature (optional)</label>
      <canvas id="signatureCanvas" style="width:100%; height:150px; border:1px dashed #ccc;"></canvas>
      <div class="mt-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">Clear Canvas</button>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="progress mb-4" style="height: 20px; display: none;" id="progressWrapper">
      <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%;">Uploading...</div>
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-success">Save Changes</button>
    </div>
  </form>

  <hr class="my-5">

  <h5>My Current Signature:</h5>
  <img id="currentSignature" src="" alt="No Signature Uploaded" class="img-thumbnail mt-2" style="max-width: 300px;">
</div>

<!-- Toast Box -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:1055">
  <div id="toastMessage" class="toast align-items-center text-white bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastBody"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
//— setup signature pad —
const signaturePad = new SignaturePad(document.getElementById('signatureCanvas'));
document.getElementById('clearSignature').onclick = () => signaturePad.clear();

//— regex for strong policy: 8+, upper, lower, digit, special —
const policyRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

//— Password strength meter —
document.getElementById('newPassword').addEventListener('input', function () {
  const pwd = this.value;
  const bar = document.getElementById('passwordStrength');
  if (!pwd) {
    bar.innerHTML = '';
    return;
  }
  // count how many rules met
  let count=0;
  if (/[A-Z]/.test(pwd)) count++;
  if (/[a-z]/.test(pwd)) count++;
  if (/\d/.test(pwd)) count++;
  if (/[^A-Za-z0-9]/.test(pwd)) count++;
  if (pwd.length>=8) count++;

  let cls='bg-danger', txt='Too Weak', pulse='';
  if (count===5) {
    cls='bg-success'; txt='Strong'; pulse='badge-pulse';
  } else if (count>=3) {
    cls='bg-warning text-dark'; txt='Medium';
  }

  bar.innerHTML = `<span class="badge ${cls} ${pulse}">${txt} Password</span>`;
});

//— show toast —
function showToast(msg, type='success') {
  const t = document.getElementById('toastMessage');
  t.className = `toast align-items-center text-white bg-${type} border-0`;
  document.getElementById('toastBody').textContent = msg;
  new bootstrap.Toast(t).show();
}

//— load current signature —
function loadSignature() {
  fetch('get_user_signature.php')
    .then(r => r.json())
    .then(d => {
      document.getElementById('currentSignature').src = (d.status==='success'&&d.path)
        ? d.path + '?' + Date.now()
        : '';
    });
}
loadSignature();

//— handle form submit —
document.getElementById('profileForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const alertBox = document.getElementById('alertBox');
  alertBox.innerHTML = '';

  const pwd = document.getElementById('newPassword').value.trim();
  // if user entered a new password, enforce policy
  if (pwd && !policyRegex.test(pwd)) {
    alertBox.innerHTML = 
      `<div class="alert alert-danger">
         Password must be ≥8 chars and include uppercase, lowercase, number & special character.
       </div>`;
    return;
  }

  // build form data
  const formData = new FormData(this);
  if (!signaturePad.isEmpty()) {
    formData.append('signature_canvas', signaturePad.toDataURL());
  }

  // show progress
  const wrapper = document.getElementById('progressWrapper');
  const bar     = document.getElementById('progressBar');
  wrapper.style.display = 'block';
  bar.style.width = '20%';

  fetch('update_profile.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(d => {
    bar.style.width = '80%';
    if (d.status==='success') {
      showToast('Profile updated successfully!','success');
      loadSignature();
    } else {
      showToast('Error: '+d.message,'danger');
    }
    bar.style.width = '100%';
    setTimeout(()=>wrapper.style.display='none', 800);
  })
  .catch(()=> {
    wrapper.style.display = 'none';
    showToast('Network error. Please try again.','danger');
  });
});
</script>
</body>
</html>
