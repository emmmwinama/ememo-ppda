<?php
require_once __DIR__ . '/auth.php';

// 1) Non‑officers get an “Access Denied” page and exit
if (empty($_SESSION['is_controlling_officer'])):
?>
<!doctype html>
<html lang="en">
<head>
  <!-- ... head for Access Denied ... -->
</head>
<body>
  <div class="alert alert-warning">🚫 No access to DG Inbox.</div>
  
</body>
</html>
<?php
  exit;
endif;

// 2) Only a controlling officer reaches this point
require_once __DIR__ . '/db.php';
$isOfficer = true;  // by definition here

// **Close PHP** so that the following HTML isn’t parsed as PHP!
?>



<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>External Letters Inbox</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"/>
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <style>
    body { padding-top:1rem; }
    .toast-container { position: fixed; top:1rem; right:1rem; z-index:2000; }
    .table-hover tbody tr { transition: background .2s; }
    .chat-time {
      font-size: 0.65rem;
      color: var(--muted);
      margin-top: 0.25rem;
      text-align: right;
    }
    .chat-thread::-webkit-scrollbar { width: 4px; }
    .chat-thread::-webkit-scrollbar-thumb {
      background: rgba(0,0,0,0.15); border-radius: 2px;
    }
  </style>

</head>
<body>
  <div class="container">
    <h2 class="mb-4">📥 External Letters Inbox</h2>
    <div id="lettersTableContainer" class="text-center">
      <div class="spinner-border text-primary"></div>
    </div>
  </div>
  <div class="toast-container"></div>

  <!-- Read Modal -->
  <div class="modal fade" id="readModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-secondary text-white">
          <h5 class="modal-title">📖 View Letter & History</h5>
          <a id="downloadMerged" class="btn btn-sm btn-outline-light ms-2" href="#" target="_blank">⬇️ Download PDF</a>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- attachments -->
          <div id="readBody" class="mb-4"></div>
          <hr>
          <!-- history -->
          <div class="bg-light p-3 rounded mb-4">
            <strong>📜 Full Letter History:</strong>
            <ul id="trailList" class="list-unstyled small mt-2"></ul>
          </div>
        </div>
        <div class="modal-footer">
          <?php if($isOfficer): ?>
            <button id="openProcess" class="btn btn-primary">Process</button>
			<button id="comment" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#commentModal">Comment</button>
            <button id="openClose"   class="btn btn-danger ms-2">Close Letter</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  

<!-- Comment Modal -->
<!-- Comment & Report Modal -->
<div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="commentModalLabel">Add Comment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label for="commentText" class="form-label">Comment</label>
          <textarea id="commentText" class="form-control" rows="3" required></textarea>
        </div>

        <div class="mb-3">
          <label for="reportFile" class="form-label">Attach File (optional)</label>
          <input type="file" class="form-control" id="reportFile">
		  <input type="hidden" id="modalLetterId" value="">

        </div>

        <div class="text-end">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button id="submitComment" type="button" class="btn btn-primary">Send</button>
        </div>
      </div>
    </div>
  </div>
</div>

</div>


<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="toastMsg" class="toast align-items-center text-white bg-primary border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastText">Placeholder message</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>



  <!-- Process Modal -->
  <div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <form id="processForm" class="p-3">
          <div class="mb-3"><h5>🗂️ Process Letter</h5></div>
          <input type="hidden" id="processId" name="id">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Reference</label>
              <input type="text" id="processRef" class="form-control" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Date Received</label>
              <input type="text" id="processDate" class="form-control" readonly>
            </div>
            <div class="col-12">
              <label class="form-label">Title</label>
              <input type="text" id="processTitle" class="form-control" readonly>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea id="processDesc" class="form-control" rows="2" readonly></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Assign To</label>
              <select id="assigneeSelect" name="assignees[]" multiple></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Due Date</label>
              <input type="text" id="processDue" name="due_date" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Instruction</label>
              <textarea id="processComment" name="instruction" class="form-control" rows="4" required></textarea>
            </div>
          </div>
          <div class="mt-4 text-end">
            <button type="submit" id="processBtn" class="btn btn-success">
              <span id="processSpinner" class="spinner-border spinner-border-sm d-none"></span>
              <span id="processText">Save</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Close Modal -->
  <div class="modal fade" id="closeModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="closeForm" class="p-3" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title">🛑 Close Letter</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <input type="hidden" id="closeId" name="id">
          <div class="mb-3">
            <label class="form-label">Final Comment (optional)</label>
            <textarea name="comment" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Attach File (optional)</label>
            <input type="file" name="close_file" class="form-control">
          </div>
          <div class="text-end">
            <button type="submit" id="closeBtn" class="btn btn-danger">
              <span id="closeSpinner" class="spinner-border spinner-border-sm d-none"></span>
              <span id="closeText">Close Letter</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Choices.js, Flatpickr & Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  
  <!-- Tagify CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" />
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>




<script>
// Toast helper
function toast(message, type = 'primary') {
  const toastEl = $('#toastMsg');
  toastEl.removeClass().addClass(`toast align-items-center text-white bg-${type} border-0`);
  $('#toastText').text(message);
  const bsToast = new bootstrap.Toast(toastEl[0]);
  bsToast.show();
}

$(document).ready(function () {
  // ✅ Store the letter ID in a hidden input when opening the comment modal

  // ✅ Submit Comment Handler
$('#submitComment').on('click', function () {
  const comment = $('#commentText').val().trim();
  const file = $('#reportFile')[0]?.files[0];
  const letterId = $('#modalLetterId').val();

  if (!letterId) {
    toast("Missing letter ID. Please open the letter first.", "danger");
    return;
  }

  if (!comment) {
    toast("Please enter a comment before submitting.", "warning");
    return;
  }

  const formData = new FormData();
  formData.append('letter_id', letterId);
  formData.append('comment', comment);
  formData.append('report', ''); // required by backend
  formData.append('report_file', file || '');

  $.ajax({
    url: 'submit_comment.php',
    type: 'POST',
    data: formData,
    contentType: false,
    processData: false,
    success: function (res) {
      if (res.status === 'success') {
        toast("Comment submitted successfully.", "success");

        // Clear inputs
        $('#commentText').val('');
        $('#reportFile').val('');
        $('#modalLetterId').val('');

        // Hide modal
        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('commentModal'));
        if (modalInstance) modalInstance.hide();

        // ✅ Reload current letter's history & attachments
        onReadClick(letterId);
      } else {
        toast("Error: " + (res.message || "Unknown issue"), "danger");
      }
    },
    error: function (xhr) {
      console.error("AJAX Error:", xhr.responseText);
      toast("Failed to submit comment. Please try again.", "danger");
    }
  });
});

});
</script>


  
  <script>
(() => {
  let letters = [], currentLetter = null;

  // Helpers
  const isOfficer      = <?= $isOfficer?'true':'false' ?>;
  const toastContainer = document.querySelector('.toast-container');
  function toast(msg, type='info'){
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 mb-2`;
    el.innerHTML = `<div class="d-flex">
      <div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"
              data-bs-dismiss="toast"></button>
    </div>`;
    toastContainer.append(el);
    new bootstrap.Toast(el,{delay:3000}).show();
  }
  async function getJson(url){
    let r = await fetch(url);
    return r.json();
  }

  // 1) Load inbox & compute pending flag
// 1) Load inbox + cache full history + pending flag
async function loadLetters(){
  const container = document.getElementById('lettersTableContainer');
  container.innerHTML = '<div class="spinner-border text-primary"></div>';

  const res = await getJson('external_letters_list.php');
  if(!res.success){
    container.innerHTML = '';
    return toast('Failed to load','danger');
  }
  letters = res.data;

  // fetch & cache history for each letter, mark pending
  await Promise.all(letters.map(async (l,i) => {
    const h = await getJson(`external_letter_history.php?letter_id=${l.id}`);
    const hist = h.success ? h.history : [];
    letters[i].history = hist;
    const c = hist.filter(e=>e.type==='comment').length;
    const a = hist.filter(e=>e.type==='action').length;
    letters[i].pending = (c>0 && a===0);
  }));

  renderTable();
}


// 2) Render table + auto‐show discussion bubbles under each row
function renderTable() {
  const rowsHtml = letters.map((l, i) => {
    // 1) Main row
    const mainRow = `
      <tr${l.pending ? ' class="table-warning"' : ''}>
        <td>${i + 1}</td>
        <td>${l.reference_number}</td>
        <td>${l.title}</td>
        <td>${l.received_from}</td>
        <td>${l.received_date}</td>
        <td><span class="badge bg-${l.badge_class}">${l.status_label}</span></td>
        <td>${l.pending
          ? '<span class="badge bg-warning text-dark">Pending</span>'
          : ''}</td>
        <td>${isOfficer
          ? `<button class="btn btn-sm btn-outline-info btn-read" data-id="${l.id}">Read</button>`
          : ''}</td>
      </tr>`;

    // 2) Build chat‐style discussion bubbles (including delegation events)
    const chatBubbles = (l.history || [])
      .filter(e =>
        e.type === 'delegation'
        || (e.text && e.text.trim())
        || e.report_file_path
      )
      .map(e => {
        const isInst   = e.type === 'instruction';
        const isDel    = e.type === 'delegation';
        const side     = isInst ? 'chat-bubble-in' : 'chat-bubble-out';
        const icon     = isInst
                       ? '🗂️'
                       : (isDel
                          ? '🔀'
                          : (e.type === 'comment' ? '💬' : '📎'));
        let msg;
        if (isDel) {
          msg = `${icon} Delegated to <strong>${e.to_position}</strong>`;
        } else {
          msg = `${icon} ${e.text || ''}`;
          if (e.report_file_path) {
            msg += `<br><a href="${e.report_file_path}" target="_blank">Download file</a>`;
          }
        }

        // show “from → to” for delegations, only “from” otherwise
        const header = isDel
                     ? `${e.by_position} → ${e.to_position}`
                     : e.by_position;

        const time = new Date(e.when_happened)
                       .toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });

        return `
          <div class="chat-bubble ${side}">
            <div class="chat-sender small text-muted mb-1">${header}</div>
            <div class="chat-content">${msg}</div>
            <div class="chat-time">${time}</div>
          </div>`;
      })
      .join('');

    const discussionRow = `
      <tr>
        <td colspan="8" class="bg-transparent">
          <div class="chat-thread">
            ${chatBubbles}
          </div>
        </td>
      </tr>`;

    return mainRow + discussionRow;
  }).join('');

  document.getElementById('lettersTableContainer').innerHTML = `
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Ref</th>
          <th>Title</th>
          <th>From</th>
          <th>Date</th>
          <th>Status</th>
          <th>Pending</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>${rowsHtml}</tbody>
    </table>`;
}






  // 3) Show letter details & history
  async function onReadClick(id){
    if(!id) return toast('Invalid ID','danger');
    currentLetter = letters.find(l=>l.id==id);
	 $('#modalLetterId').val(currentLetter.id); // ✅ Set it for comment modal

    document.getElementById('downloadMerged').href =
      `external_letter_render.php?letter_id=${id}`;
	 

    // attachments
    const f = await getJson(`external_letter_files.php?letter_id=${id}`);
    document.getElementById('readBody').innerHTML =
      (f.success?f.data:[]).map(x=>{
        const ext=x.file_name.split('.').pop().toLowerCase();
        if(ext==='pdf') return `<embed src="${x.file_path}" type="application/pdf" width="100%" height="500px">`;
        if(['jpg','jpeg','png','gif','bmp'].includes(ext)) return `<img src="${x.file_path}" class="img-fluid mb-3">`;
        return `📎 <a href="${x.file_path}" download>${x.file_name}</a>`;
      }).join('')||'<p class="text-muted">No attachments.</p>';

    // history list
    const h = await getJson(`external_letter_history.php?letter_id=${id}`);
    const hist = h.success? h.history : [];
    const listEl = document.getElementById('trailList');
    if(!hist.length){
      listEl.innerHTML = '<li class="text-muted">No history.</li>';
    } else {
      let html = '';
      const instr = hist.find(e=>e.type==='instruction');
      const underlineTo = instr?.by_position||'';
      if(instr){
        const recps = hist.filter(e=>e.type==='delegation').map(d=>d.to_position).join(', ');
        html += `<li class="mb-3"><u>${recps}</u><br>${instr.text}<br>
                 <small class="text-muted">${instr.when_happened}</small><br>${instr.by_position}</li>`;
      }
      hist.filter(e=>['comment','action'].includes(e.type)&&(e.text||e.report_file_path)).forEach(e=>{
        const icon = e.type==='comment'?'💬':'📎';
        const fileLink = e.report_file_path
          ? `<br><a href="${e.report_file_path}" target="_blank">Download report</a>`
          : '';
        html += `<li class="mb-3">${icon}<br><u>${underlineTo}</u><br>
                 ${e.text||''}${fileLink}<br>
                 <small class="text-muted">${e.when_happened}</small><br>
                 ${e.by_position}</li>`;
      });
      listEl.innerHTML = html;
    }

    new bootstrap.Modal(document.getElementById('readModal')).show();
  }

  // 4) Initialize Process modal (Choices.js + flatpickr)
  let choices;
  async function initProcessModal(){
    // instantiate
    choices = new Choices('#assigneeSelect', {
      removeItemButton: true,
      placeholderValue: 'Type to search…',
      shouldSort: false,
      searchEnabled: false
    });

    // fetch all users once
    const allResp = await getJson('get_users_list.php?all=1');
    let allUsers = [];
    if(allResp.status==='success'){
      allUsers = allResp.users.map(u=>({
        value: u.id, label: u.full_name
      }));
    }
    // clear
    choices.setChoices([], 'value','label', true);

    // wired search
    const container   = document.getElementById('assigneeSelect').closest('.choices');
    const searchInput = container.querySelector('input.choices__input--cloned');
    searchInput.addEventListener('input', e=>{
      const q = e.target.value.trim().toLowerCase();
      if(q.length < 2){
        choices.clearChoices();
        return;
      }
      const matches = allUsers.filter(u=>u.label.toLowerCase().includes(q));
      choices.clearChoices();
      choices.setChoices(matches,'value','label',true);
    });

    // datepicker
    flatpickr('#processDue',{ dateFormat:'Y-m-d' });
  }

  // 5) Wire up form submissions & buttons
  document.addEventListener('DOMContentLoaded', () => {
    // load and setup
    loadLetters();
    initProcessModal();

    // delegate Read clicks
    document.body.addEventListener('click', ev => {
      const btn = ev.target.closest('.btn-read');
      if(btn) onReadClick(btn.dataset.id);
    });

    // open Process
    document.getElementById('openProcess')?.addEventListener('click', () => {
      if(!currentLetter) return;
      document.getElementById('processId').value   = currentLetter.id;
      document.getElementById('processRef').value  = currentLetter.reference_number;
      document.getElementById('processDate').value = currentLetter.received_date;
      document.getElementById('processTitle').value= currentLetter.title;
      document.getElementById('processDesc').value = currentLetter.description||'';
      choices.clearStore();
      document.getElementById('processComment').value = '';
      document.getElementById('processDue')._flatpickr.clear();
      bootstrap.Modal.getInstance(document.getElementById('readModal')).hide();
      new bootstrap.Modal(document.getElementById('processModal')).show();
    });

    // submit Process
    document.getElementById('processForm').addEventListener('submit', async ev => {
      ev.preventDefault();
      const btn = document.getElementById('processBtn'),
            spn = document.getElementById('processSpinner'),
            txt = document.getElementById('processText'),
            frm = new FormData(ev.target);
      for(let v of choices.getValue(true)) frm.append('assignees[]', v);
      spn.classList.remove('d-none'); btn.disabled = true; txt.textContent = 'Saving…';
      const res = await fetch('external_letter_process.php',{
        method:'POST', body: frm
      }).then(r=>r.json()).catch(()=>({success:false}));
      spn.classList.add('d-none'); btn.disabled = false; txt.textContent = 'Save';
      if(res.success){
        toast('Delegated','success');
        bootstrap.Modal.getInstance(document.getElementById('processModal')).hide();
        loadLetters();
      } else toast(res.message||'Failed','danger');
    });

    // open Close
    document.getElementById('openClose')?.addEventListener('click', () => {
      document.getElementById('closeId').value = currentLetter.id;
      bootstrap.Modal.getInstance(document.getElementById('readModal')).hide();
      new bootstrap.Modal(document.getElementById('closeModal')).show();
    });

    // submit Close
    document.getElementById('closeForm').addEventListener('submit', async ev => {
      ev.preventDefault();
      const btn = document.getElementById('closeBtn'),
            spn = document.getElementById('closeSpinner'),
            txt = document.getElementById('closeText'),
            frm = new FormData(ev.target);
      spn.classList.remove('d-none'); btn.disabled = true; txt.textContent = 'Closing…';
      const res = await fetch('external_letter_close.php',{
        method:'POST', body: frm
      }).then(r=>r.json()).catch(()=>({success:false}));
      spn.classList.add('d-none'); btn.disabled = false; txt.textContent = 'Close Letter';
      if(res.success){
        toast('Closed','success');
        bootstrap.Modal.getInstance(document.getElementById('closeModal')).hide();
        loadLetters();
      } else toast(res.message||'Failed','danger');
    });
  });
})();
</script>

</body>
</html>
