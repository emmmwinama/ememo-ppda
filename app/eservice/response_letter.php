<?php
/** Printable / downloadable PDE response letter (es_pde_response). */
require __DIR__ . '/inc/bootstrap.php';

if (!es_has_role('registry', 'officer', 'supervisor', 'director', 'dg', 'board')) {
    http_response_code(403);
    exit('Not authorised.');
}

global $conn;

$id  = (int) ($_GET['id'] ?? 0);
$fmt = $_GET['format'] ?? 'html';

$row = db_one(
    "SELECT pr.*, a.id AS analysis_id, a.decided_at,
            r.serial_no, r.subject, r.tender_number, r.ref_code_pde,
            p.name AS pde_name, p.address AS pde_address,
            cb.full_name AS drafted_by_name
       FROM es_pde_response pr
       JOIN es_bid_analysis a ON a.id = pr.analysis_id
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p  ON p.id = r.pde_id
       LEFT JOIN users cb  ON cb.id = pr.created_by
      WHERE pr.id = ?",
    'i', [$id]
);
if (!$row) { http_response_code(404); exit('Response letter not found.'); }

// ---- access tracking (who views / downloads a published response) --------
if (!empty($row['published'])) {
    $act = in_array($fmt, ['txt', 'pdf'], true) ? 'download' : 'view';
    if ($st = $conn->prepare("INSERT INTO es_response_access (response_id, user_id, action) VALUES (?,?,?)")) {
        $st->bind_param('iis', $id, $ES_UID, $act);
        $st->execute();
        $st->close();
    }
    // a download counts as dispatch — stamp sent_* the first time
    if ($act === 'download' && empty($row['sent_at'])) {
        $conn->query("UPDATE es_pde_response SET sent_at = NOW(), sent_by = " . (int) $ES_UID
                   . ", sent_method = 'download' WHERE id = $id AND sent_at IS NULL");
    }
}

// who signed it off (last approver on the routing trail)
$approver = db_one(
    "SELECT u.full_name FROM es_bid_routing t JOIN users u ON u.id = t.from_user_id
      WHERE t.analysis_id = ? AND t.action = 'approve' ORDER BY t.id DESC LIMIT 1",
    'i', [(int) $row['analysis_id']]
);
$signName = $approver['full_name'] ?? ($row['drafted_by_name'] ?? 'PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY');
$letterDate = date('j F Y', strtotime($row['published_at'] ?: ($row['decided_at'] ?: $row['ts_create'])));
$ref = $row['serial_no'];

// ---- plain-text download ------------------------------------------------
if ($fmt === 'txt') {
    $fname = 'response-' . preg_replace('/[^A-Za-z0-9]+/', '-', $ref) . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $lines = [
        'PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY',
        '',
        'Our Ref: ' . $ref,
        'Date: ' . $letterDate,
        '',
        'The Head of Procuring & Disposing Entity',
        (string) ($row['pde_name'] ?? ''),
        (string) ($row['pde_address'] ?? ''),
        '',
        'Dear Sir/Madam,',
        '',
        'RE: ' . strtoupper((string) $row['subject']),
        $row['tender_number'] ? 'Tender No: ' . $row['tender_number'] : '',
        '',
        trim((string) $row['body']),
        '',
        'Yours faithfully,',
        '',
        '',
        $signName,
        'for DIRECTOR GENERAL',
    ];
    echo implode("\n", array_filter($lines, fn($l) => $l !== null));
    exit;
}

// ---- formatted PDF (FPDF — no mbstring/gd needed) ----------------------
if ($fmt === 'pdf') {
    while (ob_get_level() > 0) ob_end_clean();
    ini_set('display_errors', '0');
    error_reporting(0);
    require_once __DIR__ . '/../ememo/libs/libs/fpdf/fpdf.php';

    // FPDF is windows-1252; fold UTF-8 punctuation down to it
    $tx = static function ($s): string {
        $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string) $s);
        return $c === false ? (string) $s : $c;
    };

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetTitle($tx('Response letter ' . $ref));
    $pdf->SetMargins(25, 22, 25);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // letterhead — the shared PPDA memo / letter header image
    $hdr = __DIR__ . '/assets/img/letterhead.png';
    if (is_file($hdr)) {
        $hw = 160;                                       // content width: A4 210 - 25 - 25
        [$iw, $ih] = getimagesize($hdr) ?: [1705, 545];
        $pdf->Image($hdr, 25, 12, $hw);
        $pdf->SetY(12 + $hw * $ih / max($iw, 1) + 8);
    } else {
        $pdf->SetFont('Times', 'B', 13);
        $pdf->MultiCell(0, 6, $tx('PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY'), 0, 'C');
        $pdf->Ln(2);
        $ly = $pdf->GetY();
        $pdf->SetLineWidth(0.4);
        $pdf->Line(25, $ly, 185, $ly);
        $pdf->Ln(7);
    }

    // our ref / your ref (left)  +  date (right, same top)
    $pdf->SetFont('Times', '', 11);
    $topY = $pdf->GetY();
    $pdf->MultiCell(110, 6, $tx('Our Ref: ' . $ref . ($row['ref_code_pde'] ? "\nYour Ref: " . $row['ref_code_pde'] : '')), 0, 'L');
    $afterY = $pdf->GetY();
    $pdf->SetXY(135, $topY);
    $pdf->Cell(50, 6, $tx('Date: ' . $letterDate), 0, 1, 'R');
    $pdf->SetY(max($afterY, $topY + 6));
    $pdf->Ln(6);

    // addressee
    $addr = 'The Head of Procuring & Disposing Entity';
    if (trim((string) $row['pde_name']) !== '')    $addr .= "\n" . $row['pde_name'];
    if (trim((string) $row['pde_address']) !== '') $addr .= "\n" . $row['pde_address'];
    $pdf->MultiCell(0, 6, $tx($addr), 0, 'L');
    $pdf->Ln(4);
    $pdf->Cell(0, 6, $tx('Dear Sir/Madam,'), 0, 1, 'L');
    $pdf->Ln(3);

    // subject
    $pdf->SetFont('Times', 'BU', 11);
    $pdf->MultiCell(0, 6, $tx('RE: ' . strtoupper((string) $row['subject'])), 0, 'C');
    if (trim((string) $row['tender_number']) !== '') {
        $pdf->SetFont('Times', '', 10);
        $pdf->MultiCell(0, 5, $tx('Tender No: ' . $row['tender_number']), 0, 'C');
    }
    $pdf->Ln(4);

    // body — one justified block per paragraph
    $pdf->SetFont('Times', '', 11);
    foreach (preg_split('/\n{2,}/', trim((string) $row['body'])) as $para) {
        $para = trim((string) $para);
        if ($para === '') continue;
        $pdf->MultiCell(0, 6, $tx($para), 0, 'J');
        $pdf->Ln(3);
    }

    // sign-off
    $pdf->Ln(6);
    $pdf->Cell(0, 6, $tx('Yours faithfully,'), 0, 1, 'L');
    $pdf->Ln(16);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(0, 6, $tx($signName), 0, 1, 'L');
    $pdf->SetFont('Times', '', 11);
    $pdf->Cell(0, 6, $tx('for DIRECTOR GENERAL'), 0, 1, 'L');

    $pdf->Output('D', 'response-' . preg_replace('/[^A-Za-z0-9]+/', '-', $ref) . '.pdf');
    exit;
}

// ---- printable HTML letter -------------------------------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Response letter · <?= e($ref) ?></title>
  <style>
    :root { color-scheme: light; }
    body { margin: 0; background: #f1f3f5; font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; }
    .bar { background: #fff; border-bottom: 1px solid #dee2e6; padding: .6rem 1rem; display: flex; gap: .5rem;
           position: sticky; top: 0; font-family: system-ui, sans-serif; }
    .bar button, .bar a { font: inherit; font-size: .85rem; padding: .4rem .8rem; border-radius: 6px;
           border: 1px solid #ced4da; background: #fff; color: #212529; text-decoration: none; cursor: pointer; }
    .bar .primary { background: #2a8f2e; border-color: #2a8f2e; color: #fff; }
    .sheet { max-width: 820px; margin: 1.5rem auto; background: #fff; padding: 56px 64px;
             box-shadow: 0 1px 6px rgba(0,0,0,.12); line-height: 1.7; }
    .lh { text-align: center; margin-bottom: 28px; }
    .lh img { width: 100%; display: block; }
    .lh h1 { font-size: 15pt; margin: 0; letter-spacing: .5px; }
    .lh p { margin: 2px 0 0; font-size: 9.5pt; color: #555; }
    .meta { display: flex; justify-content: space-between; font-size: 10.5pt; margin: 22px 0 18px; }
    .addr { font-size: 10.5pt; margin-bottom: 16px; white-space: pre-line; }
    .subj { text-align: center; font-weight: bold; text-transform: uppercase; margin: 18px 0; }
    .body { text-align: justify; white-space: pre-wrap; }
    .sign { margin-top: 40px; }
    .sign .name { font-weight: bold; margin-top: 48px; }
    @media print {
      body { background: #fff; }
      .bar { display: none; }
      .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
      @page { margin: 22mm; }
    }
  </style>
</head>
<body>
  <div class="bar">
    <a class="primary" href="?id=<?= (int) $id ?>&amp;format=pdf">Download PDF</a>
    <button onclick="window.print()">Print</button>
    <a href="?id=<?= (int) $id ?>&amp;format=txt">Plain text</a>
    <a href="javascript:history.back()">Back</a>
  </div>

  <div class="sheet">
    <div class="lh">
      <img src="assets/img/letterhead.png" alt="Public Procurement and Disposal of Assets Authority">
    </div>

    <div class="meta">
      <div><strong>Our Ref:</strong> <?= e($ref) ?><?php if ($row['ref_code_pde']): ?><br><strong>Your Ref:</strong> <?= e($row['ref_code_pde']) ?><?php endif; ?></div>
      <div><strong>Date:</strong> <?= e($letterDate) ?></div>
    </div>

    <div class="addr">The Head of Procuring &amp; Disposing Entity
<?= e($row['pde_name'] ?? '') ?><?php if ($row['pde_address']): ?>
<?= e($row['pde_address']) ?><?php endif; ?></div>

    <p>Dear Sir/Madam,</p>

    <div class="subj"><?= e($row['subject']) ?><?php if ($row['tender_number']): ?><br><span style="font-weight:normal;font-size:10pt;">Tender No: <?= e($row['tender_number']) ?></span><?php endif; ?></div>

    <div class="body"><?= e(trim((string) $row['body'])) ?></div>

    <div class="sign">
      <div>Yours faithfully,</div>
      <div class="name"><?= e($signName) ?></div>
      <div>for DIRECTOR GENERAL</div>
    </div>
  </div>
</body>
</html>
