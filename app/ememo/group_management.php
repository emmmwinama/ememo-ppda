<!-- Group Management Section -->
<div class="mb-3 d-flex justify-content-between">
  <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal">
    <i class="bi bi-plus-lg me-1"></i>New group
  </button>
  <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal" id="openAddMemberBtn" disabled>
    <i class="bi bi-person-plus me-1"></i>Add member
  </button>
</div>

<div class="row mb-4">
  <div class="col-md-4">
    <select id="groupSelect" class="form-select">
      <option value="">Select group</option>
    </select>
  </div>
</div>

<div class="f-panel table-responsive">
  <table class="table table-borderless f-table align-middle mb-0" id="groupMembersTable">
    <thead>
      <tr>
        <th>Full name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="4"><div class="f-state"><i class="bi bi-people"></i><p>Select a group to see its members.</p></div></td></tr>
    </tbody>
  </table>
</div>
