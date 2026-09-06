<?php
// memo_archive.php — rendered inside index.php (header.php loads Bootstrap, Icons, theme.css)
require 'auth.php';
?>
<div class="f-head">
  <h1 class="f-title">Archive</h1>
  <p class="f-subtitle">Approved memos, searchable by year and reference</p>
</div>

<div class="f-toolbar">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Search by subject or reference…" autocomplete="off">
  </div>
  <select id="yearFilter" class="form-select form-select-sm f-control">
    <option value="">All years</option>
  </select>
</div>

<div id="archiveTableContainer">
  <div class="f-state"><div class="spinner-border spinner-border-sm" role="status"></div><p class="mt-2">Loading archive…</p></div>
</div>

<div class="f-pager" id="paginationContainer"></div>

<script>
(() => {
  let allMemos = [], filteredMemos = [], currentPage = 1;
  const memosPerPage = 10;

  const container = document.getElementById('archiveTableContainer');
  const pager     = document.getElementById('paginationContainer');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
    { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
  const fmtDate = (d) => {
    const dt = new Date(String(d).replace(' ', 'T'));
    return isNaN(dt) ? '' : dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  };

  function loadArchiveMemos() {
    fetch('get_archive.php')
      .then(r => r.json())
      .then(d => {
        if (d.status !== 'success') {
          container.innerHTML = `<div class="f-state is-error"><i class="bi bi-exclamation-octagon"></i><p>${esc(d.message || 'Could not load archive.')}</p></div>`;
          return;
        }
        allMemos = d.data || [];
        populateYearFilter(allMemos);
        applyFilters();
      })
      .catch(err => {
        container.innerHTML = `<div class="f-state is-error"><i class="bi bi-wifi-off"></i><p>Failed to load archive: ${esc(err.message)}</p></div>`;
      });
  }

  function populateYearFilter(memos) {
    const years = [...new Set(memos.map(m => new Date(String(m.created_at).replace(' ', 'T')).getFullYear()))]
      .filter(y => !isNaN(y)).sort((a, b) => b - a);
    const select = document.getElementById('yearFilter');
    years.forEach(y => {
      const o = document.createElement('option');
      o.value = y; o.textContent = y;
      select.appendChild(o);
    });
  }

  function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const year   = document.getElementById('yearFilter').value;
    filteredMemos = allMemos.filter(m => {
      const matchesSearch = !search
        || String(m.subject || '').toLowerCase().includes(search)
        || String(m.memo_id || '').toLowerCase().includes(search);
      const matchesYear = !year
        || String(new Date(String(m.created_at).replace(' ', 'T')).getFullYear()) === year;
      return matchesSearch && matchesYear;
    });
    currentPage = 1;
    renderTable();
  }

  function renderTable() {
    const start = (currentPage - 1) * memosPerPage;
    const page  = filteredMemos.slice(start, start + memosPerPage);

    if (page.length === 0) {
      container.innerHTML = `<div class="f-state"><i class="bi bi-archive"></i><p>No memos match this view.</p></div>`;
      pager.innerHTML = '';
      return;
    }

    const rows = page.map(m => `
      <tr>
        <td class="font-monospace">${esc(m.memo_id)}</td>
        <td class="fw-semibold">${esc(m.subject)}</td>
        <td><span class="pill t-green">Approved</span></td>
        <td class="text-muted">${esc(fmtDate(m.created_at))}</td>
        <td><a href="view_memo.php?memo_id=${encodeURIComponent(m.id)}" class="btn btn-sm btn-outline-success"><i class="bi bi-eye me-1"></i>View</a></td>
      </tr>`).join('');

    container.innerHTML = `
      <div class="f-panel table-responsive">
        <table class="table table-borderless f-table align-middle">
          <thead><tr><th>Reference</th><th>Subject</th><th>Status</th><th>Date approved</th><th>Action</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;

    const totalPages = Math.ceil(filteredMemos.length / memosPerPage);
    if (totalPages <= 1) { pager.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= totalPages; i++) {
      html += `<button class="${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    pager.innerHTML = html;
  }

  document.getElementById('searchInput').addEventListener('input', applyFilters);
  document.getElementById('yearFilter').addEventListener('change', applyFilters);
  pager.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-page]');
    if (!btn) return;
    currentPage = Number(btn.dataset.page);
    renderTable();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  loadArchiveMemos();
})();
</script>
