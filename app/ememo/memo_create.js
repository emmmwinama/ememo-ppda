const quill = new Quill('#editor', { theme: 'snow' });
let throughList = [], pendingStatus = 'Pending';

// Handle dynamic resizing for high DPI screens
function resizeCanvas(canvas, signaturePad) {
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  canvas.width = canvas.offsetWidth * ratio;
  canvas.height = canvas.offsetHeight * ratio;
  canvas.getContext("2d").scale(ratio, ratio);
  signaturePad.clear();
}

// Signature modal setup
const modalCanvas = document.getElementById('signatureCanvasModal');
const modalSignaturePad = new SignaturePad(modalCanvas);
window.addEventListener("resize", () => resizeCanvas(modalCanvas, modalSignaturePad));
resizeCanvas(modalCanvas, modalSignaturePad);

$('#clearSignatureModal').on('click', () => modalSignaturePad.clear());

$('#saveSignatureModal').on('click', () => {
  if (!modalSignaturePad.isEmpty()) {
    const dataUrl = modalSignaturePad.toDataURL();
    $('#signaturePreview').attr('src', dataUrl);
    $('#signature_data').val(dataUrl);
  }
});

// Load current user info
$(function () {
  $.get('user_info.php', function (res) {
    if (res.full_name) {
      $('#userName').text(res.full_name);
      $('#userPosition').text(res.position);
      $('#signedByBlock').show();
    }
  }, 'json');
});

// Search "To" and "Through"
function searchUsers(inputId, resultBoxId) {
  $(inputId).on('keyup', function () {
    const query = $(this).val();
    if (query.length > 1) {
      $.get('user_search.php', { q: query }, data => $(resultBoxId).html(data).show());
    } else {
      $(resultBoxId).hide();
    }
  });
}
searchUsers('#toInput', '#toResults');
searchUsers('#throughInput', '#throughResults');

$('#toResults').on('click', '.list-group-item-action', function () {
  $('#toInput').val($(this).text());
  $('#toResults').hide();
});

$('#throughResults').on('click', '.list-group-item-action', function () {
  const name = $(this).text(), userId = $(this).data('user-id');
  if (!throughList.includes(userId)) {
    throughList.push(userId);
    $('#throughSelected').append(`<span class="badge bg-success me-1 mb-1">${name}</span>`);
    $('#through_ids').val(throughList.join(','));
  }
  $('#throughInput').val('');
  $('#throughResults').hide();
});

// Submission logic
function attachEditorContent() {
  $('#content').val(quill.root.innerHTML);
}

function showToast(message, isSuccess = true) {
  const toast = $('#toastMessage');
  $('#toastBody').text(message);
  toast.removeClass().addClass(`toast text-white bg-${isSuccess ? 'success' : 'danger'}`);
  new bootstrap.Toast(toast[0]).show();
}

function submitMemo(status = 'Pending') {
  attachEditorContent();
  $('#through_ids').val(throughList.join(','));

  const formData = new FormData($('#memoForm')[0]);
  formData.append('status', status);

  $.ajax({
    url: 'memo_submit.php',
    method: 'POST',
    data: formData,
    contentType: false,
    processData: false,
    success: () => {
      showToast(`Memo ${status} successfully!`);
      $('#memoForm')[0].reset();
      quill.root.innerHTML = '';
      modalSignaturePad.clear();
      $('#signaturePreview').attr('src', '');
      $('#throughSelected').empty();
      throughList = [];
    },
    error: () => showToast('Something went wrong. Try again.', false)
  });
}

// Modal triggers
$('#triggerSubmit').on('click', () => {
  pendingStatus = 'Pending';
  new bootstrap.Modal(document.getElementById('confirmSubmitModal')).show();
});
$('#confirmSubmitBtn').on('click', () => {
  bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal')).hide();
  submitMemo(pendingStatus);
});
$('#saveDraft').on('click', () => submitMemo('Draft'));
