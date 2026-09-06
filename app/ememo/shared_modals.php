<!-- Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Department</h5></div>
      <div class="modal-body">
        <input type="text" id="newDepartmentInput" class="form-control" placeholder="Department name">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="addDepartment()">Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Section</h5></div>
      <div class="modal-body">
        <select id="sectionDeptSelect" class="form-select mb-2"></select>
        <input type="text" id="newSectionInput" class="form-control" placeholder="Section name">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="addSection()">Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Position Modal -->
<div class="modal fade" id="addPositionModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Position</h5></div>
      <div class="modal-body">
        <select id="positionGradeSelect" class="form-select mb-2">
          <option value="">Select Grade</option>
        </select>
        <input type="text" id="newPositionNameInput" class="form-control mb-2" placeholder="Position name">
        <input type="text" id="newPositionShortInput" class="form-control" placeholder="Short name (optional)">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="addPosition()">Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Create New Group</h5></div>
      <div class="modal-body">
        <input type="text" id="newGroupName" class="form-control mb-2" placeholder="Group Name" required>
        <textarea id="newGroupDesc" class="form-control" rows="3" placeholder="Description (optional)"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="createGroup()">Create</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Member</h5></div>
      <div class="modal-body">
        <select id="modalUserSelect" class="form-select"></select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="confirmAddUserToGroup()">Add</button>
      </div>
    </div>
  </div>
</div>
