<?php
// inbox.php — rendered inside index.php (header.php already loads Bootstrap, Icons, theme.css)
require 'auth.php';
?>
<style>
  /* ── Inbox (Farmis-style clean surface) ────────────────────────── */
  .ib { --gap: 1rem; }

  .ib-head { margin-bottom: 1.3rem; }
  .ib-title { font-size: 1.4rem; font-weight: 800; margin: 0; color: var(--text); }
  .ib-subtitle { color: var(--muted); font-size: .9rem; margin: .2rem 0 0; }

  .ib-chips { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
  .chip {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .35rem .8rem;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-pill);
    font-size: .82rem; font-weight: 600; color: var(--muted);
    cursor: pointer; user-select: none;
    transition: background .15s, color .15s, border-color .15s;
  }
  .chip:hover { border-color: var(--border-strong, #cbd5e1); color: var(--text); }
  .chip.active { background: var(--brand); border-color: var(--brand); color: #fff; }
  .chip .chip-count {
    display: inline-block; min-width: 1.25rem; text-align: center;
    padding: 0 .35rem; border-radius: var(--radius-pill);
    background: rgba(0,0,0,.06); font-size: .74rem; font-weight: 700;
  }
  .chip.active .chip-count { background: rgba(255,255,255,.25); }
  .chip .unread-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--brand);
    display: inline-block;
  }
  .chip.active .unread-dot { background: #fff; }

  .ib-toolbar { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 1.3rem; }
  .ib-search { position: relative; flex: 1 1 260px; }
  .ib-search i {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: .95rem; pointer-events: none;
  }
  .ib-search input {
    width: 100%; height: 40px; padding: 0 .9rem 0 2.3rem;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); font-size: .9rem; color: var(--text); outline: none;
    transition: border-color .15s, box-shadow .15s;
  }
  .ib-search input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-light); }
  .ib-filter { height: 40px; width: auto; border-radius: var(--radius-md); border-color: var(--border); }
  .ib-clear { height: 40px; border-radius: var(--radius-md); font-weight: 600; }

  .ib-panel {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); box-shadow: var(--shadow-flat); overflow: hidden;
  }
  .ib-table { margin: 0; font-size: .9rem; }
  .ib-table thead th {
    background: var(--bg); color: var(--muted); font-weight: 600;
    border-bottom: 1px solid var(--border); white-space: nowrap;
  }
  .ib-table tbody td { vertical-align: middle; border-top: 1px solid var(--border); }
  .ib-table tbody tr:first-child td { border-top: none; }
  .ib-table tbody tr:hover { background: var(--bg); }
  .ib-table tr.is-unread { background: #fffdf5; }
  .ib-table tr.is-unread:hover { background: #fff8e8; }
  .ib-table tr.is-unread td:first-child { box-shadow: inset 3px 0 0 var(--brand); }
  .ib-subject { font-weight: 600; color: var(--text); }

  .pill {
    display: inline-block; padding: .15rem .6rem;
    border-radius: var(--radius-pill); font-size: .74rem; font-weight: 600;
  }
  .t-dark    { background: #e8ebee; color: #1f2937; }
  .t-neutral { background: #eef1f4; color: #475569; }
  .t-slate   { background: #eef1f4; color: #475569; }
  .t-green   { background: var(--brand-light); color: var(--brand-dark); }
  .t-amber   { background: #fdefda; color: #b45309; }
  .t-rose    { background: #fdecee; color: #be123c; }
  .t-sky     { background: #e6f4fb; color: #0369a1; }

  .ib-pager { display: flex; justify-content: center; gap: .35rem; margin-top: 1.2rem; flex-wrap: wrap; }
  .ib-pager button {
    min-width: 34px; height: 34px; padding: 0 .5rem;
    border: 1px solid var(--border); background: var(--surface);
    border-radius: var(--radius-sm); font-size: .82rem; font-weight: 600; color: var(--muted);
    cursor: pointer; transition: background .15s, color .15s, border-color .15s;
  }
  .ib-pager button:hover { border-color: var(--brand); color: var(--brand); }
  .ib-pager button.active { background: var(--brand); border-color: var(--brand); color: #fff; }

</style>

<div class="ib">

  <div class="ib-head">
    <h1 class="ib-title">My Inbox</h1>
    <p class="ib-subtitle">Memos awaiting your review, endorsement or action</p>
  </div>

  <div class="ib-chips" id="inboxChips">
    <button class="chip active" data-status="main">Main <span class="chip-count" id="mainCount">0</span><span class="unread-dot d-none" id="mainNewDot"></span></button>
    <button class="chip" data-status="escalated">Clarifications <span class="chip-count" id="escalatedCount">0</span></button>
    <button class="chip" data-status="pending">Pending <span class="chip-count" id="pendingCount">0</span></button>
    <button class="chip" data-status="approved">Approved <span class="chip-count" id="approvedCount">0</span></button>
    <button class="chip" data-status="returned">Returned <span class="chip-count" id="returnedCount">0</span></button>
    <button class="chip" data-status="rejected">Rejected <span class="chip-count" id="rejectedCount">0</span></button>
    <button class="chip" data-status="forwarded">Forwarded <span class="chip-count" id="forwardedCount">0</span></button>
  </div>

  <div class="ib-toolbar">
    <div class="ib-search">
      <i class="bi bi-search"></i>
      <input type="text" id="searchInput" placeholder="Search by subject or reference…" autocomplete="off">
    </div>
    <select id="statusFilter" class="form-select form-select-sm ib-filter">
      <option value="">All statuses</option>
      <option value="Submitted">Submitted</option>
      <option value="Under Review">Under Review</option>
      <option value="Endorsed">Endorsed</option>
      <option value="Approved">Approved</option>
      <option value="Rejected">Rejected</option>
      <option value="Returned">Returned</option>
      <option value="Finalized">Finalized</option>
    </select>
    <button class="btn btn-outline-secondary btn-sm ib-clear d-none" id="clearFiltersBtn">
      <i class="bi bi-x-lg me-1"></i>Clear
    </button>
  </div>

  <div id="inboxTableContainer">
    <div class="f-state"><div class="spinner-border spinner-border-sm" role="status"></div><p class="mt-2">Loading inbox…</p></div>
  </div>

  <div class="ib-pager" id="paginationContainer"></div>
</div>

<script>
(() => {
  const validTabs = ['main','escalated','pending','approved','returned','rejected','forwarded'];
  let activeTab = localStorage.getItem('activeInboxTab') || 'main';
  if (!validTabs.includes(activeTab)) { activeTab = 'main'; localStorage.setItem('activeInboxTab', activeTab); }

  let groupedMemos = {};
  let filteredMemos = [];
  let currentPage = 1;
  const memosPerPage = 10;

  const container = document.getElementById('inboxTableContainer');
  const pager     = document.getElementById('paginationContainer');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
    { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]
  ));

  function tintFor(status) {
    const s = String(status || '').toLowerCase();
    if (s.startsWith('forwarded:')) return 't-green';
    switch (s) {
      case 'submitted': case 'under review': case 'escalated': return 't-amber';
      case 'endorsed':  return 't-sky';
      case 'approved':  return 't-green';
      case 'rejected':  case 'returned': return 't-rose';
      case 'finalized': return 't-dark';
      case 'draft':     return 't-neutral';
      default:          return 't-neutral';
    }
  }

  function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(String(d).replace(' ', 'T'));
    return isNaN(dt) ? '' : dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function loadInboxMemos() {
    fetch('get_inbox.php')
      .then(r => r.json())
      .then(({ status, data, message }) => {
        if (status !== 'success') {
          container.innerHTML = `<div class="f-state is-error"><i class="bi bi-exclamation-octagon"></i><p>${esc(message || 'Could not load inbox.')}</p></div>`;
          return;
        }
        groupedMemos = data || {};
        updateTabCounts();
        applyFilters();
      })
      .catch(err => {
        console.error('Inbox fetch error:', err);
        container.innerHTML = `<div class="f-state is-error"><i class="bi bi-wifi-off"></i><p>Failed to load inbox: ${esc(err.message)}</p></div>`;
      });
  }

  function updateTabCounts() {
    const set = (id, n) => { const el = document.getElementById(id); if (el) el.textContent = n; };
    set('mainCount',      (groupedMemos.main      || []).length);
    set('escalatedCount', (groupedMemos.escalated || []).length);
    set('pendingCount',   (groupedMemos.pending   || []).length);
    set('approvedCount',  (groupedMemos.approved  || []).length);
    set('returnedCount',  (groupedMemos.returned  || []).length);
    set('rejectedCount',  (groupedMemos.rejected  || []).length);
    set('forwardedCount', (groupedMemos.forwarded || []).length);

    const hasUnread = (groupedMemos.main || []).some(m => !m.viewed);
    document.getElementById('mainNewDot').classList.toggle('d-none', !hasUnread);
  }

  function applyFilters() {
    const statusFilter = (document.getElementById('statusFilter').value || '').toLowerCase();
    const searchFilter = (document.getElementById('searchInput').value || '').toLowerCase();

    const base = groupedMemos[activeTab] || [];
    filteredMemos = base.filter(m => {
      const s = String(m.status || '').toLowerCase();
      const matchesStatus = !statusFilter || s === statusFilter;
      const matchesSearch = !searchFilter ||
        String(m.subject || '').toLowerCase().includes(searchFilter) ||
        String(m.memo_id || '').toLowerCase().includes(searchFilter);
      return matchesStatus && matchesSearch;
    });

    currentPage = 1;
    renderTable();
  }

  function renderTable() {
    const memos = filteredMemos;
    if (memos.length === 0) {
      container.innerHTML = `<div class="f-state"><i class="bi bi-inbox"></i><p>No memos match this view.</p></div>`;
      pager.innerHTML = '';
      return;
    }

    const start = (currentPage - 1) * memosPerPage;
    const page  = memos.slice(start, start + memosPerPage);

    const rows = page.map(m => {
      const href = (m.type === 'Direct Memo' ? 'view_direct_memo.php' : 'view_memo.php') + '?memo_id=' + encodeURIComponent(m.id);
      return `
      <tr class="${m.viewed ? '' : 'is-unread'}">
        <td class="font-monospace">${esc(m.memo_id)}</td>
        <td>
          <span class="ib-subject">${esc(m.subject)}</span>
          ${m.viewed ? '' : '<span class="pill t-sky ms-2">New</span>'}
        </td>
        <td class="text-muted">${esc(m.type)}</td>
        <td><span class="pill ${tintFor(m.status)}">${esc(m.status)}</span></td>
        <td class="text-muted">${esc(fmtDate(m.created_at))}</td>
        <td>
          <a href="${href}" data-id="${esc(m.id)}" data-type="${esc(m.type)}"
             class="btn btn-sm ${m.viewed ? 'btn-outline-success' : 'btn-success'} ib-open">
            <i class="bi bi-box-arrow-up-right me-1"></i>Open
          </a>
        </td>
      </tr>`;
    }).join('');

    container.innerHTML = `
      <div class="ib-panel table-responsive">
        <table class="table table-borderless ib-table align-middle">
          <thead>
            <tr>
              <th>Reference</th><th>Subject</th><th>Type</th><th>Status</th><th>Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;

    const totalPages = Math.ceil(memos.length / memosPerPage);
    if (totalPages <= 1) { pager.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= totalPages; i++) {
      html += `<button class="${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    pager.innerHTML = html;
  }

  function markInboxViewed(id, memoType) {
    return fetch('mark_inbox_viewed.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ memo_id: id, memo_type: memoType === 'Direct Memo' ? 'Direct' : 'Workflow' })
    });
  }

  function saveFilters() {
    localStorage.setItem('inboxFilters', JSON.stringify({
      status: document.getElementById('statusFilter').value,
      search: document.getElementById('searchInput').value
    }));
  }

  function loadSavedFilters() {
    const s = localStorage.getItem('inboxFilters');
    if (!s) return;
    try {
      const f = JSON.parse(s);
      if (f.status) document.getElementById('statusFilter').value = f.status;
      if (f.search) document.getElementById('searchInput').value = f.search;
    } catch {}
  }

  function toggleClearButton() {
    const show = !!(document.getElementById('statusFilter').value || document.getElementById('searchInput').value);
    document.getElementById('clearFiltersBtn').classList.toggle('d-none', !show);
  }

  function clearFilters() {
    document.getElementById('statusFilter').value = '';
    document.getElementById('searchInput').value = '';
    localStorage.removeItem('inboxFilters');
    toggleClearButton();
    applyFilters();
  }

  // ── Wiring ──────────────────────────────────────────────────────
  document.getElementById('inboxChips').addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    document.querySelectorAll('#inboxChips .chip').forEach(c => c.classList.toggle('active', c === chip));
    activeTab = chip.dataset.status;
    localStorage.setItem('activeInboxTab', activeTab);
    applyFilters();
  });

  document.getElementById('statusFilter').addEventListener('change', () => { saveFilters(); applyFilters(); toggleClearButton(); });

  let t;
  document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => { saveFilters(); applyFilters(); toggleClearButton(); }, 150);
  });

  document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);

  pager.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-page]');
    if (!btn) return;
    currentPage = Number(btn.dataset.page);
    renderTable();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  container.addEventListener('click', (e) => {
    const link = e.target.closest('a.ib-open');
    if (!link) return;
    e.preventDefault();
    const { id, type } = link.dataset;
    markInboxViewed(id, type).finally(() => { window.location = link.href; });
  });

  // Activate saved chip
  document.querySelectorAll('#inboxChips .chip').forEach(c => c.classList.toggle('active', c.dataset.status === activeTab));

  loadSavedFilters();
  toggleClearButton();
  loadInboxMemos();
})();
</script>
