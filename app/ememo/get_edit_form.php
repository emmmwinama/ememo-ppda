<div class="container py-5">
  <h3 class="mb-4 text-success">Edit Memo</h3>

<form id="memoForm" class="border rounded-3 bg-white p-5 shadow-sm" style="max-width: 900px; margin: auto;" enctype="multipart/form-data" novalidate>
  
  <!-- Branded Header -->
  <div class="text-center mb-4">
    <img src="header.png" alt="Memo Header" style="max-width: 100%; height: auto;">
  </div>

  <!-- Title -->
 <h4 id="memoTitle" class="text-center text-decoration-underline fw-bold mb-4" style="letter-spacing: 0.5px;">INTERNAL MEMORANDUM</h4>


  <!-- Memo Type & Reference -->
  <div class="row mb-4">
    <div class="col-md-4">
      <label class="form-label fw-semibold">Communication Type <span class="text-danger">*</span></label>
      <select class="form-select" name="communication_type" required>
        <option value="">Select</option>
        <option value="Loose Minute">Loose Minute</option>
        <option value="Memorandum">Memorandum</option>
      </select>
      <div class="invalid-feedback">Please select a communication type.</div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Reference (Memo ID)</label>
      <input type="text" name="memo_id" class="form-control" placeholder="e.g., PPDA/03/349">
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
      <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
      <div class="invalid-feedback">Please enter a date.</div>
    </div>
  </div>

  <!-- Routing -->
  <div class="mb-3">
    <label class="form-label fw-semibold">To <span class="text-danger">*</span></label>
    <input type="text" id="toInput" class="form-control" placeholder="Search by name or position" required>
    <div id="toResults" class="list-group position-absolute z-3 w-100"></div>
    <div id="toSelected" class="mt-2"></div>
    <input type="hidden" name="to_id">
    <input type="hidden" name="to">
    <div class="invalid-feedback">Please select a recipient.</div>
  </div>

  <div class="mb-3">
    <label class="form-label fw-semibold">Through (Endorsers)</label>
    <input type="text" id="throughInput" class="form-control" placeholder="Search and add multiple">
    <div id="throughResults" class="list-group position-absolute z-3 w-100"></div>
    <div id="throughSelected" class="mt-2"></div>
    <input type="hidden" name="through_ids" id="through_ids">
  </div>

  <!-- Subject -->
  <div class="mb-4">
    <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
    <input type="text" name="subject" class="form-control" required placeholder="e.g., Procurement Policy Review">
    <div class="invalid-feedback">Subject is required.</div>
  </div>

  <!-- Memo Body -->
  <div class="mb-4">
    <label class="form-label fw-semibold">Memo Body <span class="text-danger">*</span></label>
    <div id="editor" class="border rounded bg-white" style="min-height: 250px;"></div>
    <input type="hidden" name="content" id="content">
    <div class="invalid-feedback d-block" id="editorFeedback" style="display: none;">Memo content is required.</div>
  </div>

 <div class="mb-4">
  <label class="form-label fw-semibold">Attachments (Optional)</label>
  <input type="file" id="attachments" name="attachments[]" class="form-control" multiple>
  <div id="filePreviewList" class="mt-2"></div>

  <!-- Existing attachments container -->
  <ul id="existingAttachments" class="list-group mt-3"></ul>

  <!-- Hidden field to track removed attachments -->
  <input type="hidden" name="removed_attachments" id="removed_attachments">
</div>

  <!-- Signature -->
  <div class="mb-4">
    <label class="form-label fw-semibold">Signature</label><br>
    <button type="button" class="btn btn-outline-primary" id="openSignModal">Capture Signature</button>
  </div>

  <div class="mb-4">
    <label class="form-label fw-semibold">Signature Preview</label>
    <div class="border p-2 bg-light rounded">
      <img id="signaturePreview" src="" alt="Signature Preview" style="max-width: 100%; height: auto;">
    </div>
  </div>

  <input type="hidden" name="signature_data" id="signature_data">
  <input type="hidden" name="signature_type" id="signature_type" value="new">

  <!-- Action Buttons -->
  <div class="d-flex justify-content-end gap-3 mt-4">
    <button type="button" class="btn btn-secondary" id="saveDraft">Save as Draft</button>
    <button type="button" class="btn btn-success" id="triggerSubmit">Submit Memo</button>
  </div>
</form>




  <!-- Toast -->
  <div class="position-fixed top-0 end-0 p-3" style="z-index: 1055">
    <div id="toastMessage" class="toast align-items-center text-white bg-success border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body" id="toastBody">Memo submitted successfully!</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>
</div>

<!-- SIGNATURE MODAL -->
<div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="signatureModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Capture Signature</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <canvas id="modalSignatureCanvas" style="width: 100%; height: 150px; border:1px solid #ccc; touch-action: none;"></canvas>
        <div class="mt-2">
          <button type="button" class="btn btn-warning btn-sm" id="clearModalSignature">Clear</button>
          <button type="button" class="btn btn-info btn-sm" id="useSavedModalSignature">Use Saved Signature</button>
        </div>
        <div class="form-check mt-2">
          <input type="checkbox" class="form-check-input" id="saveModalSignature">
          <label class="form-check-label">Save this signature</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="applySignatureBtn">Apply Signature</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to <span id="confirmActionText"></span> this memo?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmActionBtn">Yes, Proceed</button>
      </div>
    </div>
  </div>
</div>


<!-- Progress Indicator Modal -->
<div class="modal fade" id="progressModal" tabindex="-1" aria-labelledby="progressModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Processing...</span>
      </div>
      <div class="mt-3">
        <p class="mb-0">Submitting your memo, please wait...</p>
      </div>
    </div>
  </div>
</div>

