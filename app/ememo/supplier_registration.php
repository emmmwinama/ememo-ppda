<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<form id="supplierRegForm" enctype="multipart/form-data">
 <ul class="nav nav-tabs mb-3" id="supplierTabs" role="tablist">
  <li class="nav-item me-2" role="presentation">
    <button class="nav-link active text-white bg-success" id="tab-general" data-bs-toggle="tab" data-bs-target="#tabGeneral" type="button" role="tab">General Info</button>
  </li>
  <li class="nav-item me-2" role="presentation">
    <button class="nav-link text-white bg-success" id="tab-ownership" data-bs-toggle="tab" data-bs-target="#tabOwnership" type="button" role="tab">Ownership</button>
  </li>
  <li class="nav-item me-2" role="presentation">
    <button class="nav-link text-white bg-success" id="tab-categories" data-bs-toggle="tab" data-bs-target="#tabCategories" type="button" role="tab">Categories</button>
  </li>
  <li class="nav-item me-2" role="presentation">
    <button class="nav-link text-white bg-success" id="tab-legal" data-bs-toggle="tab" data-bs-target="#tabLegal" type="button" role="tab">Legal & Tax</button>
  </li>
  <li class="nav-item me-2" role="presentation">
    <button class="nav-link text-white bg-success" id="tab-msme" data-bs-toggle="tab" data-bs-target="#tabMSME" type="button" role="tab">MSME</button>
  </li>
  <li class="nav-item me-2" role="presentation">
    <button class="nav-link text-white bg-success" id="tab-bank" data-bs-toggle="tab" data-bs-target="#tabBank" type="button" role="tab">Bank Info</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link text-white bg-success" id="tab-attachments" data-bs-toggle="tab" data-bs-target="#tabAttachments" type="button" role="tab">Attachments</button>
  </li>
</ul>


  <div class="tab-content border rounded bg-white p-4" id="supplierTabContent">

<!-- General Info Tab -->
<div class="tab-pane fade show active" id="tabGeneral" role="tabpanel">
  <div class="row g-3">
    <div class="col-md-6">
      <label for="taxId" class="form-label">MRA Tax Identification Number</label>
      <input type="text" id="taxId" name="tax_id" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label for="entityName" class="form-label">Entity Name</label>
      <input type="text" id="entityName" name="entity_name" class="form-control" required>
    </div>

    <div class="col-md-6">
      <label for="entityType" class="form-label">Entity Type</label>
      <select id="entityType" name="entity_type" class="form-select" required>
        <option value="">Please select</option>
        <option value="company">Company</option>
        <option value="individual">Individual</option>
        <option value="joint_venture">Joint Venture</option>
      </select>
    </div>

    <div class="col-md-6">
      <label for="entityFormation" class="form-label">Entity Formation</label>
      <select id="entityFormation" name="entity_formation" class="form-select" required>
        <option value="">Please select</option>
        <option value="malawian">Malawian</option>
        <option value="malawian_foreign">Malawian with Foreign Shares</option>
        <option value="foreign">Foreign</option>
        <option value="foreign_malawian">Foreign with Malawian Shares</option>
        <option value="foreign_malawian">Micro Small Medium Enterprise</option>
      </select>
    </div>

    <div class="col-md-6">
      <label for="country" class="form-label">Country</label>
      <input type="text" id="country" name="country" class="form-control" value="Malawi" readonly>
    </div>

    <div class="col-md-6">
      <label for="businessLocation" class="form-label">Business Location</label>
      <select id="businessLocation" name="business_location" class="form-select" required>
        <option value="">Please select</option>
        <option value="balaka">Balaka</option>
        <option value="blantyre">Blantyre</option>
        <option value="chikwawa">Chikwawa</option>
        <option value="chiradzulu">Chiradzulu</option>
        <option value="chitipa">Chitipa</option>
        <option value="dedza">Dedza</option>
        <option value="dowa">Dowa</option>
        <option value="karonga">Karonga</option>
        <option value="kasungu">Kasungu</option>
        <option value="likoma">Likoma</option>
        <option value="lilongwe">Lilongwe</option>
        <option value="machinga">Machinga</option>
        <option value="mangochi">Mangochi</option>
        <option value="mchinji">Mchinji</option>
        <option value="mulanje">Mulanje</option>
        <option value="mwaza">Mwanza</option>
        <option value="mzimba">Mzimba</option>
        <option value="mzuzu">Mzuzu City</option>
        <option value="nkhata_bay">Nkhata Bay</option>
        <option value="nkhotakota">Nkhotakota</option>
        <option value="nsanje">Nsanje</option>
        <option value="ntcheu">Ntcheu</option>
        <option value="ntchisi">Ntchisi</option>
        <option value="phalombe">Phalombe</option>
        <option value="rumphi">Rumphi</option>
        <option value="salima">Salima</option>
        <option value="thyolo">Thyolo</option>
        <option value="zomba">Zomba</option>
      </select>
    </div>

    <div class="col-md-6">
      <label for="mainPhone" class="form-label">Main Business Phone Number</label>
      <input type="text" id="mainPhone" name="main_phone" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label for="secondaryPhone" class="form-label">Secondary Mobile Number</label>
      <input type="text" id="secondaryPhone" name="secondary_phone" class="form-control">
    </div>
    <div class="col-md-6">
      <label for="officialEmail" class="form-label">Official Email</label>
      <input type="email" id="officialEmail" name="official_email" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label for="website" class="form-label">Website</label>
      <input type="text" id="website" name="website" class="form-control">
    </div>
    <div class="col-md-6">
      <label for="postalAddress" class="form-label">Postal Address</label>
      <textarea id="postalAddress" name="postal_address" class="form-control" rows="2" required></textarea>
    </div>
    <div class="col-md-6">
      <label for="businessPremise" class="form-label">Business Premise</label>
      <input type="text" id="businessPremise" name="business_premise" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label for="applicationType" class="form-label">Type of Application</label>
      <select id="applicationType" name="application_type" class="form-select" required>
        <option value="">Please select</option>
        <option value="new">New Application</option>
        <option value="upgrade">Upgrading Certificate</option>
        <option value="renewal">Certificate Renewal</option>
      </select>
    </div>

    <?php $oneYearFromNow = date('Y-m-d', strtotime('+1 year')); ?>
    <div class="col-md-6">
      <label for="expireDate" class="form-label">Expire Date</label>
      <input type="date" id="expireDate" name="expire_date" class="form-control" value="<?= $oneYearFromNow ?>" readonly required>
    </div>
	

  </div>
</div>

	

<!-- Ownership Tab -->
<div class="tab-pane fade" id="tabOwnership" role="tabpanel">
  <div id="shareholdersContainer"></div>
  <button type="button" class="btn btn-sm btn-outline-primary mt-3" onclick="addShareholder()">
    <i class="bi bi-person-plus"></i> Add Shareholder
  </button>

</div>

<!-- Categories Tab -->
<div class="tab-pane fade" id="tabCategories" role="tabpanel">
  <div class="row g-3 mb-4">

    <!-- Goods Section -->
    <div class="col-md-4">
      <label>Goods Categories</label>
      <div id="goodsCategoryContainer">Loading...</div>
    </div>
    <div class="col-md-4">
      <label>Expected Value (MWK)</label>
      <select class="form-select" name="goods_value" id="goodsValue" onchange="calculateAllFees()"></select>
    </div>
    <div class="col-md-4">
      <label>Calculated Fee</label>
      <input type="text" class="form-control bg-light" id="goodsFee" readonly>
    </div>

    <!-- Services Section -->
    <div class="col-md-4">
      <label>Services Categories</label>
      <div id="servicesCategoryContainer">Loading...</div>
    </div>
    <div class="col-md-4">
      <label>Expected Value (MWK)</label>
      <select class="form-select" name="services_value" id="servicesValue" onchange="calculateAllFees()"></select>
    </div>
    <div class="col-md-4">
      <label>Calculated Fee</label>
      <input type="text" class="form-control bg-light" id="servicesFee" readonly>
    </div>

    <!-- Works Section -->
    <div class="col-md-4">
      <label>Works Categories</label>
      <div id="worksCategoryContainer">Loading...</div>
    </div>
    <div class="col-md-4">
      <label>Expected Value (MWK)</label>
      <select class="form-select" name="works_value" id="worksValue" onchange="calculateAllFees()"></select>
    </div>
    <div class="col-md-4">
      <label>Calculated Fee</label>
      <input type="text" class="form-control bg-light" id="worksFee" readonly>
    </div>

    <!-- Total -->
    <div class="col-md-4 offset-md-8">
      <label>Total Registration Fee (MWK)</label>
      <input type="text" name="registration_fee_total" id="totalFee" class="form-control bg-success text-white fw-bold" readonly>
    </div>

  </div>

  <div id="feeTables" class="row mt-4"></div>
</div>




<!-- Legal & Tax Tab -->
<div class="tab-pane fade" id="tabLegal" role="tabpanel">
  <div class="row g-3">
    <div class="col-md-6">
      <label>Business Identification Number</label>
      <input type="text" name="business_id_number" class="form-control">
    </div>
    <div class="col-md-6">
      <label>Trading Name</label>
      <input type="text" name="trading_name" class="form-control">
    </div>
    <div class="col-md-6">
      <label>Business Registration Date</label>
      <input type="date" name="registration_date" class="form-control">
    </div>
    <div class="col-md-6">
      <label>Number of Employees</label>
      <input type="number" name="employees" class="form-control">
    </div>
	

  </div>
</div>
<!-- MSME Tab -->
<div class="tab-pane fade" id="tabMSME" role="tabpanel">
  <div class="row g-3">
    <div class="col-md-6">
      <label>MSME Number</label>
      <input type="text" name="msme_number" class="form-control">
    </div>
    <div class="col-md-6">
      <label>Type of MSME</label>
      <select name="msme_type" class="form-select">
        <option value="">Please select</option>
        <option value="micro">Micro</option>
        <option value="small">Small</option>
        <option value="medium">Medium</option>
      </select>
    </div>

  </div>
</div>



<!-- Bank Info Tab -->
<div class="tab-pane fade" id="tabBank" role="tabpanel">
  <div class="row g-3">
    <div class="col-md-6">
      <label for="bank_name">Bank Name</label>
      <select id="bank_name" name="bank_name" class="form-select" required>
        <option value="">Select Bank</option>
      </select>
    </div>
    <div class="col-md-6">
      <label for="branch_name">Branch Name</label>
      <select id="branch_name" name="branch_name" class="form-select" required>
        <option value="">Select Branch</option>
      </select>
    </div>
    <div class="col-md-6">
      <label>Account Name</label>
      <input type="text" name="account_name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label>Account Type</label>
      <select name="account_type" class="form-select" required>
        <option value="">Select Type</option>
        <option value="savings">Savings</option>
        <option value="current">Current</option>
      </select>
    </div>
    <div class="col-md-6">
      <label>Account Number</label>
      <input type="text" name="account_number" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label>Currency</label>
      <select name="currency" class="form-select" required>
        <option value="">Select Currency</option>
        <option value="MWK">MWK</option>
        <option value="USD">USD</option>
        <option value="ZAR">ZAR</option>
      </select>
    </div>
    <div class="col-md-6">
      <label>SWIFT Code</label>
      <input type="text" name="swift_code" class="form-control">
    </div>
    <div class="col-md-6">
      <label>Country of Bank</label>
      <input type="text" name="bank_country" class="form-control" value="Malawi">
    </div>

  </div>
</div>


<!-- Attachments Tab -->
<div class="tab-pane fade" id="tabAttachments" role="tabpanel">
  <div id="attachmentsContainer"></div>

  <button type="button" class="btn btn-sm btn-outline-primary mt-3" onclick="addAttachment()">
    <i class="bi bi-paperclip"></i> Add Attachment
  </button>
</div>





 
</div>
</form>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="feedbackModalLabel">Submission Status</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="feedbackMessage"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="confirmSubmitModalLabel">Confirm Submission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to submit this registration?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmSubmitBtn" class="btn btn-primary">Yes, Submit</button>
      </div>
    </div>
  </div>
</div>


<script>

function addAttachment() {
  const index = $('.attachment-row').length;
  const html = `
    <div class="card p-3 mb-3 shadow-sm attachment-row">
      <div class="row g-3">
        <div class="col-md-6">
          <label>Description</label>
          <input type="text" name="attachments[${index}][name]" class="form-control" placeholder="e.g. Tax Clearance, URSB" required>
        </div>
        <div class="col-md-6">
          <label>File (PDF, DOC, DOCX, JPG, PNG)</label>
          <input type="file" name="attachments[${index}][file]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="form-control" required>
          <div class="form-text file-feedback mt-1"></div>
        </div>
      </div>
    </div>
  `;
  $('#attachmentsContainer').append(html);
}

</script>
<script>
const allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
const maxFileSize = 5 * 1024 * 1024; // 5MB

$(document).on('change', '.attachment-row input[type="file"]', function () {
  const fileInput = $(this);
  const file = this.files[0];
  const feedback = $('<div class="form-text mt-1 file-feedback"></div>');

  fileInput.next('.file-feedback').remove(); // Clear previous

  if (!file) return;

  const ext = file.name.split('.').pop().toLowerCase();
  const isValidExt = allowedExtensions.includes(ext);
  const isValidSize = file.size <= maxFileSize;

  if (!isValidExt || !isValidSize) {
    fileInput.addClass('is-invalid');
    feedback.addClass('text-danger').text(
      `Invalid file. Only PDF, DOC, DOCX, JPG, JPEG, PNG under 5MB allowed.`
    );
    fileInput.after(feedback);
  } else {
    fileInput.removeClass('is-invalid').addClass('is-valid');
    feedback.addClass('text-success').text(`Selected: ${file.name}`);
    fileInput.after(feedback);
  }
});

// Optional: Re-check all before submit
function validateAttachments() {
  let valid = true;
  $('.attachment-row input[type="file"]').each(function () {
    const file = this.files[0];
    if (!file) return (valid = false);

    const ext = file.name.split('.').pop().toLowerCase();
    const isValidExt = allowedExtensions.includes(ext);
    const isValidSize = file.size <= maxFileSize;

    if (!isValidExt || !isValidSize) {
      $(this).addClass('is-invalid');
      valid = false;
    }
  });

  if (!valid) {
    showModal("Please ensure all attachments are valid files under 5MB.", "danger");
  }

  return valid;
}
</script>


<script>
function addShareholder() {
  const index = $('.shareholder-card').length;
  const html = `
    <div class="card p-3 mb-3 shadow-sm shareholder-card">
      <div class="row g-3">
        <div class="col-md-6">
          <label>First Name</label>
          <input type="text" name="shareholders[${index}][first_name]" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label>Last Name</label>
          <input type="text" name="shareholders[${index}][last_name]" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label>Gender</label>
          <select name="shareholders[${index}][gender]" class="form-select">
            <option value="">Select</option>
            <option>Male</option>
            <option>Female</option>
          </select>
        </div>
        <div class="col-md-6">
          <label>National ID</label>
          <input type="text" name="shareholders[${index}][national_id]" class="form-control">
        </div>
        <div class="col-md-6">
          <label>Phone</label>
          <input type="text" name="shareholders[${index}][phone]" class="form-control">
        </div>
        <div class="col-md-6">
          <label>Email</label>
          <input type="email" name="shareholders[${index}][email]" class="form-control">
        </div>
        <div class="col-md-6">
          <label>Nationality</label>
          <input type="text" name="shareholders[${index}][nationality]" class="form-control">
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="shareholders[${index}][active]" value="1">
            <label class="form-check-label">Active</label>
          </div>
        </div>
      </div>
    </div>
  `;
  $('#shareholdersContainer').append(html);
}
</script>


<script>
function addShareholder() {
  const index = $('.shareholder-card').length;
  const html = `
    <div class="card p-3 mb-3 shadow-sm shareholder-card">
      <div class="row g-3">
        <div class="col-md-6">
          <label>First Name</label>
          <input type="text" name="shareholders[${index}][first_name]" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label>Last Name</label>
          <input type="text" name="shareholders[${index}][last_name]" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label>Gender</label>
          <select name="shareholders[${index}][gender]" class="form-select" required>
            <option value="">Select...</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>
        <div class="col-md-6">
          <label>National ID</label>
          <input type="text" name="shareholders[${index}][national_id]" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label>Contact Number</label>
          <input type="text" name="shareholders[${index}][phone]" class="form-control">
        </div>
        <div class="col-md-6">
          <label>Email</label>
          <input type="email" name="shareholders[${index}][email]" class="form-control">
        </div>
        <div class="col-md-6">
          <label>Nationality</label>
          <input type="text" name="shareholders[${index}][nationality]" class="form-control">
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="shareholders[${index}][active]" value="1">
            <label class="form-check-label">Active</label>
          </div>
        </div>
      </div>
    </div>
  `;
  $('#shareholdersContainer').append(html);
}
</script>

<script>
function collectShareholders() {
  const data = [];
  $('.shareholder-card').each(function () {
    const $el = $(this);
    data.push({
      first_name: $el.find('[name*="[first_name]"]').val(),
      last_name: $el.find('[name*="[last_name]"]').val(),
      gender: $el.find('[name*="[gender]"]').val(),
      national_id: $el.find('[name*="[national_id]"]').val(),
      phone: $el.find('[name*="[phone]"]').val(),
      email: $el.find('[name*="[email]"]').val(),
      nationality: $el.find('[name*="[nationality]"]').val(),
      active: $el.find('[name*="[active]"]').is(':checked') ? 1 : 0
    });
  });
  return data;
}

function collectAttachmentsMeta() {
  const data = [];
  $('.attachment-row').each(function () {
    data.push({
      name: $(this).find('input[type="text"]').val()
    });
  });
  return data;
}

let formDataCache = null;
let submitBtnCache = null;

$('#supplierRegForm').on('submit', function (e) {
  e.preventDefault();

  if (
    !validateRequiredFields() ||
    !validateShareholders() ||
    !validateAttachments() ||
    !validateCategories()
  ) {
    showModal('Please fill all required fields correctly.', 'warning');
    return;
  }

  formDataCache = new FormData(this);
  submitBtnCache = $(this).find('button[type="submit"]');

  // Add JSON metadata
  formDataCache.append('shareholders', JSON.stringify(collectShareholders()));
  formDataCache.append('attachments_meta', JSON.stringify(collectAttachmentsMeta()));

  // Add attachment files separately
  $('.attachment-row input[type="file"]').each(function () {
    const file = this.files[0];
    if (file) {
      formDataCache.append('attachment_files[]', file);
    }
  });

  const confirmModal = new bootstrap.Modal(document.getElementById('confirmSubmitModal'));
  confirmModal.show();
});

$('#confirmSubmitBtn').on('click', function () {
  const confirmModalEl = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal'));
  confirmModalEl.hide();

  submitBtnCache.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Submitting...');

  $.ajax({
    url: 'submit_supplier_registration.php',
    type: 'POST',
    data: formDataCache,
    processData: false,
    contentType: false,
    success: function () {
      showModal('Supplier registration submitted successfully!', 'success');
      document.getElementById('supplierRegForm').reset();
      $('#attachmentsContainer').empty();
      $('#shareholdersContainer').empty();
    },
    error: function () {
      showModal('There was an error submitting the form. Please try again later.', 'danger');
    },
    complete: function () {
      submitBtnCache.prop('disabled', false).html('<i class="bi bi-save me-2"></i> Submit Registration');
    }
  });
});
</script>



<script>
function calculateFee() {
  const type = $('#supplierType').val();
  const value = parseFloat($('#contractValue').val());

  if (!type || isNaN(value) || value <= 0) {
    $('#calculatedFee').val('');
    return;
  }

  $.get('get_fee_by_value.php', { type, value }, function (data) {
    if (data.fee) {
      $('#calculatedFee').val(`MK ${data.fee}`);
    } else {
      $('#calculatedFee').val('MK 0.00');
    }
  }, 'json');
}
</script>


<script>
function calculateAllFees() {
  const goodsValue = parseFloat($('#goodsValue').val()) || 0;
  const servicesValue = parseFloat($('#servicesValue').val()) || 0;
  const worksValue = parseFloat($('#worksValue').val()) || 0;

  // Show individual fees with commas
  $('#goodsFee').val(goodsValue.toLocaleString());
  $('#servicesFee').val(servicesValue.toLocaleString());
  $('#worksFee').val(worksValue.toLocaleString());

  const total = goodsValue + servicesValue + worksValue;
  $('#totalFee').val(total.toLocaleString());
}

</script>

<script>
function loadCategories(type, containerId, inputName) {
  $.get(`load_categories.php?type=${type}`, function(data) {
    const container = $(`#${containerId}`);
    container.empty();
    if (data.length === 0) {
      container.html('<em>No options found.</em>');
      return;
    }
    data.forEach(item => {
      container.append(`
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="${inputName}[]" value="${item.item_name}" id="${inputName}_${item.id}">
          <label class="form-check-label" for="${inputName}_${item.id}">${item.item_name}</label>
        </div>
      `);
    });
  });
}

$(document).ready(function () {
  loadCategories('goods', 'goodsCategoryContainer', 'goods_category');
  loadCategories('services', 'servicesCategoryContainer', 'services_category');
  loadCategories('works', 'worksCategoryContainer', 'works_category');
});
</script>



<script>
function loadFeeOptions(type, selectId) {
  $.get(`load_fees.php?type=${type}`, function(data) {
    const select = $(`#${selectId}`);
    select.empty().append('<option value="">-- Select Expected Value --</option>');
    data.forEach(item => {
      const fee = parseFloat(item.fee.replace(/,/g, '')); // Remove commas and parse as float
      const formattedFee = fee.toLocaleString(); // Format number with commas
      select.append(`<option value="${fee}" data-range="${item.value_range}">
        ${item.value_range} - MWK ${formattedFee}
      </option>`);
    });
  });
}

$(document).ready(() => {
  loadFeeOptions('goods', 'goodsValue');
  loadFeeOptions('services', 'servicesValue');
  loadFeeOptions('works', 'worksValue');
});


</script>

<script>
$(document).ready(function() {
  // Load banks into the bank select dropdown
  $.ajax({
    url: 'get_banks.php',
    type: 'GET',
    dataType: 'json',
    success: function(data) {
      var bankSelect = $('#bank_name');
      bankSelect.empty().append('<option value="">Select Bank</option>');
      $.each(data, function(index, bank) {
        bankSelect.append('<option value="' + bank.id + '">' + bank.name + '</option>');
      });
    },
    error: function() {
      alert('Failed to load banks.');
    }
  });

  // Load branches when a bank is selected
  $('#bank_name').change(function() {
    var bankId = $(this).val();
    var branchSelect = $('#branch_name');
    branchSelect.empty().append('<option value="">Select Branch</option>');

    if (bankId) {
      $.ajax({
        url: 'get_branches.php',
        type: 'GET',
        data: { bank_id: bankId },
        dataType: 'json',
        success: function(data) {
          $.each(data, function(index, branch) {
            branchSelect.append('<option value="' + branch.id + '">' + branch.name + '</option>');
          });
        },
        error: function() {
          alert('Failed to load branches.');
        }
      });
    }
  });
});
</script>

<script>
function validateShareholders() {
  const count = document.querySelectorAll('.shareholder-card').length;
  if (count === 0) {
    alert("Please add at least one shareholder.");
    $('#tab-ownership').tab('show');
    return false;
  }
  return true;
}

function validateAttachments() {
  const count = document.querySelectorAll('.attachment-row').length;
  if (count === 0) {
    alert("Please attach at least one document.");
    $('#tab-attachments').tab('show');
    return false;
  }
  return true;
}

function validateCategories() {
  const goodsChecked = document.querySelectorAll('input[name="goods_category[]"]:checked').length > 0;
  const servicesChecked = document.querySelectorAll('input[name="services_category[]"]:checked').length > 0;
  const worksChecked = document.querySelectorAll('input[name="works_category[]"]:checked').length > 0;

  if (!goodsChecked && !servicesChecked && !worksChecked) {
    alert("Select at least one item under Goods, Services or Works.");
    $('#tab-categories').tab('show');
    return false;
  }

  return true;
}

function validateRequiredFields() {
  let valid = true;

  $('#supplierRegForm [required]').each(function () {
    if (!$(this).val()) {
      const tabPane = $(this).closest('.tab-pane');
      const tabId = tabPane.attr('id');

      // Show tab before focusing
      $(`button[data-bs-target="#${tabId}"]`).tab('show');

      // Small timeout to allow DOM to render tab before focusing
      setTimeout(() => this.focus(), 200);

      $(this).addClass('is-invalid');
      showModal('Please complete all required fields before proceeding.', 'warning');

      valid = false;
      return false; // break loop
    } else {
      $(this).removeClass('is-invalid');
    }
  });

  return valid;
}

</script>



<script>
$(document).ready(function () {
  const tabs = $('#supplierTabs button');
  const tabPanes = $('.tab-pane');

tabPanes.each(function (i) {
  const isLast = i === tabPanes.length - 1;

  // Avoid duplicate buttons
  if ($(this).find('.next-tab, button[type="submit"]').length === 0) {
    const btn = isLast
      ? `<button type="submit" class="btn btn-success mt-4 float-end">
           <i class="bi bi-save me-2"></i> Submit Registration
         </button>`
      : `<button type="button" class="btn btn-primary mt-4 float-end next-tab">
           Next <i class="bi bi-arrow-right-circle"></i>
         </button>`;

    $(this).append(btn);
  }
});


  $(document).off('click', '.next-tab').on('click', '.next-tab', function () {
    const currentTab = $('.tab-pane.show.active');
    const currentIndex = tabPanes.index(currentTab);
    const nextTabButton = tabs.eq(currentIndex + 1);

    // Validate required fields
    let isValid = true;
    currentTab.find('[required]').each(function () {
      if (!$(this).val()) {
        $(this).addClass('is-invalid').focus();
        isValid = false;
        return false;
      } else {
        $(this).removeClass('is-invalid');
      }
    });

    if (isValid && nextTabButton.length) {
      const nextTab = new bootstrap.Tab(nextTabButton[0]);
      nextTab.show();
    }
  });
});

</script>

<script>
function showModal(message, type = 'info') {
  const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  const messageContainer = document.getElementById('feedbackMessage');
  const header = document.querySelector('#feedbackModal .modal-header');

  // Reset styles
  header.className = 'modal-header';
  header.classList.add('text-white');

  switch (type) {
    case 'success':
      header.classList.add('bg-success');
      break;
    case 'danger':
      header.classList.add('bg-danger');
      break;
    case 'warning':
      header.classList.add('bg-warning', 'text-dark');
      break;
    default:
      header.classList.add('bg-info');
  }

  messageContainer.innerHTML = message;
  modal.show();
}
</script>

<script>
$(document).ready(function () {
  const tabButtons = $('#supplierTabs button');

  tabButtons.on('shown.bs.tab', function () {
    // Remove "active" and styling from all
    tabButtons.removeClass('active bg-success text-white fw-bold').addClass('bg-success text-white');

    // Add to currently shown
    $(this).addClass('active fw-bold');
  });

  // Trigger once for the initial active tab
  tabButtons.filter('.active').trigger('shown.bs.tab');
});
</script>

