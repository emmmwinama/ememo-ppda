<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Memo Endorsements</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid px-0">
  <div class="row">
    <div class="col-12">
      <div class="mb-4">
        <input type="text" id="endorsementSearch" class="form-control form-control-lg" placeholder="Search endorsements...">
      </div>

     <ul class="nav nav-tabs mb-4" id="endorsementTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-new-tab" data-bs-toggle="tab" data-bs-target="#tab-new" type="button" role="tab" aria-controls="tab-new" aria-selected="true">
      New Requests <span class="badge bg-secondary">0</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-endorsed-tab" data-bs-toggle="tab" data-bs-target="#tab-endorsed" type="button" role="tab" aria-controls="tab-endorsed" aria-selected="false">
      Endorsed <span class="badge bg-info">0</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-returned-tab" data-bs-toggle="tab" data-bs-target="#tab-returned" type="button" role="tab" aria-controls="tab-returned" aria-selected="false">
      Returned <span class="badge bg-danger">0</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-escalated-tab" data-bs-toggle="tab" data-bs-target="#tab-escalated" type="button" role="tab" aria-controls="tab-escalated" aria-selected="false">
      Escalations <span class="badge bg-warning">0</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-approved-tab" data-bs-toggle="tab" data-bs-target="#tab-approved" type="button" role="tab" aria-controls="tab-approved" aria-selected="false">
      Approved <span class="badge bg-success">0</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-rejected-tab" data-bs-toggle="tab" data-bs-target="#tab-rejected" type="button" role="tab" aria-controls="tab-rejected" aria-selected="false">
      Rejected <span class="badge bg-danger">0</span>
    </button>
  </li>
</ul>

<!-- ✅ All tab contents here correctly -->
<div class="tab-content" id="endorsementTabsContent">
  <div class="tab-pane fade show active" id="tab-new" role="tabpanel" aria-labelledby="tab-new-tab">
    <div class="list-group" id="list-new"></div>
  </div>
  <div class="tab-pane fade" id="tab-endorsed" role="tabpanel" aria-labelledby="tab-endorsed-tab">
    <div class="list-group" id="list-endorsed"></div>
  </div>
  <div class="tab-pane fade" id="tab-returned" role="tabpanel" aria-labelledby="tab-returned-tab">
    <div class="list-group" id="list-returned"></div>
  </div>
  <div class="tab-pane fade" id="tab-escalated" role="tabpanel" aria-labelledby="tab-escalated-tab">
    <div class="list-group" id="list-escalated"></div>
  </div>
  <div class="tab-pane fade" id="tab-approved" role="tabpanel" aria-labelledby="tab-approved-tab">
    <div class="list-group" id="list-approved"></div>
  </div>
  <div class="tab-pane fade" id="tab-rejected" role="tabpanel" aria-labelledby="tab-rejected-tab">
    <div class="list-group" id="list-rejected"></div>
  </div>
</div>


      <div class="text-center mt-4">
        <div class="spinner-border text-primary d-none" id="loadingSpinner"></div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  loadEndorsements();
  document.getElementById('endorsementSearch').addEventListener('input', filterEndorsements);

  setInterval(() => {
    if (document.getElementById('endorsementSearch').value.trim() === '') {
      loadEndorsements();
    }
  }, 30000);
});

let allEndorsements = [];

function loadEndorsements() {
  const spinner = document.getElementById('loadingSpinner');
  spinner.classList.remove('d-none');

  fetch('get_endorsements.php')
    .then(res => res.json())
    .then(data => {
      spinner.classList.add('d-none');
      if (data.status !== 'success') return;

      if (JSON.stringify(allEndorsements) !== JSON.stringify(data.data)) {
        allEndorsements = data.data;
        renderEndorsements(allEndorsements);
      }
    })
    .catch(err => {
      spinner.classList.add('d-none');
      console.error("Error loading endorsements: " + err.message);
    });
}

function renderEndorsements(items) {
  const tabs = {
    'pending': { el: document.getElementById('list-new'), badge: 'tab-new', count: 0 },
    'endorsed': { el: document.getElementById('list-endorsed'), badge: 'tab-endorsed', count: 0 },
    'escalated': { el: document.getElementById('list-escalated'), badge: 'tab-escalated', count: 0 },
    'approved': { el: document.getElementById('list-approved'), badge: 'tab-approved', count: 0 },
    'rejected': { el: document.getElementById('list-rejected'), badge: 'tab-rejected', count: 0 },
    'returned': { el: document.getElementById('list-returned'), badge: 'tab-returned', count: 0 }, // ✅ Add Returned tab properly
  };

  // Clear all tabs first
  Object.values(tabs).forEach(t => t.el.innerHTML = '');

  items.forEach(item => {
    let status = item.endorsement_status?.trim().toLowerCase() || 'pending'; 

    if (item.memo_status && item.memo_status.toLowerCase() === 'returned') {
      status = 'returned';
    }

    const tab = tabs[status];
    if (!tab) return;

    tab.el.innerHTML += `
      <a href="view_memo.php?memo_id=${item.id}" 
         class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
        <div class="flex-grow-1">
          <div class="fw-semibold">${item.subject}
            ${status === 'returned' && item.return_comment ? `
              <span class="badge bg-warning ms-2" data-bs-toggle="tooltip" title="${item.return_comment}">
                📝 Returned
              </span>
            ` : ''}
          </div>
          <small class="text-muted">${item.memo_id} · ${item.sender_name} · ${item.stage || 'N/A'}</small>
        </div>
        <span class="badge bg-${getStatusColor(status)}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
      </a>
    `;
    tab.count++;
  });

  // Update badge counts for each tab
  for (const [key, { count, badge }] of Object.entries(tabs)) {
    const tabBtn = document.querySelector(`button[data-bs-target="#${badge}"] .badge`);
    if (tabBtn) tabBtn.textContent = count;
  }

  // Activate Bootstrap tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });
}


function filterEndorsements() {
  const query = document.getElementById('endorsementSearch').value.toLowerCase();
  const filtered = allEndorsements.filter(item =>
    item.subject.toLowerCase().includes(query) ||
    item.memo_id.toLowerCase().includes(query) ||
    item.sender_name.toLowerCase().includes(query)
  );
  renderEndorsements(filtered);
}

function getStatusColor(status) {
  switch ((status || '').toLowerCase()) {
    case 'draft': return 'secondary';
    case 'submitted': return 'primary';
    case 'pending': return 'secondary';
    case 'under review': return 'warning';
    case 'endorsed': return 'info';
    case 'approved': return 'success';
    case 'rejected': return 'danger';
    case 'returned': return 'danger';
    case 'escalated': return 'warning';
    case 'finalized': return 'dark';
    default: return 'light';
  }
}
</script>

</body>
</html>
