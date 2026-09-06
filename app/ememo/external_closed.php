<?php
// external_closed.php — rendered inside index.php (header.php loads Bootstrap, Icons, theme.css)
require_once __DIR__ . '/auth.php';
?>
<link href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet"/>
<style>
  .cf-toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 2000; }
  #external-closed .choices { min-width: 220px; margin: 0; }
  #external-closed .choices__inner { border-radius: var(--radius-md); border-color: var(--border); min-height: 40px; background: var(--surface); }
  #external-closed .flatpickr-input { max-width: 160px; }
</style>

<div id="external-closed">
  <div class="f-head">
    <h1 class="f-title">Closed Files</h1>
    <p class="f-subtitle">Archived external letters and their full history</p>
  </div>

  <div class="f-toolbar">
    <div class="f-search">
      <i class="bi bi-search"></i>
      <input id="generalSearch" placeholder="Search reference, title or sender…" autocomplete="off">
    </div>
    <input id="topicSearch" class="form-control form-control-sm f-control" placeholder="Topic keyword…">
    <select id="instSelect" multiple class="form-select" placeholder="Filter by institution…"></select>
    <input id="dateFrom" class="form-control form-control-sm f-control" placeholder="From date">
    <input id="dateTo" class="form-control form-control-sm f-control" placeholder="To date">
  </div>

  <div id="tableContainer">
    <div class="f-state"><div class="spinner-border spinner-border-sm" role="status"></div><p class="mt-2">Loading closed files…</p></div>
  </div>
</div>

<!-- Read-only modal -->
<div class="modal fade" id="readModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>View closed letter</h5>
        <a id="downloadMerged" class="btn btn-sm btn-outline-success ms-3" href="#" target="_blank">
          <i class="bi bi-download me-1"></i>Download PDF
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="readBody" class="mb-4"></div>
        <hr>
        <div class="f-panel p-3">
          <strong><i class="bi bi-clock-history me-1"></i>Full letter history</strong>
          <ul id="trailList" class="list-unstyled small mt-2 mb-0"></ul>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="cf-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(() => {
  let allData = [], currentPage = 1, totalPages = 1;
  const perPage = 10;
  const toastContainer = document.querySelector('.cf-toast-container');

  const genInput   = document.getElementById('generalSearch');
  const topicInput = document.getElementById('topicSearch');
  const fromInput  = document.getElementById('dateFrom');
  const toInput    = document.getElementById('dateTo');
  const instSelect = document.getElementById('instSelect');
  const container  = document.getElementById('tableContainer');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
    { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));

  const instChoices = new Choices(instSelect, {
    removeItemButton: true, placeholderValue: 'Filter by institution…', shouldSort: false
  });

  function toast(msg, type = 'secondary') {
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 mb-2`;
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(msg)}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    toastContainer.append(el);
    new bootstrap.Toast(el, { delay: 3000 }).show();
  }

  async function getJson(url) {
    const res = await fetch(url);
    const txt = await res.text();
    try { return JSON.parse(txt); }
    catch (err) { console.error(`Bad JSON from ${url}:`, txt); throw err; }
  }

  async function loadClosed(page = 1) {
    currentPage = page;
    container.innerHTML = `<div class="f-state"><div class="spinner-border spinner-border-sm" role="status"></div><p class="mt-2">Loading…</p></div>`;
    const res = await getJson(`closed_letters.php?page=${page}&per_page=${perPage}`);
    if (!res.success) {
      container.innerHTML = `<div class="f-state is-error"><i class="bi bi-exclamation-octagon"></i><p>Failed to load closed letters.</p></div>`;
      return;
    }
    allData = res.data; totalPages = res.total_pages;
    populateInstitutionFilter(allData);
    applyFilters();
  }

  function populateInstitutionFilter(data) {
    const uniq = [...new Set(data.map(r => r.received_from))].sort();
    instChoices.clearChoices();
    instChoices.setChoices(uniq.map(i => ({ value: i, label: i })), 'value', 'label', true);
  }

  function applyFilters() {
    const gen = genInput.value.toLowerCase();
    const topic = topicInput.value.toLowerCase();
    const from = fromInput.value, to = toInput.value;
    const insts = instChoices.getValue(true);

    const filtered = allData.filter(r => {
      if (gen && !(
        r.reference_number.toLowerCase().includes(gen) ||
        r.title.toLowerCase().includes(gen) ||
        r.received_from.toLowerCase().includes(gen))) return false;
      if (topic && !r.title.toLowerCase().includes(topic)) return false;
      if (insts.length && !insts.includes(r.received_from)) return false;
      if (from && r.received_date < from) return false;
      if (to && r.received_date > to) return false;
      return true;
    });

    renderTable(filtered);
  }

  function renderTable(data) {
    if (!data.length) {
      container.innerHTML = `<div class="f-state"><i class="bi bi-folder-check"></i><p>No closed letters found.</p></div>`;
      return;
    }
    container.innerHTML = `
      <div class="f-panel table-responsive">
        <table class="table table-borderless f-table align-middle">
          <thead><tr><th>#</th><th>Ref</th><th>Title</th><th>From</th><th>Date</th><th>Status</th><th>View</th></tr></thead>
          <tbody>
            ${data.map((r, i) => `
              <tr>
                <td class="text-muted">${(currentPage - 1) * perPage + i + 1}</td>
                <td class="font-monospace">${esc(r.reference_number)}</td>
                <td class="fw-semibold">${esc(r.title)}</td>
                <td class="text-muted">${esc(r.received_from)}</td>
                <td class="text-muted">${esc(r.received_date)}</td>
                <td><span class="badge bg-${esc(r.badge_class)}">${esc(r.status_label)}</span></td>
                <td><button class="btn btn-sm btn-outline-success btn-view" data-id="${esc(r.id)}"><i class="bi bi-eye me-1"></i>View</button></td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>
      <div class="f-pager" id="pagination"></div>`;
    renderPagination();
  }

  function renderPagination() {
    const el = document.getElementById('pagination');
    if (!el || totalPages <= 1) { if (el) el.innerHTML = ''; return; }
    let html = `<button data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>‹</button>`;
    for (let p = 1; p <= totalPages; p++) {
      html += `<button class="${p === currentPage ? 'active' : ''}" data-page="${p}">${p}</button>`;
    }
    html += `<button data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>›</button>`;
    el.innerHTML = html;
  }

  async function onView(id) {
    if (!id) return;
    document.getElementById('downloadMerged').href = `external_letter_render.php?letter_id=${id}`;

    const files = await getJson(`external_letter_files.php?letter_id=${id}`);
    document.getElementById('readBody').innerHTML =
      (files.success ? files.data : []).map(f => {
        const ext = f.file_name.split('.').pop().toLowerCase();
        if (ext === 'pdf') return `<embed src="${f.file_path}" type="application/pdf" width="100%" height="500px">`;
        if (['jpg','jpeg','png','gif','bmp'].includes(ext)) return `<img src="${f.file_path}" class="img-fluid mb-3">`;
        return `<a href="${f.file_path}" download><i class="bi bi-paperclip me-1"></i>${esc(f.file_name)}</a>`;
      }).join('') || '<p class="text-muted">No attachments.</p>';

    const h = await getJson(`external_letter_history.php?letter_id=${id}`);
    const hist = h.success ? h.history : [];
    const list = document.getElementById('trailList');

    if (!hist.length) { list.innerHTML = '<li class="text-muted">No history.</li>'; }
    else {
      let html = '';
      const instr = hist.find(e => e.type === 'instruction');
      const underlineTo = instr?.by_position || '';
      if (instr) {
        const recps = hist.filter(e => e.type === 'delegation').map(d => d.to_position).join(', ');
        html += `<li class="mb-3"><u>${esc(recps)}</u><br>${esc(instr.text)}<br>
          <small class="text-muted">${esc(instr.when_happened)}</small><br>${esc(instr.by_position)}</li>`;
      }
      hist.filter(e => (e.type === 'comment' || e.type === 'action') && ((e.text && e.text.trim()) || e.report_file_path))
        .forEach(e => {
          const icon = e.type === 'comment' ? '<i class="bi bi-chat-left-text"></i>' : '<i class="bi bi-paperclip"></i>';
          const fileLink = e.report_file_path ? `<br><a href="${e.report_file_path}" target="_blank">Download report</a>` : '';
          html += `<li class="mb-3">${icon}<br><u>${esc(underlineTo)}</u><br>${esc(e.text || '')}${fileLink}<br>
            <small class="text-muted">${esc(e.when_happened)}</small><br>${esc(e.by_position)}</li>`;
        });
      list.innerHTML = html;
    }
    new bootstrap.Modal(document.getElementById('readModal')).show();
  }

  flatpickr('#dateFrom', { dateFormat: 'Y-m-d' });
  flatpickr('#dateTo',   { dateFormat: 'Y-m-d' });
  genInput.addEventListener('input', applyFilters);
  topicInput.addEventListener('input', applyFilters);
  fromInput.addEventListener('change', applyFilters);
  toInput.addEventListener('change', applyFilters);
  instSelect.addEventListener('change', applyFilters);

  document.body.addEventListener('click', e => {
    const pg = e.target.closest('#pagination button[data-page]');
    if (pg && !pg.disabled) {
      const n = parseInt(pg.dataset.page);
      if (n >= 1 && n <= totalPages) loadClosed(n);
    }
    const btn = e.target.closest('.btn-view');
    if (btn) onView(btn.dataset.id);
  });

  loadClosed(1);
})();
</script>
