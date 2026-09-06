<?php

require 'auth.php';

?>
<div class="card shadow-sm">
  <div class="card-body">
    <h5 class="card-title mb-3">📁 Approved Memos Archive</h5>

    <!-- Filters -->
    <div class="row mb-3">
      <div class="col-md-4">
        <select id="yearFilter" class="form-select">
          <option value="">All Years</option>
        </select>
      </div>
      <div class="col-md-6">
        <input type="text" id="searchInput" class="form-control" placeholder="Search by subject or reference...">
      </div>
    </div>

    <!-- Table -->
    <div id="archiveTableContainer">
      <div class="text-center py-4">
        <div class="spinner-border text-success" role="status"></div>
      </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4 d-flex justify-content-center" id="paginationContainer"></div>
  </div>
</div>

<script>
let allMemos = [];
let filteredMemos = [];
let currentPage = 1;
const memosPerPage = 10;

document.addEventListener('DOMContentLoaded', () => {
  loadArchiveMemos();

  document.getElementById('searchInput').addEventListener('input', applyFilters);
  document.getElementById('yearFilter').addEventListener('change', applyFilters);
});

function loadArchiveMemos() {
  fetch('get_archive.php')
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'success') {
        document.getElementById('archiveTableContainer').innerHTML =
          `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }

      allMemos = data.data;
      populateYearFilter(allMemos);
      applyFilters();
    })
    .catch(err => {
      document.getElementById('archiveTableContainer').innerHTML =
        `<div class="alert alert-danger">Failed to load archive: ${err.message}</div>`;
    });
}

function populateYearFilter(memos) {
  const years = [...new Set(memos.map(m => new Date(m.created_at).getFullYear()))].sort((a, b) => b - a);
  const select = document.getElementById('yearFilter');
  years.forEach(y => {
    const option = document.createElement('option');
    option.value = y;
    option.textContent = y;
    select.appendChild(option);
  });
}

function applyFilters() {
  const search = document.getElementById('searchInput').value.toLowerCase();
  const year = document.getElementById('yearFilter').value;

  filteredMemos = allMemos.filter(memo => {
    const matchesSearch = !search || memo.subject.toLowerCase().includes(search) || memo.memo_id.toLowerCase().includes(search);
    const matchesYear = !year || new Date(memo.created_at).getFullYear().toString() === year;
    return matchesSearch && matchesYear;
  });

  currentPage = 1;
  renderTable();
  renderPagination();
}

function renderTable() {
  const start = (currentPage - 1) * memosPerPage;
  const pageMemos = filteredMemos.slice(start, start + memosPerPage);

  if (pageMemos.length === 0) {
    document.getElementById('archiveTableContainer').innerHTML =
      `<div class="alert alert-info">No memos match your criteria.</div>`;
    return;
  }

  const rows = pageMemos.map(memo => `
    <tr>
      <td>${memo.memo_id}</td>
      <td>${memo.subject}</td>
      <td><span class="badge bg-success">Approved</span></td>
      <td>${new Date(memo.created_at).toLocaleDateString()}</td>
      <td><a href="view_memo.php?memo_id=${memo.id}" class="btn btn-sm btn-outline-primary">📄 View</a></td>
    </tr>
  `).join('');

  document.getElementById('archiveTableContainer').innerHTML = `
    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>Reference</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Date Approved</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
  `;
}

function renderPagination() {
  const totalPages = Math.ceil(filteredMemos.length / memosPerPage);
  const container = document.getElementById('paginationContainer');
  if (totalPages <= 1) {
    container.innerHTML = '';
    return;
  }

  let html = '';
  for (let i = 1; i <= totalPages; i++) {
    html += `<button class="btn btn-sm ${i === currentPage ? 'btn-success' : 'btn-outline-success'} me-1" onclick="goToPage(${i})">${i}</button>`;
  }
  container.innerHTML = html;
}

function goToPage(page) {
  currentPage = page;
  renderTable();
  renderPagination();
}
</script>


