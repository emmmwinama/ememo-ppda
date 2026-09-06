<script>
document.addEventListener('DOMContentLoaded', function () {
  const memoId = new URLSearchParams(window.location.search).get('memo_id');
  let availableUsers = [];
  let escalationTargets = [];
  let mentionedUsers = [];

  const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
  const escalateModal = new bootstrap.Modal(document.getElementById('escalateModal'));
  const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
  const confirmSendClarificationModal = new bootstrap.Modal(document.getElementById('confirmClarificationModal'));
  const confirmClarificationResponseModal = new bootstrap.Modal(document.getElementById('confirmClarificationResponseModal'));
  const clarificationResponseModal = new bootstrap.Modal(document.getElementById('clarificationResponseModal'));
  const clarificationModal = new bootstrap.Modal(document.getElementById('clarificationModal'));

  function showLoading() { loadingModal.show(); }
  function hideLoading() { loadingModal.hide(); }

  function showToast(message, type = 'info') {
    const icons = ['success', 'error', 'warning', 'info', 'question'];
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icons.includes(type) ? type : 'info',
      title: message,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
  }

  function getStatusColor(status) {
    const map = {
      'Draft': 'secondary', 'Submitted': 'primary', 'Finalized': 'success',
      'Rejected': 'danger', 'Under Review': 'warning', 'Returned': 'danger'
    };
    return map[status] || 'light';
  }

  function getMovementBadgeColor(action) {
    const map = {
      'Submitted': 'primary', 'Returned': 'danger', 'Rejected': 'danger',
      'Endorsed': 'info', 'Approved': 'success', 'Escalated': 'warning', 'Finalized': 'dark'
    };
    return map[action] || 'secondary';
  }

  fetch(`get_memo_data.php?memo_id=${memoId}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'success') return showToast(data.message, 'error');
      window.currentMemoData = data;
      renderMemo(data);
    })
    .catch(err => {
      console.error(err);
      showToast('Failed to load memo.', 'error');
    });

  fetch('get_users_list.php')
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        availableUsers = data.users || [];
        initializeMentions(availableUsers);
      }
    })
    .catch(console.error);

  function renderMemo(data) {
    const m = data.memo;
    escalationTargets = data.escalation_targets || [];

    document.getElementById('memoTitle').textContent = m.communication_type || 'MEMO';
    document.getElementById('memoRef').textContent = m.memo_id;
    document.getElementById('memoDate').textContent = new Date(m.date).toLocaleDateString('en-CA');
    document.getElementById('memoTo').textContent = data.to_user?.position || 'N/A';
    document.getElementById('memoSubject').textContent = m.subject;
    document.getElementById('memoContent').innerHTML = m.content || '';
    document.getElementById('signatureImg').src = data.originator_signature || '';
    document.getElementById('originatorName').textContent = data.originator?.name || 'N/A';
    document.getElementById('originatorPosition').textContent = data.originator?.position || 'N/A';

    const statusBadge = document.getElementById('memoStatusBadge');
    statusBadge.textContent = m.status;
    statusBadge.className = 'badge bg-' + getStatusColor(m.status);

    renderThrough(data);
    renderMovementTrail(data.memo_movements || []);
    renderClarifications(data.memo_clarifications || []);
    renderDGComments(data.memo_comments || []);
    renderAttachments(data.attachments || []);
    setupActionButtons(data);
  }

  function renderThrough(data) {
    const throughUsers = data.through_users || [];
    const endorsements = Object.fromEntries((data.through_endorsements || []).map(e => [e.user_id, e]));
    const memoThrough = document.getElementById('memoThrough');
    memoThrough.innerHTML = throughUsers.map(user => {
      const e = endorsements[user.id];
      return `<div class="through-comment">
        <p>${user.position}</p>
        ${e ? `<p>${e.comment}</p><small class="text-muted">Endorsed on ${new Date(e.endorsed_at).toLocaleString()}</small>
        ${e.signature_path ? `<img src="${e.signature_path}" class="signature-img mb-2 mt-2">` : ''}` : '<p class="text-muted">Not yet endorsed.</p>'}
      </div>`;
    }).join('');
  }

  function renderAttachments(attachments) {
    const container = document.getElementById('attachmentsList');
    if (!attachments.length) return document.getElementById('attachmentsSection').style.display = 'none';
    container.innerHTML = attachments.map(file => `
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column justify-content-between">
            <h6 class="card-title text-primary">${file.file_name}</h6>
            <a href="${file.file_path}" download="${file.file_name}" class="btn btn-sm btn-outline-success mt-3">⬇️ Download</a>
          </div>
        </div>
      </div>
    `).join('');
  }

  function renderClarifications(clarifications) {
    const list = document.getElementById('clarificationList');
    list.innerHTML = clarifications.length === 0
      ? '<p class="text-muted">No clarifications requested.</p>'
      : clarifications.map(c => `
        <div class="list-group-item">
          <p><strong>From:</strong> ${c.requested_by_name}</p>
          <p><strong>To:</strong> ${c.user_name}</p>
          <p><strong>Requested:</strong> ${new Date(c.requested_at).toLocaleString('en-US')}</p>
          <p><strong>Message:</strong> ${c.clarification_comment}</p>
          ${c.response_comment ? `<p><strong>Response:</strong> ${c.response_comment}</p>` : ''}
          ${c.responded_at ? `<p><strong>Responded:</strong> ${new Date(c.responded_at).toLocaleString()}</p>` : ''}
        </div>
      `).join('');
  }

  function renderMovementTrail(trail) {
    const list = document.getElementById('movementTrailList');
    if (!trail.length) return document.getElementById('movementTrailSection').style.display = 'none';
    list.innerHTML = trail.map(entry => `
      <li class="list-group-item">
        <div class="d-flex justify-content-between align-items-start">
          <div><strong>${entry.from_name || 'System'}</strong> ➔ <strong>${entry.to_name || 'System'}</strong><br>
          <small class="text-muted">${new Date(entry.timestamp).toLocaleString('en-US')}</small></div>
          <span class="badge bg-${getMovementBadgeColor(entry.action)} rounded-pill text-uppercase">${entry.action}</span>
        </div>
        ${entry.comments ? `<div class="mt-2"><em>${entry.comments}</em></div>` : ''}
      </li>
    `).join('');
  }

  function renderDGComments(comments) {
    const dgSection = document.getElementById('dgCommentSection');
    const container = document.getElementById('dgCommentContent');
    const dgComments = comments.filter(c => c.stage === 'Director General');
    if (!dgComments.length) return dgSection.style.display = 'none';
    dgSection.style.display = 'block';
    container.innerHTML = dgComments.map((c, i) => `
      <div class="mb-3">
        <strong>Comment ${i + 1}:</strong>
        <p class="mb-1">${c.comment}</p>
        <small class="text-muted">By ${c.name} on ${new Date(c.timestamp).toLocaleString()}</small>
      </div>
    `).join('');
  }

  // Action Button Setup
  function setupActionButtons(data) {
    const btns = document.getElementById('actionButtonsSection');
    const m = data.memo;
    btns.innerHTML = '';

    const isEndorsed = (data.through_endorsements || []).some(e => e.user_id == data.current_user_id);
    const hasPendingClarification = (data.memo_clarifications || []).some(c =>
      c.status === 'Pending' && c.user_id == data.current_user_id
    );

    if (hasPendingClarification) {
      btns.innerHTML = `<button class="btn btn-warning" onclick="openClarificationResponseModal(${m.id})">📝 Respond to Clarification</button>`;
      return;
    }

    if (data.current_user_is_recipient && !isEndorsed && ['Submitted', 'Under Review'].includes(m.status)) {
      btns.innerHTML = `
        <button class="btn btn-success me-2" onclick="handleAction('endorse')">✅ Endorse</button>
        <button class="btn btn-warning me-2" onclick="handleAction('return')">✏️ Return</button>
        <button class="btn btn-info me-2" onclick="handleAction('escalate')">⬆️ Escalate</button>
        <button class="btn btn-danger" onclick="handleAction('reject')">❌ Reject</button>
      `;
    } else if (data.current_user_is_approver) {
      btns.innerHTML = `
        <button class="btn btn-primary me-2" onclick="handleAction('approve')">✅ Approve</button>
        <button class="btn btn-info me-2" onclick="handleAction('seek_clarification')">❓ Seek Clarification</button>
        <button class="btn btn-danger" onclick="handleAction('reject')">❌ Reject</button>
      `;
    }
  }

  window.handleAction = function(action) {
    if (action === 'seek_clarification') clarificationModal.show();
    else {
      document.getElementById('actionLabel').textContent = action.charAt(0).toUpperCase() + action.slice(1);
      document.getElementById('actionType').value = action;
      document.getElementById('actionComment').value = '';
      actionModal.show();
    }
  };

  document.getElementById('actionSubmitBtn')?.addEventListener('click', () => {
    const action = document.getElementById('actionType').value;
    const comment = document.getElementById('actionComment').value.trim();
    if (!comment) return showToast('Please add a comment.', 'warning');
    actionModal.hide();
    performMemoAction(action, comment);
  });

  function performMemoAction(action, comment, target_id = null) {
    showLoading();
    fetch('memo_action.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ memo_id: memoId, action, comment, target_id })
    })
    .then(res => res.json())
    .then(data => {
      hideLoading();
      if (data.status === 'success') Swal.fire('Success', data.message, 'success').then(() => location.reload());
      else Swal.fire('Error', data.message, 'error');
    })
    .catch(err => {
      hideLoading();
      console.error(err);
      Swal.fire('Error', 'Server error', 'error');
    });
  }

  window.openClarificationResponseModal = function (memoId) {
    const clarifications = (window.currentMemoData?.memo_clarifications || []);
    const pending = clarifications.find(c =>
      c.memo_id == memoId && c.status === 'Pending' && c.user_id == window.currentMemoData.current_user_id
    );
    if (!pending) return Swal.fire('Not Found', 'No pending clarification assigned to you.', 'info');
    document.getElementById('clarificationResponseId').value = pending.id;
    document.getElementById('clarificationResponseMessage').value = '';
    clarificationResponseModal.show();
  };

});
</script>
