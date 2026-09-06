<?php
require_once 'auth.php';
require_once 'header.php';
?>
<style>
  /* a pill-shaped chat button style */
  .btn-chat {
    background-color: var(--brand);
    color: #fff;
    border: none;
    padding: 0.45rem 1rem;
    border-radius: var(--radius-pill);
    box-shadow: var(--shadow-flat);
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    transition: background-color 0.2s;
  }
  .btn-chat:hover {
    background-color: var(--brand-dark);
  }
  .btn-chat .bi {
    font-size: 1.1em;
    margin-right: 0.4em;
  }
</style>

  <style>
    body {
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      font-size: 14px;
    }

	strong span {
  white-space: nowrap;
}


    .memo-wrapper {
      background: var(--surface);
      padding: 20px;
      margin: auto;
      max-width: 1000px;
      display: flex;
      flex-direction: row;
      border: 1px solid var(--border);
    }

    .left-margin-note {
      width: 25%;
      font-size: 0.75rem;
      padding-right: 10px;
      border-right: 1px dashed #ccc;
      overflow-wrap: break-word;
    }

    .right-content {
      width: 75%;
      padding-left: 20px;
    }

    .memo-title {
      text-align: center;
      font-weight: bold;
      text-decoration: underline;
      margin: 20px 0;
      font-size: 1.4rem;
    }

    .signature-img {
      max-height: 60px;
      width: auto;
    }

    img {
      max-width: 100%;
      height: auto;
    }

    .approval-section,
    .attachments-section,
    .movement-trail-section,
    .clarification-section {
      margin-top: 30px;
      page-break-inside: avoid;
    }

    @media print {
      @page {
        size: A4;
        margin: 20mm;
      }

      body {
        background: white !important;
      }

      .memo-wrapper {
        box-shadow: none;
        padding: 0;
      }

      .left-margin-note {
        font-size: 10px;
        background: none;
        border-color: #999;
      }

      .no-print,
      .btn,
      .modal,
      .toast-container {
        display: none !important;
      }

      .approval-section,
      .attachments-section,
      .clarification-section {
        page-break-inside: avoid;
      }

      .movement-trail-section {
        page-break-before: always;
      }
    }
  </style> 
  
  <style>
  /* Always show badge background and white text */
  #memoStatusBadge {
    color: #fff !important;
  }

  /* Reinforce Bootstrap colors for each status class */
  #memoStatusBadge.bg-secondary  { background-color: #6c757d !important; }
  #memoStatusBadge.bg-primary    { background-color: #0d6efd !important; }
  #memoStatusBadge.bg-success    { background-color: #198754 !important; }
  #memoStatusBadge.bg-danger     { background-color: #dc3545 !important; }
  #memoStatusBadge.bg-warning    { background-color: #ffc107 !important; color: #212529 !important; }
  #memoStatusBadge.bg-info       { background-color: #0dcaf0 !important; color: #212529 !important; }
  #memoStatusBadge.bg-light      { background-color: #f8f9fa !important; color: #212529 !important; }
  #memoStatusBadge.bg-dark       { background-color: #212529 !important; }

  /* Force color printing for badges */
  .badge {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
</style>
<style>
  /* 1) Make all letter‐line labels the same fixed width so colons line up */
  #letterSection .letter-line {
    display: flex;
    margin-bottom: 1rem;
  }
  #letterSection .letter-line .label {
    flex: 0 0 60px;           /* every label occupies exactly 60px */
    font-weight: bold;
  }
  #letterSection .letter-line .content {
    flex: 1;                  /* the rest of the space */
    white-space: pre-wrap;    /* preserve <br> in your addresses */
  }

  /* 2) Draft Letter container */
  #letterSection.draft-letter {
    margin: 2rem 0;
    padding: 1rem;
    border: 2px dashed #999;
    background: #f9f9f9;
  }
  #letterSection.draft-letter h6.draft-label {
    font-size: 0.9rem;
    font-weight: bold;
    color: #555;
    text-align: center;
    margin-bottom: 1rem;
  }

  /* 3) Subject (centered, bold, spaced) */
  #letterViewSubject {
    text-align: center !important;
    font-weight: bold;
    margin-bottom: 1rem;
  }

  /* 4) Center the signature block (image, name, position) */
  #letterSection .letter-signature {
    text-align: center;
    margin-top: 2rem;
  }
  
  
</style>

<div class="container my-3 no-print d-flex gap-2">
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  <button class="btn btn-outline-secondary btn-sm" onclick="exportMemoPDF()"><i class="bi bi-printer me-1"></i>Export as PDF</button>
  <button id="exportLetterBtn" class="btn btn-outline-secondary btn-sm" onclick="exportLetterPDF()"><i class="bi bi-printer me-1"></i>Export Letter PDF</button>
</div>

<div class="memo-wrapper" id="memoContainer">

  <!-- 📝 Left Column: Approval Trail -->
  <div class="left-margin-note">
    <div id="approvalCommentTrail">
      <!-- Populated dynamically -->
    </div>

    <!-- ▶︎ New Instructions Section -->
    <div id="instructionSection" class="mt-4">
     
      <div id="instructionList" class="ps-2 border-start border-3 border-info">
        
      </div>
    </div>
  </div>


  <!-- 📄 Right Column: Main Memo -->
  <div class="right-content">

    <div class="memo-header">
      <img src="header.png" alt="Header">
    </div>

    <h4 class="memo-title" id="memoTitle">Loading...</h4>

    <div class="memo-meta mb-3">
      <div class="d-flex justify-content-between mb-3 px-1">
	    <strong>Reference: <span id="memoRef">Loading...</span></strong>
  <strong>Date: <span id="memoDate">Loading...</span></strong>

</div>

      <strong>To:</strong> <span id="memoTo">...</span><br>
      <strong>Status:</strong> <span id="memoStatusBadge" class="badge bg-secondary">...</span><br>
    </div>

    <p><strong>Through:</strong></p>
    <div id="memoThrough" class="mb-4">
      <p class="text-muted">Loading endorsements...</p>
    </div>

    <h5 id="memoSubject" class="text-center fw-bold mb-3">Loading...</h5>

    <div class="memo-content" id="memoContent">Loading content...</div>

    <!-- 🖋️ Originator -->
    <div class="approval-section text-center">
      <img id="signatureImg" class="signature-img" src="" alt="Signature">
      <p id="originatorName">Loading...</p>
      <p><strong id="originatorPosition">Loading...</strong></p>
    </div>

    <!-- 🗒️ DG Comment Section -->
    <div class="approval-section" id="dgCommentSection" style="display: none;">
      <strong>DG Comment(s):</strong>
      <div id="dgCommentContent"></div>
    </div>

    <!-- ✅ Action Buttons Section -->
    <div class="approval-section text-end" id="actionButtonsSection" style="margin-top: 20px;">
      <!-- Buttons loaded via JS -->
    </div>

    <!-- 📎 Attachments -->
    <div class="attachments-section" id="attachmentsSection">
      <h5>Attachments</h5>
      <div id="attachmentsList" class="row g-2"></div>
    </div>


<hr class="my-4" style="border-top:2px dashed #666;">
<div class="text-center mb-3" id="draftLetterCaption" style="display:none">
  <strong>Draft External Letter</strong>
</div>


<!-- ◼︎ Draft External Letter -->
<div class="attachments-section mt-5" id="letterSection" style="display: none;">

  <!-- Letter Header Image -->
  <div class="text-center mb-4">
    <img src="header_letter.png" alt="Letter Header" style="max-width:100%; height:auto;">
  </div>

  <!-- Reference & Date -->
  <div class="d-flex justify-content-between mb-3">
    <div><strong id="letterViewRef"></strong></div>
    <div><strong id="letterViewDate"></strong></div>
  </div>

  <!-- Multi-recipient block -->
  <div id="letterMultiRow" style="display:none;">
  
    <!-- From: -->
    <div class="letter-line hanging-indent">
      <strong>From:</strong>
      DIRECTOR GENERAL, PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY, P/BAG 383,
      LILONGWE 3, MALAWI
    </div>
    <!-- To: first recipient -->
    <div class="letter-line hanging-indent">
      <strong>To:</strong> <span id="letterFirstRecipient"></span>
    </div>
    <!-- Other recipients -->
    <div id="letterOtherRecipients"></div>
  </div>

  <!-- Single-recipient block -->
  <div id="letterSingleRow" style="display:none;">
    <div class="letter-line hanging-indent">
      <strong>To:</strong> <span id="letterSingleRecipient"></span>
    </div>
  </div>

  <!-- Subject (centered) -->
  <h5 id="letterViewSubject" class="text-center mb-3"></h5>

  <!-- Body -->
  <div id="letterViewContent" class="border rounded p-4 bg-light mb-4"></div>

  <!-- Signature block -->
  <div class="letter-signature d-flex flex-column align-items-center mb-5">
    <img id="letterApproverSignature"
         class="mb-2"
         src=""
         alt="Approver Signature"
         style="max-height:80px; display:none;">
    <div id="letterApproverName" class="fw-bold"></div>
    <div id="letterApproverPos"></div>
  </div>

</div>






    <!-- 🧾 Movement Trail -->
    <div class="movement-trail-section" id="movementTrailSection">
      <h5>Movement Trail</h5>
      <ul id="movementTrailList"></ul>
    </div>

    <!-- ❓ Clarifications -->
    <div class="clarification-section" id="clarificationSection">
      <h5>Clarifications</h5>
      <div id="clarificationList" class="list-group"></div>
    </div>
	
	
	
<!-- Escalation Modal -->
<div class="modal fade" id="escalateModal" tabindex="-1" aria-labelledby="escalateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="escalateModalLabel">Consult</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="escalateForm">
         <!-- in your HTML, replace the <select> with this -->
<div class="mb-3">
  <label for="escalationComment" class="form-label">Comments <span class="text-danger">*</span></label>
  <textarea id="escalationComment" class="form-control" rows="4" placeholder="@ to mention…"></textarea>
  <div id="mentionedBadges" class="mt-2"></div>
</div>

          
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-info" onclick="submitEscalation()">Confirm</button>
      </div>
    </div>
  </div>
</div>


<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="actionModalLabel">Action</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="actionForm">
          <div class="mb-3">
            <label for="actionComment" class="form-label" id="actionLabel">Comment</label>
            <textarea id="actionComment" class="form-control" rows="4" required></textarea>
          </div>
          <input type="hidden" id="actionType">
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="actionSubmitBtn">Submit</button>
      </div>
    </div>
  </div>
</div>

<!-- Loading Spinner Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 bg-transparent">
      <div class="modal-body text-center">
        <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status"></div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Seek Clarification Modal -->
<div class="modal fade" id="clarificationModal" tabindex="-1" aria-labelledby="clarificationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title" id="clarificationModalLabel">Seek Clarification</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="clarificationForm">
          <div class="mb-3">
            <label for="clarificationMessage" class="form-label">Clarification Message <span class="text-danger">*</span></label>

            <!-- 🔹 Mentioned User Badges (updated in real-time) -->
      <div id="clarBadges" class="mb-2"></div>

            <!-- 🔹 Message Input -->
            <textarea id="clarificationMessage" class="form-control" rows="5" placeholder="Type your clarification... use @ to mention people" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" id="clarificationSubmitBtn">Seek Clarification</button>
      </div>
    </div>
  </div>
</div>


<!-- Confirmation Modal -->
<div class="modal fade" id="confirmClarificationModal" tabindex="-1" aria-labelledby="confirmClarificationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title" id="confirmClarificationModalLabel">Confirm Send</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to send this clarification request?
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" id="confirmSendClarificationBtn">Yes, Send</button>
      </div>
    </div>
  </div>
</div>


<!-- ▶︎ Forward Modal -->
<div class="modal fade" id="forwardModal" tabindex="-1" aria-labelledby="forwardModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="forwardModalLabel">Forward Memo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="forwardForm">
          <div class="mb-3">
            <label for="forwardType" class="form-label">Forward Type</label>
            <select id="forwardType" class="form-select" required>
              <option value="">– Select type –</option>
              <option value="general">General</option>
              <option value="payment">For Payment</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Tag Officers</label>
            <div id="forwardMentionBadges" class="mb-2"></div>
            <textarea id="forwardMentions" class="form-control"
                      placeholder="Type @ to mention officers"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer bg-light">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="forwardSubmitBtn">Forward</button>
      </div>
    </div>
  </div>
</div>


<!-- 🛠️ Instruction Modal -->
<!-- Instruction Modal -->
<div class="modal fade" id="instructionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Instruction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <textarea id="instructionMessage" class="form-control" rows="4" placeholder="Type @ to mention…"></textarea>
        <div id="instructionMentionedBadges" class="mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <!-- THIS must match your selector below -->
        <button type="button" id="instructionSubmitBtn" class="btn btn-info">Submit Instruction</button>
      </div>
    </div>
  </div>
</div>







<!-- ✅ Clarification Response Modal -->
<div class="modal fade" id="clarificationResponseModal" tabindex="-1" aria-labelledby="clarificationResponseModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="clarificationResponseModalLabel">Respond to Clarification</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="clarificationResponseForm">
          <div class="mb-3">
            <label for="clarificationResponseMessage" class="form-label">Your Response <span class="text-danger">*</span></label>
            <textarea id="clarificationResponseMessage" class="form-control" rows="4" required></textarea>
          </div>
          <input type="hidden" id="clarificationId">
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="clarificationResponseSubmitBtn">Submit Response</button>
      </div>
    </div>
  </div>
</div>

<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
	
	

  </div> <!-- End right-content -->
</div>




<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tributejs@5.1.3/dist/tribute.css">
<script src="https://cdn.jsdelivr.net/npm/tributejs@5.1.3/dist/tribute.min.js"></script>


<script>
$('#clarificationResponseSubmitBtn').on('click', () => {
  const clarId = $('#clarificationId').val();
  const resp   = $('#clarificationResponseMessage').val().trim();
  if (!resp) {
    return alert('Please enter your response.');
  }

  $.ajax({
    url: 'respond_clarification.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
      clarification_id: clarId,
      response_comment: resp
    }),
    dataType: 'json'
  })
  .done(res => {
    if (res.status === 'success') {
      bootstrap.Modal.getInstance(
        document.getElementById('clarificationResponseModal')
      ).hide();
     location.reload();
    } else {
      alert(res.message || 'Failed to submit response.');
    }
  })
  .fail(() => {
    alert('Server error.');
  });
});
</script>


<script>

// ──── Global memo_id ────
const memo_id = new URLSearchParams(window.location.search).get('memo_id');
let mentionedUsers = [];
let forwardUsers   = [];
let escalationTargets = [];
let clarificationUsers = [];
let instructionTargets = [];

// ----------------- Helpers & Utilities -----------------

async function fetchUserInfo(userId) {
  try {
    const res = await fetch(`get_user_info.php?user_id=${userId}`);
    const body = await res.json();
    return body.status === 'success' ? body.user : null;
  } catch {
    return null;
  }
}

async function enrichData(memoData) {
  for (const c of memoData.memo_clarifications || []) {
    if (!c.user_position || !c.user_id_signature) {
      const u = await fetchUserInfo(c.user_id);
      if (u) {
        c.user_position     ||= u.position;
        c.user_id_signature ||= u.signature_path;
      }
    }
    if (!c.requested_by_position || !c.requested_by_signature) {
      const r = await fetchUserInfo(c.requested_by);
      if (r) {
        c.requested_by_position  ||= r.position;
        c.requested_by_signature ||= r.signature_path;
      }
    }
  }

  const finals = [];
  for (const m of memoData.memo_movements || []) {
    if (['Approved','Rejected'].includes(m.action)) {
      let pos = m.from_position, sig = m.signature_path;
      if (!pos || !sig) {
        const u = await fetchUserInfo(m.from_user_id || m.user_id);
        pos ||= u?.position;
        sig ||= u?.signature_path;
      }
      finals.push({
        timestamp:      m.timestamp,
        action:         m.action,
        comments:       m.comments||m.comment||'',
        from_position:  pos,
        signature_path: sig
      });
    }
  }
  memoData.final_actions = finals;
}

// Replace your old showLoading/hideLoading with these:

function showLoading() {
  // Bootstrap 5: get or create the modal instance, then show it
  const modalEl = document.getElementById('loadingModal');
  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function hideLoading() {
  const modalEl = document.getElementById('loadingModal');
  bootstrap.Modal.getOrCreateInstance(modalEl).hide();
}

function showToast(msg, type='info') {
  Swal.fire({ toast:true, position:'top-end', icon:type, title:msg, showConfirmButton:false, timer:3000 });
}

function getStatusColor(s) {
  return { Draft:'secondary', Submitted:'primary', Finalized:'success',
           Rejected:'danger','Under Review':'warning',Returned:'danger' }[s]||'light';
}
function getMovementBadgeColor(a) {
  return { Submitted:'primary', Returned:'danger', Rejected:'danger',
           Endorsed:'info', Approved:'success', Escalated:'warning', Finalized:'dark' }[a]||'secondary';
}

// ----------------- Mention Helpers -----------------

function initializeClarificationMentions(users) {
  const tribute = new Tribute({
    trigger: '@',
    lookup: 'key',
    fillAttr: 'value',
    menuItemTemplate: item => item.string,
    values: users.map(u => ({ key: u.full_name, value: u.full_name, id: u.id })),
    noMatchTemplate: () => 'No match',
    selectTemplate: () => ''
  });

  const input  = document.getElementById('clarificationMessage');
  const badgeContainer = document.getElementById('clarBadges');
  tribute.attach(input);

  input.addEventListener('tribute-replaced', e => {
    const { id, value: name } = e.detail.item.original;
    if (!clarificationUsers.some(u => u.id === id)) {
      clarificationUsers.push({ id, full_name: name });
      badgeContainer.innerHTML = clarificationUsers.map(u =>
        `<span class="badge bg-warning me-1">
           @${u.full_name}
           <button type="button" class="btn-close btn-close-white btn-sm ms-1 remove-clar" data-user-id="${u.id}"></button>
         </span>`
      ).join('');
    }
    input.value = '';
  });

  badgeContainer.addEventListener('click', e => {
    if (e.target.matches('.remove-clar')) {
      const id = +e.target.dataset.userId;
      clarificationUsers = clarificationUsers.filter(u => u.id !== id);
      e.target.closest('.badge').remove();
    }
  });
}


function initializeForwardMentions(users) {
  const tribute = new Tribute({
    trigger: '@',
    lookup: 'key',
    fillAttr: 'value',
    menuItemTemplate: item => item.string,
    values: users.map(u => ({ key: u.full_name, value: u.full_name, id: u.id })),
    noMatchTemplate: () => 'No match',
    selectTemplate: () => ''
  });
  const input  = document.getElementById('forwardMentions');
  const badges = document.getElementById('forwardMentionBadges');
  tribute.attach(input);
  input.addEventListener('tribute-replaced', e => {
    const { id, value:name } = e.detail.item.original;
    if (!forwardUsers.some(u=>u.id===id)) {
      forwardUsers.push({ id, name });
      badges.innerHTML = forwardUsers.map(u=>
        `<span class="badge bg-secondary me-1">@${u.name}
           <button type="button" class="btn-close btn-close-white btn-sm ms-1 remove-forward-mention" data-user-id="${u.id}"></button>
         </span>`
      ).join('');
    }
    input.value = '';
  });
  badges.addEventListener('click', e => {
    if (e.target.matches('.remove-forward-mention')) {
      const id = +e.target.dataset.userId;
      forwardUsers = forwardUsers.filter(u=>u.id!==id);
      badges.querySelector(`[data-user-id="${id}"]`)?.parentElement.remove();
    }
  });
}

// ----------------- Renderers -----------------

function renderAttachments(atts) {
  const sec  = document.getElementById('attachmentsSection'),
        list = document.getElementById('attachmentsList');
  if (!atts.length) return sec.style.display='none';
  list.innerHTML = atts.map(f=>`
    <div class="col-md-6 col-lg-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column justify-content-between">
          <h6 class="card-title text-primary">${f.file_name}</h6>
          <a href="${f.file_path}" download class="btn btn-sm btn-outline-success mt-3">⬇️ Download</a>
        </div>
      </div>
    </div>`).join('');
}

function renderClarifications(clar) {
  const list = document.getElementById('clarificationList');
  if (!clar.length) return list.innerHTML = '<p class="text-muted">No clarifications requested.</p>';
  list.innerHTML = clar.map(c=>`
    <div class="list-group-item border-0 mb-3 p-3 shadow-sm rounded bg-white">
      <div class="mb-2">
        <strong>@${c.requested_by_name}</strong> → <strong>@${c.user_name}</strong>
        <div class="small text-muted">${new Date(c.requested_at).toLocaleString()}</div>
      </div>
      <div><strong>📝 Clarification:</strong><br>${c.clarification_comment}</div>
      ${(c.response_comment?`<div><strong>✅ Response:</strong><br>${c.response_comment}</div>`:'')}
      ${(c.responded_at?`<div class="small text-muted">Responded: ${new Date(c.responded_at).toLocaleString()}</div>`:'')}
    </div>`).join('');
}

function renderMovementTrail(trail) {
  const sec  = document.getElementById('movementTrailSection'),
        list = document.getElementById('movementTrailList');
  if (!trail.length) { sec.style.display='none'; return; }
  list.innerHTML = trail.map(e=>`
    <li class="list-group-item border-0 mb-3 p-3 shadow-sm rounded bg-light">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <div class="fw-bold text-primary">${e.from_name||'System'} ➔ ${e.to_name||'System'}</div>
        <span class="badge bg-${getMovementBadgeColor(e.action)} text-uppercase">${e.action}</span>
      </div>
      <div class="small text-muted mb-2">${new Date(e.timestamp).toLocaleString()}</div>
      ${(e.comments?`<div class="fst-italic">${e.comments}</div>`:'')}
    </li>`).join('');
}

function renderDGComments(comments) {
  const sec  = document.getElementById('dgCommentSection'),
        body = document.getElementById('dgCommentContent'),
        dg   = comments.filter(c=>c.stage==='Director General');
  if (!dg.length) return sec.style.display='none';
  body.innerHTML = dg.map((c,i)=>`
    <div class="mb-3">
      <strong>Comment ${i+1}:</strong>
      <p>${c.comment}</p>
      <small class="text-muted">By ${c.name} on ${new Date(c.timestamp).toLocaleString()}</small>
    </div>`).join('');
}
function renderApprovalTrail(clarifications, finals) {
  const trail = document.getElementById('approvalCommentTrail');
  trail.innerHTML = '';
  let step = 1;

  // Grab the memo status from the global data
  const status = window.currentMemoData?.memo?.status
              || window._lastMemoData?.memo?.status
              || '';

  clarifications.forEach(c => {
    const clarId  = c.id;
    const isAuthor = c.requested_by === window.currentUserId;

    // only allow edits if you're the author AND the memo isn't approved
    const canEdit = isAuthor && status.toLowerCase() !== 'approved';

    trail.insertAdjacentHTML('beforeend', `
      <div class="mb-4" data-clar-id="${clarId}">
        <div class="mb-2 fw-bold">${step++}.</div>
        <div class="ps-2 border-start border-3 border-primary">
          <p class="mb-1"><u>${c.user_position}</u></p>
          <p class="mb-1" id="clar-comment-${clarId}">
            ${c.clarification_comment}
          </p>
          
          ${canEdit
            ? `<button 
                 class="btn btn-sm btn-outline-danger edit-clar-btn mb-2"
                 data-clar-id="${clarId}"
               >Edit</button>`
            : ''
          }

          <p class="mb-1 text-muted">
            ${c.requested_by_position}, 
            ${new Date(c.requested_at).toLocaleString()}
          </p>
          ${c.requested_by_signature
            ? `<div class="mt-2">
                 <img src="${c.requested_by_signature}?t=${Date.now()}" 
                      class="signature-img">
               </div>`
            : ''
          }
        </div>
      </div>
    `);

    // Response block (unchanged)
    if (c.response_comment) {
      trail.insertAdjacentHTML('beforeend', `
        <div class="mb-4">
          <div class="mb-2 fw-bold">${step++}.</div>
          <div class="ps-2 border-start border-3 border-success">
            <p class="mb-1"><u>${c.requested_by_position}</u></p>
            <p class="mb-1">${c.response_comment}</p>
            <p class="mb-1 text-muted">
              ${c.user_position}, ${new Date(c.responded_at).toLocaleString()}
            </p>
            ${c.user_id_signature
              ? `<div class="mt-2">
                   <img src="${c.user_id_signature}?t=${Date.now()}" 
                        class="signature-img">
                 </div>`
              : ''
            }
          </div>
        </div>
      `);
    }
  });

  // Final approvals/rejections (unchanged)
  const hasClar = clarifications.length > 0;
  finals
    .filter(a => ['Approved','Rejected'].includes(a.action))
    .forEach(a => {
      const prefix = hasClar ? `<div class="mb-2 fw-bold">${step++}.</div>` : '';
      const sigHtml = a.signature_path
        ? `<div class="mt-2">
             <img src="${a.signature_path}?t=${Date.now()}" class="signature-img">
           </div>`
        : `<div class="mt-2 text-muted"><em>No signature on file</em></div>`;

      trail.insertAdjacentHTML('beforeend', `
        ${prefix}
        <div class="ps-2 border-start border-3 ${
             a.action==='Approved' ? 'border-success' : 'border-danger'
           } mb-4">
          <p class="mb-1">${a.comments || '<em>No comment provided</em>'}</p>
          <p class="mb-1 text-muted">
            ${a.from_position}, ${new Date(a.timestamp).toLocaleString()}
          </p>
          ${sigHtml}
        </div>
      `);
    });
}


// 3) Delegate click events for Edit/Save buttons
// 3) Delegate click events for Edit/Save buttons
// Delegate click events for Edit/ave buttons, using showToast()
document
  .getElementById('approvalCommentTrail')
  .addEventListener('click', async (e) => {
    const btn = e.target;

    // a) Click “Edit”
    if (btn.matches('.edit-clar-btn')) {
      const id = btn.dataset.clarId;
      const p  = document.getElementById(`clar-comment-${id}`);
      const ta = document.createElement('textarea');
      ta.id         = `clar-comment-${id}`;
      ta.className  = 'form-control mb-2';
      ta.value      = p.textContent.trim();
      p.replaceWith(ta);
      btn.textContent = 'Save';
      btn.classList.replace('edit-clar-btn', 'save-clar-btn');
    }

    // b) Click “Save”
    else if (btn.matches('.save-clar-btn')) {
      const id = btn.dataset.clarId;
      const ta = document.getElementById(`clar-comment-${id}`);
      if (!ta) {
        return showToast('Could not find your edit field.', 'error');
      }
      const newComment = ta.value.trim();
      if (!newComment) {
        return showToast('Comment cannot be empty.', 'warning');
      }

      try {
        const res  = await fetch('update_clarification.php', {
          method: 'POST',
          headers: { 'Content-Type':'application/json' },
          body: JSON.stringify({ id, comment: newComment })
        });
        const json = await res.json();

        if (json.status === 'success') {
          // swap textarea back to <p>
          const p = document.createElement('p');
          p.id         = `clar-comment-${id}`;
          p.className  = 'mb-1';
          p.textContent= newComment;
          ta.replaceWith(p);

          // restore button
          btn.textContent = 'Edit';
          btn.classList.replace('save-clar-btn', 'edit-clar-btn');
          showToast('Clarification updated.', 'success');
        } else {
          showToast(json.message || 'Failed to save.', 'error');
        }
      } catch (err) {
        console.error(err);
        showToast('Server or network error.', 'error');
      }
    }
  });



function renderInstructions(ins) {
  const container = document.getElementById('instructionList');
  container.innerHTML = '';

  ins.forEach(i => {
    container.insertAdjacentHTML('beforeend', `
      <div class="mb-4 ps-2 border-start border-3 border-info">
        <p class="mb-1"><strong>To:</strong> ${i.recipient_position}</p>
        <p class="mb-1">${i.instruction}</p>
        <p class="mb-1 text-muted">${new Date(i.created_at).toLocaleString()}</p>
        <p class="mb-1"><strong>From:</strong> ${i.sender_position}</p>
        ${i.signature_path
          ? `<div class="mt-2"><img src="${i.signature_path}?t=${Date.now()}" class="signature-img"></div>`
          : ''
        }
      </div>
    `);
  });
}






function setupActionButtons(data) {
  const btns = document.getElementById('actionButtonsSection'),
        m    = data.memo,
        me   = data.current_user_id;

 // 0) If it’s already approved, show only “Forward”
  if (m.status === 'Approved') {
    btns.innerHTML = `
      <button data-action="forward" class="btn btn-success">
        ➡️ Forward
      </button> 
	  <button data-action="instruction" class="btn btn-info me-2">🛠️ Instruction</button>
	  `;
    wire();
    return;
  }


  // Clear everything first
  btns.innerHTML = '';

  // 1) Respond to pending clarification only
  const myPending = (data.memo_clarifications || [])
    .some(c => c.user_id === me && c.status === 'Pending');
  if (myPending) {
    btns.innerHTML = `
      <button data-action="respond_clarification" class="btn btn-primary me-2">
        💬 Respond to Clarification
      </button>`;
    wire();
    return;
  }

  // 2) Through-recipient, hasn’t yet endorsed, status is Submitted/Under Review
  const notYetEndorsed = !(data.through_endorsements || [])
                          .some(e => e.user_id === me);
  if (data.current_user_is_recipient
      && notYetEndorsed
      && ['Submitted','Under Review'].includes(m.status)) {
    btns.innerHTML = `
      <button data-action="endorse" class="btn btn-success me-2">✅ Endorse</button>
      <button data-action="return"  class="btn btn-warning me-2">✏️ Return</button>
	    <button data-action="escalate"class="btn btn-info me-2">❓ Consult</button>
      <button data-action="reject"  class="btn btn-danger">❌ Reject</button>`;
    wire();
    return;
  }

  // 3) Final approver (to_user) and not yet finalized
  if (data.current_user_is_approver && m.status !== 'Finalized') {
    btns.innerHTML = `
      <button data-action="approve"           class="btn btn-primary me-2">✅ Approve</button>
      <button data-action="seek_clarification"class="btn btn-info me-2">❓ Seek Clarification</button>
	   </button><button data-action="return"  class="btn btn-warning me-2">✏️ Return</button>
    
	  
      <button data-action="reject"            class="btn btn-danger">❌ Reject</button>`;
	  
    wire();
    return;
  }

if ((data.memo_movements || []).some(mv =>
        mv.to_user_id === me &&
        mv.action.toLowerCase().startsWith('forwarded')
      )) {
    btns.innerHTML = `
      <button data-action="instruction" class="btn btn-info me-2">🛠️ Instruction</button>
      <button data-action="forward"     class="btn btn-success">➡️ Forward Again</button>`;
    wire();
    return;
  }

  // 5) Otherwise (still in workflow, not approved) allow “Consult”
  btns.innerHTML = `
    <button data-action="escalate" class="btn-chat">
      <i class="bi bi-chat-dots-fill"></i> Consult
    </button><button data-action="return"  class="btn btn-warning me-2">✏️ Return</button>`;
	
  wire();
  
  
  
  
  
  

  function wire() {
    btns.querySelectorAll('button[data-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        handleAction(btn.getAttribute('data-action'));
      });
    });
  }
}








function openClarificationModal() {
  new bootstrap.Modal(
    document.getElementById('clarificationModal')
  ).show();
}




// 1) Called from handleAction('escalate')
function openEscalateModal() {
  // Fetch list of possible users (adjust URL to your actual endpoint)
  $.getJSON('get_all_users.php', function(users) {
    const $sel = $('#escalationTarget').empty();
    
    // Optionally, skip the current user:
    const me = window._lastMemoData.current_user_id;
    users.forEach(u => {
      if (u.id === me) return;
      $sel.append(
        `<option value="${u.id}">${u.full_name} (${u.position || '—'})</option>`
      );
    });

    // Show the modal
    bootstrap.Modal.getOrCreateInstance(
      document.getElementById('escalateModal')
    ).show();
  })
  .fail(() => alert('Failed to load users.'));
}

// 2) Wire your “Consult” button to open it
function handleAction(action) {
  switch (action) {
    // …
    case 'escalate':
      openEscalateModal();
      break;
    // …
  }
}

// 3) On “Confirm” in the modal


  // 2) your helper (adapted from clarification version)
  function initializeMentions(users) {
    const tribute = new Tribute({
      trigger: '@',
      lookup: 'key',
      fillAttr: 'key',
      menuItemTemplate: item => item.string,
      values: users.map(u => ({ key: u.full_name, id: u.id })),
      noMatchTemplate: () => 'No match',
      selectTemplate: () => ''           // we clear the @text once chosen
    });
    const input  = document.getElementById('escalationComment');
    const badges = document.getElementById('mentionedBadges');
    tribute.attach(input);

    input.addEventListener('tribute-replaced', e => {
      const name = e.detail.item.original.key;
      const user = users.find(u => u.full_name === name);
      if (user && !escalationTargets.includes(user.id)) {
        escalationTargets.push(user.id);
      }
      // re-render badges
      badges.innerHTML = escalationTargets.map(id => {
        const u = users.find(x => x.id === id);
        return `
          <span class="badge bg-secondary me-1">
            @${u.full_name}
            <button type="button"
                    class="btn-close btn-close-white btn-sm ms-1 remove-mention"
                    data-user-id="${id}"></button>
          </span>`;
      }).join('');
      input.value = '';  // wipe the textarea for next @mention
    });

    badges.addEventListener('click', e => {
      if (e.target.matches('.remove-mention')) {
        const id = +e.target.dataset.userId;
        escalationTargets = escalationTargets.filter(x => x !== id);
        e.target.closest('.badge').remove();
      }
    });
  }

  // 3) submitEscalation now uses target_ids array:
  function submitEscalation() {
    const comment = document.getElementById('escalationComment').value.trim();
    if (!comment) return alert('Please enter a comment.');
    if (escalationTargets.length === 0)
      return alert('Please @-mention at least one user.');

    fetch('memo_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:     'escalate',
        memo_id:    window._lastMemoData.memo.id,
        comment:    comment,
        target_ids: escalationTargets
      })
    })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        bootstrap.Modal.getInstance(
          document.getElementById('escalateModal')
        ).hide();
        location.reload();
      } else {
        alert(res.message || 'Failed to consult.');
      }
    })
    .catch(() => alert('Server error.'));
  }





// Shows the “Seek Clarification” modal
function openClarificationModal() {
  new bootstrap.Modal(
    document.getElementById('clarificationModal')
  ).show();
}

// Shows the “Respond to Clarification” modal
function openClarificationResponseModal() {
  const data = window._lastMemoData;
  const me   = data.current_user_id;
  const clar = (data.memo_clarifications || [])
    .find(c => c.user_id === me && c.status === 'Pending');
  if (!clar) return alert('No pending clarification found.');

  $('#clarificationId').val(clar.id);
  $('#clarificationResponseMessage').val('');
  new bootstrap.Modal(document.getElementById('clarificationResponseModal')).show();
}



// Shows the “Consult/Escalate” modal
function openEscalateModal() {
  $.getJSON('get_users_list.php', function(data) {
    if (data.status !== 'success') {
      return alert('Failed to load users.');
    }
    const users = data.users;           // ← grab the array
    const me    = window._lastMemoData.current_user_id;
    const $sel  = $('#escalationTarget').empty();

    users.forEach(u => {
      if (u.id === me) return;          // skip self
      $sel.append(
        `<option value="${u.id}">${u.full_name}</option>`
      );
    });

    bootstrap.Modal
      .getOrCreateInstance(document.getElementById('escalateModal'))
      .show();
  })
  .fail(() => alert('Server error loading users.'));
}


// Shows the “Forward” modal
function openForwardModal() {
  new bootstrap.Modal(
    document.getElementById('forwardModal')
  ).show();
}


function openInstructionModal() {
  bootstrap.Modal
    .getOrCreateInstance(document.getElementById('instructionModal'))
    .show();
}




function handleAction(action) {
  switch (action) {
    // Seek clarification
    case 'seek_clarification':
      openClarificationModal();
      break;

    // Forward memo
    case 'forward':
      openForwardModal();
      break;
	  
	  case 'instruction':
      // instead of falling into openActionModal:
      openInstructionModal();
      break;

    // Consult / escalate
    case 'escalate':
      openEscalateModal();
      break;

    // Respond to a clarification request
    case 'respond_clarification':
      openClarificationResponseModal();
      break;

    // All of these use the generic “Action” modal
    case 'endorse':
    case 'approve':
    case 'reject':
    case 'return':
    case 'instruction':
      openActionModal(action);
      break;

    default:
      console.error(`Unhandled action: ${action}`);
  }
}




function openActionModal(type) {
  const labels = {
    endorse:'Endorsement', approve:'Approval',
    return:'Return Reason', reject:'Rejection Reason',
    escalate:'Escalation Reason', seek_clarification:'Clarification Details'
  };
  document.getElementById('actionLabel').textContent = labels[type]||'Comment';
  document.getElementById('actionType').value = type;
  document.getElementById('actionComment').value = '';
  new bootstrap.Modal(document.getElementById('actionModal')).show();
}





async function performMemoAction(action, comment, target_ids = null) {
  showLoading();
  try {
    console.log('→ memo_action payload:', { memo_id, action, comment, target_ids });

    const resp = await fetch('memo_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ memo_id, action, comment, target_ids })
    });

    // Grab raw text so we can inspect HTML, PHP errors, etc.
    const text = await resp.text();
    console.log('← HTTP', resp.status, text);

    if (!resp.ok) {
      // non-2xx response
      throw new Error(`HTTP ${resp.status}\n${ text.slice(0,200) }…`);
    }

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error(`Invalid JSON from server:\n${ text.slice(0,200) }…`);
    }

    console.log('← parsed JSON:', data);
    hideLoading();

    if (data.status === 'success') {
      await Swal.fire('Success', data.message, 'success');
      location.reload();
    } else {
      Swal.fire('Error', data.message, 'error');
    }
  }
  catch (err) {
    hideLoading();
    console.error('⚠️ performMemoAction error:', err);
    Swal.fire('Error', err.message, 'error');
  }
}




async function exportMemoPDF() {
  const memoData = {...window.currentMemoData};
  await enrichData(memoData);

  // Transform finals for PDF preview (remove action text)
  if (memoData.finals && Array.isArray(memoData.finals)) {
    memoData.finals = memoData.finals.map(f => ({
      ...f,
      action: '',  // blank so PDF doesn't render "Approved/Rejected"
    }));
  }

  try {
    const res = await fetch('workflow_memo_export.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(memoData)
    });

    const ctype = res.headers.get('content-type') || '';
    if (!res.ok || ctype.includes('application/json')) {
      let msg = `Export failed (HTTP ${res.status}).`;
      try { const j = await res.json(); if (j && j.message) msg = j.message; } catch {}
      throw new Error(msg);
    }

    const blob = await res.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `memo_${memoData.memo.memo_id}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (err) {
    showToast(err.message || 'Failed to export memo','error');
  }
}




async function exportLetterPDF() {
  const data   = window.currentMemoData;
  const memo   = data.memo;
  const letter = data.outgoing_letter || {};

  // nothing to export?
  if (!letter.letter_ref_no) {
    return showToast('No draft letter to export.','warning');
  }

  const fromPosDefault  = 'DIRECTOR GENERAL, PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY';
  const fromAddrDefault = 'P/BAG 383, LILONGWE 3, MALAWI';
  const fromPos  = letter.from_position  || fromPosDefault;
  const fromAddr = letter.from_address   || fromAddrDefault;

  // **Use the same signature path you set on preview**
  const approverSigPath = data.to_user?.signature_path || '';

  const payload = {
    letterRef:   letter.letter_ref_no,
    memoStatus:  memo.status,
    letterDate:  letter.letter_date,
    headerImage: 'header_letter.png',
    from: {
      position: fromPos,
      address:  fromAddr
    },
    recipients: letter.recipients.map(r => ({
      position: r.position,
      address:  r.address
    })),
    subject:  letter.letter_subject,
    bodyHtml: letter.letter_content,
    approver: {
      name:          data.to_user?.name     || '',
      position:      data.to_user?.position || '',
      signaturePath: approverSigPath
    }
  };

  // debug
  console.log('exportLetterPDF ➔ approverSigPath:', approverSigPath);

  try {
    const res = await fetch('workflow_letter_export.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const blob = await res.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `letter_${letter.letter_ref_no}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (err) {
    console.error('exportLetterPDF error:', err);
    showToast('Failed to export letter','error');
  }
}





// ----------------- Initialization -----------------

document.addEventListener('DOMContentLoaded', () => {
  const memoId = new URLSearchParams(window.location.search).get('memo_id');

  fetch(`get_memo_data.php?memo_id=${memoId}`)
    .then(r=>r.json())
    .then(data => {
		
		 // 1) expose the real current user ID
    window.currentUserId = data.current_user_id;
	
		window._lastMemoData = data;

		

		
		// Show/hide the Export Letter button
      const exportLetterBtn = document.getElementById('exportLetterBtn');
     
      if (data.outgoing_letter && data.outgoing_letter.letter_ref_no) {
        exportLetterBtn.style.display = 'inline-block';
      
      } else {
        exportLetterBtn.style.display = 'none';
      
      }
	  
      if (data.status!=='success') return showToast(data.message,'error');
      window.currentMemoData = data;
      const m = data.memo;
	  
	  

      // Memo header
      document.getElementById('memoTitle').textContent   = m.communication_type||'MEMO';
      document.getElementById('memoRef').textContent     = m.memo_id;
      document.getElementById('memoDate').textContent    = new Date(m.date).toLocaleDateString('en-CA');
      document.getElementById('memoTo').textContent      = data.to_user?.position||'N/A';
      document.getElementById('memoSubject').textContent = m.subject;
      document.getElementById('memoContent').innerHTML   = m.content||'';
      document.getElementById('originatorName').textContent     = data.originator?.name||'N/A';
      document.getElementById('originatorPosition').textContent = data.originator?.position||'N/A';
      const sigImg = document.getElementById('signatureImg');
      sigImg.src = data.originator_signature?`${data.originator_signature}?t=${Date.now()}`:'';

      // Status badge
      const sb = document.getElementById('memoStatusBadge');
      sb.textContent = m.status;
      sb.className   = 'badge bg-'+getStatusColor(m.status);

      // Through / endorsements
      const thruEl = document.getElementById('memoThrough'),
            users  = data.through_users||[],
            ends   = Object.fromEntries((data.through_endorsements||[]).map(e=>[e.user_id,e]));
      if (!users.length) {
        thruEl.innerHTML = '<p class="text-muted">No endorsements.</p>';
      } else {
        thruEl.innerHTML = users.map(u => {
          const e = ends[u.id];
          return e
            ? `<div class="through-comment mb-3">
                 <p><strong>${u.position}</strong></p>
                 <p>${e.comment}</p>
                 <small class="text-muted">Endorsed: ${new Date(e.endorsed_at).toLocaleString()}</small>
                 ${(e.signature_path?`<div><img src="${e.signature_path}?t=${Date.now()}" class="signature-img mt-2"></div>`:'')}
               </div>`
            : `<div class="through-comment mb-3">
                 <p><strong>${u.position}</strong></p>
                 <p class="text-muted">Not yet endorsed.</p>
               </div>`;
        }).join('');
      }

      renderAttachments(data.attachments||[]);
      renderClarifications(data.memo_clarifications||[]);
      renderMovementTrail(data.memo_movements||[]);
      renderDGComments(data.memo_comments||[]);
      renderApprovalTrail(data.memo_clarifications||[], data.memo_movements||[]);
      renderInstructions(data.memo_instructions||[]);
      setupActionButtons(data);

      // Draft letter
      const letter    = data.outgoing_letter||{},
            letterSec = document.getElementById('letterSection'),
            multi     = document.getElementById('letterMultiRow'),
            single    = document.getElementById('letterSingleRow');

      if (letter.letter_ref_no) {
        document.getElementById('letterViewRef').textContent  = letter.letter_ref_no;
        document.getElementById('letterViewDate').textContent= letter.letter_date
          ? new Date(letter.letter_date).toLocaleDateString('en-CA')
          : '';

        // FROM
        const fromPosDefault  = 'DIRECTOR GENERAL, PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY';
        const fromAddrDefault = 'P/BAG 383, LILONGWE 3, MALAWI';
        const fromPos  = letter.from_position||fromPosDefault;
        const fromAddr = (letter.from_address||fromAddrDefault).replace(/\n/g,'<br>');
        const fromLine = `
          <div class="letter-line">
            <span class="label">FROM:</span>
            <span class="content">${fromPos}, ${fromAddr}</span>
          </div>`;

        // TO
        const recips = letter.recipients||[];
        const recipLines = recips.map((r,i)=> {
          const pos  = r.position||'';
          const addr = (r.address||'').replace(/\n/g,'<br>');
          if (i===0) {
            return `
              <div class="letter-line">
                <span class="label">TO:</span>
                <span class="content">${pos}${addr? ' '+addr:''}</span>
              </div>`;
          } else {
            return `
              <div class="letter-line">
                <span class="label"></span>
                <span class="content">${pos}${addr? ', '+addr:''}</span>
              </div>`;
          }
        }).join('');

        if (recips.length>1) {
          multi.style.display  = 'block';
          single.style.display = 'none';
          multi.innerHTML      = fromLine+recipLines;
        } else if (recips.length===1) {
          multi.style.display  = 'none';
          single.style.display = 'block';
          single.innerHTML     = fromLine+recipLines;
        } else {
          multi.style.display = single.style.display = 'none';
        }

        document.getElementById('letterViewSubject').textContent = letter.letter_subject||'';
       // Render letter body
const letterContentEl = document.getElementById('letterViewContent');
letterContentEl.innerHTML = letter.letter_content || '';

// Add a gap and centered “Yours faithfully”
const footer = document.createElement('div');
footer.className = 'text-center mt-4';
footer.textContent = 'Yours faithfully,';
letterContentEl.appendChild(footer);


 const sigEl  = document.getElementById('letterApproverSignature'),
      nameEl = document.getElementById('letterApproverName'),
      posEl  = document.getElementById('letterApproverPos');

// fill in approver info
nameEl.textContent = data.to_user?.name     || '';
posEl.textContent  = data.to_user?.position || '';
sigEl.style.display = 'none';

if (m.status === 'Approved' && data.to_user?.signature_path) {
  sigEl.src = data.to_user.signature_path + '?t=' + Date.now();
  sigEl.style.display = 'block';
}


        letterSec.style.display = 'block';
      } else {
        letterSec.style.display = 'none';
      }

      // Mentions
      fetch('get_users_list.php')
        .then(r=>r.json())
        .then(d=> {
          if (d.status==='success') {
            initializeMentions(d.users);
            initializeForwardMentions(d.users);
			initializeClarificationMentions(d.users);// <-- **new** Clarification widget
          }
        });
    })
    .catch(()=> showToast('Failed to load memo.','error'));

  // Bind action-submit after DOM ready
  const actionBtn = document.getElementById('actionSubmitBtn');
  if (actionBtn) {
    actionBtn.addEventListener('click', ()=>{
      const type = document.getElementById('actionType').value,
            cm   = document.getElementById('actionComment').value.trim();
      if (!cm) return showToast('Please add a comment.','warning');
      performMemoAction(type, cm);
      bootstrap.Modal.getInstance(document.getElementById('actionModal')).hide();
    });
  }
});


// keep track of the current clarification draft
let pendingClarification = { message: '', userIds: [] };

// when user clicks “Seek Clarification”
document.getElementById('clarificationSubmitBtn').addEventListener('click', () => {
  const msg = document.getElementById('clarificationMessage').value.trim();
  // mentionedUsers is your array from initializeMentions
  if (!msg && clarificationUsers.length === 0) {
    return showToast('Please enter a clarification or mention someone.', 'warning');
  }
  pendingClarification = {
    message: msg,
    userIds: clarificationUsers.map(u => u.id),
  };
  // show the confirmation modal
  new bootstrap.Modal(document.getElementById('confirmClarificationModal')).show();
});

// when they confirm “Yes, Send”
document.getElementById('confirmSendClarificationBtn').addEventListener('click', () => {
  const { message, userIds } = pendingClarification;
  // actually POST the clarification
  performMemoAction('seek_clarification', message, userIds);
  // hide the confirm dialog
  bootstrap.Modal.getInstance(document.getElementById('confirmClarificationModal')).hide();
  // and clear the draft so they can’t accidentally resend
  pendingClarification = { message: '', userIds: [] };
});




// Forward submit handler
document.getElementById('forwardSubmitBtn').addEventListener('click', () => {
  const type = document.getElementById('forwardType').value;
  if (!type) {
    return showToast('Please select a forward type.', 'warning');
  }
  if (forwardUsers.length === 0) {
    return showToast('Please mention at least one officer to forward to.', 'warning');
  }

  // hide the modal
  bootstrap.Modal.getInstance(document.getElementById('forwardModal')).hide();

  // Perform the action
  const targetIds = forwardUsers.map(u => u.id);
  performMemoAction('forward', type, targetIds);

  // reset for next time
  forwardUsers = [];
  document.getElementById('forwardMentionBadges').innerHTML = '';
  document.getElementById('forwardType').value = '';
});







</script>



<script>
document.addEventListener('DOMContentLoaded', () => {

  // 1) Load your user list, then init Tribute
  fetch('get_users_list.php')
    .then(r => r.json())
    .then(d => {
      if (d.status !== 'success') throw new Error();
      initInstructionTribute(d.users);
    })
    .catch(console.error);

  function initInstructionTribute(users) {
    const tribute = new Tribute({
      trigger: '@',
      lookup: 'key',      // <-- must match the property in your values items
      fillAttr: 'key',
      menuItemTemplate: item => item.string,
      values: users.map(u => ({ key: u.full_name, id: u.id })),
      selectTemplate: () => ''  // we clear the @text after selection
    });

    const input = document.getElementById('instructionMessage');
    const badgeContainer = document.getElementById('instructionMentionedBadges');
    tribute.attach(input);

    input.addEventListener('tribute-replaced', e => {
      const name = e.detail.item.original.key;
      const user = users.find(u => u.full_name === name);
      if (user && !instructionTargets.includes(user.id)) {
        instructionTargets.push(user.id);
        renderBadges();
      }
      input.value = '';  // clear so you can keep typing
    });

    badgeContainer.addEventListener('click', e => {
      if (e.target.matches('.remove-instr-mention')) {
        const id = +e.target.dataset.userId;
        instructionTargets = instructionTargets.filter(x => x !== id);
        renderBadges();
      }
    });

    function renderBadges() {
      badgeContainer.innerHTML = instructionTargets.map(id => {
        const u = users.find(x => x.id === id);
        return `
          <span class="badge bg-secondary me-1">
            @${u.full_name}
            <button type="button"
                    class="btn-close btn-close-white btn-sm ms-1 remove-instr-mention"
                    data-user-id="${id}"></button>
          </span>`;
      }).join('');
    }
  }

  // 2) Wire up your “🛠️ Instruction” button
  document.querySelector('[data-action="instruction"]')
    .addEventListener('click', () => {
      instructionTargets = [];
      document.getElementById('instructionMessage').value = '';
      document.getElementById('instructionMentionedBadges').innerHTML = '';
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById('instructionModal')
      ).show();
    });

  // 3) Handle the Submit
  document.getElementById('instructionSubmitBtn')
    .addEventListener('click', () => {
      const msg = document.getElementById('instructionMessage').value.trim();
      if (!msg || instructionTargets.length === 0) {
        return alert('Please enter an instruction and mention at least one user.');
      }
      bootstrap.Modal.getInstance(
        document.getElementById('instructionModal')
      ).hide();
      performMemoAction('instruction', msg, instructionTargets);
    });
});

</script>


<script>
  // 1) Ensure instructionTargets exists (step 1 above)
  // 2) Bind the click *after* the element is in the DOM:
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('instructionSubmitBtn');
    if (!btn) {
      console.error('instructionSubmitBtn not found!');
      return;
    }
    btn.addEventListener('click', () => {
      const msg = document.getElementById('instructionMessage').value.trim();
      if (!msg || instructionTargets.length === 0) {
        return alert('Please enter an instruction and mention at least one user.');
      }
      // hide modal
      bootstrap.Modal.getInstance(
        document.getElementById('instructionModal')
      ).hide();
      // call your existing helper:
      performMemoAction('instruction', msg, instructionTargets);
    });
  });
</script>
<?php require_once 'footer.php'; ?>
