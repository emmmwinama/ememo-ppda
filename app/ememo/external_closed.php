<?php
// extenal_closed.php
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>🗄️ Closed Files</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet"/>
  <style>
    body { padding: 1rem; }
    .filters .form-control { max-width: 200px; }
    .toast-container { position: fixed; top:1rem; right:1rem; z-index:2000; }
    .table-hover tbody tr:hover { background: #f8f9fa; }
  </style>
</head>
<body>
  <div class="container-fluid">
    <h2 class="mb-4">🗄️ Closed Files</h2>

    <!-- Filters -->
<div class="row mb-3 filters gx-2">
  <div class="col-auto">
    <input id="generalSearch" class="form-control" placeholder="Search all…">
  </div>
  <div class="col-auto">
    <input id="topicSearch" class="form-control" placeholder="Topic keyword…">
  </div>
  <div class="col-auto">
    <!-- this must be a select[multiple] -->
    <select id="instSelect" multiple class="form-select" placeholder="Filter by institution…"></select>
  </div>
  <div class="col-auto">
    <input id="dateFrom" class="form-control" placeholder="From date">
  </div>
  <div class="col-auto">
    <input id="dateTo" class="form-control" placeholder="To date">
  </div>
</div>



    <!-- Table -->
    <div id="tableContainer" class="text-center">
      <div class="spinner-border text-primary"></div>
    </div>
  </div>

  <!-- Read-only Modal -->
  <div class="modal fade" id="readModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-secondary text-white">
          <h5 class="modal-title">📖 View Closed Letter</h5>
          <a id="downloadMerged" class="btn btn-sm btn-outline-light ms-2" href="#" target="_blank">
            ⬇️ Download PDF
          </a>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="readBody" class="mb-4"></div>
          <hr>
          <div class="bg-light p-3 rounded">
            <strong>📜 Full Letter History:</strong>
            <ul id="trailList" class="list-unstyled small mt-2"></ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-container"></div>

  <!-- Dependencies -->
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  let allData = [], currentPage = 1, totalPages = 1;
  const perPage = 10;
  const toastContainer = document.querySelector('.toast-container');

  const genInput   = document.getElementById('generalSearch');
  const topicInput = document.getElementById('topicSearch');
  const fromInput  = document.getElementById('dateFrom');
  const toInput    = document.getElementById('dateTo');
  const instSelect = document.getElementById('instSelect');

  const instChoices = new Choices(instSelect, {
    removeItemButton: true,
    placeholderValue: 'Filter by institution…',
    shouldSort: false
  });

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
    const txt = await res.text();
    try { return JSON.parse(txt); }
    catch (err) {
      console.error(`Bad JSON from ${url}:`, txt);
      throw err;
    }
  }

  async function loadClosed(page = 1) {
    currentPage = page;
    const container = document.getElementById('tableContainer');
    container.innerHTML = '<div class="spinner-border text-primary"></div>';
    const res = await getJson(`closed_letters.php?page=${page}&per_page=${perPage}`);
    if (!res.success) {
      container.innerHTML = '';
      return toast('Failed to load closed letters', 'danger');
    }
    allData     = res.data;
    totalPages  = res.total_pages;
    populateInstitutionFilter(allData);
    applyFilters();
  }

  function populateInstitutionFilter(data) {
    const uniq = [...new Set(data.map(r => r.received_from))].sort();
    instChoices.clearChoices();
    instChoices.setChoices(
      uniq.map(i => ({ value: i, label: i })),
      'value','label', true
    );
  }

  function applyFilters() {
    const gen   = genInput.value.toLowerCase();
    const topic = topicInput.value.toLowerCase();
    const from  = fromInput.value;
    const to    = toInput.value;
    const insts = instChoices.getValue(true);

    const filtered = allData.filter(r => {
      if (gen && !(
        r.reference_number.toLowerCase().includes(gen) ||
        r.title.toLowerCase().includes(gen) ||
        r.received_from.toLowerCase().includes(gen)
      )) return false;
      if (topic && !r.title.toLowerCase().includes(topic)) return false;
      if (insts.length && !insts.includes(r.received_from)) return false;
      if (from && r.received_date < from) return false;
      if (to   && r.received_date > to)   return false;
      return true;
    });

    renderTable(filtered);
    renderPagination();
  }

  function renderTable(data) {
    const t = document.getElementById('tableContainer');
    if (!data.length) {
      t.innerHTML = '<div class="alert alert-info">No closed letters found.</div>';
      return;
    }
    t.innerHTML = `
      <table class="table table-hover">
        <thead class="table-dark"><tr>
          <th>#</th><th>Ref</th><th>Title</th>
          <th>From</th><th>Date</th><th>Status</th><th>View</th>
        </tr></thead>
        <tbody>
          ${data.map((r,i)=>`
            <tr>
              <td>${(currentPage-1)*perPage + i+1}</td>
              <td>${r.reference_number}</td>
              <td>${r.title}</td>
              <td>${r.received_from}</td>
              <td>${r.received_date}</td>
              <td><span class="badge bg-${r.badge_class}">${r.status_label}</span></td>
              <td>
                <button class="btn btn-sm btn-outline-info btn-view" data-id="${r.id}">View</button>
              </td>
            </tr>`).join('')}
        </tbody>
      </table>
      <nav aria-label="Page navigation">
        <ul id="pagination" class="pagination justify-content-center mt-3"></ul>
      </nav>`;
  }

  function renderPagination() {
    const ul = document.getElementById('pagination');
    ul.innerHTML = '';

    // Prev
    ul.innerHTML += `
      <li class="page-item ${currentPage===1?'disabled':''}">
        <button class="page-link" data-page="${currentPage-1}" ${currentPage===1?'disabled':''}>‹ Prev</button>
      </li>`;

    for (let p = 1; p <= totalPages; p++) {
      ul.innerHTML += `
        <li class="page-item ${p===currentPage?'active':''}">
          <button class="page-link" data-page="${p}">${p}</button>
        </li>`;
    }

    // Next
    ul.innerHTML += `
      <li class="page-item ${currentPage===totalPages?'disabled':''}">
        <button class="page-link" data-page="${currentPage+1}" ${currentPage===totalPages?'disabled':''}>Next ›</button>
      </li>`;
  }

async function onView(id) {
  if (!id) return;
  // 1) Update download link for the merged PDF
  document.getElementById('downloadMerged').href =
    `external_letter_render.php?letter_id=${id}`;

  // 2) Embed original attachments
  const files = await getJson(`external_letter_files.php?letter_id=${id}`);
  document.getElementById('readBody').innerHTML =
    (files.success ? files.data : []).map(f => {
      const ext = f.file_name.split('.').pop().toLowerCase();
      if (ext === 'pdf') {
        return `<embed src="${f.file_path}" type="application/pdf" width="100%" height="500px">`;
      }
      if (['jpg','jpeg','png','gif','bmp'].includes(ext)) {
        return `<img src="${f.file_path}" class="img-fluid mb-3">`;
      }
      return `📎 <a href="${f.file_path}" download>${f.file_name}</a>`;
    }).join('') || '<p class="text-muted">No attachments.</p>';

  // 3) Fetch and render history
  const h    = await getJson(`external_letter_history.php?letter_id=${id}`);
  const hist = h.success ? h.history : [];
  const list = document.getElementById('trailList');

  if (!hist.length) {
    list.innerHTML = '<li class="text-muted">No history.</li>';
  } else {
    let html = '';

    // a) Instruction entry
    const instr = hist.find(e => e.type === 'instruction');
    const underlineTo = instr?.by_position || '';
    if (instr) {
      const recps = hist
        .filter(e => e.type === 'delegation')
        .map(d => d.to_position)
        .join(', ');
      html += `
        <li class="mb-3">
          <u>${recps}</u><br>
          ${instr.text}<br>
          <small class="text-muted">${instr.when_happened}</small><br>
          ${instr.by_position}
        </li>`;
    }

    // b) Only meaningful comments/actions
    hist
      .filter(e =>
        (e.type === 'comment' || e.type === 'action') &&
        ((e.text && e.text.trim()) || e.report_file_path)
      )
      .forEach(e => {
        const icon = e.type === 'comment' ? '💬' : '📎';
        const fileLink = e.report_file_path
          ? `<br><a href="${e.report_file_path}" target="_blank">Download report</a>`
          : '';
        html += `
          <li class="mb-3">
            ${icon}<br>
            <u>${underlineTo}</u><br>
            ${e.text || ''}${fileLink}<br>
            <small class="text-muted">${e.when_happened}</small><br>
            ${e.by_position}
          </li>`;
      });

    list.innerHTML = html;
  }

  // 4) Show the modal
  new bootstrap.Modal(document.getElementById('readModal')).show();
}


  document.addEventListener('DOMContentLoaded', () => {
    flatpickr('#dateFrom',{ dateFormat:'Y-m-d' });
    flatpickr('#dateTo',  { dateFormat:'Y-m-d' });
    genInput.addEventListener('input',   applyFilters);
    topicInput.addEventListener('input', applyFilters);
    fromInput.addEventListener('change',  applyFilters);
    toInput.addEventListener('change',    applyFilters);
    instSelect.addEventListener('change', applyFilters);

    document.body.addEventListener('click', e => {
      if (e.target.matches('.page-link')) {
        const pg = parseInt(e.target.dataset.page);
        if (pg >= 1 && pg <= totalPages) loadClosed(pg);
      }
      const btn = e.target.closest('.btn-view');
      if (btn) onView(btn.dataset.id);
    });

    loadClosed(1);
  });
})();
</script>






</body>
</html>
