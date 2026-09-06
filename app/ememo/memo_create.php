<?php
require_once 'auth.php';

// Originator identity for the live preview signature block
$previewFromName = $_SESSION['username'] ?? '';
$previewFromRole = '';
if (!empty($_SESSION['user_id']) && isset($conn)) {
    $pstmt = $conn->prepare(
        "SELECT u.full_name, p.name AS position
         FROM users u LEFT JOIN positions p ON u.position_id = p.id
         WHERE u.id = ?"
    );
    $pstmt->bind_param('i', $_SESSION['user_id']);
    $pstmt->execute();
    if ($prow = $pstmt->get_result()->fetch_assoc()) {
        $previewFromName = $prow['full_name'] ?: $previewFromName;
        $previewFromRole = $prow['position'] ?? '';
    }
    $pstmt->close();
}

// Best-guess supervisor title (same section, a Manager/Director/Chief/Head role) —
// the originator can override it in the form.
$previewSupervisorRole = '';
if (!empty($_SESSION['user_id']) && isset($conn)) {
    $sstmt = $conn->prepare(
        "SELECT p.name
         FROM users u JOIN positions p ON u.position_id = p.id
         WHERE u.id <> ? AND u.active = 1
           AND u.section_id = (SELECT section_id FROM users WHERE id = ?)
           AND (p.name LIKE '%Manager%' OR p.name LIKE '%Director%'
                OR p.name LIKE '%Chief%'   OR p.name LIKE '%Head%')
         ORDER BY p.id
         LIMIT 1"
    );
    $sstmt->bind_param('ii', $_SESSION['user_id'], $_SESSION['user_id']);
    $sstmt->execute();
    if ($srow = $sstmt->get_result()->fetch_assoc()) {
        $previewSupervisorRole = $srow['name'];
    }
    $sstmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Memo - e-Memo System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>

  <style>
    /* ── New-memo layout: form + live preview pane (Farmis pattern) ──── */
    .memo-create-layout {
      display: flex;
      gap: 1.5rem;
      align-items: stretch;   /* preview column stretches full height so its sticky child can travel */
      max-width: 1400px;
      margin: 0 auto;
    }
    .memo-form-col { flex: 1 1 620px; min-width: 0; }
    .memo-preview-col { flex: 0 0 380px; }
    /* top offset clears the sticky app navbar from header.php */
    .memo-preview-sticky { position: sticky; top: 70px; }
    .memo-preview-label {
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .09em;
      text-transform: uppercase;
      color: var(--muted, #6b7280);
      margin-bottom: .5rem;
    }
    /* ── Preview document: PPDA loose-minute / minute-paper format ────── */
    .memo-preview-doc {
      --mp-fs: 10.5px;            /* compact in the sidebar; the expanded modal overrides this */
      font-family: Tahoma, Geneva, Verdana, sans-serif;
      font-size: var(--mp-fs);
      line-height: 1.45;
      color: #1b1b1b;
      background: #fff;
      border: 1px solid var(--border, #e3e7ea);
      border-radius: var(--radius-md, 10px);
      max-height: calc(100vh - 90px);
      overflow: auto;
      display: flex;
      align-items: stretch;
    }
    .memo-preview-doc * { font-family: Tahoma, Geneva, Verdana, sans-serif; }

    /* Sidebar preview is a click target that opens the full, downloadable view */
    .memo-preview-col .memo-preview-doc { cursor: zoom-in; transition: box-shadow .15s; }
    .memo-preview-col .memo-preview-doc:hover { box-shadow: 0 0 0 2px var(--brand, #2a8f2e); }
    .memo-preview-expand-hint {
      font-size: 10px;
      color: var(--muted, #6b7280);
      margin-top: .35rem;
      display: flex;
      align-items: center;
      gap: .3rem;
    }

    /* Left margin — where endorsers & signatories write their notes */
    .memo-preview-doc .mp-notes {
      flex: 0 0 23%;
      max-width: 23%;
      padding: 14px 8px 14px 12px;
      border-right: 1px dashed #bbb;
      font-size: .82em;
      color: #555;
    }
    .memo-preview-doc .mp-notes-hint { color: #9aa0a6; font-style: italic; }
    .memo-preview-doc .mp-note { margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px dotted #ddd; }
    .memo-preview-doc .mp-note .mp-note-by { font-weight: bold; }

    /* Right column — the minute itself */
    .memo-preview-doc .mp-main {
      flex: 1 1 auto;
      min-width: 0;
      padding: 14px 14px 14px 14px;
    }
    .memo-preview-doc .mp-letterhead { margin-bottom: 10px; }
    .memo-preview-doc .mp-letterhead img { max-width: 100%; height: auto; }
    .memo-preview-doc .mp-title {
      font-size: 1.05em;
      font-weight: bold;
      text-align: center;
      text-decoration: underline;
      letter-spacing: .4px;
      margin: 4px 0 10px;
    }
    .memo-preview-doc .mp-row { display: flex; gap: 6px; margin-bottom: 2px; }
    /* NB: no CSS text-transform here — html2canvas drops font-weight on
       elements that also carry text-transform, so uppercasing is done in JS. */
    .memo-preview-doc .mp-row .mp-k { font-weight: bold; flex: 0 0 6.2em; }
    .memo-preview-doc .mp-row .mp-v { flex: 1 1 auto; font-weight: bold; word-break: break-word; }
    .memo-preview-doc .mp-through-list { display: block; }
    .memo-preview-doc .mp-through-list span { display: block; font-weight: bold; }
    .memo-preview-doc .mp-subject {
      text-align: center;
      font-weight: bold;
      margin: 11px 0;
    }
    .memo-preview-doc .mp-body { margin-top: 6px; text-align: justify; }
    .memo-preview-doc .mp-body p { margin: 0 0 7px; }
    .memo-preview-doc .mp-empty { color: #999; font-style: italic; }
    .memo-preview-doc .mp-sign { margin-top: 28px; text-align: center; }
    .memo-preview-doc .mp-sign img { max-height: 4.6em; width: auto; display: inline-block; }
    .memo-preview-doc .mp-sign-name { font-weight: bold; }
    .memo-preview-doc .mp-refline {
      display: flex;
      justify-content: space-between;
      margin-top: 18px;
      font-weight: bold;
    }

    /* Expanded view inside the modal — page-like, full size */
    .memo-preview-doc.memo-preview-doc--full {
      --mp-fs: 12px;
      line-height: 1.55;
      width: 794px;              /* ~A4 @96dpi */
      max-width: 100%;
      max-height: none;
      margin: 0 auto;
      border-color: #ccc;
      border-radius: 0;
    }
    .memo-preview-doc.memo-preview-doc--full .mp-notes { padding: 28px 14px 28px 22px; }
    .memo-preview-doc.memo-preview-doc--full .mp-main { padding: 28px 30px; }

    /* ── Draft-letter modal: form + PPDA letter preview ─────────────── */
    .letter-modal-layout { display: flex; gap: 1.25rem; align-items: flex-start; }
    .letter-modal-form { flex: 1 1 46%; min-width: 0; }
    .letter-modal-preview { flex: 1 1 54%; min-width: 0; position: sticky; top: 0; }
    .letter-doc {
      font-family: Tahoma, Geneva, Verdana, sans-serif;
      font-size: 11px;
      line-height: 1.5;
      color: #1b1b1b;
      background: #fff;
      border: 1px solid #ccc;
      padding: 22px 26px;
      max-height: 70vh;
      overflow: auto;
    }
    .letter-doc * { font-family: Tahoma, Geneva, Verdana, sans-serif; }
    .letter-doc .lt-letterhead { margin-bottom: 8px; }
    .letter-doc .lt-letterhead img { max-width: 100%; height: auto; }
    .letter-doc .lt-addr { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
    .letter-doc .lt-addr-left { font-weight: bold; }
    .letter-doc .lt-addr-right { text-align: right; font-weight: bold; border: 1px solid #ddd; padding: 6px 10px; }
    .letter-doc .lt-refline { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 14px; }
    .letter-doc .lt-row { display: flex; gap: 8px; margin-bottom: 8px; }
    .letter-doc .lt-row .lt-k { font-weight: bold; flex: 0 0 42px; }
    .letter-doc .lt-row .lt-v { flex: 1 1 auto; font-weight: bold; word-break: break-word; }
    .letter-doc .lt-to { margin-bottom: 14px; }
    .letter-doc .lt-to .lt-to-row { display: flex; gap: 8px; margin-bottom: 6px; }
    .letter-doc .lt-to .lt-to-row .lt-k { font-weight: bold; flex: 0 0 42px; }
    .letter-doc .lt-to .lt-to-row .lt-v { flex: 1 1 auto; font-weight: bold; }
    .letter-doc .lt-subject {
      font-weight: bold;
      text-decoration: underline;
      margin: 14px 0;
    }
    .letter-doc .lt-body { text-align: justify; }
    .letter-doc .lt-body p { margin: 0 0 8px; }
    .letter-doc .lt-empty { color: #999; font-style: italic; }
    .letter-doc .lt-close { text-align: center; margin-top: 22px; }
    .letter-doc .lt-sign { text-align: center; margin-top: 6px; }
    .letter-doc .lt-sign img { max-height: 52px; width: auto; display: inline-block; margin: 4px 0; }
    .letter-doc .lt-sign-name { font-weight: bold; }
    .letter-doc .lt-sign-role { font-weight: bold; }
    .letter-doc .lt-footer {
      text-align: center;
      font-style: italic;
      color: var(--brand, #2a8f2e);
      margin-top: 34px;
      padding-top: 8px;
      border-top: 1px solid #eee;
    }
    @media (max-width: 991px) {
      .letter-modal-layout { flex-direction: column; }
      .letter-modal-preview { position: static; width: 100%; }
      .letter-doc { max-height: none; }
      .memo-create-layout { flex-direction: column; }
      .memo-preview-col { flex-basis: auto; width: 100%; align-self: stretch; }
      .memo-preview-sticky { position: static; }
      .memo-preview-doc { max-height: none; }
    }
  </style>
</head>
<body>

<div class="container-fluid py-4">
  <div class="memo-create-layout">
    <div class="memo-form-col">

<form id="memoForm" class="border rounded-3 bg-white p-4 p-md-5 shadow-sm w-100" enctype="multipart/form-data" novalidate>
  
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
  <label for="memoId" class="form-label fw-semibold">Reference (Memo ID)</label>
  <input
    type="text"
    class="form-control"
    id="memoId"
    name="memo_id"
    placeholder="Loading…"
    readonly
    style="background-color: #e9ecef;"
  >
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

  <!-- Attachments -->
  <div class="mb-4">
    <label class="form-label fw-semibold">Attachments (Optional)</label>
    <input type="file" id="attachments" name="attachments[]" class="form-control" multiple>
    <div id="filePreviewList" class="mt-2"></div>
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

  <!-- Signing capacity: sign in own title, or "For:" a supervisor -->
  <div class="mb-4">
    <label class="form-label fw-semibold">Signing capacity</label>
    <select class="form-select" name="signing_capacity" id="signingCapacity"
            data-own-role="<?php echo htmlspecialchars($previewFromRole); ?>">
      <option value="self" selected>In my own title<?php echo $previewFromRole !== '' ? ' — ' . htmlspecialchars($previewFromRole) : ''; ?></option>
      <option value="for_supervisor">For my supervisor</option>
    </select>
    <div class="mt-2" id="supervisorTitleWrap" style="display:none;">
      <input type="text" class="form-control" name="signing_for_title" id="signingForTitle"
             placeholder="Supervisor's title (e.g. e-Procurement Manager)"
             value="<?php echo htmlspecialchars($previewSupervisorRole); ?>">
      <div class="form-text">Shown on the minute as “For: &lt;title&gt;” under your name.</div>
    </div>
  </div>

  <input type="hidden" name="signature_data" id="signature_data">
  <input type="hidden" name="signature_type" id="signature_type" value="new">
  
  
  <input type="hidden" name="letter_content" id="letter_content">
<input type="hidden" name="letter_date"    id="letter_date">
<input type="hidden" name="letter_ref_no"  id="letter_ref_no">
<input type="hidden" name="letter_subject" id="letter_subject">
<input type="hidden" name="letter_signed_by_id"   id="letter_signed_by_id">
<input type="hidden" name="letter_signed_by_name" id="letter_signed_by_name">
<input type="hidden" name="letter_signing_capacity" id="letter_signing_capacity" value="self">
<input type="hidden" name="letter_signing_title"  id="letter_signing_title">

  
    <!-- Letter Preview (hidden until you save a draft) -->
  <div id="letterPreviewContainer" class="border rounded p-3 mb-4" style="display: none;">
    <h5 class="mb-3">External Letter Draft Preview</h5>
    <div class="mb-2"><strong>Date:</strong> <span id="previewLetterDate"></span></div>
    <div class="mb-2"><strong>Ref #:</strong>  <span id="previewLetterRef"></span></div>
    <div class="mb-2"><strong>Subject:</strong> <span id="previewLetterSubject"></span></div>
    <div class="mb-3">
      <strong>To:</strong>
      <ul id="previewLetterRecipients" class="mb-0 ps-3"></ul>
    </div>
    <div id="previewLetterContent" class="border rounded p-2 bg-light"></div>
    <button type="button" class="btn btn-link btn-sm mt-2" id="editLetterBtn">
      ✎ Edit Letter
    </button>
  </div>


  <!-- Action Buttons -->
  <div class="d-flex justify-content-end gap-3 mt-4">
   <button type="button" class="btn btn-secondary" id="saveDraft">Save as Draft</button>
    <button type="button" class="btn btn-success" id="triggerSubmit">Submit Memo</button>
	<!-- inside your <form>’s action-button row -->
<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#letterModal">
  Include Draft Letter
</button>

  </div>
</form>

    </div><!-- /memo-form-col -->

    <aside class="memo-preview-col">
      <div class="memo-preview-sticky">
        <div class="memo-preview-label">Live Preview</div>
        <div id="memoPreviewDoc" class="memo-preview-doc" role="button" tabindex="0"
             title="Click to open the full preview">

          <!-- Left margin: endorsers' & signatories' notes -->
          <div class="mp-notes" id="mpNotes">
            <div class="mp-notes-hint">Endorsers&rsquo; &amp; signatories&rsquo; notes will appear here.</div>
          </div>

          <!-- The minute -->
          <div class="mp-main">
            <div class="mp-letterhead"><img src="header.png" alt="PPDA"></div>

            <div class="mp-title" id="mpTitle">LOOSE MINUTE</div>

            <div class="mp-row"><span class="mp-k">TO:</span><span class="mp-v" id="mpTo">&mdash;</span></div>
            <div class="mp-row" id="mpThroughRow" style="display:none;">
              <span class="mp-k">THROUGH</span><span class="mp-v mp-through-list" id="mpThrough"></span>
            </div>

            <div class="mp-subject" id="mpSubject">&mdash;</div>

            <div class="mp-body" id="mpBody"><span class="mp-empty">Start typing the memo body&hellip;</span></div>

            <div class="mp-sign">
              <img id="mpSignImg" src="" alt="" style="display:none;">
              <div class="mp-sign-name" id="mpFrom"><?php echo htmlspecialchars($previewFromName); ?></div>
              <div class="mp-sign-role" id="mpRole"<?php echo $previewFromRole === '' ? ' style="display:none;"' : ''; ?>><?php echo htmlspecialchars($previewFromRole); ?></div>
            </div>

            <div class="mp-refline">
              <span id="mpRef">Ref: &mdash;</span>
              <span id="mpDate">&mdash;</span>
            </div>
          </div>
        </div>
        <div class="memo-preview-expand-hint">
          <i class="bi bi-arrows-fullscreen"></i> Click the preview to open the full, downloadable view
        </div>
      </div>
    </aside>

    <!-- Full preview modal (downloadable as PDF) -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Memo Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background:#f1f3f5;">
            <div id="previewModalBody"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" id="previewPrintBtn">
              <i class="bi bi-printer me-1"></i>Print
            </button>
            <button type="button" class="btn btn-success" id="previewPdfBtn">
              <i class="bi bi-file-earmark-arrow-down me-1"></i>Download PDF
            </button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /memo-create-layout -->

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

<!-- DRAFT LETTER MODAL -->
<div class="modal fade" id="letterModal" tabindex="-1" aria-labelledby="letterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="letterModalLabel">Draft External Letter</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="letter-modal-layout">

          <!-- FORM -->
          <div class="letter-modal-form">
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label fw-semibold">Letter Date</label>
                <input type="date" id="modal_letter_date" class="form-control" value="<?=date('Y-m-d')?>">
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Reference #</label>
                <input type="text" id="modal_letter_ref_no" class="form-control" placeholder="e.g. PPDA/03/349">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Subject</label>
              <input type="text" id="modal_letter_subject" class="form-control" placeholder="Subject of the letter">
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">To (recipients)</label>
              <div id="recipientsContainer">
                <div class="recipient-row input-group mb-2">
                  <input type="text" class="recipient-position form-control" placeholder="Position / Name" required>
                  <input type="text" class="recipient-address form-control" placeholder="Address line(s)" required>
                  <button type="button" class="btn btn-outline-danger remove-recipient">&times;</button>
                </div>
              </div>
              <button type="button" id="addRecipientBtn" class="btn btn-sm btn-outline-primary">+ Add Recipient</button>
            </div>

            <div class="mb-3 p-3 rounded" style="background:var(--bg,#f7f9fa);border:1px solid var(--border,#e3e7ea);">
              <label class="form-label fw-semibold mb-1">Signed by</label>
              <div id="letterSignerName" class="fw-bold">—</div>
              <div class="form-text mb-2">The memo’s <strong>To:</strong> recipient signs the outgoing letter.</div>

              <label class="form-label fw-semibold" for="letterSigningCapacity">Signing capacity</label>
              <select class="form-select" id="letterSigningCapacity">
                <option value="self" selected>In their own title</option>
                <option value="for_dg">For the Director General</option>
                <option value="for_adg">For the Acting Director General</option>
              </select>
              <div class="form-text">Shown under the name as “For: …” unless signing in their own title.</div>
            </div>

            <label class="form-label fw-semibold">Letter Content</label>
            <div id="letterEditor" class="border rounded" style="min-height:220px;"></div>
          </div>

          <!-- PREVIEW -->
          <div class="letter-modal-preview">
            <div class="memo-preview-label">Letter Preview</div>
            <div id="letterPreviewDoc" class="letter-doc">
              <div class="lt-letterhead"><img src="header.png" alt="PPDA"></div>
              <div class="lt-addr">
                <div class="lt-addr-left">
                  Jireh Bible House<br>Off Colby Road<br>Area 3<br>Lilongwe<br>Malawi
                </div>
              </div>
              <div class="lt-refline">
                <span id="ltRef">Ref. No: —</span>
                <span id="ltDate">—</span>
              </div>
              <div class="lt-row"><span class="lt-k">FROM:</span>
                <span class="lt-v">THE DIRECTOR GENERAL, PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY, PRIVATE BAG 383, LILONGWE 3, MALAWI.</span>
              </div>
              <div class="lt-to" id="ltTo"></div>
              <div class="lt-subject" id="ltSubject">—</div>
              <div class="lt-body" id="ltBody"><span class="lt-empty">Type the letter content…</span></div>
              <div class="lt-close">Yours faithfully,</div>
              <div class="lt-sign">
                <img id="ltSignImg" src="" alt="" style="display:none;">
                <div class="lt-sign-name" id="ltSignName">—</div>
                <div class="lt-sign-role" id="ltSignRole"></div>
              </div>
              <div class="lt-footer">All correspondence should be addressed to the Director General</div>
            </div>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary me-auto" id="letterPdfBtn">
          <i class="bi bi-file-earmark-arrow-down me-1"></i>Download PDF
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="applyLetterBtn">Save Letter Draft</button>
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

<!-- No‐Signature Warning Modal -->
<div class="modal fade" id="noSigModal" tabindex="-1" aria-labelledby="noSigModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="noSigModalLabel">Signature Required</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>You don’t have a saved signature on file.  You must draw or upload one before you can send or save your memo.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
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


<!-- JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Quill Better Table -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill-better-table@1.2.10/dist/quill-better-table.min.css">
<script src="https://cdn.jsdelivr.net/npm/quill-better-table@1.2.10/dist/quill-better-table.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>

<script>
// --- Letter Drafting & Preview Logic ---

// 1) Initialize the Quill editor for the letter
const letterQuill = new Quill('#letterEditor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [1, 2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      ['blockquote', 'code-block'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      [{ indent: '-1' }, { indent: '+1' }],
      [{ color: [] }, { background: [] }],
      [{ align: [] }],
      ['link', 'image'],
      ['clean'],
      ['table'] // support for tables if you have quill-table extension
    ]
  }
});

// 2) Add / remove recipient rows
$('#addRecipientBtn').on('click', () => {
  $('#recipientsContainer').append(`
    <div class="recipient-row input-group mb-2">
      <input type="text" name="letter_recipients[position][]" class="recipient-position form-control" placeholder="Position / Name" required>
      <input type="text" name="letter_recipients[address][]" class="recipient-address form-control" placeholder="Address line(s)" required>
      <button type="button" class="btn btn-outline-danger remove-recipient">&times;</button>
    </div>
  `);
});
$('#recipientsContainer').on('click', '.remove-recipient', function() {
  if ($('#recipientsContainer .recipient-row').length > 1) {
    $(this).closest('.recipient-row').remove();
    renderLetterPreview();
  }
});

// ── Live PPDA letter preview ───────────────────────────────────────────
let letterSigner = { id: null, name: '', position: '', signature: '' };

function refreshLetterSigner() {
  // The person on the memo's To: field signs the outgoing letter
  const name = (typeof toUser !== 'undefined' && toUser) ? toUser.name : '';
  $('#letterSignerName').text(name || '— (set the memo’s “To” first)');
  $('#letterSigningCapacity option[value="self"]').text('In their own title');

  if (!name || !toUser || !toUser.id) {
    letterSigner = { id: null, name: name, position: '', signature: '' };
    renderLetterPreview();
    return;
  }
  if (letterSigner.id === toUser.id) { renderLetterPreview(); return; }

  $.getJSON('get_user_info.php', { user_id: toUser.id })
    .done(res => {
      if (res && res.status === 'success' && res.user) {
        letterSigner = {
          id: toUser.id,
          name: res.user.full_name || name,
          position: res.user.position || '',
          signature: res.user.signature_path || ''
        };
        if (letterSigner.position) {
          $('#letterSigningCapacity option[value="self"]').text('In their own title — ' + letterSigner.position);
        }
      } else {
        letterSigner = { id: toUser.id, name: name, position: '', signature: '' };
      }
      renderLetterPreview();
    })
    .fail(() => { letterSigner = { id: toUser.id, name: name, position: '', signature: '' }; renderLetterPreview(); });
}

function letterFmtDate(iso) {
  const dt = new Date(iso + 'T00:00:00');
  if (isNaN(dt)) return iso || '—';
  const day = dt.getDate();
  const suf = (day % 10 === 1 && day !== 11) ? 'st' : (day % 10 === 2 && day !== 12) ? 'nd'
            : (day % 10 === 3 && day !== 13) ? 'rd' : 'th';
  return day + suf + ' ' + dt.toLocaleDateString('en-GB', { month: 'long' }) + ' ' + dt.getFullYear();
}

function renderLetterPreview() {
  const esc = s => $('<div>').text(s == null ? '' : s).html();

  const date = $('#modal_letter_date').val();
  $('#ltDate').text(date ? letterFmtDate(date) : '—');

  const ref = $('#modal_letter_ref_no').val().trim();
  $('#ltRef').text('Ref. No: ' + (ref || '—'));

  const subject = $('#modal_letter_subject').val().trim();
  $('#ltSubject').text(subject ? subject.toUpperCase() : '—');

  // Recipients — first row "TO :", the rest just ":"
  const rows = [];
  $('#recipientsContainer .recipient-row').each(function () {
    const pos  = ($(this).find('.recipient-position').val() || '').trim();
    const addr = ($(this).find('.recipient-address').val()  || '').trim();
    if (!pos && !addr) return;
    rows.push([pos, addr].filter(Boolean).join(', ').toUpperCase());
  });
  if (rows.length) {
    $('#ltTo').html(rows.map((r, i) =>
      '<div class="lt-to-row"><span class="lt-k">' + (i === 0 ? 'TO&nbsp;:' : ':') + '</span>' +
      '<span class="lt-v">' + esc(r) + '</span></div>'
    ).join(''));
  } else {
    $('#ltTo').html('<div class="lt-to-row"><span class="lt-k">TO&nbsp;:</span><span class="lt-v">—</span></div>');
  }

  const html = letterQuill.root.innerHTML.trim();
  $('#ltBody').html(html && html !== '<p><br></p>' ? html : '<span class="lt-empty">Type the letter content…</span>');

  // Signature block — signer = memo's To: recipient
  $('#ltSignName').text(letterSigner.name || '—');
  if (letterSigner.signature) { $('#ltSignImg').attr('src', letterSigner.signature).show(); }
  else { $('#ltSignImg').hide(); }

  const cap = $('#letterSigningCapacity').val();
  let roleText = '', roleShow = true;
  if (cap === 'for_dg')       roleText = 'For: DIRECTOR GENERAL';
  else if (cap === 'for_adg') roleText = 'For: ACTING DIRECTOR GENERAL';
  else { roleText = (letterSigner.position || '').toUpperCase(); roleShow = !!roleText; }
  $('#ltSignRole').text(roleText).toggle(roleShow);
}

// Re-render on any letter-form change
$('#letterModal').on('input change',
  '#modal_letter_date, #modal_letter_ref_no, #modal_letter_subject, #letterSigningCapacity, .recipient-position, .recipient-address',
  renderLetterPreview);
letterQuill.on('text-change', renderLetterPreview);
$('#addRecipientBtn').on('click', renderLetterPreview);

// When the modal opens: prefill ref from the memo, refresh signer, render
$('#letterModal').on('shown.bs.modal', () => {
  if (!$('#modal_letter_ref_no').val().trim()) {
    const r = $('#memoId').val();
    if (r && r !== 'Loading…' && r !== 'Loading...') $('#modal_letter_ref_no').val(r);
  }
  refreshLetterSigner();
});

// Download the letter preview as a PDF (render a detached clone — see note above)
$('#letterPdfBtn').on('click', function () {
  const src = document.getElementById('letterPreviewDoc');
  if (!src || typeof html2pdf === 'undefined') return;
  const raw = ($('#modal_letter_subject').val() || 'letter').trim();
  const fname = (raw.replace(/[^\w\- ]+/g, '').replace(/\s+/g, '_').slice(0, 60) || 'letter') + '.pdf';
  const btn = this; btn.disabled = true;

  const holder = document.createElement('div');
  holder.style.cssText = 'position:absolute;left:-9999px;top:0;width:794px;background:#fff;';
  const clone = src.cloneNode(true);
  clone.style.maxHeight = 'none';
  clone.style.overflow = 'visible';
  clone.style.width = '794px';
  clone.style.border = 'none';
  holder.appendChild(clone);
  document.body.appendChild(holder);

  html2pdf().set({
    margin:      [10, 12, 12, 12],
    filename:    fname,
    image:       { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
    jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak:   { mode: ['css', 'legacy'] }
  }).from(clone).save()
    .catch(() => {})
    .finally(() => { holder.remove(); btn.disabled = false; });
});

$('#applyLetterBtn').on('click', () => {
  const date    = $('#modal_letter_date').val().trim();
  const refNo   = $('#modal_letter_ref_no').val().trim();
  const subject = $('#modal_letter_subject').val().trim();
  const html    = letterQuill.root.innerHTML.trim();

  if (!date || !refNo || !subject) {
    return alert('Please fill in Date, Reference #, and Subject.');
  }
  if (!html || html === '<p><br></p>') {
    return alert('Please enter letter content.');
  }

  $('#letter_date').val(date);
  $('#letter_ref_no').val(refNo);
  $('#letter_subject').val(subject);
  $('#letter_content').val(html);

  // Letter signatory = memo's To: recipient + chosen capacity
  const cap = $('#letterSigningCapacity').val();
  const capTitle = cap === 'for_dg' ? 'For: DIRECTOR GENERAL'
                 : cap === 'for_adg' ? 'For: ACTING DIRECTOR GENERAL'
                 : (letterSigner.position || '').toUpperCase();
  $('#letter_signed_by_id').val(letterSigner.id || (typeof toUser !== 'undefined' && toUser ? toUser.id : ''));
  $('#letter_signed_by_name').val(letterSigner.name || (typeof toUser !== 'undefined' && toUser ? toUser.name : ''));
  $('#letter_signing_capacity').val(cap);
  $('#letter_signing_title').val(capTitle);

  $('#memoForm .letter-recipient-hidden').remove();

  $('#recipientsContainer .recipient-row').each(function() {
    const pos  = $(this).find('.recipient-position').val()?.trim() || '';
    const addr = $(this).find('.recipient-address').val()?.trim() || '';

    if (!pos || !addr) return;

    $('<input>', {
      type: 'hidden',
      name: 'letter_recipients[position][]',
      class: 'letter-recipient-hidden',
      value: pos
    }).appendTo('#memoForm');

    $('<input>', {
      type: 'hidden',
      name: 'letter_recipients[address][]',
      class: 'letter-recipient-hidden',
      value: addr
    }).appendTo('#memoForm');
  });

  // Preview
  const items = [];
  $('#recipientsContainer .recipient-row').each(function(){
    const pos  = $(this).find('.recipient-position').val()?.trim() || '';
    const addr = $(this).find('.recipient-address').val()?.trim() || '';
    items.push(`<li><strong>${pos}</strong><br>${addr.replace(/\n/g,'<br>')}</li>`);
  });

  $('#previewLetterDate').text(date);
  $('#previewLetterRef').text(refNo);
  $('#previewLetterSubject').text(subject);
  $('#previewLetterRecipients').html(items.join(''));
  $('#previewLetterContent').html(html);
  $('#letterPreviewContainer').show();

  // 🔥 Properly close modal and fix overlay
 const letterModal = document.getElementById('letterModal');
bootstrap.Modal.getOrCreateInstance(letterModal).hide();

// Fix any stuck modal state
setTimeout(() => {
  $('.modal-backdrop').remove();
  $('body').removeClass('modal-open').css('overflow', '');
}, 300);

});



// 8) “Edit Letter” re-opens the modal for further edits
$('#editLetterBtn').on('click', () => {
  bootstrap.Modal.getOrCreateInstance(document.getElementById('letterModal')).show();
});
</script>



<script>

// on load, try to fetch saved signature
// on load, try to fetch saved signature
$(function(){
  $.getJSON('load_signature.php')
    .done(data => {
      if (data.image) {
        // we have a saved signature
        $('#signaturePreview').attr('src', data.image);
        $('#signature_data').val(data.image);
        $('#signature_type').val('saved');
      } else {
        // No image field → warn the user
        new bootstrap.Modal(document.getElementById('noSigModal')).show();
      }
    })
    .fail(() => {
      // Couldn't load at all → still warn
      new bootstrap.Modal(document.getElementById('noSigModal')).show();
    })
    .always(() => {
      // In any case, re-evaluate whether we can enable submit
     //checkSubmitable 
    });
});








$(function(){
  console.log('Requesting next memo reference…');
$.getJSON('next_memo_ref.php')
  .done(data => {
    if (data.status === 'success') {
      $('input[name="memo_id"]').val(data.ref);
    } else {
      showToast(data.message, false);
    }
  })
  .fail((xhr, status, err) => {
    showToast('Failed to get memo reference.', false);
  });

});
</script>


<script>


const quill = new Quill('#editor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [1, 2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      ['blockquote', 'code-block'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      [{ indent: '-1' }, { indent: '+1' }],
      [{ color: [] }, { background: [] }],
      [{ align: [] }],
      ['link', 'image'],
      ['clean'],
      ['table'] // support for tables if you have quill-table extension
    ]
  }
});

let toUser = null;
let throughList = [];
let selectedFiles = [];
let loadedFromSaved = false;

// Signature Pad Setup
const modalCanvas = document.getElementById('modalSignatureCanvas');
const modalSignaturePad = new SignaturePad(modalCanvas);

modalSignaturePad.onEnd = () => {
  loadedFromSaved = false;
  $('#signature_type').val('new');
};

const signatureModal = new bootstrap.Modal(document.getElementById('signatureModal'));

$('#openSignModal').on('click', () => signatureModal.show());
$('#clearModalSignature').on('click', () => modalSignaturePad.clear());

$('#useSavedModalSignature').on('click', () => {
  $.get('load_signature.php', function (data) {
    if (data?.image) {
      const img = new Image();
      img.onload = () => {
        modalSignaturePad.clear();
        modalCanvas.getContext('2d').drawImage(img, 0, 0, modalCanvas.width, modalCanvas.height);
        loadedFromSaved = true;
        $('#signature_type').val('saved');
      };
      img.src = data.image;
    } else {
      showToast("No saved signature found.", false);
    }
  }, 'json');
});

$('#applySignatureBtn').on('click', () => {
  if (!modalSignaturePad.isEmpty() || loadedFromSaved) {
    const dataURL = modalCanvas.toDataURL();
    $('#signature_data').val(dataURL);
    $('#signaturePreview').attr('src', dataURL);

    if ($('#saveModalSignature').is(':checked')) {
      $.post('save_signature.php', { image: dataURL });
    }

    loadedFromSaved = false;
    signatureModal.hide();
  } else {
    showToast("Please provide a signature.", false);
  }
});

// Memo Content
function attachEditorContent() {
  $('#content').val(quill.root.innerHTML);
}

// Toast
function showToast(message, isSuccess = true) {
  const toast = document.getElementById('toastMessage');
  $('#toastBody').text(message);
  toast.className = `toast align-items-center text-white bg-${isSuccess ? 'success' : 'danger'} border-0`;
  new bootstrap.Toast(toast).show();
}

// TO field logic
$('#toResults').on('click', '.list-group-item-action', function () {
  const userId = $(this).data('user-id');
  const name = $(this).text();
  toUser = { id: userId, name };
  $('#toInput').val('');
  $('#toResults').hide();
  updateToBadge();
});

function updateToBadge() {
  const container = $('#toSelected');
  container.empty();
  if (toUser) {
    container.append(`
      <span class="badge bg-primary me-2">
        ${toUser.name}
        <button type="button" class="btn-close btn-close-white btn-sm ms-2" onclick="removeToUser()"></button>
      </span>
    `);
    $('input[name="to"]').val(toUser.name);
    $('input[name="to_id"]').val(toUser.id);
    $('#toInput').removeClass('is-invalid');
  }
}
window.removeToUser = function () {
  toUser = null;
  updateToBadge();
  $('input[name="to"]').val('');
  $('input[name="to_id"]').val('');
}

// THROUGH field logic
$('#throughResults').on('click', '.list-group-item-action', function () {
  const userId = $(this).data('user-id');
  const name = $(this).text();
  if (!throughList.find(u => u.id === userId)) {
    throughList.push({ id: userId, name });
    updateThroughBadges();
  }
  $('#throughInput').val('');
  $('#throughResults').hide();
});

function updateThroughBadges() {
  const container = $('#throughSelected');
  container.empty();
  throughList.forEach((user, index) => {
    container.append(`
      <span class="badge bg-success me-1 mb-1">
        ${user.name}
        <button type="button" class="btn-close btn-close-white btn-sm ms-1" onclick="removeThrough(${index})"></button>
      </span>
    `);
  });
  $('#through_ids').val(throughList.map(user => user.id).join(','));
}
window.removeThrough = function (index) {
  throughList.splice(index, 1);
  updateThroughBadges();
}

// User search AJAX
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
searchUsers('#toInput', '#toResults');
searchUsers('#throughInput', '#throughResults');

// File upload logic
$('#attachments').on('change', function () {
  const newFiles = Array.from(this.files);

  // Add new files to the existing list if not duplicates
  newFiles.forEach(file => {
    if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
      selectedFiles.push(file);
    }
  });

  updateFilePreview();

  // Reset the input so you can re-select the same file again if needed
  $(this).val('');
});


function updateFilePreview() {
  const preview = $('#filePreviewList');
  preview.empty();
  selectedFiles.forEach((file, index) => {
    preview.append(`
      <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-1">
        <span class="text-muted">${file.name}</span>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeFile(${index})">Remove</button>
      </div>
    `);
  });
}
window.removeFile = function (index) {
  selectedFiles.splice(index, 1);
  updateFilePreview();
}

function submitMemo(status = 'Submitted') {
  // 1) Attach main memo body
  attachEditorContent();

  // 2) No need to re-read modal—hidden inputs already hold any drafted letter data:
  //    letter_date, letter_ref_no, letter_subject, letter_content,
  //    and letter_recipients[...][]

  // 3) Build FormData
  const formData = new FormData($('#memoForm')[0]);
  formData.append('status', status);

  // 4) Attach files
  selectedFiles.forEach(file => {
    formData.append('attachments[]', file);
  });

  // 5) AJAX POST
  $.ajax({
    url: 'memo_submit.php',
    method: 'POST',
    data: formData,
    contentType: false,
    processData: false,
    success(res) {
      progressModal.hide();
      if (res.status === 'success') {
        showToast(`Memo ${status} successfully!`);

        // Reset everything
        $('#memoForm')[0].reset();
        quill.root.innerHTML = '';
        letterQuill.root.innerHTML = '';
        $('#signaturePreview').attr('src', '');
        $('#throughSelected, #toSelected').empty();
        $('#letterPreviewContainer').hide();
        selectedFiles = [];
        throughList = [];
        toUser = null;
        updateFilePreview();
      } else {
        showToast(res.message || 'Something went wrong while saving the memo.', false);
      }
    },
    error() {
      progressModal.hide();
      showToast('Network or server error. Try again.', false);
    }
  });
}


function attachLetterContent() {
  const html = letterQuill.root.innerHTML.trim();
  $('#letter_content').val(
    html && html !== '<p><br></p>' 
      ? html 
      : ''
  );
}


const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));

let submissionStatus = '';

// ── Client-side validation ─────────────────────────────────────────────
// Mirrors the server-side required check in memo_submit.php. Runs before
// the confirm modal opens so an invalid form never reaches submitMemo().
function validateMemoForm() {
  let firstInvalid = null;
  const fail = $el => { if (!firstInvalid && $el && $el.length) firstInvalid = $el; };
  const mark = ($el, bad) => { $el.toggleClass('is-invalid', !!bad); if (bad) fail($el); };

  mark($('select[name="communication_type"]'), !$('select[name="communication_type"]').val());
  mark($('input[name="date"]'),                !$('input[name="date"]').val());
  mark($('#toInput'),                          !$('input[name="to_id"]').val());   // hidden id set on recipient pick
  mark($('input[name="subject"]'),             !$('input[name="subject"]').val().trim());

  // Memo body (Quill writes into the hidden #content on submit)
  const body    = quill.root.innerHTML.trim();
  const bodyBad = body === '' || body === '<p><br></p>';
  $('#editorFeedback').toggle(bodyBad);
  $('#editor').css('border-color', bodyBad ? 'var(--bs-danger, #dc3545)' : '');
  if (bodyBad) fail($('#editor'));

  // Drafted external letter: only validate if one was actually attached
  if ($('#letter_content').val().trim() !== '') {
    [['#letter_date', '#modal_letter_date'],
     ['#letter_ref_no', '#modal_letter_ref_no'],
     ['#letter_subject', '#modal_letter_subject']].forEach(([hid, modalSel]) => {
      const bad = !$(hid).val().trim();
      $(modalSel).toggleClass('is-invalid', bad);
      if (bad) fail($('[data-bs-target="#letterModal"]'));
    });
  }

  if (firstInvalid) {
    showToast('Please complete the highlighted required fields.', false);
    const el = firstInvalid[0];
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (typeof el.focus === 'function') { try { el.focus({ preventScroll: true }); } catch (e) {} }
    return false;
  }
  return true;
}

// Clear a field's error state as soon as the user edits it
$('#memoForm').on('input change', 'input, select, textarea', function () {
  $(this).removeClass('is-invalid');
});
$('#toInput').on('input', () => $('#toInput').removeClass('is-invalid'));
quill.on('text-change', () => {
  const body = quill.root.innerHTML.trim();
  if (body !== '' && body !== '<p><br></p>') {
    $('#editorFeedback').hide();
    $('#editor').css('border-color', '');
  }
});

$('#triggerSubmit').on('click', () => {
  if (!validateMemoForm()) return;
  submissionStatus = 'Submitted';
  $('#confirmActionText').text('submit');
  confirmModal.show();
});

$('#saveDraft').on('click', () => {
  if (!validateMemoForm()) return;
  submissionStatus = 'Draft';
  $('#confirmActionText').text('save as draft');
  confirmModal.show();
});

$('#confirmActionBtn').on('click', () => {
  confirmModal.hide();
  if (!validateMemoForm()) return;          // backstop
  progressModal.show();
  submitMemo(submissionStatus);
});



// Bootstrap validation trigger
(() => {
  'use strict';
  const form = document.getElementById('memoForm');
  form.addEventListener('submit', event => {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add('was-validated');
  }, false);
})();

// Quill editor validation
function attachEditorContent() {
  const content = quill.root.innerHTML.trim();
  $('#content').val(content);
  if (content === '' || content === '<p><br></p>') {
    $('#editorFeedback').show();
  } else {
    $('#editorFeedback').hide();
  }
}




// Update memo title based on communication type
// Update memo title based on communication type
$('select[name="communication_type"]').on('change', function () {
  const selectedType = $(this).val();
  let title = 'INTERNAL MEMORANDUM';

  if (selectedType === 'Loose Minute') {
    title = 'LOOSE MINUTE';
  } else if (selectedType === 'Memorandum') {
    title = 'INTERNAL MEMORANDUM';
  }

  $('#memoTitle').text(title);
  renderPreview();
});


// ── Live memo preview — PPDA loose-minute format ───────────────────────
function formatMinuteDate(iso) {
  const dt = new Date(iso + 'T00:00:00');
  if (isNaN(dt)) return iso;
  const day = dt.getDate();
  const suffix = (day % 10 === 1 && day !== 11) ? 'st'
               : (day % 10 === 2 && day !== 12) ? 'nd'
               : (day % 10 === 3 && day !== 13) ? 'rd' : 'th';
  return day + suffix + ' ' + dt.toLocaleDateString('en-GB', { month: 'long' }) + ' ' + dt.getFullYear();
}

function renderPreview() {
  const esc = s => $('<div>').text(s == null ? '' : s).html();

  $('#mpTitle').text($('#memoTitle').text() || 'LOOSE MINUTE');

  const ref = $('#memoId').val();
  $('#mpRef').text('Ref: ' + (ref && ref !== 'Loading…' && ref !== 'Loading...' ? ref : '—'));

  // Date at the bottom — taken straight from the form's Date field
  const d = $('#memoForm input[name="date"]').val();
  $('#mpDate').text(d ? formatMinuteDate(d) : '—');

  // Uppercased in JS (not via CSS text-transform — see CSS note re: html2canvas)
  $('#mpTo').text(toUser ? toUser.name.toUpperCase() : '—');

  if (throughList.length) {
    $('#mpThrough').html(throughList.map(u => '<span>' + esc(u.name.toUpperCase()) + '</span>').join(''));
    $('#mpThroughRow').show();
  } else {
    $('#mpThroughRow').hide();
  }

  const subject = $('input[name="subject"]').val();
  $('#mpSubject').text(subject ? subject.toUpperCase() : '—');

  const body = quill.root.innerHTML.trim();
  $('#mpBody').html(body && body !== '<p><br></p>'
    ? body
    : '<span class="mp-empty">Start typing the memo body…</span>');

  // Signature block
  const sig = $('#signature_data').val() || $('#signaturePreview').attr('src') || '';
  if (sig) { $('#mpSignImg').attr('src', sig).show(); } else { $('#mpSignImg').hide(); }

  // Title line under the name — own title, or "For: <supervisor title>"
  const cap = $('#signingCapacity').val();
  const ownRole = $('#signingCapacity').data('own-role') || '';
  let roleText, roleShow = true;
  if (cap === 'for_supervisor') {
    const t = ($('#signingForTitle').val() || '').trim();
    roleText = 'For: ' + (t || '…');
  } else {
    roleText = ownRole;
    roleShow = !!ownRole;
  }
  $('#mpRole').text(roleText).toggle(roleShow);

  // Keep the expanded modal copy in sync while it is open
  const full = document.getElementById('previewDocFull');
  if (full && document.getElementById('previewModal').classList.contains('show')) {
    full.innerHTML = document.getElementById('memoPreviewDoc').innerHTML;
    full.querySelectorAll('.mp-notes-hint').forEach(n => n.remove());
  }
}

// Reveal the supervisor-title field only when signing "for" a supervisor
$('#signingCapacity').on('change', function () {
  $('#supervisorTitleWrap').toggle(this.value === 'for_supervisor');
  renderPreview();
});

// ── Click the sidebar preview → open the full, downloadable view ────────
const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));

function openFullPreview() {
  const src = document.getElementById('memoPreviewDoc');
  const body = document.getElementById('previewModalBody');
  body.innerHTML = '';
  const full = src.cloneNode(true);
  full.id = 'previewDocFull';
  full.classList.add('memo-preview-doc--full');
  full.removeAttribute('role');
  full.removeAttribute('tabindex');
  full.removeAttribute('title');
  full.querySelectorAll('.mp-notes-hint').forEach(n => n.remove());
  body.appendChild(full);
  previewModal.show();
}

$('#memoPreviewDoc').on('click', openFullPreview);
$('#memoPreviewDoc').on('keydown', e => {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openFullPreview(); }
});

// Build an off-DOM, fully-expanded copy of the minute for rendering.
// (html2canvas renders blank when the source sits inside a scrolled /
//  transformed Bootstrap modal, so we render a detached clone instead.)
function buildMemoRenderClone() {
  const src = document.getElementById('previewDocFull') || document.getElementById('memoPreviewDoc');
  const holder = document.createElement('div');
  holder.style.cssText = 'position:absolute;left:-9999px;top:0;width:794px;background:#fff;';
  const clone = src.cloneNode(true);
  clone.id = 'memoRenderClone';
  clone.classList.add('memo-preview-doc--full');
  clone.style.maxHeight = 'none';
  clone.style.overflow = 'visible';
  clone.style.width = '794px';
  clone.style.border = 'none';
  clone.removeAttribute('role');
  clone.removeAttribute('tabindex');
  clone.removeAttribute('title');
  clone.querySelectorAll('.mp-notes-hint').forEach(n => n.remove());
  holder.appendChild(clone);
  document.body.appendChild(holder);
  return { holder, clone };
}

function memoPdfOptions() {
  const raw = ($('input[name="subject"]').val() || $('#mpTitle').text() || 'loose-minute').trim();
  const fname = (raw.replace(/[^\w\- ]+/g, '').replace(/\s+/g, '_').slice(0, 60) || 'loose-minute') + '.pdf';
  return {
    margin:      [10, 10, 12, 10],
    filename:    fname,
    image:       { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
    jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak:   { mode: ['css', 'legacy'] }
  };
}

// Download the minute as a PDF
$('#previewPdfBtn').on('click', function () {
  if (typeof html2pdf === 'undefined') return;
  const btn = this; btn.disabled = true;
  const { holder, clone } = buildMemoRenderClone();
  html2pdf().set(memoPdfOptions()).from(clone).save()
    .catch(() => {})
    .finally(() => { holder.remove(); btn.disabled = false; });
});

// Print the minute — via a real PDF (no browser page headers / about:blank)
$('#previewPrintBtn').on('click', function () {
  if (typeof html2pdf === 'undefined') return;
  const btn = this; btn.disabled = true;
  const { holder, clone } = buildMemoRenderClone();
  html2pdf().set(memoPdfOptions()).from(clone).outputPdf('bloburl')
    .then(url => {
      holder.remove();
      const w = window.open(url, '_blank');
      if (w) w.addEventListener('load', () => { try { w.focus(); w.print(); } catch (e) {} });
    })
    .catch(() => { holder.remove(); })
    .finally(() => { btn.disabled = false; });
});

quill.on('text-change', renderPreview);
$('#memoForm').on('input change', 'input, select, textarea', renderPreview);
// Re-render whenever recipients change (badges are rebuilt outside form inputs)
['updateToBadge', 'updateThroughBadges'].forEach(fn => {
  const orig = window[fn];
  if (typeof orig === 'function') window[fn] = function () { orig.apply(this, arguments); renderPreview(); };
});
const _removeToUser = window.removeToUser;
window.removeToUser = function () { _removeToUser && _removeToUser(); renderPreview(); };
const _removeThrough = window.removeThrough;
window.removeThrough = function (i) { _removeThrough && _removeThrough(i); renderPreview(); };

// Keep preview in sync with the async memo-reference fetch
$(document).ajaxComplete(renderPreview);
renderPreview();




</script>



</body>
</html>
