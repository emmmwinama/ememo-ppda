<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Memos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../../assets/css/theme.css" rel="stylesheet">

  <style>
    h3 {
      font-weight: 600;
    }
    .returned-comment {
      background: #f8d7da;
      border-left: 5px solid var(--danger);
      padding: 1rem;
      margin-top: 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.95rem;
    }
    .returned-comment strong {
      color: #842029;
    }
    .returned-comment small {
      color: var(--muted);
    }
    .returned-comment p {
      margin-top: 0.5rem;
      margin-bottom: 0;
      color: var(--text);
    }
    .nav-tabs .nav-link {
      font-weight: 500;
      border: none;
      border-bottom: 3px solid transparent;
    }
    .nav-tabs .nav-link.active {
      border-color: var(--brand);
      background-color: transparent;
    }
    .card-body h5 {
      font-weight: 500;
    }
  </style>
</head>

<body>

<div class="container py-5">
  <h3 class="mb-4 text-primary">📂 My Memos</h3>

  <!-- Search & Sort -->
  <div class="mb-4">
    <input type="text" id="memoSearch" class="form-control form-control-lg" placeholder="Search memos by subject or reference...">
  </div>

  <div class="mb-4 d-flex justify-content-between align-items-center">
    <div class="text-muted">Sort by:</div>
    <select id="sortSelect" class="form-select form-select-sm w-auto">
      <option value="newest" selected>Newest First</option>
      <option value="oldest">Oldest First</option>
    </select>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4" id="memoTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#draft">Draft <span class="badge bg-secondary" id="count-draft">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#submitted">Submitted <span class="badge bg-primary" id="count-submitted">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#under-review">Under Review <span class="badge bg-warning" id="count-under-review">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#endorsed">Endorsed <span class="badge bg-info" id="count-endorsed">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#approved">Approved <span class="badge bg-success" id="count-approved">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rejected">Rejected <span class="badge bg-danger" id="count-rejected">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#returned">Returned <span class="badge bg-danger" id="count-returned">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#finalized">Finalized <span class="badge bg-dark" id="count-finalized">0</span></button></li>
  </ul>

  <!-- Memo Containers -->
  <div class="tab-content">
    <div class="tab-pane fade show active" id="draft"><div class="row g-4" id="draftMemosContainer"></div></div>
    <div class="tab-pane fade" id="submitted"><div class="row g-4" id="submittedMemosContainer"></div></div>
    <div class="tab-pane fade" id="under-review"><div class="row g-4" id="underReviewMemosContainer"></div></div>
    <div class="tab-pane fade" id="endorsed"><div class="row g-4" id="endorsedMemosContainer"></div></div>
    <div class="tab-pane fade" id="approved"><div class="row g-4" id="approvedMemosContainer"></div></div>
    <div class="tab-pane fade" id="rejected"><div class="row g-4" id="rejectedMemosContainer"></div></div>
    <div class="tab-pane fade" id="returned"><div class="row g-4" id="returnedMemosContainer"></div></div>
    <div class="tab-pane fade" id="finalized"><div class="row g-4" id="finalizedMemosContainer"></div></div>
  </div>
</div>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let allMemos = [];

function getStatusColor(status) {
  switch (status) {
    case 'Draft': return 'secondary';
    case 'Submitted': return 'primary';
    case 'Under Review': return 'warning';
    case 'Endorsed': return 'info';
    case 'Approved': return 'success';
    case 'Rejected': return 'danger';
    case 'Returned': return 'danger';
    case 'Finalized': return 'dark';
    default: return 'light';
  }
}

function renderMemos(memos) {
  const containers = {
    'Draft': document.getElementById('draftMemosContainer'),
    'Submitted': document.getElementById('submittedMemosContainer'),
    'Under Review': document.getElementById('underReviewMemosContainer'),
    'Endorsed': document.getElementById('endorsedMemosContainer'),
    'Approved': document.getElementById('approvedMemosContainer'),
    'Rejected': document.getElementById('rejectedMemosContainer'),
    'Returned': document.getElementById('returnedMemosContainer'),
    'Finalized': document.getElementById('finalizedMemosContainer')
  };

  // Clear
  Object.values(containers).forEach(c => { if (c) c.innerHTML = ''; });

  const counts = {
    'Draft': 0, 'Submitted': 0, 'Under Review': 0,
    'Endorsed': 0, 'Approved': 0, 'Rejected': 0, 'Returned': 0, 'Finalized': 0
  };

  memos.forEach(memo => {
    const container = containers[memo.status];
    if (!container) return;

    counts[memo.status]++;

    container.innerHTML += `
      <div class="col-12">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">${memo.subject}</h5>
            <h6 class="card-subtitle mb-2 text-muted">${memo.memo_id}</h6>
            <p class="card-text mb-2">
              <span class="badge bg-${getStatusColor(memo.status)}">${memo.status}</span>
              <span class="ms-2"><strong>Stage:</strong> ${memo.stage || 'N/A'}</span>
            </p>
${(memo.status === 'Draft' || memo.status === 'Returned') ? `
  <div class="returned-comment">
    ${memo.status === 'Returned' && memo.return_comment ? `
      <strong>🔄 Returned by ${memo.returned_by || 'Unknown'}</strong><br>
      <small>${memo.return_time ? new Date(memo.return_time).toLocaleString() : 'Unknown time'}</small>
      <p class="mt-2"><i class="bi bi-chat-left-text-fill text-danger me-2"></i>${memo.return_comment}</p>
    ` : ''}
    <div class="mt-3">
      <a href="edit_memo.php?memo_id=${memo.id}" class="btn btn-sm btn-outline-warning me-2">✏️ Edit Memo</a>
      <a href="view_memo.php?memo_id=${memo.id}" class="btn btn-sm btn-outline-primary">📄 View Memo</a>
    </div>
  </div>
` : `
  <div class="mt-3">
    <a href="view_memo.php?memo_id=${memo.id}" class="btn btn-sm btn-outline-primary">📄 View Memo</a>
  </div>
`}

          </div>
          
        </div>
      </div>
    `;
  });

  // Update badge counts
  for (const status in counts) {
    const badge = document.getElementById(`count-${status.toLowerCase().replace(/\s+/g, '-')}`);
    if (badge) {
      badge.textContent = counts[status];
    }
  }
}

function loadMemos() {
  fetch('get_my_memos.php')
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        allMemos = data.data;
        renderMemos(allMemos);
      }
    })
    .catch(err => {
      console.error('Failed to load memos', err);
    });
}

document.addEventListener('DOMContentLoaded', () => {
  loadMemos();
});
</script>

</body>
</html>
