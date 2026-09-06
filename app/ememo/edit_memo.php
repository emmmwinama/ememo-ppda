<?php require_once 'auth.php'; ?>
<?php
if (!isset($_GET['memo_id'])) {
  die('Memo ID is missing.');
}
$memo_id = intval($_GET['memo_id']);
?>
<?php require_once 'header.php'; ?>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>

  <style>
    .badge-user {
      display: inline-flex;
      align-items: center;
      margin-right: 5px;
      margin-bottom: 5px;
      padding: 0.5em;
      background-color: var(--brand);
      color: white;
      border-radius: var(--radius-sm);
    }
    .badge-user button {
      background: none;
      border: none;
      color: white;
      margin-left: 8px;
      cursor: pointer;
    }
    #signaturePreview {
      max-width: 300px;
      max-height: 120px;
      border: 1px solid var(--border);
      padding: 5px;
      background: var(--bg);
    }
  </style>

<div class="container py-5">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Memo</h3>
    <a href="index.php?module=my_memos" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to My Memos</a>
  </div>

  <form id="editMemoForm" class="border rounded-3 bg-white p-5 shadow-sm" style="max-width: 900px; margin: auto;" enctype="multipart/form-data">
   <!-- Inside your <form id="editMemoForm" ...> -->
   
   <!-- HIDDEN LETTER FIELDS (always inside the form) -->
  <input type="hidden" name="letter_content" id="letter_content">
  <input type="hidden" name="letter_date"    id="letter_date">
  <input type="hidden" name="letter_ref_no"  id="letter_ref_no">
  <input type="hidden" name="letter_subject" id="letter_subject">
  <input type="hidden" name="letter_recipients[position][]" value="">
  <input type="hidden" name="letter_recipients[address][]"  value="">


<!-- Company Header inside form -->
<div class="text-center mb-4">
  <img src="header.png" alt="Memo Header" style="max-width: 100%; height: auto;">
</div>

<!-- Memo Title -->
<h4 id="memoTitle" class="text-center text-decoration-underline fw-bold mb-4" style="letter-spacing: 0.5px;">INTERNAL MEMORANDUM</h4>

<!-- Communication Type, Memo ID, Date -->
<div class="row mb-4">
  <div class="col-md-4">
    <label class="form-label fw-semibold">Communication Type <span class="text-danger">*</span></label>
    <select class="form-select" name="communication_type" required>
      <option value="">Select</option>
      <option value="Loose Minute">Loose Minute</option>
      <option value="Memorandum">Memorandum</option>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label fw-semibold">Reference (Memo ID)</label>
    <input type="text" name="memo_id" class="form-control">
  </div>
  <div class="col-md-4">
    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
    <input type="date" name="date" class="form-control" required>
  </div>
</div>

<!-- TO Field -->
<div class="mb-3 position-relative">
  <label class="form-label fw-semibold">To <span class="text-danger">*</span></label>
  <input type="text" id="toInput" class="form-control mb-2" placeholder="Search recipient...">
  <div id="toResults" class="list-group position-absolute w-100 z-3"></div>
  <div id="toSelected" class="mt-2"></div>
  <input type="hidden" name="to_user_id">
</div>

<!-- THROUGH Field -->
<div class="mb-3 position-relative">
  <label class="form-label fw-semibold">Through (Endorsers)</label>
  <input type="text" id="throughInput" class="form-control mb-2" placeholder="Search and add endorsers...">
  <div id="throughResults" class="list-group position-absolute w-100 z-3"></div>
  <div id="throughSelected" class="mt-2"></div>
  <input type="hidden" name="through_user_ids">
</div>

<!-- Subject -->
<div class="mb-4">
  <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
  <input type="text" name="subject" class="form-control" required>
</div>

<!-- Memo Body (Editor) -->
<div class="mb-4">
  <label class="form-label fw-semibold">Memo Body <span class="text-danger">*</span></label>
  <div id="editor" class="border rounded bg-white" style="min-height: 300px;"></div>
  <input type="hidden" name="content" id="content">
</div>

<!-- Existing Attachments -->
<div class="mb-4">
  <label class="form-label fw-semibold">Existing Attachments</label>
  <div id="existingAttachments"></div>
</div>

<!-- New Attachments Upload -->
<div class="mb-4">
  <label class="form-label fw-semibold">Add New Attachments (Optional)</label>
  <input type="file" id="attachments" name="attachments[]" class="form-control" multiple>
  <div id="filePreviewList" class="mt-2"></div>
</div>

<!-- Signature -->
<div class="mb-4">
  <label class="form-label fw-semibold">Signature</label><br>
  <img id="signaturePreview" src="" alt="Signature Preview" class="mb-2" style="max-width:300px; height:auto;">
  <br>
  <button type="button" class="btn btn-sm btn-outline-primary" id="updateSignatureBtn">Update Signature</button>



<!-- And you’ll also need to be prepared to collect the recipients arrays:
     these get injected as hidden inputs by your modal’s “Save Changes” handler -->

</div>

  <div class="mb-4">
    <button
      type="button"
      class="btn btn-outline-secondary"
      id="openEditLetterBtn"
      data-bs-toggle="modal"
      data-bs-target="#letterModal"
    >
      ✉️ Edit External Letter
    </button>
  </div>

  <!-- Letter Preview (hidden until user has a draft) -->
  <div
    id="letterPreviewContainer"
    class="border rounded p-3 mb-4"
    style="display: none;"
  >
    <h5 class="mb-3">External Letter Draft Preview</h5>
    <div class="mb-2">
      <strong>Date:</strong> <span id="previewLetterDate"></span>
    </div>
    <div class="mb-2">
      <strong>Ref #:</strong> <span id="previewLetterRef"></span>
    </div>
    <div class="mb-2">
      <strong>Subject:</strong> <span id="previewLetterSubject"></span>
    </div>
    <div class="mb-3">
      <strong>To:</strong>
      <ul id="previewLetterRecipients" class="mb-0 ps-3"></ul>
    </div>
    <div
      id="previewLetterContent"
      class="border rounded p-2 bg-light mb-2"
    ></div>
    <button
      type="button"
      class="btn btn-link btn-sm"
      id="editLetterBtn"
    >
      ✎ Edit Letter
    </button>
  </div>

<!-- Save Button -->
<!-- … inside your form … -->
<!-- inside your <form>…</form> around the bottom -->
<div class="d-flex justify-content-end gap-3 mt-4">
  <button type="button" class="btn btn-outline-primary" id="saveDraftBtn">
    💾 Save Draft
  </button>
  <button type="button" class="btn btn-success" id="submitBtn">
    ✅ Submit Memo
  </button>
</div>



<!-- Hidden Memo ID -->
<input type="hidden" name="memo_id_hidden" value="<?php echo $memo_id; ?>">


  </form>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1055">
  <div id="toastMessage" class="toast align-items-center text-white bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastBody">Memo updated successfully!</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>




<!-- EDIT LETTER MODAL -->
<div class="modal fade" id="letterModal" tabindex="-1" aria-labelledby="letterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="letterModalLabel">Edit External Letter Draft</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Letter Date</label>
            <input type="date" name="letter_date" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Reference #</label>
            <input type="text" name="letter_ref_no" class="form-control" placeholder="e.g. OUT/2025/001" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Subject</label>
            <input type="text" name="letter_subject" class="form-control" placeholder="Subject of the letter" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">To (multiple recipients)</label>
          
          <div id="recipientsContainer">
            <!-- initial template row -->
            <div class="recipient-row input-group mb-2">
              <input type="text" class="form-control" placeholder="Position / Name" required>
              <input type="text" class="form-control" placeholder="Address line(s)" required>
              <button type="button" class="btn btn-outline-danger remove-recipient">&times;</button>
            </div>
          </div>
          
          <button type="button" id="addRecipientBtn" class="btn btn-sm btn-outline-primary">
            + Add Recipient
          </button>
        </div>

        <label class="form-label">Letter Content</label>
        <div id="letterEditor" class="border rounded" style="min-height:200px;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="applyLetterBtn">
          Save Changes
        </button>
      </div>
    </div>
  </div>
</div>


<!-- Signature Modal -->
<div class="modal fade" id="signatureModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <h5>Capture Signature</h5>
      <canvas id="signatureCanvas" style="width: 100%; height: 120px; border: 1px solid #ccc;"></canvas>
      <div class="d-flex justify-content-between mt-2">
        <button class="btn btn-warning btn-sm" id="clearSignature">Clear</button>
        <button class="btn btn-primary btn-sm" id="saveSignature">Save Signature</button>
      </div>
    </div>
  </div>
</div>


<!-- Confirm Save Modal -->
<!-- Bootstrap Modal -->
<div class="modal fade" id="confirmSaveModal" tabindex="-1" aria-labelledby="confirmSaveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="confirmSaveModalLabel">Confirm Action</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">Are you sure?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmSaveBtn" class="btn btn-success">Yes, Proceed</button>
      </div>
    </div>
  </div>
</div>


<!-- Scripts -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>


<script>

let draftLetter = null;

// reuse your existing letterQuill instance:
const letterQuill = new Quill('#letterEditor', {
  theme: 'snow',
  modules: { toolbar: [
    [{ header: [1,2,false] }],
    ['bold','italic','underline'],
    [{ list:'ordered' },{ list:'bullet' }],
    ['link','image']
  ]}
});

// helper: render one recipient row in modal
function makeRecipientRow(pos='', addr='') {
  return `
    <div class="recipient-row input-group mb-2">
      <input type="text" class="form-control" placeholder="Position / Name" value="${pos}" required>
      <input type="text" class="form-control" placeholder="Address line(s)" value="${addr}" required>
      <button type="button" class="btn btn-outline-danger remove-recipient">&times;</button>
    </div>`;
}

// 1) “Edit Letter” button → load into modal
$('#editLetterBtn, #openEditLetterBtn').on('click', () => {
  if (!draftLetter) {
    return alert('No external letter to edit.');
  }

  // load modal fields from the global
  $('[name="letter_date"]').val(draftLetter.letter_date);
  $('[name="letter_ref_no"]').val(draftLetter.letter_ref_no);
  $('[name="letter_subject"]').val(draftLetter.letter_subject);

  // seed Quill
  letterQuill.clipboard.dangerouslyPasteHTML(
    draftLetter.letter_content || '<p><br></p>'
  );

  // rebuild recipients rows
  const $rc = $('#recipientsContainer').empty();
  (draftLetter.recipients || []).forEach(r => {
    $rc.append(makeRecipientRow(r.position, r.address));
  });

  bootstrap.Modal.getOrCreateInstance($('#letterModal')).show();
});


// 2) add/remove rows in modal
$('#addRecipientBtn').on('click', () => {
  $('#recipientsContainer').append(makeRecipientRow());
});
$('#recipientsContainer').on('click', '.remove-recipient', function(){
  if ($('#recipientsContainer .recipient-row').length > 1) {
    $(this).closest('.recipient-row').remove();
  }
});

// 3) on “Save Letter Draft” inside modal
$('#applyLetterBtn').on('click', () => {
  const date    = $('[name="letter_date"]').val();
  const refNo   = $('[name="letter_ref_no"]').val().trim();
  const subj    = $('[name="letter_subject"]').val().trim();
  const html    = letterQuill.root.innerHTML.trim();

  if (!html || html === '<p><br></p>') {
    return alert('Please enter letter content.');
  }

  // 3a) store into hidden form fields
  $('#letter_date').val(date);
  $('#letter_ref_no').val(refNo);
  $('#letter_subject').val(subj);
  $('#letter_content').val(html);

  // 3b) wipe old hidden arrays and rebuild
  $('#editMemoForm')
    .find('input[name="letter_recipients[position][]"], input[name="letter_recipients[address][]"]')
    .remove();

  $('#recipientsContainer .recipient-row').each(function(){
    const p = $(this).find('input').eq(0).val();
    const a = $(this).find('input').eq(1).val();
    $('<input>')
      .attr({type:'hidden', name:'letter_recipients[position][]'})
      .val(p).appendTo('#editMemoForm');
    $('<input>')
      .attr({type:'hidden', name:'letter_recipients[address][]'})
      .val(a).appendTo('#editMemoForm');
  });

  // 3c) update the on-page preview block
  $('#previewLetterDate').text(date);
  $('#previewLetterRef').text(refNo);
  $('#previewLetterSubject').text(subj);
  $('#previewLetterContent').html(html);

  const items = $('#recipientsContainer .recipient-row').map(function(){
    const p = $(this).find('input').eq(0).val();
    const a = $(this).find('input').eq(1).val().replace(/\n/g,'<br>');
    return `<li><strong>${p}</strong><br>${a}</li>`;
  }).get().join('');

  $('#previewLetterRecipients').html(items);
  $('#letterPreviewContainer').show();

  // 3d) close modal
  // 3d) close modal safely
const modalEl = document.getElementById('letterModal');

// Force hide the modal (handle cases where instance doesn't exist or is buggy)
try {
  const modalInstance = bootstrap.Modal.getInstance(modalEl);
  if (modalInstance) {
    modalInstance.hide();
  } else {
    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
  }
} catch (e) {
  console.warn("Force fallback closing modal");
  $(modalEl).modal('hide'); // Bootstrap 4 fallback (in case you're mixing)
}

// 💣 FORCE CLEANUP
setTimeout(() => {
  // Remove backdrop manually
  $('.modal-backdrop').remove();

  // Restore scroll and layout
  $('body').removeClass('modal-open');
  $('body').css('padding-right', ''); // remove scroll-lock padding
  $('body').css('overflow', '');      // remove scroll-lock overflow

  // Reset z-index of the preview if it floated above
  $('#letterPreviewContainer').css('z-index', '');
}, 300);



});
</script>



<script>
// Initialize Quill Editor with full toolbar (tables, etc.)
const quill = new Quill('#editor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [1, 2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      ['blockquote', 'code-block'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      [{ indent: '-1' }, { indent: '+1' }],
      [{ color: [] }, { background: [] }],
      [{ align: [] }],
      ['link', 'image'],
      ['clean'],
      ['table'] // support for tables if you have quill-table extension
    ]
  }
});

const memoId = <?php echo $memo_id; ?>;

let toUser = null;
let throughUsers = [];
let selectedFiles = [];

const signatureModal = new bootstrap.Modal(document.getElementById('signatureModal'));
const confirmSaveModal = new bootstrap.Modal(document.getElementById('confirmSaveModal'));
const signaturePad = new SignaturePad(document.getElementById('signatureCanvas'));

// Toast
function showToast(message, isSuccess = true) {
  const toast = document.getElementById('toastMessage');
  $('#toastBody').text(message);
  toast.className = `toast align-items-center text-white bg-${isSuccess ? 'success' : 'danger'} border-0`;
  new bootstrap.Toast(toast).show();
}

// Update Memo Title dynamically
$('select[name="communication_type"]').on('change', function () {
  const selectedType = $(this).val();
  $('#memoTitle').text(selectedType === 'Loose Minute' ? 'LOOSE MINUTE' : 'INTERNAL MEMORANDUM');
});

// Fetch Memo
$(document).ready(function() {
  $.get('get_memo.php', { memo_id: memoId }, function(data) {
    if (data.status === 'success') {
      const memo = data.memo;

      // Populate core memo fields
      $('select[name="communication_type"]').val(memo.communication_type).trigger('change');
      $('input[name="memo_id"]').val(memo.memo_id);
      $('input[name="date"]').val(memo.date);
      $('input[name="subject"]').val(memo.subject);
      quill.root.innerHTML = memo.content;

      // To user
      if (data.to_user) {
        toUser = data.to_user;
        updateToBadge();
      }

      // Through users
      throughUsers = data.through_users || [];
      updateThroughBadges();

      // Existing attachments
      if (data.attachments.length) {
        data.attachments.forEach(att => {
          $('#existingAttachments').append(`
            <div class="d-flex justify-content-between align-items-center border p-2 mb-2">
              <a href="${att.file_path}" target="_blank">${att.file_name}</a>
              <button class="btn btn-sm btn-danger" onclick="deleteAttachment(${att.id}, this)">Delete</button>
            </div>
          `);
        });
      }

      // Signature preview
      if (data.originator_signature) {
        $('#signaturePreview').attr('src', data.originator_signature);
      }

      // **Draft External Letter**
const letter = data.outgoing_letter;
// store into the global
draftLetter = data.outgoing_letter || null;

if (draftLetter) {
  // 1) Hidden inputs
  $('#letter_content').val(draftLetter.letter_content);
  $('#letter_date').val(draftLetter.letter_date);
  $('#letter_ref_no').val(draftLetter.letter_ref_no);
  $('#letter_subject').val(draftLetter.letter_subject);

  // 2) Seed Quill immediately
  letterQuill.clipboard.dangerouslyPasteHTML(
    draftLetter.letter_content || '<p><br></p>'
  );

  // 3) Build modal recipient rows
  const $rc = $('#recipientsContainer').empty();
  (draftLetter.recipients || []).forEach(r => {
    $rc.append(makeRecipientRow(r.position, r.address));
  });

  // 4) Populate the on‐page preview
  $('#previewLetterDate').text(draftLetter.letter_date);
  $('#previewLetterRef').text(draftLetter.letter_ref_no);
  $('#previewLetterSubject').text(draftLetter.letter_subject);
  $('#previewLetterContent').html(draftLetter.letter_content);

  const items = (draftLetter.recipients || [])
    .map(r => `<li><strong>${r.position}</strong><br>${r.address.replace(/\n/g,'<br>')}</li>`)
    .join('');
  $('#previewLetterRecipients').html(items);

  // show preview container
  $('#letterPreviewContainer').show();
}



    }
  }, 'json');
});


// Update TO Badge
function updateToBadge() {
  $('#toSelected').html(toUser ? `
    <span class="badge-user">${toUser.name}
      <button onclick="removeToUser()">×</button>
    </span>` : '');
  $('input[name="to_user_id"]').val(toUser ? toUser.id : '');
}

// Update THROUGH Badges
function updateThroughBadges() {
  const container = $('#throughSelected');
  container.empty();
  throughUsers.forEach((user, index) => {
    container.append(`
      <span class="badge-user">${user.name}
        <button onclick="removeThrough(${user.id}, ${index})">×</button>
      </span>`);
  });
  $('input[name="through_user_ids"]').val(throughUsers.map(u => u.id).join(','));
}

// Remove TO
function removeToUser() {
  toUser = null;
  updateToBadge();
}

// Remove THROUGH instantly
function removeThrough(userId, index) {
  $.post('remove_through.php', { memo_id: memoId, user_id: userId }, function(res) {
    if (res.status === 'success') {
      throughUsers.splice(index, 1);
      updateThroughBadges();
      showToast('Through user removed!');
    } else {
      showToast('Failed to remove through user.', false);
    }
  }, 'json');
}

// Delete Attachment instantly
function deleteAttachment(attId, btn) {
  $.post('delete_attachment.php', { attachment_id: attId }, function(res) {
    if (res.status === 'success') {
      $(btn).parent().remove();
      showToast('Attachment deleted!');
    } else {
      showToast('Failed to delete attachment.', false);
    }
  }, 'json');
}

// Signature
$('#updateSignatureBtn').click(() => signatureModal.show());
$('#clearSignature').click(() => signaturePad.clear());
$('#saveSignature').click(() => {
  if (!signaturePad.isEmpty()) {
    $('#signaturePreview').attr('src', signaturePad.toDataURL());
    signatureModal.hide();
  } else {
    showToast('Please provide a signature.', false);
  }
});

// Search Users for TO
$('#toInput').on('keyup', function() {
  const query = $(this).val();
  if (query.length > 1) {
    $.get('user_search.php', { q: query }, function(data) {
      $('#toResults').html(data).show();
    });
  } else {
    $('#toResults').hide();
  }
});

// Select TO user
$('#toResults').on('click', '.list-group-item-action', function() {
  const userId = $(this).data('user-id');
  const name = $(this).text();
  toUser = { id: userId, name };
  updateToBadge();
  $('#toInput').val('');
  $('#toResults').hide();
});

// Search Users for THROUGH
$('#throughInput').on('keyup', function() {
  const query = $(this).val();
  if (query.length > 1) {
    $.get('user_search.php', { q: query }, function(data) {
      $('#throughResults').html(data).show();
    });
  } else {
    $('#throughResults').hide();
  }
});

// Select THROUGH user
$('#throughResults').on('click', '.list-group-item-action', function() {
  const userId = $(this).data('user-id');
  const name = $(this).text();
  if (!throughUsers.find(u => u.id == userId)) {
    throughUsers.push({ id: userId, name });
    updateThroughBadges();
  }
  $('#throughInput').val('');
  $('#throughResults').hide();
});

// New Attachments Selection
// Allowed file extensions
const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

// Handle new attachments
$('#attachments').on('change', function() {
  const newFiles = Array.from(this.files);
  const invalidFiles = [];

  newFiles.forEach(file => {
    const extension = file.name.split('.').pop().toLowerCase();
    if (allowedExtensions.includes(extension)) {
      // Check if already selected
      if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
        selectedFiles.push(file);
      }
    } else {
      invalidFiles.push(file.name);
    }
  });

  if (invalidFiles.length > 0) {
    showToast('Invalid file types: ' + invalidFiles.join(', '), false);
  }

  updateFilePreview();
  $(this).val('');
});


function updateFilePreview() {
  const preview = $('#filePreviewList');
  preview.empty();

  selectedFiles.forEach((file, index) => {
    preview.append(`
      <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-1">
        <span class="text-muted">${file.name}</span>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeSelectedFile(${index})">Remove</button>
      </div>
    `);
  });
}

function removeSelectedFile(index) {
  selectedFiles.splice(index, 1);
  updateFilePreview();
}


</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  function handleMemoAction(action) {
    const $btn = action === 'submit' ? $('#submitBtn') : $('#saveDraftBtn');

    // Disable and show spinner
    $btn.prop('disabled', true).html(`
      <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
      Processing...
    `);

    const formData = new FormData($('#editMemoForm')[0]);

    // Add content from editors
    formData.set('content', quill.root.innerHTML.trim());
    formData.set('letter_content', letterQuill.root.innerHTML.trim());
    formData.set('letter_date', $('[name="letter_date"]').val());
    formData.set('letter_ref_no', $('[name="letter_ref_no"]').val().trim());
    formData.set('letter_subject', $('[name="letter_subject"]').val().trim());

    // Add attachments
    selectedFiles.forEach(file => {
      formData.append('attachments[]', file);
    });

    // Set action type
    formData.set('action', action);

    // AJAX submit
    $.ajax({
      url: 'update_memo.php',
      method: 'POST',
      data: formData,
      contentType: false,
      processData: false
    }).done(response => {
      const msg = action === 'submit'
        ? 'Memo submitted successfully!'
        : 'Draft saved successfully!';
      if (response.status === 'success') {
        showToast(msg, true);

        if (action === 'submit') {
          setTimeout(() => {
            window.location.href = `index.php?module=my_memos`;
          }, 1500);
        }
      } else {
        showToast(response.message || 'Operation failed.', false);
      }
    }).fail(() => {
      showToast('Server error.', false);
    }).always(() => {
      // Restore button
      $btn.prop('disabled', false).html(
        action === 'submit' ? 'Submit' : 'Save Draft'
      );
    });
  }

  // Bind buttons
  $('#saveDraftBtn').click(() => handleMemoAction('save'));
  $('#submitBtn').click(() => handleMemoAction('submit'));
});
</script>
<?php require_once 'footer.php'; ?>
