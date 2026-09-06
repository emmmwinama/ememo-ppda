<?php
// external_assigned.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
$userId = intval($_SESSION['user_id'] ?? 0);
if (!$userId) {
  header('Location: login.php');
  exit;
}
$currentUserName = $_SESSION['full_name'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assigned Letters</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/tributejs@5.1.3/dist/tribute.css">
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <style>
    body { padding-top:1rem; }
    .toast-container { position: fixed; top:1rem; right:1rem; z-index:2000; }
    .skeleton-loader .skeleton-row {
      height:1.5rem; margin-bottom:.75rem;
      background: var(--border);
      border-radius: var(--radius-sm);
      animation: skeleton-fade 1.4s ease-in-out infinite;
    }
    @keyframes skeleton-fade { 50% { opacity:.4; } }
    .chat-time{ font-size:.65rem; color:var(--muted);
      margin-top:.25rem; text-align:right; }
    .chat-thread::-webkit-scrollbar{ width:4px }
    .chat-thread::-webkit-scrollbar-thumb{
      background:rgba(0,0,0,0.15); border-radius:2px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2 class="mb-4">📬 My Assigned Letters</h2>
    <div class="d-flex justify-content-end mb-2">
      <input id="letterSearch" class="form-control w-25" placeholder="Search…" />
    </div>
    <div id="assignedTableContainer" class="text-center">
      <div class="skeleton-loader">
        <?= str_repeat('<div class="skeleton-row"></div>',5) ?>
      </div>
    </div>
  </div>
  <div class="toast-container"></div>

  <!-- Read Modal -->
  <div class="modal fade" id="readModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-secondary text-white">
          <h5 class="modal-title">📖 View Letter & History</h5>
          <a id="downloadMerged" class="btn btn-sm btn-outline-light ms-2" href="#" target="_blank">
            ⬇️ Download Full PDF
          </a>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="readBody" class="mb-4"></div>
          <hr>
          <div class="bg-light p-3 rounded mb-4">
            <strong>📜 Instruction & History:</strong>
            <ul id="trailList" class="list-unstyled small mt-2"></ul>
          </div>
        </div>
        <div class="modal-footer">
          <button id="openRespond" class="btn btn-success">Respond</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Respond Modal -->
  <div class="modal fade" id="respondModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="responseForm" class="p-3" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title">✍️ Submit Response</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <input type="hidden" id="respLetterId" name="letter_id">
          <div class="mb-3">
            <label class="form-label">Your Comment</label>
            <textarea id="respComment" name="comment" class="form-control" rows="3" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Final Report (optional)</label>
            <textarea id="respReport" name="report" class="form-control" rows="4"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Attach File (optional)</label>
            <input type="file" id="respFile" name="report_file" class="form-control">
          </div>
          <div class="text-end">
            <button type="submit" id="respBtn" class="btn btn-primary">
              <span id="respSpinner" class="spinner-border spinner-border-sm d-none"></span>
              <span id="respText">Submit</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delegate Modal -->
<!-- Delegate Modal -->
<div class="modal fade" id="delegateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delegate Letter</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body position-relative">
        <p>Select users to delegate to, then add your instruction:</p>
        <div id="delegateBadges" class="mb-2"></div>

        <!-- user search -->
        <input type="text"
               id="delegateInput"
               class="form-control mb-1"
               placeholder="Type to search… (min 2 chars)">
        <div id="delegateResults"
             class="list-group position-absolute w-100"
             style="z-index:1055; display:none; max-height:150px; overflow:auto;">
        </div>

        <!-- your instruction field -->
        <textarea id="delegateInstruction"
                  class="form-control mt-2"
                  rows="3"
                  placeholder="Your instruction…"></textarea>
      </div>
      <div class="modal-footer">
        <button id="delegateSubmit" type="button" class="btn btn-primary">Delegate</button>
        <button type="button" class="btn btn-secondary"
                data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>


  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/tributejs@5.1.3/dist/tribute.min.js"></script>

<script>
(() => {
  const currentUserId  = <?= $userId ?>;
  const toastContainer = document.querySelector('.toast-container');

  let letters         = [];
  let allUsers        = [];
  let delegateTargets = [];

  function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 mb-2`;
    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
      </div>`;
    toastContainer.append(el);
    new bootstrap.Toast(el, { delay: 3000 }).show();
  }

  async function getJson(url) {
    const res = await fetch(url);
    return res.json();
  }

  // ─── Delegate Picker (plain JS) ───────────────────────
  async function initDelegatePicker() {
    const res = await getJson('get_users_list.php?all=1');
    if (res.status !== 'success') return;
    allUsers = res.users;

    const input   = document.getElementById('delegateInput');
    const results = document.getElementById('delegateResults');

    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      if (q.length < 2) {
        results.style.display = 'none';
        return;
      }
      const matches = allUsers.filter(u =>
        u.full_name.toLowerCase().includes(q)
      );
      if (!matches.length) {
        results.style.display = 'none';
        return;
      }
      results.innerHTML = matches.map(u => `
        <button type="button"
                class="list-group-item list-group-item-action"
                data-id="${u.id}">
          ${u.full_name}
        </button>
      `).join('');
      results.style.display = 'block';
    });

    results.addEventListener('click', e => {
      const btn = e.target.closest('.list-group-item');
      if (!btn) return;
      const id = btn.dataset.id;
      if (!delegateTargets.includes(id)) {
        delegateTargets.push(id);
        renderDelegateBadges();
      }
      input.value = '';
      results.style.display = 'none';
    });

    document.addEventListener('click', e => {
      if (!e.target.closest('#delegateInput,#delegateResults')) {
        results.style.display = 'none';
      }
    });
  }

  function renderDelegateBadges() {
    document.getElementById('delegateBadges').innerHTML =
      delegateTargets.map(id => {
        const u = allUsers.find(u => u.id == id);
        return `
          <span class="badge bg-secondary me-1">
            @${u.full_name}
            <button type="button"
                    class="btn-close btn-close-white btn-sm remove-delegate"
                    data-id="${id}"></button>
          </span>`;
      }).join('');
  }

  document.getElementById('delegateBadges')
          .addEventListener('click', e => {
    if (!e.target.matches('.remove-delegate')) return;
    const id = e.target.dataset.id;
    delegateTargets = delegateTargets.filter(x => x !== id);
    renderDelegateBadges();
  });

  function openDelegateModal(letterId) {
    delegateTargets = [];
    renderDelegateBadges();
    document.getElementById('delegateInput').value = '';
    document.getElementById('delegateInstruction').value = '';
    document.getElementById('delegateSubmit').dataset.letterId = letterId;
    new bootstrap.Modal(document.getElementById('delegateModal')).show();
  }

  function attachDelegateButtons() {
    document.querySelectorAll('.btn-delegate').forEach(btn => {
      btn.onclick = () => openDelegateModal(btn.dataset.id);
    });
  }

  document.getElementById('delegateSubmit')
          .addEventListener('click', async function() {
    const letterId    = this.dataset.letterId;
    const instruction = document.getElementById('delegateInstruction').value.trim();
    if (!delegateTargets.length) return alert('Please select at least one user.');
    if (!instruction)         return alert('Please provide an instruction.');

    this.disabled = true;
    const resp = await fetch('delegate_letter.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({
        letter_id:   letterId,
        delegate_to: delegateTargets,
        instruction
      })
    }).then(r => r.json());
    this.disabled = false;

    if (resp.success) {
      toast('Delegation successful','success');
      bootstrap.Modal.getInstance(document.getElementById('delegateModal')).hide();
      loadAssigned();
      attachDelegateButtons();
    } else {
      toast(resp.error || 'Failed','danger');
    }
  });

  // ─── Inbox Loading & Rendering ───────────────────────
  async function loadAssigned() {
    const c = document.getElementById('assignedTableContainer');
    c.innerHTML = '<div class="skeleton-loader">' +
      '<div class="skeleton-row"></div>'.repeat(5) +
      '</div>';

    const res = await getJson('external_letters_list.php');
    if (!res.success) {
      c.innerHTML = '';
      return toast('Failed to load','danger');
    }
    letters = res.data;
    await Promise.all(letters.map(async (l, i) => {
      const h = await getJson(`external_letter_history.php?letter_id=${l.id}`);
      const hist = h.success ? h.history : [];
      letters[i]._history = hist;
      const ccount = hist.filter(e => e.type==='comment').length;
      const acount = hist.filter(e => e.type==='action').length;
      letters[i].pending = ccount > 0 && acount === 0;
    }));
    renderTable();
  }
  
  
function renderTable() {
  const q = document.getElementById('letterSearch')
              .value.trim().toLowerCase();

  const html = letters
    .filter(l => !q || l.title.toLowerCase().includes(q))
    .map((l, i) => {
      // build full preview—including delegation events
      const preview = (l._history || [])
        .filter(e =>
          e.type === 'delegation'
          || (e.text || '').trim()
          || e.report_file_path
        )
        .map(e => {
          // decide left/right bubble
          const side = e.type === 'instruction'
                     ? 'chat-bubble-in'
                     : 'chat-bubble-out';

          // pick an icon
          const icon = e.type === 'instruction'
                     ? '🗂️'
                     : (e.type === 'delegation'
                        ? '🔀'
                        : (e.type === 'comment' ? '💬' : '📎')
                       );

          // build the message body
          let msg;
          if (e.type === 'delegation') {
            msg = `${icon} Delegated to <strong>${e.to_position}</strong>`;
          } else {
            msg = `${icon} ${e.text || ''}`;
            if (e.report_file_path) {
              msg += `<br><a href="${e.report_file_path}"
                              target="_blank">Download</a>`;
            }
          }

          // header shows “from” and, for delegations, “→ to”
          const header = e.type === 'delegation'
            ? `${e.by_position} → ${e.to_position}`
            : e.by_position;

          // timestamp
          const time = new Date(e.when_happened)
                         .toLocaleTimeString([], {
                           hour:   '2-digit',
                           minute: '2-digit'
                         });

          return `
            <div class="chat-bubble ${side}">
              <div class="chat-sender small text-muted mb-1">${header}</div>
              <div class="chat-content">${msg}</div>
              <div class="chat-time">${time}</div>
            </div>`;
        })
        .join('');

      return `
        <div class="card mb-3${l.pending ? ' border-warning' : ''}">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <strong>${i + 1}. ${l.reference_number}</strong> — ${l.title}<br>
                <small class="text-muted">
                  From ${l.received_from} on ${l.received_date}
                </small>
              </div>
              <div>
                ${l.pending
                  ? '<span class="badge bg-warning text-dark me-2">Pending</span>'
                  : ''}
                <button class="btn btn-sm btn-outline-info btn-read me-1"
                        data-id="${l.id}">
                  Read
                </button>
                <button class="btn btn-sm btn-outline-secondary btn-delegate"
                        data-id="${l.id}">
                  Delegate
                </button>
              </div>
            </div>
            ${preview
              ? `<div class="chat-thread">${preview}</div>`
              : ''}
          </div>
        </div>`;
    })
    .join('') || '<div class="text-center text-muted">No letters found.</div>';

  document.getElementById('assignedTableContainer').innerHTML = html;

  // re‑attach handlers
  document.querySelectorAll('.btn-read').forEach(btn =>
    btn.onclick = () => onRead(btn.dataset.id)
  );
  attachDelegateButtons();
}


  async function onRead(id) {
    const l = letters.find(x => x.id == id);
    if (!l) return toast('Invalid','danger');
    document.getElementById('respLetterId').value = id;
    document.getElementById('downloadMerged').href =
      `external_letter_render.php?letter_id=${id}`;

    const f = await getJson(`external_letter_files.php?letter_id=${id}`);
    document.getElementById('readBody').innerHTML =
      (f.success?f.data:[]).map(x => {
        const ext = x.file_name.split('.').pop().toLowerCase();
        if (ext==='pdf') return `<embed src="${x.file_path}" type="application/pdf" width="100%" height="500px">`;
        if (['jpg','jpeg','png','gif','bmp'].includes(ext))
          return `<img src="${x.file_path}" class="img-fluid mb-3">`;
        return `📎 <a href="${x.file_path}" download>${x.file_name}</a>`;
      }).join('') || '<p class="text-muted">No attachments.</p>';

    const hist = l._history || [];
    const list = document.getElementById('trailList');
    list.innerHTML = hist
      .filter(e => (e.text||'').trim() || e.report_file_path)
      .map(e => {
        const icon = e.type==='comment'?'💬':'📎';
        const link = e.report_file_path
          ? `<br><a href="${e.report_file_path}" target="_blank">Download report</a>`
          : '';
        return `
          <li class="mb-3">
            <span class="timeline-icon bg-secondary">${icon}</span>
            <div class="timeline-content">
              <strong>${e.by_position}</strong><br>
              ${e.text||''}${link}<br>
              <small class="text-muted">${e.when_happened}</small>
            </div>
          </li>`;
      }).join('');
    new bootstrap.Modal(document.getElementById('readModal')).show();
  }

  document.getElementById('openRespond').onclick = () => {
    bootstrap.Modal.getInstance(document.getElementById('readModal')).hide();
    new bootstrap.Modal(document.getElementById('respondModal')).show();
  };

  document.getElementById('responseForm').onsubmit = async ev => {
    ev.preventDefault();
    const btn     = document.getElementById('respBtn'),
          spinner = document.getElementById('respSpinner'),
          text    = document.getElementById('respText'),
          form    = new FormData(ev.target);

    spinner.classList.remove('d-none');
    btn.disabled = true;
    text.textContent = 'Submitting…';

    const res = await fetch('external_assigned_process.php', {
      method:'POST', body: form
    }).then(r=>r.json()).catch(()=>({success:false}));

    spinner.classList.add('d-none');
    btn.disabled = false;
    text.textContent = 'Submit';

    if (res.success) {
      toast('Saved','success');
      bootstrap.Modal.getInstance(document.getElementById('respondModal')).hide();
      loadAssigned();
    } else {
      toast(res.message || 'Failed','danger');
    }
  };

  document.getElementById('letterSearch').oninput = renderTable;
  document.addEventListener('DOMContentLoaded', () => {
    initDelegatePicker();
    loadAssigned();
  });
})();
</script>

</body>
</html>
