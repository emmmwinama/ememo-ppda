<?php
// dg_memos.php — rendered inside index.php (header.php loads Bootstrap, Icons, theme.css)
require_once 'auth.php';
?>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
  .swal2-confirm { background-color: var(--brand) !important; color: #fff !important; }
  .swal2-cancel  { background-color: #6c757d !important; color: #fff !important; }

  .dg-tabs { display: flex; gap: .5rem; margin-bottom: 1.4rem; border: none; }
  .dg-tabs .nav-link {
    border: 1px solid var(--border); background: var(--surface);
    border-radius: var(--radius-pill);
    padding: .35rem .95rem; font-size: .82rem; font-weight: 600; color: var(--muted);
  }
  .dg-tabs .nav-link:hover { color: var(--text); border-color: var(--border-strong, #cbd5e1); }
  .dg-tabs .nav-link.active { background: var(--brand); border-color: var(--brand); color: #fff; }

  .memo-container {
    background: var(--surface);
    padding: 48px 56px;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-flat);
    font-family: 'Times New Roman', Times, serif;
    line-height: 1.8;
    max-width: 900px;
    margin: auto;
  }
  .memo-header img { max-width: 100%; margin-bottom: 20px; }
  .memo-title {
    text-align: center; font-weight: bold; text-decoration: underline;
    font-size: 20px; margin-bottom: 30px;
  }
  .memo-field { margin-bottom: 1.2rem; }
  .memo-field label { font-weight: bold; margin-bottom: .2rem; }
  .memo-body {
    min-height: 250px; padding: 15px;
    border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg);
  }
  .recipient-selected .badge { margin-bottom: 4px; }
  .signature-preview { max-width: 100%; max-height: 120px; }
</style>

<div class="f-head">
  <h1 class="f-title">DG Broadcasts</h1>
  <p class="f-subtitle">Create and issue circulars and director-general memos</p>
</div>

<ul class="nav dg-tabs" id="memoTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab">
      <i class="bi bi-pencil-square me-1"></i>Create
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="drafts-tab" data-bs-toggle="tab" data-bs-target="#drafts" type="button" role="tab">
      <i class="bi bi-file-earmark me-1"></i>Drafts
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="submitted-tab" data-bs-toggle="tab" data-bs-target="#submitted" type="button" role="tab">
      <i class="bi bi-send me-1"></i>Submitted
    </button>
  </li>
</ul>

<div class="tab-content" id="memoTabContent">
  <!-- Create -->
  <div class="tab-pane fade show active" id="create" role="tabpanel">
    <form id="dgMemoForm" class="memo-container" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="to_user_ids" id="to_user_ids">
      <input type="hidden" name="to_group_ids" id="to_group_ids">
      <input type="hidden" name="cc_user_ids" id="cc_user_ids">
      <input type="hidden" name="cc_group_ids" id="cc_group_ids">

      <div class="memo-header text-center">
        <img src="header.png" alt="Memo Header">
      </div>

      <h4 class="memo-title" id="memoTitle">CIRCULAR</h4>

      <div class="row memo-field">
        <div class="col-md-4">
          <label for="memoType">Memo Type</label>
          <select id="memoType" class="form-select" required>
            <option value="">Select</option>
            <option value="CIRCULAR">CIRCULAR</option>
            <option value="DIRECTOR GENERAL'S MEMO">DIRECTOR GENERAL'S MEMO</option>
            <option value="BROADCAST MEMO">BROADCAST MEMO</option>
            <option value="DEPARTMENTAL NOTICE MEMO">DEPARTMENTAL NOTICE MEMO</option>
          </select>
          <input type="text" id="customMemoType" class="form-control mt-2" placeholder="Enter memo title" style="display: none;">
          <input type="hidden" name="memo_type" id="memoTypeHidden" value="">
        </div>

        <div class="col-md-8">
          <label for="reference_number">Reference Number</label>
          <input type="text" name="reference_number" id="reference_number" class="form-control" placeholder="e.g. DG/HR/2025/001" required>
        </div>
      </div>

      <div class="row memo-field">
        <div class="col-md-9">
          <label for="subject">Subject</label>
          <input type="text" name="subject" id="subject" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label for="date">Date</label>
          <input type="date" name="date" id="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
      </div>

      <div class="memo-field">
        <label for="toInput">To</label>
        <input type="text" id="toInput" class="form-control" placeholder="Search users or groups...">
        <div id="toResults" class="list-group position-absolute w-100 z-3"></div>
        <div id="toSelected" class="recipient-selected mt-2"></div>
      </div>

      <div class="memo-field">
        <label for="ccInput">CC</label>
        <input type="text" id="ccInput" class="form-control" placeholder="Search users or groups...">
        <div id="ccResults" class="list-group position-absolute w-100 z-3"></div>
        <div id="ccSelected" class="recipient-selected mt-2"></div>
      </div>

      <div class="form-check memo-field">
        <input class="form-check-input" type="checkbox" id="sendAll" name="send_all">
        <label class="form-check-label" for="sendAll">Send to All Users</label>
      </div>

      <div class="memo-field">
        <label for="editor">Body</label>
        <div id="editor" class="memo-body"></div>
        <input type="hidden" name="content" id="content">
      </div>

      <div class="memo-field">
        <label for="attachmentsInput">Attachments (select one file at a time, multiple allowed)</label>
        <input type="file" id="attachmentsInput" class="form-control" onchange="handleFileAdd(event)">
        <div id="filePreviewList" class="mt-3"></div>
      </div>

      <div class="memo-field">
        <label for="signaturePreview">Signature</label><br>
        <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="openSignModal">Capture Signature</button>
        <div class="border p-2 bg-light">
          <img id="signaturePreview" class="signature-preview" alt="Signature Preview">
        </div>
        <input type="hidden" name="signature_data" id="signature_data">
      </div>

      <div class="text-end mt-4">
        <button type="button" class="btn btn-secondary me-2" onclick="submitMemo('Draft')">Save as Draft</button>
        <button type="button" class="btn btn-success" onclick="submitMemo('Submitted')">Submit Memo</button>
      </div>
    </form>
  </div>

  <!-- Drafts -->
  <div class="tab-pane fade" id="drafts" role="tabpanel">
    <div id="draftsContent" class="mt-4 text-center text-muted">
      <div class="spinner-border spinner-border-sm text-secondary mt-3" role="status"></div>
      <p class="mt-3">Loading drafts…</p>
    </div>
  </div>

  <!-- Submitted -->
  <div class="tab-pane fade" id="submitted" role="tabpanel">
    <div id="submittedContent" class="mt-4 text-center text-muted">
      <div class="spinner-border spinner-border-sm text-secondary mt-3" role="status"></div>
      <p class="mt-3">Loading submitted memos…</p>
    </div>
  </div>
</div>

<!-- Signature Modal -->
<div class="modal fade" id="signatureModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <canvas id="signatureCanvas" class="w-100 border" height="150"></canvas>
      <div class="mt-2">
        <button class="btn btn-sm btn-warning" id="clearSignature">Clear</button>
        <button class="btn btn-sm btn-primary" id="applySignature">Apply</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let quill;

function initQuill() {
  if (!quill && document.getElementById('editor')) {
    quill = new Quill('#editor', { theme: 'snow' });
  }
}

const memoTypeSelect = document.getElementById('memoType');
const customInput     = document.getElementById('customMemoType');
const memoTitle       = document.getElementById('memoTitle');
const memoHidden      = document.getElementById('memoTypeHidden');

memoTypeSelect.addEventListener('change', () => {
  if (memoTypeSelect.value === 'DEPARTMENTAL NOTICE MEMO') {
    customInput.style.display = 'block';
    customInput.value = '';
    memoTitle.textContent = '';
    memoHidden.value = '';
  } else {
    customInput.style.display = 'none';
    memoTitle.textContent = memoTypeSelect.value || 'MEMO';
    memoHidden.value = memoTypeSelect.value;
  }
});

customInput.addEventListener('input', () => {
  memoTitle.textContent = customInput.value || 'MEMO';
  memoHidden.value    = customInput.value;
});

document.addEventListener('DOMContentLoaded', () => {
  const memoTypeSelect = document.getElementById('memoType');
  const memoTitle = document.getElementById('memoTitle');
  const memoId = new URLSearchParams(window.location.search).get('id');

  initQuill();
  document.getElementById('create-tab').addEventListener('shown.bs.tab', initQuill);

  memoTypeSelect.addEventListener('change', () => {
    memoTitle.textContent = memoTypeSelect.value;
  });

  if (memoId) {
    const waitForQuill = setInterval(() => {
      if (quill) {
        clearInterval(waitForQuill);
        fetch(`get_dg_memo.php?id=${memoId}`)
          .then(response => response.json())
          .then(data => {
            if (data.status === 'success') {
              const memo = data.memo;
              memoTypeSelect.value = memo.memo_type;
              memoTitle.textContent = memo.memo_type;
              document.getElementById('subject').value = memo.subject;
              document.getElementById('reference_number').value = memo.reference_number;
              document.getElementById('date').value = memo.date;
              quill.root.innerHTML = memo.content;
              document.getElementById('content').value = memo.content;
              populateRecipients('to', memo.to_users, memo.to_groups);
              populateRecipients('cc', memo.cc_users, memo.cc_groups);
              document.getElementById('sendAll').checked = memo.send_all === '1';
              if (memo.signature_data) {
                document.getElementById('signaturePreview').src = memo.signature_data;
                document.getElementById('signature_data').value = memo.signature_data;
              }
            } else {
              alert('Failed to load memo data.');
            }
          });
      }
    }, 100);
  } else {
    fetch('load_signature.php')
      .then(res => res.json())
      .then(data => {
        if (data.image) {
          document.getElementById('signaturePreview').src = data.image;
          document.getElementById('signature_data').value = data.image;
        }
      });
  }

  const signaturePad = new SignaturePad(document.getElementById('signatureCanvas'));
  document.getElementById('openSignModal').addEventListener('click', () => {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('signatureModal')).show();
  });
  document.getElementById('clearSignature').addEventListener('click', () => signaturePad.clear());
  document.getElementById('applySignature').addEventListener('click', () => {
    if (!signaturePad.isEmpty()) {
      const dataURL = signaturePad.toDataURL();
      document.getElementById('signaturePreview').src = dataURL;
      document.getElementById('signature_data').value = dataURL;
      bootstrap.Modal.getInstance(document.getElementById('signatureModal')).hide();
    }
  });

  ['to', 'cc'].forEach(field => {
    const input = document.getElementById(`${field}Input`);
    const resultsBox = document.getElementById(`${field}Results`);
    const selectedContainer = document.getElementById(`${field}Selected`);
    const userInput = document.getElementById(`${field}_user_ids`);
    const groupInput = document.getElementById(`${field}_group_ids`);
    const userIds = [], groupIds = [];

    input.addEventListener('input', function () {
      const query = this.value.trim();
      if (query.length < 2) return resultsBox.innerHTML = '';
      fetch(`user_search.php?q=${encodeURIComponent(query)}`)
        .then(res => res.text())
        .then(html => {
          resultsBox.innerHTML = html;
          resultsBox.style.display = 'block';
        });
    });

    resultsBox.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-user-id], [data-group-id]');
      if (!btn) return;
      const type = btn.dataset.type;
      const id = btn.dataset.userId || btn.dataset.groupId;
      const label = btn.textContent.trim();

      if (type === 'user' && !userIds.includes(id)) {
        userIds.push(id);
        userInput.value = userIds.join(',');
        selectedContainer.innerHTML += `<span class="badge bg-primary me-1">${label}</span>`;
      } else if (type === 'group' && !groupIds.includes(id)) {
        groupIds.push(id);
        groupInput.value = groupIds.join(',');
        selectedContainer.innerHTML += `<span class="badge bg-warning text-dark me-1">${label} (group)</span>`;
      }

      input.value = '';
      resultsBox.innerHTML = '';
    });
  });

  const draftsTab = document.getElementById('drafts-tab');
  const submittedTab = document.getElementById('submitted-tab');

  draftsTab.addEventListener('click', () => fetchMemos('Draft', 'draftsContent'));
  submittedTab.addEventListener('click', () => fetchMemos('Submitted', 'submittedContent'));

  function fetchMemos(status, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '<div class="spinner-border spinner-border-sm text-secondary mt-3" role="status"></div><p class="mt-3">Loading ' + status + ' memos…</p>';
    fetch('fetch_dg_memos.php?status=' + encodeURIComponent(status))
      .then(response => response.text())
      .then(html => { container.innerHTML = html; })
      .catch(error => {
        container.innerHTML = '<p class="text-danger">Error loading memos.</p>';
        console.error('Error fetching memos:', error);
      });
  }

  fetchMemos('Submitted', 'submittedContent');
});

window.submitMemo = function (status) {
  document.getElementById('content').value = quill.root.innerHTML;
  const form    = document.getElementById('dgMemoForm');
  const formData= new FormData(form);
  const memoId  = new URLSearchParams(window.location.search).get('id');
  formData.append('status', status);
  if (memoId) formData.append('id', memoId);

  if (typeof selectedFiles !== 'undefined') {
    selectedFiles.forEach(file => formData.append('attachments[]', file));
  }

  Swal.fire({
    title: 'Confirm Submission',
    text:  `Are you sure you want to ${status === 'Submitted' ? 'submit' : 'save'} this memo?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: status === 'Submitted' ? 'Yes, Submit' : 'Yes, Save Draft',
    cancelButtonText:  'Cancel'
  })
  .then(result => {
    if (!result.isConfirmed) return;
    return fetch('dg_memo_submit.php', { method: 'POST', body: formData });
  })
  .then(res => res && res.json())
  .then(data => {
    if (!data) return;
    if (data.status === 'success') {
      Swal.fire({
        toast: true, icon: 'success',
        title: `Memo ${status.toLowerCase()} successfully!`,
        position: 'top-end', showConfirmButton: false,
        timer: 2000, timerProgressBar: true
      });
      setTimeout(() => {
        const url = new URL(window.location.href);
        if (data.id) url.searchParams.set('id', data.id);
        window.location.href = url.toString();
      }, 2100);
    } else {
      Swal.fire('Error', data.message || 'Submission failed', 'error');
    }
  })
  .catch(() => {
    Swal.fire('Error', 'Network error occurred', 'error');
  });
};
</script>
