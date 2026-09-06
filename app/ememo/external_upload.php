<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>External Letters Management</title>
  <?php require_once 'auth.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<div class="container-fluid py-4">
  <div id="alertPlaceholder"></div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#upload">Upload</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#list">Letters</button></li>
  </ul>

  <div class="tab-content">
    <!-- Upload -->
    <div class="tab-pane fade show active" id="upload">
      <div class="card">
        <div class="card-header bg-success text-white">Upload Letter</div>
        <div class="card-body">
          <form id="uploadForm" class="needs-validation" novalidate>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Reference Number</label><input name="reference_number" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">From</label><input name="received_from" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Received Date</label><input type="date" name="received_date" class="form-control" required></div>
              <div class="col-12"><label>Description (optional)</label><textarea name="description" class="form-control"></textarea></div>
              <div class="col-12"><label>Attach Files</label><input type="file" id="fileInput" class="form-control" multiple><ul id="fileList" class="list-group mt-2"></ul></div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-success w-100" id="submitUpload">Submit</button>
              <div id="uploadProgress" class="progress mt-2 d-none"><div class="progress-bar bg-success" style="width:0%"></div></div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- List -->
    <div class="tab-pane fade" id="list">
      <div class="card">
        <div class="card-header bg-dark text-white">Received Letters</div>
        <div class="card-body" id="lettersTableContainer">Loading...</div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editLetterModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="editLetterForm">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">Edit Letter</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editId">
          <div class="mb-3"><label>Reference</label><input name="reference_number" id="editRef" class="form-control" required></div>
          <div class="mb-3"><label>From</label><input name="received_from" id="editFrom" class="form-control" required></div>
          <div class="mb-3"><label>Title</label><input name="title" id="editTitle" class="form-control" required></div>
          <div class="mb-3"><label>Date</label><input type="date" name="received_date" id="editDate" class="form-control" required></div>
          <div class="mb-3"><label>Description</label><textarea name="description" id="editDesc" class="form-control"></textarea></div>
          <hr>
          <div><strong>Existing Files</strong><ul id="existingFilesList" class="list-group mt-2"></ul></div>
          <div class="mt-3"><label>Add New Files</label><input type="file" id="editFileInput" class="form-control" multiple><ul id="editNewFileList" class="list-group mt-2"></ul></div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Save Changes</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header bg-danger text-white"><h5 class="modal-title">Confirm Delete</h5></div>
      <div class="modal-body">Are you sure you want to delete this letter?</div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewLetterModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info">
        <h5 class="modal-title">Letter Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Letter details will be injected here -->
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function () {
  let allFiles = [], editNewFiles = [], deleteLetterId = null;

  function showAlert(msg, type = 'success') {
    const el = $(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
    $('#alertPlaceholder').append(el);
    setTimeout(() => el.alert('close'), 4000);
  }

  // Upload
  $('#fileInput').on('change', function () {
    [...this.files].forEach(f => {
      if (!allFiles.find(x => x.name === f.name && x.size === f.size)) allFiles.push(f);
    });
    this.value = '';
    $('#fileList').html(allFiles.map(f => `<li class="list-group-item d-flex justify-content-between"><span>📎 ${f.name}</span><span class="text-muted small">${(f.size / 1024).toFixed(1)} KB</span></li>`).join(''));
  });

  $('#uploadForm').on('submit', function (e) {
    e.preventDefault();
    const btn = $('#submitUpload');
    btn.html('<span class="spinner-border spinner-border-sm"></span> Uploading...').prop('disabled', true);
    $('#uploadProgress').removeClass('d-none');
    $('.progress-bar').css('width', '0%');

    const fd = new FormData(this);
    allFiles.forEach(f => fd.append('files[]', f));

    $.ajax({
      xhr: function () {
        let x = new window.XMLHttpRequest();
        x.upload.addEventListener("progress", e => {
          if (e.lengthComputable) $('.progress-bar').css('width', `${(e.loaded / e.total) * 100}%`);
        });
        return x;
      },
      url: 'external_upload_handler.php',
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      success: res => {
        btn.html('Submit').prop('disabled', false);
        $('#uploadProgress').addClass('d-none');
        res.success ? (
          showAlert('✅ Upload successful'),
          $('#uploadForm')[0].reset(),
          allFiles = [], $('#fileList').empty(), loadLetters()
        ) : showAlert('❌ ' + (res.message || 'Upload failed'), 'danger');
      },
      error: () => {
        btn.html('Submit').prop('disabled', false);
        $('#uploadProgress').addClass('d-none');
        showAlert('❌ Network error', 'danger');
      }
    });
  });

  // List Letters
function loadLetters() {
  $.getJSON('external_letters_list.php', function (res) {
    const container = $('#lettersTableContainer');
    if (!res.success || !res.data.length) {
      return container.html('<p class="text-center text-muted">No letters found.</p>');
    }

    // For efficiency we’ll batch‑fetch “last delegation” for all IDs at once:
    const ids = res.data.map(r => r.id);
    $.post('external_last_delegations.php', { ids }, function (delRes) {
      // delRes: { letter_id: { from: "...", to: "...", when: "..." }, ... }
      const rows = res.data.map((r, idx) => {
        // build last‑delegation cell
        const last = delRes[r.id];
        const delHtml = last
          ? `<small title="${last.when}">${last.from} → ${last.to}</small>`
          : `<em class="text-muted">none</em>`;

        // status badge
        const badge = `<span class="badge bg-${r.badge_class}">${r.status_label}</span>`;

        return `
          <tr>
            <td>${idx + 1}</td>
            <td>${r.reference_number}</td>
            <td>
              <span data-bs-toggle="tooltip" title="${r.title}">
                ${r.title.length > 30 ? r.title.slice(0,27) + '…' : r.title}
              </span>
            </td>
            <td>${r.received_from}</td>
            <td>${r.received_date}</td>
            <td>${badge}</td>
            <td>${delHtml}</td>
            <td>
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary view-letter" data-id="${r.id}">
                  View
                </button>
                ${r.status === 'pending' ? `
                  <button class="btn btn-outline-secondary edit-letter" data-id="${r.id}">
                    Edit
                  </button>
                  <button class="btn btn-outline-danger delete-letter" data-id="${r.id}">
                    Delete
                  </button>` : ''}
              </div>
            </td>
          </tr>`;
      }).join('');

      container.html(`
        <div class="table-responsive">
          <table class="table table-sm table-hover table-bordered">
            <thead class="table-dark">
              <tr>
                <th>#</th><th>Ref</th><th>Title</th><th>From</th>
                <th>Date</th><th>Status</th><th>Last Delegation</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      `);

      // init Bootstrap tooltips
      container.find('[data-bs-toggle="tooltip"]').tooltip();
    }, 'json');
  });
}

// reload when the “list” tab is shown
$('button[data-bs-target="#list"]').on('shown.bs.tab', loadLetters);


  // View
$(document).on('click', '.view-letter', function () {
  const id = $(this).data('id');
  $.get('external_letter_view.php', { id }, function (res) {
    if (!res.success) return showAlert('❌ Failed to load', 'danger');

    const d       = res.data;
    const files   = res.files && res.files.length
      ? `<ul class="list-group">
          ${res.files.map(f => `
            <li class="list-group-item d-flex justify-content-between">
              <a href="${f.file_path}" target="_blank">📎 ${f.file_name}</a>
              <span class="text-muted small">${Math.round(f.size_kb)} KB</span>
            </li>
          `).join('')}
        </ul>`
      : '<em>No files</em>';

    // find the last delegation entry in history
    const lastDel = res.history
      .filter(e => e.entry_type === 'delegation')
      .slice(-1)[0];

    const delegationHtml = lastDel
      ? `<p><strong>Last Delegated By:</strong> ${lastDel.from_position} → <strong>To:</strong> ${lastDel.to_position}
         <br><small class="text-muted">${lastDel.when_happened}</small></p>`
      : `<p><strong>Last Delegation:</strong> <em>No delegation yet</em></p>`;

    const modalBody = `
      <p><strong>Reference:</strong> ${d.reference_number}</p>
      <p><strong>Title:</strong> ${d.title}</p>
      <p><strong>Description:</strong><br>${d.description || '<em>(none)</em>'}</p>
      <p><strong>Received From:</strong> ${d.received_from}</p>
      <p><strong>Date Received:</strong> ${d.received_date}</p>
      <p><strong>Status:</strong> ${d.status}</p>
      <p><strong>Files:</strong><br>${files}</p>
      ${delegationHtml}
    `;

    $('#viewLetterModal .modal-body').html(modalBody);
    new bootstrap.Modal(document.getElementById('viewLetterModal')).show();
  }, 'json');
});


  // Edit
  $('#editFileInput').on('change', function () {
    [...this.files].forEach(f => {
      if (!editNewFiles.find(x => x.name === f.name && x.size === f.size)) editNewFiles.push(f);
    });
    this.value = '';
    $('#editNewFileList').html(editNewFiles.map((f, i) => `<li class="list-group-item d-flex justify-content-between">📎 ${f.name}<button class="btn btn-sm btn-link text-danger remove-new-file" data-index="${i}">✖</button></li>`).join(''));
  });

  $(document).on('click', '.remove-new-file', function () {
    editNewFiles.splice($(this).data('index'), 1);
    $(this).closest('li').remove();
  });

  $(document).on('click', '.edit-letter', function () {
    const id = $(this).data('id');
    $.get('external_letter_view.php', { id }, function (res) {
      if (!res.success) return showAlert('❌ Failed to load', 'danger');
      const d = res.data;
      $('#editId').val(d.id); $('#editRef').val(d.reference_number); $('#editTitle').val(d.title);
      $('#editFrom').val(d.received_from); $('#editDate').val(d.received_date); $('#editDesc').val(d.description);
      $('#existingFilesList').html(res.files.map(f => `<li class="list-group-item d-flex justify-content-between"><a href="${f.file_path}" target="_blank">📎 ${f.file_name}</a><button class="btn btn-sm btn-danger remove-old-file" data-file-id="${f.id}">🗑️</button></li>`).join(''));
      editNewFiles = [];
      $('#editNewFileList').empty();
      new bootstrap.Modal(document.getElementById('editLetterModal')).show();

    }, 'json');
  });

  $(document).on('click', '.remove-old-file', function () {
    const btn = $(this);
    const fileId = btn.data('file-id');
    btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);
    $.post('external_letter_file_delete.php', { file_id: fileId }, function (res) {
      res.success ? btn.closest('li').remove() : showAlert('❌ Deletion failed', 'danger');
    }).always(() => btn.prop('disabled', false).html('🗑️'));
  });

  $('#editLetterForm').on('submit', function (e) {
    e.preventDefault();
    const btn = $(this).find('button[type="submit"]');
    btn.html('<span class="spinner-border spinner-border-sm"></span> Saving...').prop('disabled', true);
    const fd = new FormData(this);
    editNewFiles.forEach(f => fd.append('new_files[]', f));
    $.ajax({
      url: 'external_letter_update.php',
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      success: res => {
        btn.html('Save Changes').prop('disabled', false);
        if (res.success) {
          bootstrap.Modal.getInstance($('#editLetterModal')).hide();
          showAlert('✅ Letter updated successfully');
          loadLetters();
        } else showAlert('❌ Update failed', 'danger');
      },
      error: () => {
        btn.html('Save Changes').prop('disabled', false);
        showAlert('❌ Network error', 'danger');
      }
    });
  });

  $(document).on('click', '.delete-letter', function () {
    deleteLetterId = $(this).data('id');
    new bootstrap.Modal('#deleteModal').show();
  });

  $('#confirmDeleteBtn').on('click', function () {
    const btn = $(this);
    btn.html('<span class="spinner-border spinner-border-sm"></span> Deleting...').prop('disabled', true);
    $.post('external_letter_delete.php', { id: deleteLetterId }, function (res) {
      btn.html('Delete').prop('disabled', false);
      if (res.success) {
        bootstrap.Modal.getInstance($('#deleteModal')).hide();
        loadLetters();
        showAlert('✅ Letter deleted');
      } else showAlert('❌ Failed to delete', 'danger');
    });
  });
});
</script>
</body>
</html>
