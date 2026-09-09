<?php
/**
 * Supplier registration certificate (PDF, FPDF — no GD/mbstring needed).
 * Layout follows the PPDA "Certificate of Registration" sample, with a QR code
 * that points at supplier_verify.php for public validation.
 */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/cert.php';

global $conn;

$id = (int) ($_GET['id'] ?? 0);
$s  = db_one(
    "SELECT s.id, s.supplier_code, s.name, s.trading_name, s.postal_address, s.physical_address,
            s.city, s.website, s.status, s.expire_date, c.name AS country_name
       FROM es_supplier s LEFT JOIN es_country c ON c.id = s.country_id
      WHERE s.id = ?",
    'i', [$id]
);
if (!$s) { http_response_code(404); exit('Supplier not found.'); }

$code  = (string) ($s['supplier_code'] ?: $s['id']);
$data  = es_cert_data($conn, $s);
$vurl  = es_cert_verify_url($code);
$certNo = $data['cert']['certificate_no'] ?? ('PPDA/REG/' . $code);

while (ob_get_level() > 0) ob_end_clean();
ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/../ememo/libs/libs/fpdf/fpdf.php';

/** UTF-8 -> windows-1252 for FPDF's core fonts. */
$tx = static function ($v): string {
    $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string) $v);
    return $c === false ? (string) $v : $c;
};

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetTitle($tx('Certificate of Registration - ' . $s['name']));
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(18, 16, 18);
$pdf->AddPage();
$pageW = 210;
$contentW = $pageW - 36;

// outer border
$pdf->SetDrawColor(120, 120, 120);
$pdf->SetLineWidth(0.3);
$pdf->Rect(10, 10, 190, 277);

// ---- letterhead --------------------------------------------------------
$hdr = __DIR__ . '/assets/img/letterhead.png';
if (is_file($hdr)) {
    [$iw, $ih] = @getimagesize($hdr) ?: [1705, 545];
    $hw = 150;
    $pdf->Image($hdr, ($pageW - $hw) / 2, 16, $hw);
    $pdf->SetY(16 + $hw * $ih / max($iw, 1) + 6);
} else {
    $pdf->SetFillColor(42, 143, 46);                 // PPDA green
    $pdf->Rect(10, 10, 190, 22, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 15);
    $pdf->SetXY(10, 16);
    $pdf->Cell(190, 10, $tx('PUBLIC PROCUREMENT AND DISPOSAL OF ASSETS AUTHORITY'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetY(40);
}

// ---- title -----------------------------------------------------------
$pdf->SetFont('Times', 'B', 20);
$pdf->Cell(0, 12, $tx('Certificate of Registration'), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Times', '', 11);
$pdf->SetX(18);
$pdf->MultiCell($contentW, 6,
    $tx('This is to certify that the following entity has been registered with the Public '
      . 'Procurement and Disposal of Assets Authority. The details are as follows:'), 0, 'C');
$pdf->Ln(6);

// ---- detail rows ---------------------------------------------------
$labelW = 62;
$valW   = $contentW - $labelW - 6;
$rowGap = 2;

$putRow = function (string $label, string $value) use ($pdf, $tx, $labelW, $valW, $rowGap) {
    $x = 18;
    $y = $pdf->GetY();
    $pdf->SetFont('Times', 'B', 11);
    $pdf->SetXY($x, $y);
    $pdf->MultiCell($labelW, 6, $tx($label), 0, 'R');
    $lh = $pdf->GetY() - $y;
    $pdf->SetFont('Times', '', 11);
    $pdf->SetXY($x + $labelW + 6, $y);
    $pdf->MultiCell($valW, 6, $tx($value !== '' ? $value : '-'), 0, 'L');
    $vh = $pdf->GetY() - $y;
    $pdf->SetY($y + max($lh, $vh) + $rowGap);
};

foreach ($data['fields'] as $label => $value) {
    $putRow($label, (string) $value);
}

// ---- signatory ---------------------------------------------------
$pdf->Ln(14);
$pdf->SetFont('Times', 'I', 13);
$pdf->Cell(0, 6, $tx(ES_CERT_SIGNATORY), 0, 1, 'C');       // stands in for the signature
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 6, $tx(ES_CERT_SIGNATORY), 0, 1, 'C');
$pdf->SetFont('Times', '', 9);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(0, 5, $tx('for ' . ES_CERT_SIGNATORY_TITLE), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(4);

// ---- QR + validation note ------------------------------------
$pdf->SetFont('Times', '', 10);
$pdf->Cell(0, 6, $tx('Validate this certificate by scanning the QR code below.'), 0, 1, 'C');
$pdf->SetFont('Courier', '', 7);
$pdf->SetTextColor(110, 110, 110);
$pdf->Cell(0, 4, $tx($vurl), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

require_once __DIR__ . '/../ememo/libs/libs/phpqrcode/qrlib.php';
$frame = @QRcode::text($vurl, false, defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 1, 4, 2);
if (is_array($frame) && $frame) {
    $n   = count($frame);
    $dim = 34.0;
    $mod = $dim / $n;
    $x0  = ($pageW - $dim) / 2;
    $y0  = $pdf->GetY();
    $pdf->SetFillColor(0, 0, 0);
    for ($r = 0; $r < $n; $r++) {
        $line = rtrim((string) $frame[$r]);
        $len  = strlen($line);
        for ($c = 0; $c < $len; $c++) {
            if ($line[$c] === '1') {
                $pdf->Rect($x0 + $c * $mod, $y0 + $r * $mod, $mod + 0.06, $mod + 0.06, 'F');
            }
        }
    }
    $pdf->SetY($y0 + $dim + 3);
} else {
    $pdf->SetFont('Times', 'I', 9);
    $pdf->Cell(0, 5, $tx('Verification: ' . $vurl), 0, 1, 'C');
}

$pdf->SetFont('Times', '', 8);
$pdf->SetTextColor(110, 110, 110);
$pdf->Cell(0, 5, $tx('Certificate No: ' . $certNo . '   |   Issued: ' . date('d/m/Y')), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

// ---- footer --------------------------------------------------
$pdf->SetY(272);
$pdf->SetFont('Times', '', 8);
$pdf->SetTextColor(110, 110, 110);
$pdf->SetX(14);
$pdf->Cell(60, 5, $tx('www.ppda.mw'), 0, 0, 'L');
$pdf->Cell(122, 5, $tx('Promoting Accountability, Transparency, and Integrity in Public Procurement in Malawi'), 0, 0, 'R');

$fname = 'certificate-' . preg_replace('/[^A-Za-z0-9]+/', '-', $code) . '.pdf';
$pdf->Output(isset($_GET['dl']) ? 'D' : 'I', $fname);
exit;
