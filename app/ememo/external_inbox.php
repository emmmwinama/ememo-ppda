<?php
require_once __DIR__ . '/auth.php';

// Non-controlling-officers get an inline "no access" panel.
if (empty($_SESSION['is_controlling_officer'])):
?>
<div class="f-state is-error">
  <i class="bi bi-shield-lock"></i>
  <p>You don't have access to the DG In-Tray.</p>
</div>
<?php
  return;
endif;

require_once __DIR__ . '/db.php';
$isOfficer = true;
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" />
<style>
  .ei-toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 2000; }
  #external-inbox .choices { margin: 0; }
  #external-inbox .choices__inner { border-radius: var(--radius-md); border-color: var(--border); min-height: 40px; background: var(--surface); }
  .chat-time { font-size: .65rem; color: var(--muted); margin-top: .25rem; text-align: right; }
  .chat-thread::-webkit-scrollbar { width: 4px; }
  .chat-thread::-webkit-scrollbar-thumb { background: rgba(0,0,0,.15); border-radius: 2px; }
</style>

<div id="external-inbox">
  <div class="f-head">
    <h1 class="f-title">DG In-Tray</h1>
    <p class="f-subtitle">Incoming external letters awaiting delegation or closure</p>
  </div>

  <div id="lettersTableContainer">
    <div class="f-state"><div class="spinner-border spinner-border-sm" role="status"></div><p class="mt-2">Loading in-tray…</p></div>
  </div>
</div>

<div class="ei-toast-container"></div>

<!-- Read Modal -->
<div class="modal fade" id="readModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>View letter &amp; history</h5>
        <a id="downloadMerged" class="btn btn-sm btn-outline-success ms-3" href="#" target="_blank"><i class="bi bi-download me-1"></i>Download PDF</a>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="readBody" class="mb-4"></div>
        <hr>
        <div class="f-panel p-3 mb-2">
          <strong><i class="bi bi-clock-history me-1"></i>Full letter history</strong>
          <ul id="trailList" class="list-unstyled small mt-2 mb-0"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <?php if ($isOfficer): ?>
          <button id="openProcess" class="btn btn-success">Process</button>
          <button id="comment" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#commentModal">Comment</button>
          <button id="openClose" class="btn btn-danger">Close letter</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Comment Modal -->
<div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add comment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="commentText" class="form-label">Comment</label>
          <textarea id="commentText" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label for="reportFile" class="form-label">Attach file <span class="text-muted fw-normal">(optional)</span></label>
          <input type="file" class="form-control" id="reportFile">
          <input type="hidden" id="modalLetterId" value="">
        </div>
        <div class="text-end">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button id="submitComment" type="button" class="btn btn-success">Send</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="ei-toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="toastMsg" class="toast align-items-center text-white bg-primary border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastText"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Process Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="processForm" class="p-3">
        <div class="mb-3"><h5><i class="bi bi-diagram-3 me-2"></i>Process letter</h5></div>
        <input type="hidden" id="processId" name="id">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Reference</label><input type="text" id="processRef" class="form-control" readonly></div>
          <div class="col-md-6"><label class="form-label">Date received</label><input type="text" id="processDate" class="form-control" readonly></div>
          <div class="col-12"><label class="form-label">Title</label><input type="text" id="processTitle" class="form-control" readonly></div>
          <div class="col-12"><label class="form-label">Description</label><textarea id="processDesc" class="form-control" rows="2" readonly></textarea></div>
          <div class="col-md-6"><label class="form-label">Assign to</label><select id="assigneeSelect" name="assignees[]" multiple></select></div>
          <div class="col-md-6"><label class="form-label">Due date</label><input type="text" id="processDue" name="due_date" class="form-control"></div>
          <div class="col-12"><label class="form-label">Instruction</label><textarea id="processComment" name="instruction" class="form-control" rows="4" required></textarea></div>
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
          <h5 class="modal-title"><i class="bi bi-folder-check me-2"></i>Close letter</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <input type="hidden" id="closeId" name="id">
        <div class="mb-3"><label class="form-label">Final comment <span class="text-muted fw-normal">(optional)</span></label><textarea name="comment" class="form-control" rows="3"></textarea></div>
        <div class="mb-3"><label class="form-label">Attach file <span class="text-muted fw-normal">(optional)</span></label><input type="file" name="close_file" class="form-control"></div>
        <div class="text-end">
          <button type="submit" id="closeBtn" class="btn btn-danger">
            <span id="closeSpinner" class="spinner-border spinner-border-sm d-none"></span>
            <span id="closeText">Close letter</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Toast helper (jQuery)
function toast(message, type = 'primary') {
  const toastEl = $('#toastMsg');
  toastEl.removeClass().addClass(`toast align-items-center text-white bg-${type} border-0`);
  $('#toastText').text(message);
  new bootstrap.Toast(toastEl[0]).show();
}

$(document).ready(function () {
  $('#submitComment').on('click', function () {
    const comment = $('#commentText').val().trim();
    const file = $('#reportFile')[0]?.files[0];
    const letterId = $('#modalLetterId').val();

    if (!letterId) { toast("Missing letter ID. Please open the letter first.", "danger"); return; }
    if (!comment)  { toast("Please enter a comment before submitting.", "warning"); return; }

    const formData = new FormData();
    formData.append('letter_id', letterId);
    formData.append('comment', comment);
    formData.append('report', '');
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
          $('#commentText').val('');
          $('#reportFile').val('');
          $('#modalLetterId').val('');
          const modalInstance = bootstrap.Modal.getInstance(document.getElementById('commentModal'));
          if (modalInstance) modalInstance.hide();
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

  const isOfficer      = <?= $isOfficer ? 'true' : 'false' ?>;
  const toastContainer = document.querySelector('.ei-toast-container');
  function toast(msg, type = 'secondary') {
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 mb-2`;
    el.innerHTML = `<div class="d-flex">
      <div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
    toastContainer.append(el);
    new bootstrap.Toast(el, { delay: 3000 }).show();
  }
  async function getJson(url) { return (await fetch(url)).json(); }

  async function loadLetters() {
    const container = document.getElementById('lettersTableContainer');
    container.innerHTML = `<div class="f-state"><div class="spinner-border spinner-border-sm" role="status"></div><p class="mt-2">Loading…</p></div>`;

    const res = await getJson('external_letters_list.php');
    if (!res.success) {
      container.innerHTML = `<div class="f-state is-error"><i class="bi bi-exclamation-octagon"></i><p>Failed to load in-tray.</p></div>`;
      return;
    }
    letters = res.data;

    await Promise.all(letters.map(async (l, i) => {
      const h = await getJson(`external_letter_history.php?letter_id=${l.id}`);
      const hist = h.success ? h.history : [];
      letters[i].history = hist;
      const c = hist.filter(e => e.type === 'comment').length;
      const a = hist.filter(e => e.type === 'action').length;
      letters[i].pending = (c > 0 && a === 0);
    }));

    renderTable();
  }

  function renderTable() {
    if (!letters.length) {
      document.getElementById('lettersTableContainer').innerHTML =
        `<div class="f-state"><i class="bi bi-inbox"></i><p>Nothing in the in-tray.</p></div>`;
      return;
    }

    const rowsHtml = letters.map((l, i) => {
      const mainRow = `
        <tr${l.pending ? ' class="is-pending"' : ''}>
          <td class="text-muted">${i + 1}</td>
          <td class="font-monospace">${l.reference_number}</td>
          <td class="fw-semibold">${l.title}</td>
          <td class="text-muted">${l.received_from}</td>
          <td class="text-muted">${l.received_date}</td>
          <td><span class="badge bg-${l.badge_class}">${l.status_label}</span></td>
          <td>${l.pending ? '<span class="pill t-amber">Pending</span>' : ''}</td>
          <td>${isOfficer ? `<button class="btn btn-sm btn-outline-success btn-read" data-id="${l.id}"><i class="bi bi-eye me-1"></i>Read</button>` : ''}</td>
        </tr>`;

      const chatBubbles = (l.history || [])
        .filter(e => e.type === 'delegation' || (e.text && e.text.trim()) || e.report_file_path)
        .map(e => {
          const isInst = e.type === 'instruction';
          const isDel  = e.type === 'delegation';
          const side   = isInst ? 'chat-bubble-in' : 'chat-bubble-out';
          const icon   = isInst ? '<i class="bi bi-diagram-3"></i>'
                       : (isDel ? '<i class="bi bi-arrow-left-right"></i>'
                       : (e.type === 'comment' ? '<i class="bi bi-chat-left-text"></i>' : '<i class="bi bi-paperclip"></i>'));
          let msg;
          if (isDel) {
            msg = `${icon} Delegated to <strong>${e.to_position}</strong>`;
          } else {
            msg = `${icon} ${e.text || ''}`;
            if (e.report_file_path) msg += `<br><a href="${e.report_file_path}" target="_blank">Download file</a>`;
          }
          const header = isDel ? `${e.by_position} → ${e.to_position}` : e.by_position;
          const time = new Date(e.when_happened).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          return `
            <div class="chat-bubble ${side}">
              <div class="chat-sender small text-muted mb-1">${header}</div>
              <div class="chat-content">${msg}</div>
              <div class="chat-time">${time}</div>
            </div>`;
        }).join('');

      const discussionRow = `
        <tr class="chat-row">
          <td colspan="8" class="bg-transparent">
            <div class="chat-thread">${chatBubbles}</div>
          </td>
        </tr>`;

      return mainRow + discussionRow;
    }).join('');

    document.getElementById('lettersTableContainer').innerHTML = `
      <div class="f-panel table-responsive">
        <table class="table table-borderless f-table align-middle mb-0">
          <thead><tr>
            <th>#</th><th>Ref</th><th>Title</th><th>From</th><th>Date</th><th>Status</th><th>Pending</th><th>Actions</th>
          </tr></thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>`;
  }

  async function onReadClick(id) {
    if (!id) return toast('Invalid ID', 'danger');
    currentLetter = letters.find(l => l.id == id);
    $('#modalLetterId').val(currentLetter.id);

    document.getElementById('downloadMerged').href = `external_letter_render.php?letter_id=${id}`;

    const f = await getJson(`external_letter_files.php?letter_id=${id}`);
    document.getElementById('readBody').innerHTML =
      (f.success ? f.data : []).map(x => {
        const ext = x.file_name.split('.').pop().toLowerCase();
        if (ext === 'pdf') return `<embed src="${x.file_path}" type="application/pdf" width="100%" height="500px">`;
        if (['jpg','jpeg','png','gif','bmp'].includes(ext)) return `<img src="${x.file_path}" class="img-fluid mb-3">`;
        return `<a href="${x.file_path}" download><i class="bi bi-paperclip me-1"></i>${x.file_name}</a>`;
      }).join('') || '<p class="text-muted">No attachments.</p>';

    const h = await getJson(`external_letter_history.php?letter_id=${id}`);
    const hist = h.success ? h.history : [];
    const listEl = document.getElementById('trailList');
    if (!hist.length) {
      listEl.innerHTML = '<li class="text-muted">No history.</li>';
    } else {
      let html = '';
      const instr = hist.find(e => e.type === 'instruction');
      const underlineTo = instr?.by_position || '';
      if (instr) {
        const recps = hist.filter(e => e.type === 'delegation').map(d => d.to_position).join(', ');
        html += `<li class="mb-3"><u>${recps}</u><br>${instr.text}<br>
                 <small class="text-muted">${instr.when_happened}</small><br>${instr.by_position}</li>`;
      }
      hist.filter(e => ['comment','action'].includes(e.type) && (e.text || e.report_file_path)).forEach(e => {
        const icon = e.type === 'comment' ? '<i class="bi bi-chat-left-text"></i>' : '<i class="bi bi-paperclip"></i>';
        const fileLink = e.report_file_path ? `<br><a href="${e.report_file_path}" target="_blank">Download report</a>` : '';
        html += `<li class="mb-3">${icon}<br><u>${underlineTo}</u><br>${e.text || ''}${fileLink}<br>
                 <small class="text-muted">${e.when_happened}</small><br>${e.by_position}</li>`;
      });
      listEl.innerHTML = html;
    }

    new bootstrap.Modal(document.getElementById('readModal')).show();
  }
  window.onReadClick = onReadClick;

  let choices;
  async function initProcessModal() {
    choices = new Choices('#assigneeSelect', {
      removeItemButton: true, placeholderValue: 'Type to search…', shouldSort: false, searchEnabled: false
    });

    const allResp = await getJson('get_users_list.php?all=1');
    let allUsers = [];
    if (allResp.status === 'success') {
      allUsers = allResp.users.map(u => ({ value: u.id, label: u.full_name }));
    }
    choices.setChoices([], 'value', 'label', true);

    const container   = document.getElementById('assigneeSelect').closest('.choices');
    const searchInput = container.querySelector('input.choices__input--cloned');
    searchInput.addEventListener('input', e => {
      const q = e.target.value.trim().toLowerCase();
      if (q.length < 2) { choices.clearChoices(); return; }
      const matches = allUsers.filter(u => u.label.toLowerCase().includes(q));
      choices.clearChoices();
      choices.setChoices(matches, 'value', 'label', true);
    });

    flatpickr('#processDue', { dateFormat: 'Y-m-d' });
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadLetters();
    initProcessModal();

    document.body.addEventListener('click', ev => {
      const btn = ev.target.closest('.btn-read');
      if (btn) onReadClick(btn.dataset.id);
    });

    document.getElementById('openProcess')?.addEventListener('click', () => {
      if (!currentLetter) return;
      document.getElementById('processId').value    = currentLetter.id;
      document.getElementById('processRef').value   = currentLetter.reference_number;
      document.getElementById('processDate').value  = currentLetter.received_date;
      document.getElementById('processTitle').value = currentLetter.title;
      document.getElementById('processDesc').value  = currentLetter.description || '';
      choices.clearStore();
      document.getElementById('processComment').value = '';
      document.getElementById('processDue')._flatpickr.clear();
      bootstrap.Modal.getInstance(document.getElementById('readModal')).hide();
      new bootstrap.Modal(document.getElementById('processModal')).show();
    });

    document.getElementById('processForm').addEventListener('submit', async ev => {
      ev.preventDefault();
      const btn = document.getElementById('processBtn'),
            spn = document.getElementById('processSpinner'),
            txt = document.getElementById('processText'),
            frm = new FormData(ev.target);
      for (let v of choices.getValue(true)) frm.append('assignees[]', v);
      spn.classList.remove('d-none'); btn.disabled = true; txt.textContent = 'Saving…';
      const res = await fetch('external_letter_process.php', { method: 'POST', body: frm })
        .then(r => r.json()).catch(() => ({ success: false }));
      spn.classList.add('d-none'); btn.disabled = false; txt.textContent = 'Save';
      if (res.success) {
        toast('Delegated', 'success');
        bootstrap.Modal.getInstance(document.getElementById('processModal')).hide();
        loadLetters();
      } else toast(res.message || 'Failed', 'danger');
    });

    document.getElementById('openClose')?.addEventListener('click', () => {
      document.getElementById('closeId').value = currentLetter.id;
      bootstrap.Modal.getInstance(document.getElementById('readModal')).hide();
      new bootstrap.Modal(document.getElementById('closeModal')).show();
    });

    document.getElementById('closeForm').addEventListener('submit', async ev => {
      ev.preventDefault();
      const btn = document.getElementById('closeBtn'),
            spn = document.getElementById('closeSpinner'),
            txt = document.getElementById('closeText'),
            frm = new FormData(ev.target);
      spn.classList.remove('d-none'); btn.disabled = true; txt.textContent = 'Closing…';
      const res = await fetch('external_letter_close.php', { method: 'POST', body: frm })
        .then(r => r.json()).catch(() => ({ success: false }));
      spn.classList.add('d-none'); btn.disabled = false; txt.textContent = 'Close letter';
      if (res.success) {
        toast('Closed', 'success');
        bootstrap.Modal.getInstance(document.getElementById('closeModal')).hide();
        loadLetters();
      } else toast(res.message || 'Failed', 'danger');
    });
  });
})();
</script>

<style>
  .f-table tbody tr.is-pending td { background: #fffdf5; }
  .f-table tbody tr.is-pending td:first-child { box-shadow: inset 3px 0 0 #b45309; }
  .f-table tbody tr.chat-row:hover td { background: transparent; }
  .f-table tbody tr.chat-row td { border-top: none; padding-top: 0; }
</style>
