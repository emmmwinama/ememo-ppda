// 🔔 Toast Notifications
function showToast(message, type = 'success') {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: type,
    title: message,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });
}

// Status pill (no emoji — matches Farmis flat-badge style)
function statusBadge(active) {
  return active
    ? '<span class="badge bg-success">Active</span>'
    : '<span class="badge bg-secondary">Inactive</span>';
}

// 🚀 Load Users into Table
function loadUsers() {
  fetch('get_users.php')
    .then(res => res.json())
    .then(users => {
      const tbody = document.querySelector('#userTable tbody');
      tbody.innerHTML = '';
      users.forEach(u => {
        tbody.innerHTML += `
          <tr id="userRow${u.id}">
            <td>${u.full_name}</td>
            <td>${u.username}</td>
            <td class="text-capitalize">${u.role}</td>
            <td>${u.email}</td>
            <td id="status${u.id}">${statusBadge(u.active)}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary btn-icon me-1" title="Edit"
                onclick="openEditUser(${u.id})">
                <i class="bi bi-pencil"></i>
              </button>
              <button id="toggleBtn${u.id}" class="btn btn-sm ${u.active ? 'btn-outline-danger' : 'btn-success'}"
                onclick="toggleUser(${u.id})">
                ${u.active ? 'Deactivate' : 'Activate'}
              </button>
            </td>
          </tr>`;
      });
    })
    .catch(err => showToast('Failed to load users', 'error'));
}

// 🧩 Toggle User Active Status
function toggleUser(id) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Change user active status?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes',
    cancelButtonText: 'Cancel'
  }).then(result => {
    if (!result.isConfirmed) return;

    fetch('toggle_user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showToast(data.message);
          const status = document.getElementById('status' + id);
          const button = document.getElementById('toggleBtn' + id);
          const isActive = data.newStatus == 1;
          status.innerHTML = statusBadge(isActive);
          button.className = `btn btn-sm ${isActive ? 'btn-outline-danger' : 'btn-success'}`;
          button.textContent = isActive ? 'Deactivate' : 'Activate';
        } else {
          showToast(data.message || 'Failed to toggle', 'error');
        }
      });
  });
}

// 📤 Handle Create User Form
document.getElementById('createUserForm').onsubmit = function (e) {
  e.preventDefault();
  const form = new FormData(this);
  fetch('create_user.php', { method: 'POST', body: form })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        showToast('User created!');
        this.reset();
        loadUsers();
      } else {
        showToast(data.message || 'Creation failed', 'error');
      }
    });
};

// 🔽 Load Dropdown Data
function loadDropdown(type, targetId, placeholder = '') {
  fetch(`load_entities.php?type=${type}`)
    .then(res => res.json())
    .then(data => {
      const sel = document.getElementById(targetId);
      sel.innerHTML = `<option value="">${placeholder || 'Select ' + type}</option>`;
      data.forEach(item => {
        sel.innerHTML += `<option value="${item.id}">${item.name || item.code}</option>`;
      });
    });
}

// 🔁 Department → Section linkage
document.getElementById('departmentSelect')?.addEventListener('change', function () {
  loadSections(this.value);
});

function loadSections(deptId) {
  fetch(`load_entities.php?type=sections&department_id=${deptId}`)
    .then(res => res.json())
    .then(data => {
      const sel = document.getElementById('sectionSelect');
      sel.innerHTML = `<option value="">Section</option>`;
      data.forEach(s => {
        sel.innerHTML += `<option value="${s.id}">${s.name}</option>`;
      });
    });
}

// ➕ Entity Create (Generic)
function sendEntity(type, payload, callback) {
  fetch('manage_entities.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type, ...payload })
  })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        showToast(`${type} added`);
        callback();
      } else {
        showToast(data.message || `Failed to add ${type}`, 'error');
      }
    });
}

function addDepartment() {
  const name = document.getElementById('newDepartmentInput').value.trim();
  if (!name) return;
  sendEntity('department', { name }, () => {
    document.getElementById('newDepartmentInput').value = '';
    loadDropdown('departments', 'departmentSelect');
    loadDropdown('departments', 'sectionDeptSelect');
  });
}

function addSection() {
  const name = document.getElementById('newSectionInput').value.trim();
  const department_id = document.getElementById('sectionDeptSelect').value;
  if (!name || !department_id) return;
  sendEntity('section', { name, department_id }, () => {
    document.getElementById('newSectionInput').value = '';
    loadSections(department_id);
  });
}

function addPosition() {
  const name = document.getElementById('newPositionNameInput').value.trim();
  const short_name = document.getElementById('newPositionShortInput').value.trim();
  const code = document.getElementById('positionGradeSelect').value;
  if (!name || !code) return;
  sendEntity('position', { name, short_name, position_code: code }, () => {
    document.getElementById('newPositionNameInput').value = '';
    document.getElementById('newPositionShortInput').value = '';
    loadDropdown('positions', 'positionSelect');
  });
}

// 🧑‍🤝‍🧑 GROUP LOGIC
function loadGroups() {
  fetch('group_data.php?action=groups')
    .then(res => res.json())
    .then(groups => {
      const sel = document.getElementById('groupSelect');
      sel.innerHTML = '<option value="">Select Group</option>';
      groups.forEach(g => {
        sel.innerHTML += `<option value="${g.id}">${g.name}</option>`;
      });
    });
}

function loadGroupMembers(groupId) {
  fetch(`group_data.php?action=members&group_id=${groupId}`)
    .then(res => res.json())
    .then(data => {
      const tbody = document.querySelector('#groupMembersTable tbody');
      tbody.innerHTML = '';
      data.forEach(u => {
        tbody.innerHTML += `
          <tr>
            <td>${u.full_name}</td>
            <td>${u.username}</td>
            <td>${u.email}</td>
            <td><button class="btn btn-sm btn-danger" onclick="removeMemberFromGroup(${groupId}, ${u.id})">Remove</button></td>
          </tr>`;
      });
    });
}

function loadUsersForModal() {
  fetch('get_users.php')
    .then(res => res.json())
    .then(users => {
      const sel = document.getElementById('modalUserSelect');
      sel.innerHTML = '';
      users.forEach(u => {
        sel.innerHTML += `<option value="${u.id}">${u.full_name} (${u.username})</option>`;
      });
    });
}

function createGroup() {
  const name = document.getElementById('newGroupName').value.trim();
  const description = document.getElementById('newGroupDesc').value.trim();
  if (!name) return showToast('Group name is required', 'error');

  fetch('group_crud.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'create_group', name, description })
  })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        showToast('Group created');
        bootstrap.Modal.getInstance(document.getElementById('createGroupModal')).hide();
        loadGroups();
      } else {
        showToast(data.message || 'Create failed', 'error');
      }
    });
}

function confirmAddUserToGroup() {
  const group_id = document.getElementById('groupSelect').value;
  const user_id = document.getElementById('modalUserSelect').value;
  if (!group_id || !user_id) return showToast('Select group and user', 'error');

  fetch('group_crud.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'add_member', group_id, user_id })
  })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        showToast('User added');
        bootstrap.Modal.getInstance(document.getElementById('addMemberModal')).hide();
        loadGroupMembers(group_id);
      } else {
        showToast(data.message || 'Add failed', 'error');
      }
    });
}

function removeMemberFromGroup(group_id, user_id) {
  Swal.fire({
    title: 'Remove member?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Remove',
    cancelButtonText: 'Cancel'
  }).then(result => {
    if (!result.isConfirmed) return;

    fetch('group_crud.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'remove_member', group_id, user_id })
    })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showToast('User removed');
          loadGroupMembers(group_id);
        } else {
          showToast(data.message || 'Remove failed', 'error');
        }
      });
  });
}

document.getElementById('groupSelect')?.addEventListener('change', function () {
  const id = this.value;
  document.getElementById('openAddMemberBtn').disabled = !id;
  if (id) {
    loadGroupMembers(id);
    loadUsersForModal();
  } else {
    document.querySelector('#groupMembersTable tbody').innerHTML = '';
  }
});

// ═══════════════════ EDIT USER (slide-over) ═══════════════════
const editOverlay = document.getElementById('editUserOverlay');

function fillSelect(el, rows, valueKey = 'id', labelKey = 'name', placeholder = '—') {
  el.innerHTML = `<option value="">${placeholder}</option>` +
    rows.map(r => `<option value="${r[valueKey]}">${r[labelKey] || r.code}</option>`).join('');
}

function loadEditSections(deptId, selectedId) {
  const sel = document.getElementById('edit_section');
  if (!deptId) { fillSelect(sel, []); return Promise.resolve(); }
  return fetch(`load_entities.php?type=sections&department_id=${deptId}`)
    .then(r => r.json())
    .then(rows => {
      fillSelect(sel, Array.isArray(rows) ? rows : []);
      if (selectedId) sel.value = selectedId;
    });
}

function closeEditUser() {
  if (editOverlay) editOverlay.hidden = true;
}

function openEditUser(id) {
  if (!editOverlay) return;

  Promise.all([
    fetch(`get_user_edit.php?id=${id}`).then(r => r.json()),
    fetch('load_entities.php?type=departments').then(r => r.json()),
    fetch('load_entities.php?type=positions').then(r => r.json()),
  ]).then(([res, depts, positions]) => {
    if (res.status !== 'success') { showToast(res.message || 'Could not load user', 'error'); return; }
    const u = res.user;

    fillSelect(document.getElementById('edit_department'), Array.isArray(depts) ? depts : []);
    fillSelect(document.getElementById('edit_position'), Array.isArray(positions) ? positions : []);

    document.getElementById('edit_user_id').value   = u.id;
    document.getElementById('edit_full_name').value = u.full_name || '';
    document.getElementById('edit_username').value  = u.username || '';
    document.getElementById('edit_email').value     = u.email || '';
    document.getElementById('edit_phone').value     = u.phone_number || '';
    document.getElementById('edit_role').value      = u.role || 'originator';
    document.getElementById('edit_position').value  = u.position_id || '';
    document.getElementById('edit_department').value = u.department_id || '';
    document.getElementById('edit_password').value  = '';
    document.getElementById('edit_is_co').checked   = u.is_controlling_officer == 1;
    document.getElementById('edit_is_sec').checked  = u.is_secretary == 1;
    document.getElementById('edit_active').checked  = u.active == 1;
    document.getElementById('editUserSubtitle').textContent = `${u.full_name} · @${u.username}`;

    loadEditSections(u.department_id, u.section_id);
    editOverlay.hidden = false;
  }).catch(() => showToast('Could not load user', 'error'));
}
window.openEditUser = openEditUser;

if (editOverlay) {
  editOverlay.addEventListener('click', e => { if (e.target.hasAttribute('data-close')) closeEditUser(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !editOverlay.hidden) closeEditUser(); });

  document.getElementById('edit_department').addEventListener('change', function () {
    loadEditSections(this.value);
  });

  document.getElementById('editUserForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('editUserSave');
    const form = new FormData(this);
    // Unchecked checkboxes are absent from FormData — send explicit 0
    ['is_controlling_officer', 'is_secretary', 'active'].forEach(k => {
      if (!form.has(k)) form.append(k, '0');
    });

    btn.disabled = true;
    fetch('update_user.php', { method: 'POST', body: form })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          showToast('User updated');
          closeEditUser();
          loadUsers();
        } else {
          showToast(data.message || 'Update failed', 'error');
        }
      })
      .catch(() => showToast('Network or server error', 'error'))
      .finally(() => { btn.disabled = false; });
  });
}

// Initial load
loadUsers();
loadGroups();
loadDropdown('departments', 'departmentSelect');
loadDropdown('departments', 'sectionDeptSelect');
loadDropdown('positions', 'positionSelect');
loadDropdown('grades', 'positionGradeSelect');
