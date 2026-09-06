<!-- User Management Section -->
<form id="createUserForm" class="f-panel p-4 mb-4">
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <input name="full_name" class="form-control" placeholder="Full Name" required>
    </div>
    <div class="col-md-3">
      <input name="username" class="form-control" placeholder="Username" required>
    </div>
    <div class="col-md-3">
      <input name="email" class="form-control" placeholder="Email" type="email" required>
    </div>
    <div class="col-md-3">
      <input name="phone_number" class="form-control" placeholder="Phone (optional)">
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <input name="password" type="password" class="form-control" placeholder="Password" required>
    </div>
    <div class="col-md-3">
      <select name="role" class="form-select" required>
        <option value="">Select Role</option>
        <option value="originator">Originator</option>
        <option value="endorser">Endorser</option>
        <option value="approver">Approver</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="col-md-2">
      <div class="input-group">
        <select name="department_id" class="form-select" id="departmentSelect" required>
          <option value="">Department</option>
        </select>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addDeptModal">+</button>
      </div>
    </div>
    <div class="col-md-2">
      <div class="input-group">
        <select name="section_id" class="form-select" id="sectionSelect" required>
          <option value="">Section</option>
        </select>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addSectionModal">+</button>
      </div>
    </div>
    <div class="col-md-2">
      <div class="input-group">
        <select name="position_id" class="form-select" id="positionSelect" required>
          <option value="">Position</option>
        </select>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addPositionModal">+</button>
      </div>
    </div>
  </div>

  <div class="text-end">
    <button class="btn btn-success px-5">Create User</button>
  </div>
</form>

<div class="f-panel table-responsive">
  <table class="table table-borderless f-table align-middle mb-0" id="userTable">
    <thead>
      <tr>
        <th>Name</th>
        <th>Username</th>
        <th>Role</th>
        <th>Email</th>
        <th>Status</th>
        <th class="text-end">Action</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<!-- ── Edit User slide-over (Farmis pattern) ─────────────────────────── -->
<div id="editUserOverlay" class="edit-user-overlay" hidden>
  <div class="edit-user-backdrop" data-close></div>
  <aside class="edit-user-panel" role="dialog" aria-modal="true" aria-labelledby="editUserTitle">
    <header class="eup-head">
      <div>
        <h2 id="editUserTitle">Edit user</h2>
        <p id="editUserSubtitle" class="eup-sub"></p>
      </div>
      <button type="button" class="eup-x" data-close aria-label="Close">
        <i class="bi bi-x-lg"></i>
      </button>
    </header>

    <form id="editUserForm" class="eup-body">
      <input type="hidden" id="edit_user_id" name="id">

      <div class="eup-field">
        <label class="eup-label" for="edit_full_name">Full name</label>
        <input class="form-control" id="edit_full_name" name="full_name" required>
      </div>
      <div class="eup-grid">
        <div class="eup-field">
          <label class="eup-label" for="edit_username">Username</label>
          <input class="form-control" id="edit_username" name="username" required>
        </div>
        <div class="eup-field">
          <label class="eup-label" for="edit_phone">Phone</label>
          <input class="form-control" id="edit_phone" name="phone_number" placeholder="Optional">
        </div>
      </div>
      <div class="eup-field">
        <label class="eup-label" for="edit_email">Email</label>
        <input class="form-control" type="email" id="edit_email" name="email" required>
      </div>
      <div class="eup-grid">
        <div class="eup-field">
          <label class="eup-label" for="edit_role">Role</label>
          <select class="form-select" id="edit_role" name="role" required>
            <option value="originator">Originator</option>
            <option value="endorser">Endorser</option>
            <option value="approver">Approver</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="eup-field">
          <label class="eup-label" for="edit_position">Position</label>
          <select class="form-select" id="edit_position" name="position_id"><option value="">—</option></select>
        </div>
      </div>
      <div class="eup-grid">
        <div class="eup-field">
          <label class="eup-label" for="edit_department">Department</label>
          <select class="form-select" id="edit_department" name="department_id"><option value="">—</option></select>
        </div>
        <div class="eup-field">
          <label class="eup-label" for="edit_section">Section</label>
          <select class="form-select" id="edit_section" name="section_id"><option value="">—</option></select>
        </div>
      </div>

      <div class="eup-field">
        <label class="eup-label" for="edit_password">Reset password</label>
        <input class="form-control" type="text" id="edit_password" name="password" placeholder="Leave blank to keep current">
      </div>

      <div class="eup-toggles">
        <label class="eup-check">
          <input type="checkbox" id="edit_is_co" name="is_controlling_officer" value="1">
          <span>Controlling officer</span>
        </label>
        <label class="eup-check">
          <input type="checkbox" id="edit_is_sec" name="is_secretary" value="1">
          <span>Secretary</span>
        </label>
        <label class="eup-check">
          <input type="checkbox" id="edit_active" name="active" value="1">
          <span>Active account</span>
        </label>
      </div>
    </form>

    <footer class="eup-foot">
      <button type="button" class="btn btn-outline-secondary" data-close>Cancel</button>
      <button type="submit" form="editUserForm" class="btn btn-success" id="editUserSave">
        <i class="bi bi-check-lg me-1"></i>Save changes
      </button>
    </footer>
  </aside>
</div>

<style>
  #userTable .btn-icon {
    width: 30px; height: 30px; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: var(--radius-sm, 6px);
  }
  .edit-user-overlay { position: fixed; inset: 0; z-index: 1080; display: flex; justify-content: flex-end; }
  .edit-user-overlay[hidden] { display: none !important; }
  .edit-user-backdrop {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, .32);
    backdrop-filter: blur(2px);
  }
  .edit-user-panel {
    position: relative;
    width: 100%; max-width: 460px;
    height: 100%;
    display: flex; flex-direction: column;
    background: var(--surface, #fff);
    border-left: 1px solid var(--border, #e3e7ea);
    box-shadow: -8px 0 40px rgba(15, 23, 42, .14);
    animation: eupSlide .22s ease-out;
  }
  @keyframes eupSlide { from { transform: translateX(24px); opacity: .6; } to { transform: translateX(0); opacity: 1; } }
  .eup-head {
    flex-shrink: 0;
    display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
    padding: 1.1rem 1.25rem;
    background: var(--bg, #f7f9fa);
    border-bottom: 1px solid var(--border, #e3e7ea);
  }
  .eup-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--text, #24292b); }
  .eup-sub { margin: .15rem 0 0; font-size: .8rem; color: var(--muted, #6b7280); }
  .eup-x {
    flex-shrink: 0;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--border, #e3e7ea);
    border-radius: var(--radius-sm, 6px);
    background: var(--surface, #fff);
    color: var(--muted, #6b7280);
  }
  .eup-x:hover { color: var(--text, #24292b); }
  .eup-body { flex: 1 1 auto; overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
  .eup-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .eup-field { display: flex; flex-direction: column; }
  .eup-label {
    font-size: .66rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
    color: var(--muted, #6b7280); margin-bottom: .3rem;
  }
  .eup-toggles { display: flex; flex-direction: column; gap: .55rem; padding-top: .25rem; }
  .eup-check { display: flex; align-items: center; gap: .55rem; font-size: .85rem; color: var(--text, #24292b); cursor: pointer; }
  .eup-check input { width: 16px; height: 16px; }
  .eup-foot {
    flex-shrink: 0;
    display: flex; gap: .75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border, #e3e7ea);
    background: var(--surface, #fff);
  }
  .eup-foot .btn { flex: 1 1 0; font-weight: 600; }
</style>
