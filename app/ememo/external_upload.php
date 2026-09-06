<?php
// external_upload.php — rendered inside index.php (header.php loads Bootstrap, Icons, theme.css)
require_once 'auth.php';
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>
  .eu-tabs { display: flex; gap: .5rem; margin-bottom: 1.3rem; border: none; }
  .eu-tabs .nav-link {
    border: 1px solid var(--border); background: var(--surface);
    border-radius: var(--radius-pill);
    padding: .35rem .95rem; font-size: .82rem; font-weight: 600; color: var(--muted);
  }
  .eu-tabs .nav-link:hover { color: var(--text); border-color: var(--border-strong, #cbd5e1); }
  .eu-tabs .nav-link.active { background: var(--brand); border-color: var(--brand); color: #fff; }
</style>

<div class="f-head">
  <h1 class="f-title">Upload Files</h1>
  <p class="f-subtitle">Register incoming external letters and their attachments</p>
</div>

<div id="alertPlaceholder"></div>

<ul class="nav eu-tabs">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#upload"><i class="bi bi-cloud-upload me-1"></i>Upload</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#list"><i class="bi bi-inbox me-1"></i>Letters</button></li>
</ul>

<div class="tab-content">
  <!-- Upload -->
  <div class="tab-pane fade show active" id="upload">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-file-earmark-arrow-up"></i> Register a letter</div>
      <div class="f-panel-body">
        <form id="uploadForm" class="needs-validation" novalidate>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Reference Number</label><input name="reference_number" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">From</label><input name="received_from" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Received Date</label><input type="date" name="received_date" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label><textarea name="description" class="form-control"></textarea></div>
            <div class="col-12"><label class="form-label">Attach files</label><input type="file" id="fileInput" class="form-control" multiple><ul id="fileList" class="list-group mt-2"></ul></div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-success w-100" id="submitUpload"><i class="bi bi-check-lg me-1"></i>Submit</button>
            <div id="uploadProgress" class="progress mt-2 d-none"><div class="progress-bar bg-success" style="width:0%"></div></div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- List -->
  <div class="tab-pane fade" id="list">
    <div id="lettersTableContainer">
      <div class="f-state"><div class="spinner-border spinner-border-sm" role="status"></div><p class="mt-2">Loading letters…</p></div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editLetterModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="editLetterForm">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit letter</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editId">
          <div class="mb-3"><label class="form-label">Reference</label><input name="reference_number" id="editRef" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">From</label><input name="received_from" id="editFrom" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Title</label><input name="title" id="editTitle" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Date</label><input type="date" name="received_date" id="editDate" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="editDesc" class="form-control"></textarea></div>
          <hr>
          <div><strong>Existing files</strong><ul id="existingFilesList" class="list-group mt-2"></ul></div>
          <div class="mt-3"><label class="form-label">Add new files</label><input type="file" id="editFileInput" class="form-control" multiple><ul id="editNewFileList" class="list-group mt-2"></ul></div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-success w-100">Save changes</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header"><h5 class="modal-title">Confirm delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">Are you sure you want to delete this letter?</div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewLetterModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Letter details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>

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
    $('#fileList').html(allFiles.map(f => `<li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-paperclip me-1"></i>${f.name}</span><span class="text-muted small">${(f.size / 1024).toFixed(1)} KB</span></li>`).join(''));
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
          showAlert('Upload successful'),
          $('#uploadForm')[0].reset(),
          allFiles = [], $('#fileList').empty(), loadLetters()
        ) : showAlert((res.message || 'Upload failed'), 'danger');
      },
      error: () => {
        btn.html('Submit').prop('disabled', false);
        $('#uploadProgress').addClass('d-none');
        showAlert('Network error', 'danger');
      }
    });
  });

  // List Letters
  function loadLetters() {
    $.getJSON('external_letters_list.php', function (res) {
      const container = $('#lettersTableContainer');
      if (!res.success || !res.data.length) {
        return container.html('<div class="f-state"><i class="bi bi-inbox"></i><p>No letters found.</p></div>');
      }

      const ids = res.data.map(r => r.id);
      $.post('external_last_delegations.php', { ids }, function (delRes) {
        const rows = res.data.map((r, idx) => {
          const last = delRes[r.id];
          const delHtml = last
            ? `<small title="${last.when}">${last.from} → ${last.to}</small>`
            : `<em class="text-muted">none</em>`;
          const badge = `<span class="badge bg-${r.badge_class}">${r.status_label}</span>`;

          return `
            <tr>
              <td class="text-muted">${idx + 1}</td>
              <td class="font-monospace">${r.reference_number}</td>
              <td class="fw-semibold">
                <span data-bs-toggle="tooltip" title="${r.title}">
                  ${r.title.length > 30 ? r.title.slice(0, 27) + '…' : r.title}
                </span>
              </td>
              <td class="text-muted">${r.received_from}</td>
              <td class="text-muted">${r.received_date}</td>
              <td>${badge}</td>
              <td>${delHtml}</td>
              <td>
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-outline-success view-letter" data-id="${r.id}">View</button>
                  ${r.status === 'pending' ? `
                    <button class="btn btn-outline-secondary edit-letter" data-id="${r.id}">Edit</button>
                    <button class="btn btn-outline-danger delete-letter" data-id="${r.id}">Delete</button>` : ''}
                </div>
              </td>
            </tr>`;
        }).join('');

        container.html(`
          <div class="f-panel table-responsive">
            <table class="table table-borderless f-table align-middle">
              <thead><tr>
                <th>#</th><th>Ref</th><th>Title</th><th>From</th><th>Date</th><th>Status</th><th>Last delegation</th><th>Actions</th>
              </tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </div>`);

        container.find('[data-bs-toggle="tooltip"]').tooltip();
      }, 'json');
    });
  }

  $('button[data-bs-target="#list"]').on('shown.bs.tab', loadLetters);

  // View
  $(document).on('click', '.view-letter', function () {
    const id = $(this).data('id');
    $.get('external_letter_view.php', { id }, function (res) {
      if (!res.success) return showAlert('Failed to load', 'danger');
      const d = res.data;
      const files = res.files && res.files.length
        ? `<ul class="list-group">
            ${res.files.map(f => `
              <li class="list-group-item d-flex justify-content-between">
                <a href="${f.file_path}" target="_blank"><i class="bi bi-paperclip me-1"></i>${f.file_name}</a>
                <span class="text-muted small">${Math.round(f.size_kb)} KB</span>
              </li>`).join('')}
          </ul>`
        : '<em>No files</em>';

      const lastDel = res.history.filter(e => e.entry_type === 'delegation').slice(-1)[0];
      const delegationHtml = lastDel
        ? `<p><strong>Last delegated by:</strong> ${lastDel.from_position} → <strong>To:</strong> ${lastDel.to_position}
           <br><small class="text-muted">${lastDel.when_happened}</small></p>`
        : `<p><strong>Last delegation:</strong> <em>No delegation yet</em></p>`;

      $('#viewLetterModal .modal-body').html(`
        <p><strong>Reference:</strong> ${d.reference_number}</p>
        <p><strong>Title:</strong> ${d.title}</p>
        <p><strong>Description:</strong><br>${d.description || '<em>(none)</em>'}</p>
        <p><strong>Received from:</strong> ${d.received_from}</p>
        <p><strong>Date received:</strong> ${d.received_date}</p>
        <p><strong>Status:</strong> ${d.status}</p>
        <p><strong>Files:</strong><br>${files}</p>
        ${delegationHtml}
      `);
      new bootstrap.Modal(document.getElementById('viewLetterModal')).show();
    }, 'json');
  });

  // Edit
  $('#editFileInput').on('change', function () {
    [...this.files].forEach(f => {
      if (!editNewFiles.find(x => x.name === f.name && x.size === f.size)) editNewFiles.push(f);
    });
    this.value = '';
    $('#editNewFileList').html(editNewFiles.map((f, i) => `<li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-paperclip me-1"></i>${f.name}</span><button class="btn btn-sm btn-link text-danger remove-new-file" data-index="${i}">✕</button></li>`).join(''));
  });

  $(document).on('click', '.remove-new-file', function () {
    editNewFiles.splice($(this).data('index'), 1);
    $(this).closest('li').remove();
  });

  $(document).on('click', '.edit-letter', function () {
    const id = $(this).data('id');
    $.get('external_letter_view.php', { id }, function (res) {
      if (!res.success) return showAlert('Failed to load', 'danger');
      const d = res.data;
      $('#editId').val(d.id); $('#editRef').val(d.reference_number); $('#editTitle').val(d.title);
      $('#editFrom').val(d.received_from); $('#editDate').val(d.received_date); $('#editDesc').val(d.description);
      $('#existingFilesList').html(res.files.map(f => `<li class="list-group-item d-flex justify-content-between"><a href="${f.file_path}" target="_blank"><i class="bi bi-paperclip me-1"></i>${f.file_name}</a><button class="btn btn-sm btn-danger remove-old-file" data-file-id="${f.id}"><i class="bi bi-trash"></i></button></li>`).join(''));
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
      res.success ? btn.closest('li').remove() : showAlert('Deletion failed', 'danger');
    }).always(() => btn.prop('disabled', false).html('<i class="bi bi-trash"></i>'));
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
        btn.html('Save changes').prop('disabled', false);
        if (res.success) {
          bootstrap.Modal.getInstance($('#editLetterModal')).hide();
          showAlert('Letter updated successfully');
          loadLetters();
        } else showAlert('Update failed', 'danger');
      },
      error: () => {
        btn.html('Save changes').prop('disabled', false);
        showAlert('Network error', 'danger');
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
        showAlert('Letter deleted');
      } else showAlert('Failed to delete', 'danger');
    });
  });
});
</script>
