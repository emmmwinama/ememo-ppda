function loadMemoData(memoId, quill) {
  fetch(`get_memo_data.php?memo_id=${memoId}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'success') return;

      const m = data.memo;
      document.querySelector('[name="communication_type"]').value = m.communication_type;
      document.querySelector('[name="memo_id_text"]').value = m.memo_id;
      document.querySelector('[name="date"]').value = m.date;
      document.querySelector('[name="subject"]').value = m.subject;
      document.querySelector('[name="to"]').value = m.to;
      document.querySelector('[name="to_id"]').value = m.to_user_id;

      window.toUser = { id: m.to_user_id, name: m.to };
      updateToBadge();

      window.throughList = data.through_ids.map(id => ({ id, name: `User ${id}` }));
      updateThroughBadges();

      quill.root.innerHTML = m.content;
      document.getElementById('content').value = m.content;

      if (data.signature_path) {
        document.getElementById('signaturePreview').src = data.signature_path;
      }

      const attachmentsEl = document.getElementById('existingAttachments');
      attachmentsEl.innerHTML = '';
      data.attachments.forEach(att => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `<a href="${att.file_path}" target="_blank">${att.file_name}</a>
                        <button type="button" class="btn btn-sm btn-danger remove-attachment" data-id="${att.id}">Delete</button>`;
        attachmentsEl.appendChild(li);
      });
    });
}





// Badge logic
function updateToBadge() {
  const container = document.getElementById('toSelected');
  const inputToId = document.querySelector('[name="to_id"]');
  const inputToName = document.querySelector('[name="to"]');
  if (!window.toUser) return;

  container.innerHTML = `
    <span class="badge bg-primary me-2">${toUser.name}
      <button type="button" class="btn-close btn-close-white btn-sm ms-2" onclick="removeToUser()"></button>
    </span>`;
  inputToId.value = toUser.id;
  inputToName.value = toUser.name;
}

function removeToUser() {
  window.toUser = null;
  document.getElementById('toSelected').innerHTML = '';
  document.querySelector('[name="to"]').value = '';
  document.querySelector('[name="to_id"]').value = '';
}

function updateThroughBadges() {
  const container = document.getElementById('throughSelected');
  const input = document.getElementById('through_ids');
  container.innerHTML = '';
  if (!window.throughList) return;

  throughList.forEach((user, index) => {
    container.innerHTML += `
      <span class="badge bg-success me-1 mb-1">${user.name}
        <button type="button" class="btn-close btn-close-white btn-sm ms-1" onclick="removeThrough(${index})"></button>
      </span>`;
  });
  input.value = throughList.map(u => u.id).join(',');
}

function removeThrough(index) {
  window.throughList.splice(index, 1);
  updateThroughBadges();
}

function searchUsers(inputId, resultBoxId) {
  $(inputId).on('keyup', function () {
    const query = $(this).val();
    if (query.length > 1) {
      $.get('user_search.php', { q: query }, function (data) {
        $(resultBoxId).html(data).show();
      });
    } else {
      $(resultBoxId).hide();
    }
  });
}