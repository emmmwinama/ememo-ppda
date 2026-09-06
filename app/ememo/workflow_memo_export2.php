<?php
require('libs/libs/fpdf/fpdf.php');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['memo'])) {
    http_response_code(400);
    echo "Invalid data";
    exit;
}

class MemoPDF extends FPDF {
    function Header() {
        if ($this->PageNo() === 1 && file_exists('header.png')) {
            $this->Image('header.png', 10, 10, 190);
        }
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new MemoPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetFont('Arial','',11);

// === Extracted values ===
$memo           = $data['memo'];
$trail          = $data['memo_movements']    ?? [];
$clarifications = $data['memo_clarifications'] ?? [];
$originator     = $data['originator'];
$to             = $data['to_user']['position'] ?? 'Recipient';
$throughUsers   = $data['through_users']     ?? [];
$endorsements   = $data['through_endorsements'] ?? [];

$date    = date('F j, Y', strtotime($memo['date']));
$ref     = $memo['memo_id'];
$subject = $memo['subject'];
$content = strip_tags($memo['content'] ?? '');

// === Layout Parameters ===
$pageHeight = $pdf->GetPageHeight();
$startY     = round($pageHeight * 0.30);

$leftX  = 10;
$leftW  = 45;
$gap    = 5;
$rightX = $leftX + $leftW + $gap;
$rightW = 190 - $rightX - 10;

// === Divider ===
$pdf->SetDrawColor(150, 150, 150);
$pdf->SetLineWidth(0.2);
$pdf->Line($rightX - ($gap / 2), $startY, $rightX - ($gap / 2), $pageHeight - 15);

// === RIGHT COLUMN FIRST ===
$currentYRight = $startY;
$pdf->SetXY($rightX, $currentYRight);
$pdf->SetFont('Arial','',10);

// Meta
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($rightW / 2, 6, 'Date: ' . $date, 0, 0, 'L');
$pdf->Cell($rightW / 2, 6, 'Reference: ' . $ref, 0, 1, 'R');

$currentYRight += 9;

// To
$pdf->SetXY($rightX, $currentYRight);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(18, 6, 'To: ', 0, 0);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0, 6, $to, 0, 1);
$currentYRight += 8;

// Through
if ($throughUsers) {
    foreach ($throughUsers as $index => $user) {
        if ($currentYRight > $pdf->GetPageHeight() - 50) {
            $pdf->AddPage();
            $currentYRight = $startY;
            $pdf->SetXY($rightX, $currentYRight);
        }

        $pdf->SetXY($rightX, $currentYRight);
        if ($index === 0) {
            $pdf->SetFont('Arial','B',11);
            $pdf->Cell(18, 6, 'Through: ', 0, 0);
        } else {
            $pdf->Cell(18, 6, '', 0, 0);
        }

        $pdf->SetFont('Arial','',11);
        $pdf->Cell(0, 6, $user['position'], 0, 1);
        $currentYRight += 6;

        $e = array_values(array_filter($endorsements, fn($en) => $en['user_id'] == $user['id']));
        if ($e) {
            $e = $e[0];
            $pdf->SetFont('Arial','I',10);
            $pdf->SetXY($rightX + 15, $currentYRight);
            $pdf->MultiCell($rightW - 15, 6, $e['comment']);
            $currentYRight = $pdf->GetY();

            if (!empty($e['signature_path']) && file_exists($e['signature_path'])) {
                $imgY     = max($currentYRight - 1, $startY);
                $sigHeight = 20;                   // tightened from 25→20
                $pdf->Image($e['signature_path'], $rightX + 15, $imgY, 0, $sigHeight);
                $pdf->SetY($imgY + $sigHeight + 2); // smaller gap: +2 instead of +25
                $currentYRight = $pdf->GetY();
            }

            $pdf->SetFont('Arial','',9);
            $pdf->SetX($rightX + 15);
            $pdf->Cell(0, 6, 'Endorsed: ' . date('n/j/Y g:i A', strtotime($e['endorsed_at'])), 0, 1);
            $currentYRight = $pdf->GetY() + 2;
        }
    }
}

// Subject
if ($currentYRight > $pdf->GetPageHeight() - 50) {
    $pdf->AddPage();
    $currentYRight = $startY;
}
$pdf->SetXY($rightX, $currentYRight + 5);
$pdf->SetFont('Arial','B',12);
$pdf->Cell($rightW, 8, $subject, 0, 1, 'C');
$currentYRight = $pdf->GetY() + 2;

// Content
if ($currentYRight > $pdf->GetPageHeight() - 50) {
    $pdf->AddPage();
    $currentYRight = $startY;
}
$pdf->SetXY($rightX, $currentYRight);
$pdf->SetFont('Arial','',11);
$pdf->MultiCell($rightW, 6, $content);
$currentYRight = $pdf->GetY() + 10;

// Signature (Originator)
if ($currentYRight > $pdf->GetPageHeight() - 50) {
    $pdf->AddPage();
    $currentYRight = $startY;
}
$blockStartY = $currentYRight;
if (!empty($data['originator_signature']) && file_exists($data['originator_signature'])) {
    $sigWidth = 40;
    $centerX  = $rightX + ($rightW / 2) - ($sigWidth / 2);
    $pdf->Image($data['originator_signature'], $centerX, $blockStartY, $sigWidth);
    $blockStartY += 20;                 // tightened from +32→+20
    $pdf->SetY($blockStartY);
} else {
    $pdf->SetY($blockStartY + 5);      // tightened from +10→+5
}
$pdf->SetFont('Arial','',11);
$pdf->Cell($pdf->GetPageWidth(), 6, $originator['name'], 0, 1, 'C');
$pdf->SetFont('Arial','B',11);
$pdf->Cell($pdf->GetPageWidth(), 6, $originator['position'], 0, 1, 'C');

// === LEFT COLUMN ===
$pdf->SetFont('Arial','',10);
$step = 1;
$pdf->SetXY($leftX, $startY);

foreach ($clarifications as $c) {
    if ($pdf->GetY() > $pageHeight - 50) {
        $pdf->AddPage();
        $pdf->SetXY($leftX, $startY);
    }

    // Request
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell($leftW, 6, ($step++) . '.', 0, 1);
    $pdf->SetFont('Arial','BU',10);
    $pdf->MultiCell($leftW, 6, $c['user_position'] ?? '', 0);
    $pdf->Ln(1);
    $pdf->SetFont('Arial','',10);
    $pdf->MultiCell($leftW, 6, $c['clarification_comment'], 0);
    $pdf->Ln(1);
    if (!empty($c['requested_by_signature']) && file_exists($c['requested_by_signature'])) {
        $imgY = max($pdf->GetY() - 3, $startY);
        $pdf->Image($c['requested_by_signature'], $leftX, $imgY, 35);
        $pdf->SetY($imgY + 20); // tightened from +25→+20
    }
    $pdf->SetFont('Arial','I',9);
    $pdf->MultiCell($leftW, 5, ($c['requested_by_position'] ?? '') . ', ' . date('n/j/Y g:i A', strtotime($c['requested_at'])), 0);
    $pdf->Ln(2);

    // Response
    if (!empty($c['response_comment'])) {
        if ($pdf->GetY() > $pageHeight - 50) {
            $pdf->AddPage();
            $pdf->SetXY($leftX, $startY);
        }
        $pdf->SetFont('Courier','B',11);
        $pdf->Cell($leftW, 6, ($step++) . '.', 0, 1);
        $pdf->SetFont('Courier','BU',10);
        $pdf->MultiCell($leftW, 6, $c['requested_by_position'] ?? '', 0);
        $pdf->Ln(1);
        $pdf->SetFont('Courier','',10);
        $pdf->MultiCell($leftW, 6, $c['response_comment'], 0);
        $pdf->Ln(1);
        if (!empty($c['user_id_signature']) && file_exists($c['user_id_signature'])) {
           $imgY = max($pdf->GetY() - 3, $startY);
           $pdf->Image($c['user_id_signature'], $leftX, $imgY, 35);
           $pdf->SetY($imgY + 20);   // tightened from +25→+20
        }
        $pdf->SetFont('Courier','I',9);
        $pdf->MultiCell($leftW, 5, ($c['user_position'] ?? '') . ', ' . date('n/j/Y g:i A', strtotime($c['responded_at'])), 0);
        $pdf->Ln(3);
    }
}

// === Movement Trail (Final Approvals/Rejections) ===
foreach ($trail as $t) {
    if (in_array($t['action'], ['Approved', 'Rejected'])) {
        if ($pdf->GetY() > $pageHeight - 50) {
            $pdf->AddPage();
            $pdf->SetXY($leftX, $startY);
        }

        $pdf->SetFont('Arial','B',11);
        $pdf->Cell($leftW, 6, ($step++) . '.', 0, 1);

        $pdf->SetFont('Arial','BU',10);
        $pdf->MultiCell($leftW, 5.5, $t['from_position'] ?? 'User', 0);
        $pdf->Ln(1);

        $pdf->SetFont('Arial','',10);
        $pdf->MultiCell($leftW, 5.5, $t['comments'], 0);
        $pdf->Ln(1);

        // Signature
        if (!empty($t['signature_path'])) {
            $sigPath = $t['signature_path'];
            if (filter_var($sigPath, FILTER_VALIDATE_URL)) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
                file_put_contents($tmpFile, file_get_contents($sigPath));
                $sigPath = $tmpFile;
                $isTemp  = true;
            } else {
                $isTemp = false;
            }

            if (file_exists($sigPath)) {
                $imgY      = max($pdf->GetY() - 3, $startY);
                $sigHeight = 20;                  // tightened from 25→20
                $pdf->Image($sigPath, $leftX, $imgY, 0, $sigHeight);
                $pdf->SetY($imgY + $sigHeight + 1);
            }

            if (!empty($isTemp) && file_exists($sigPath)) {
                unlink($sigPath);
            }
        }

        $pdf->SetFont('Arial','I',9);
        $pdf->MultiCell($leftW, 5, ($t['from_position'] ?? '') . ', ' . date('n/j/Y g:i A', strtotime($t['timestamp'])), 0);
        $pdf->Ln(3);
    }
}

// === Output PDF ===
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="memo_' . $memo['memo_id'] . '.pdf"');
$pdf->Output('I');
