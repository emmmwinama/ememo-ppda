<?php
require 'auth.php';
?>

<div class="container py-4">
  <h3 class="mb-4">📥 My Inbox</h3>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="inboxTabs">
    <li class="nav-item">
      <button class="nav-link active" data-status="main">
        Main 
        <span class="badge bg-secondary" id="mainCount">0</span>
        <span class="ms-1 text-primary d-none" id="mainNewDot">🔵</span>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-status="escalated">Clarifications <span class="badge bg-warning" id="escalatedCount">0</span></button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-status="pending">Pending <span class="badge bg-primary" id="pendingCount">0</span></button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-status="approved">Approved <span class="badge bg-success" id="approvedCount">0</span></button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-status="returned">Returned <span class="badge bg-danger" id="returnedCount">0</span></button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-status="rejected">Rejected <span class="badge bg-danger" id="rejectedCount">0</span></button>
    </li>
	<li class="nav-item">
   <button class="nav-link" data-status="forwarded">
     Forwarded <span class="badge bg-success" id="forwardedCount">0</span>
   </button>
  </li>
  </ul>

  <!-- Filters -->
  <div class="row mb-3">
    <div class="col-md-4 mb-2">
      <select id="statusFilter" class="form-select">
        <option value="">All Statuses</option>
        <option value="Submitted">Submitted</option>
        <option value="Under Review">Under Review</option>
        <option value="Endorsed">Endorsed</option>
        <option value="Approved">Approved</option>
        <option value="Rejected">Rejected</option>
        <option value="Returned">Returned</option>
        <option value="Finalized">Finalized</option>
      </select>
    </div>

    <div class="col-md-6 mb-2">
      <input type="text" id="searchInput" class="form-control" placeholder="Search by Subject or Reference...">
    </div>

    <div class="col-md-2 mb-2 d-grid">
      <button class="btn btn-outline-secondary d-none" id="clearFiltersBtn">Clear Filters</button>
    </div>
  </div>

  <!-- Inbox Table -->
  <div id="inboxTableContainer">
    <div class="text-center">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>
  </div>

  <!-- Pagination Controls -->
  <div class="mt-4 d-flex justify-content-center" id="paginationContainer"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const validTabs = ['main','escalated','pending','approved','returned','rejected','forwarded'];
let activeTab = localStorage.getItem('activeInboxTab') || 'main';
if (!validTabs.includes(activeTab)) {
  activeTab = 'main';
  localStorage.setItem('activeInboxTab', activeTab);
}

let groupedMemos = {};    // { main: [...], escalated: [...], … }
let filteredMemos = [];
let currentPage = 1;
const memosPerPage = 10;

document.addEventListener('DOMContentLoaded', () => {
  loadInboxMemos();

  // Tab clicks
  document.querySelectorAll('#inboxTabs button').forEach(tab => {
    const status = tab.dataset.status;
    tab.classList.toggle('active', status === activeTab);
    tab.addEventListener('click', () => {
      document.querySelectorAll('#inboxTabs button').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeTab = status;
      localStorage.setItem('activeInboxTab', activeTab);
      applyFilters();
    });
  });

  // Filters
  document.getElementById('statusFilter')
    .addEventListener('change', () => { saveFilters(); applyFilters(); toggleClearButton(); });
  document.getElementById('searchInput')
    .addEventListener('input',  () => { saveFilters(); applyFilters(); toggleClearButton(); });
  document.getElementById('clearFiltersBtn')
    .addEventListener('click', confirmClearFilters);
  loadSavedFilters();
});

function loadInboxMemos() {
  fetch('get_inbox.php')
    .then(r => r.json())
    .then(({status, data, message}) => {
      if (status !== 'success') {
        return document.getElementById('inboxTableContainer').innerHTML =
          `<div class="alert alert-danger">${message}</div>`;
      }
      groupedMemos = data;       // now holds main, escalated, etc.
      updateTabCounts();
      applyFilters();
    })
    .catch(err => {
      console.error('Inbox fetch error:', err);
      document.getElementById('inboxTableContainer').innerHTML =
        `<div class="alert alert-danger">Failed to load inbox: ${err.message}</div>`;
    });
}

function applyFilters() {
  const statusFilter = (document.getElementById('statusFilter').value || '').toLowerCase();
  const searchFilter = (document.getElementById('searchInput').value || '').toLowerCase();

  const base = groupedMemos[activeTab] || [];
  filteredMemos = base.filter(memo => {
    const s = memo.status.toLowerCase();
    const matchesStatus = !statusFilter || s === statusFilter;
    const matchesSearch = !searchFilter ||
      memo.subject.toLowerCase().includes(searchFilter) ||
      memo.memo_id.toLowerCase().includes(searchFilter);
    return matchesStatus && matchesSearch;
  });

  currentPage = 1;
  renderTable(filteredMemos);
}

function updateTabCounts() {
  document.getElementById('mainCount').textContent      = (groupedMemos.main      || []).length;
  document.getElementById('escalatedCount').textContent = (groupedMemos.escalated || []).length;
  document.getElementById('pendingCount').textContent   = (groupedMemos.pending   || []).length;
  document.getElementById('approvedCount').textContent  = (groupedMemos.approved  || []).length;
  document.getElementById('returnedCount').textContent  = (groupedMemos.returned  || []).length;
  document.getElementById('rejectedCount').textContent  = (groupedMemos.rejected  || []).length;
  document.getElementById('forwardedCount').textContent = (groupedMemos.forwarded || []).length;

  const hasUnread = (groupedMemos.main || []).some(m => !m.viewed);
  document.getElementById('mainNewDot')
    .classList.toggle('d-none', !hasUnread);
}

function renderTable(memos) {
  const container = document.getElementById('inboxTableContainer');
  const pager     = document.getElementById('paginationContainer');

  if (memos.length === 0) {
    container.innerHTML = `<div class="alert alert-info">No memos match your criteria.</div>`;
    pager.innerHTML     = '';
    return;
  }

  // paginate
  const start = (currentPage - 1) * memosPerPage;
  const page  = memos.slice(start, start + memosPerPage);

  // build rows, using "m" consistently
  const rowsHtml = page.map(m => `
    <tr${!m.viewed ? ' class="table-warning"' : ''}>
      <td>${m.memo_id}</td>
      <td>
        ${m.subject}
        ${!m.viewed ? '<span class="badge bg-info ms-2">New</span>' : ''}
      </td>
      <td><span class="text-muted">${m.type}</span></td>
      <td><span class="badge bg-${getStatusColor(m.status)}">${m.status}</span></td>
      <td>${new Date(m.created_at).toLocaleDateString()}</td>
      <td>
        <a
          href="${m.type === 'Direct Memo' ? 'view_direct_memo.php' : 'view_memo.php'}?memo_id=${m.id}"
          class="btn btn-sm ${m.viewed ? 'btn-outline-primary' : 'btn-primary'}"
          onclick="event.preventDefault();
                   markInboxViewed(${m.id}, '${m.type}')
                     .then(() => { window.location = this.href; })
                     .catch(() => { window.location = this.href; });">
          📄 Open
        </a>
      </td>
    </tr>
  `).join('');

  // render table
  container.innerHTML = `
    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Reference</th>
            <th>Subject</th>
            <th>Type</th>
            <th>Status</th>
            <th>Date Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          ${rowsHtml}
        </tbody>
      </table>
    </div>
  `;

  // pagination
  const totalPages = Math.ceil(memos.length / memosPerPage);
  if (totalPages <= 1) {
    pager.innerHTML = '';
    return;
  }
  let pagerHtml = '';
  for (let i = 1; i <= totalPages; i++) {
    pagerHtml += `
      <button
        class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline-primary'} me-1"
        onclick="goToPage(${i})">
        ${i}
      </button>
    `;
  }
  pager.innerHTML = pagerHtml;
}


function renderPagination(total) {
  const totalPages = Math.ceil(total / memosPerPage);
  if (totalPages <= 1) {
    document.getElementById('paginationContainer').innerHTML = '';
    return;
  }
  let html = '';
  for (let i = 1; i <= totalPages; i++) {
    html += `<button class="btn btn-sm ${i===currentPage?'btn-primary':'btn-outline-primary'} me-1"
                     onclick="goToPage(${i})">${i}</button>`;
  }
  document.getElementById('paginationContainer').innerHTML = html;
}

function goToPage(p) {
  currentPage = p;
  renderTable(filteredMemos);
}

function markInboxViewed(id, memoType) {
  return fetch('mark_inbox_viewed.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      memo_id:   id,
      memo_type: memoType === 'Direct Memo' ? 'Direct' : 'Workflow'
    })
  });
}


function getStatusColor(status) {
  const s = status.toLowerCase();

  // ▶︎ New: all forwarded statuses get green
  if (s.startsWith('forwarded:')) {
    return 'success';
  }

  switch (s) {
    case 'draft':          return 'secondary';
    case 'submitted':      return 'primary';
    case 'under review':   return 'warning';
    case 'endorsed':       return 'info';
    case 'approved':       return 'success';
    case 'rejected':       return 'danger';
    case 'returned':       return 'danger';
    case 'escalated':      return 'warning';
    case 'finalized':      return 'dark';
    default:               return 'light';
  }
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
    applyFilters();
    toggleClearButton();
  } catch {}
}

function toggleClearButton() {
  const show = !!(
    document.getElementById('statusFilter').value ||
    document.getElementById('searchInput').value
  );
  document.getElementById('clearFiltersBtn')
    .classList.toggle('d-none', !show);
}

function confirmClearFilters() {
  Swal.fire({
    title: 'Clear All Filters?',
    text: "This will reset all search and status filters.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, Clear'
  }).then(r => {
    if (r.isConfirmed) {
      clearFilters();
      Swal.fire('Cleared!','All filters reset.','success');
    }
  });
}

function clearFilters() {
  document.getElementById('statusFilter').value = '';
  document.getElementById('searchInput').value = '';
  localStorage.removeItem('inboxFilters');
  toggleClearButton();
  applyFilters();
}
</script>





 
