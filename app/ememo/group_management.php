<!-- Group Management Section -->
<h4 class="mb-4">Group Management</h4>

<!-- Action Buttons -->
<div class="mb-3 d-flex justify-content-between">
  <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
    ➕ New Group
  </button>
  <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal" id="openAddMemberBtn" disabled>
    ➕ Add Member
  </button>
</div>

<!-- Group Select Dropdown -->
<div class="row mb-4">
  <div class="col-md-4">
    <select id="groupSelect" class="form-select">
      <option value="">Select Group</option>
      <!-- Options populated via JS -->
    </select>
  </div>
</div>

<!-- Members Table -->
<div class="table-responsive">
  <table class="table table-bordered" id="groupMembersTable">
    <thead>
      <tr>
        <th>Full Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <!-- Members populated via JS -->
    </tbody>
  </table>
</div>
