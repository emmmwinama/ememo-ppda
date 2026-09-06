<?php
require('libs/libs/fpdf/fpdf.php');

$data = $_POST['data'] ?? null;
if (!$data) die('No memo data provided');

$memo = json_decode($data, true);
if (!$memo) die('Invalid memo format');

// Create A4 PDF (210 × 297 mm), default unit = mm
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(20, 20, 20);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// HEADER
$pdf->Image('header.png', 20, 15, 170); // Add some left/right margin
$pdf->SetY(80); // <-- Push down after image

// MEMO TYPE
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, strtoupper($memo['memo_type']), 0, 1, 'C');

// REF AND DATE
$pdf->SetFont('Arial', 'B', 12); // ✅ Make both bold

// Reference on the left, Date slightly in on the right
$pdf->Cell(110, 7, $memo['reference_number'], 0, 0); // Left side
$pdf->Cell(50, 7, date('jS F Y', strtotime($memo['created_at'])), 0, 1, 'R'); // Date pulled in via 70 width

// Flat brand-green divider under the meta line
$pdf->SetDrawColor(42, 143, 46);
$pdf->SetLineWidth(0.5);
$pdf->Line(20, $pdf->GetY() + 1, 190, $pdf->GetY() + 1);
$pdf->SetLineWidth(0.2);
$pdf->SetDrawColor(0, 0, 0);

// TO / COPY
$pdf->Ln(5);
$pdf->Cell(20, 6, 'To:', 0);
$pdf->Cell(0, 6, formatRecipients($memo['to_recipients']), 0, 1);

$pdf->Cell(20, 6, 'Copy:', 0);
$pdf->Cell(0, 6, formatRecipients($memo['cc_recipients']), 0, 1);

// SUBJECT
$pdf->Ln(6);
$pdf->SetFont('Arial', 'BU', 12); // Bold + Underline
$pdf->Cell(0, 7, strtoupper($memo['subject']), 0, 1, 'C'); // ✅ 'C' centers it


// CONTENT
$pdf->Ln(4);
$pdf->SetFont('Arial', '', 12);
$cleanContent = preg_replace('/<br\s*\/?>/i', "\n", $memo['content']);
$cleanContent = strip_tags($cleanContent);
$pdf->MultiCell(0, 6, $cleanContent);

// SIGNATURE BLOCK CENTERED
$pdf->Ln(15);
if (!empty($memo['signature_data']) && file_exists($memo['signature_data'])) {
    $pdf->Image($memo['signature_data'], ($pdf->GetPageWidth() - 40) / 2, $pdf->GetY(), 40);
    $pdf->Ln(25);
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, $memo['originator_name'], 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, strtoupper($memo['originator_position']), 0, 1, 'C');

// OUTPUT
$filename = $_POST['filename'] ?? ($memo['reference_number'] . '_memo.pdf');
$pdf->Output('D', $filename);


function formatRecipients($list) {
    return implode('; ', array_column($list, 'name'));
}

