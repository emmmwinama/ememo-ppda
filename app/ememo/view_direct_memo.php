<?php
require_once 'auth.php';
require_once 'header.php';
?>
<style>
    .memo-wrapper {
      background: var(--surface);
      padding: 40px;
      border-radius: var(--radius-md);
      border: 1px solid var(--border);
      margin: auto;
      max-width: 850px;
    }

    .memo-title {
      text-align: center;
      font-weight: bold;
      text-decoration: underline;
      margin: 20px 0;
      text-transform: uppercase;
    }

    .memo-meta p {
      margin-bottom: 4px;
    }

.memo-content {
  margin-top: 20px;
  padding: 20px;
  border-left: 4px solid var(--brand);
  border-radius: var(--radius-sm);
  background: none;
}

#memoSubject {
  text-align: center;
  font-weight: bold;
  text-transform: uppercase;
  margin-top: 30px;
}



    .signature-img {
      height: 45px;
      margin-bottom: 5px;
    }

    .signature-block {
      text-align: center;
      margin-top: 40px;
    }

    .avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--brand);
      color: white;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 1rem;
    }
  </style>

<div class="container py-4">
<div class="memo-wrapper" id="memoContainer">
  <div class="d-flex justify-content-between mb-3">
    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
    <a id="pdfExportBtn" href="#" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Export as PDF</a>
  </div>

  <!-- Branded Header -->
  <div class="text-center mb-4">
    <img src="header.png" alt="Memo Header" style="max-width: 100%; height: auto;">
  </div>

  <h4 class="memo-title" id="memoTitle">Loading...</h4>

  <div class="d-flex justify-content-between mb-2">
  <strong id="memoRef" class="fw-bold">Loading ref...</strong>
  <span id="memoDate" class="fw-bold pe-3">Loading date...</span>
</div>


  <div class="mb-3">
    <p><strong>To:</strong> <span id="memoTo">Loading...</span></p>
    <p><strong>Copy:</strong> <span id="memoCC">Loading...</span></p>
  </div>

  <h5 class="fw-bold text-uppercase mt-3" id="memoSubject">Loading subject...</h5>

  <div class="memo-content" id="memoContent">Loading content...</div>

  <div class="signature-block">
    <img id="signatureImg" src="" alt="Signature" class="signature-img"><br>
    <strong id="originatorName">Loading...</strong><br>
    <span id="originatorPosition" class="text-uppercase small">Loading...</span>
  </div>

  <div class="attachments-section mt-4" id="attachmentsSection">
    <h5>Attachments</h5>
    <ul id="attachmentsList" class="list-group"></ul>
  </div>

  <div class="views-section mt-4" id="viewsSection">
    <h5>Views</h5>
    <ul id="viewsList" class="list-group"></ul>
  </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const memoId = new URLSearchParams(window.location.search).get('memo_id');
  if (!memoId) {
    alert('No memo ID provided.');
    return;
  }

document.getElementById('pdfExportBtn').addEventListener('click', function (e) {
  e.preventDefault();
  fetch(`get_direct_memo.php?memo_id=${memoId}`)
    .then(res => res.json())
    .then(data => {
      const memo = data.data;

      // ── NEW: mirror the “send_all” logic for PDF export ──
      if (memo.send_all === 1) {
        memo.to_recipients = [{ name: 'All members of staff' }];
        memo.cc_recipients = [];
      }
      // ─────────────────────────────────────────────────────

      const fileName = `${memo.subject.replace(/\s+/g, '_')}_${new Date(memo.created_at).toLocaleDateString('en-GB')}.pdf`;

      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'export_memo_pdf.php';
      form.target = 'downloadFrame';

      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'data';
      input.value = JSON.stringify(memo);

      const downloadInput = document.createElement('input');
      downloadInput.type = 'hidden';
      downloadInput.name = 'filename';
      downloadInput.value = fileName;

      form.appendChild(input);
      form.appendChild(downloadInput);
      document.body.appendChild(form);

      let frame = document.getElementById('downloadFrame');
      if (!frame) {
        frame = document.createElement('iframe');
        frame.name = 'downloadFrame';
        frame.style.display = 'none';
        document.body.appendChild(frame);
      }

      form.submit();
      document.body.removeChild(form);
    });
});



  fetch(`get_direct_memo.php?memo_id=${memoId}`)
    .then(res => res.json())
    .then(data => {
      const m = data.data;

      document.getElementById('memoTitle').textContent = m.memo_type || 'MEMO';
      document.getElementById('memoRef').textContent = m.reference_number;
      document.getElementById('memoDate').textContent = new Date(m.created_at).toLocaleDateString('en-US');
      document.getElementById('memoSubject').textContent = m.subject;
      document.getElementById('memoContent').innerHTML = m.content;
      document.getElementById('originatorName').textContent = m.originator_name;
      document.getElementById('originatorPosition').textContent = m.originator_position;
      document.getElementById('signatureImg').src = m.signature_data;

  // after you’ve pulled in `const m = data.data;`

// find the elements
const toElem         = document.getElementById('memoTo');
const ccElem         = document.getElementById('memoCC');
const ccContainer    = ccElem.closest('p');

// if send_all is true, override “To” and hide Copy entirely
if (m.send_all === 1) {
  toElem.textContent = 'All members of staff';
  ccContainer.style.display = 'none';
} else {
  // normal recipients
  const toFormatted = m.to_recipients.map(r => r.name).join('; ');
  toElem.textContent = toFormatted || '—';

  // only show CC if there are any
  if (m.cc_recipients && m.cc_recipients.length) {
    const ccFormatted = m.cc_recipients.map(r => r.name).join('; ');
    ccElem.textContent = ccFormatted;
  } else {
    ccContainer.style.display = 'none';
  }
}


      const attachmentsList = document.getElementById('attachmentsList');
      if (m.attachments.length > 0) {
        m.attachments.forEach(file => {
          const li = document.createElement('li');
          li.className = 'list-group-item';
          li.innerHTML = `<a href="${file.file_path}" download>${file.file_name}</a>`;
          attachmentsList.appendChild(li);
        });
      } else {
        document.getElementById('attachmentsSection').style.display = 'none';
      }

      const viewsList = document.getElementById('viewsList');
      if (m.views.length > 0) {
        m.views.forEach(view => {
          const li = document.createElement('li');
          li.className = 'list-group-item d-flex align-items-center gap-2';
          const viewedAt = new Date(view.viewed_at).toLocaleString('en-US');
          li.innerHTML = `
            <div class="avatar" title="${viewedAt}">
              <i class="bi bi-person-fill"></i>
            </div>
            <span>${view.full_name} viewed on ${viewedAt}</span>
          `;
          viewsList.appendChild(li);
        });
      } else {
        document.getElementById('viewsSection').style.display = 'none';
      }
    });
});
</script>
<?php require_once 'footer.php'; ?>
