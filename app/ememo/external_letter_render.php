<?php
// external_letter_render.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/libs/libs/fpdf/fpdf.php';


// 1) Validate letter ID
$letterId = intval($_GET['letter_id'] ?? 0);
if (!$letterId) {
    http_response_code(400);
    exit('Invalid letter ID');
}

// 2) Find the first attachment
$stmt = $conn->prepare("
  SELECT file_path 
    FROM external_letter_files 
   WHERE letter_id = ? 
   ORDER BY id ASC 
   LIMIT 1
");
$stmt->bind_param("i", $letterId);
$stmt->execute();
$stmt->bind_result($relPath);
$stmt->fetch();
$stmt->close();

if (!$relPath) {
    http_response_code(404);
    exit('No attachment found');
}

$absPath = __DIR__ . '/' . ltrim($relPath, '/');
if (!file_exists($absPath)) {
    http_response_code(404);
    exit('File not found');
}

// 3) Load history entries (with by_position & to_position)
$sql = "
  SELECT 
    'instruction' AS kind,
    t.instruction AS text,
    t.created_at  AS ts,
    NULL          AS to_position,
    (
      SELECT p.name
        FROM users u
        JOIN positions p ON u.position_id = p.id
       WHERE u.id = t.from_user_id
    )              AS by_position
  FROM external_letter_trail AS t
  WHERE t.letter_id = ?

  UNION ALL

  SELECT 
    'delegation'       AS kind,
    ''                 AS text,
    d.created_at       AS ts,
    (
      SELECT p.name
        FROM users u2
        JOIN positions p ON u2.position_id = p.id
       WHERE u2.id = d.delegated_to
    )                   AS to_position,
    NULL                AS by_position
  FROM external_letter_delegation AS d
  WHERE d.letter_id = ?

  UNION ALL

  SELECT 
    'comment'          AS kind,
    c.comment          AS text,
    c.created_at       AS ts,
    NULL               AS to_position,
    (
      SELECT p.name
        FROM users u
        JOIN positions p ON u.position_id = p.id
       WHERE u.id = c.user_id
    )                   AS by_position
  FROM external_letter_comments AS c
  WHERE c.letter_id = ?

  UNION ALL

  SELECT
    'action'           AS kind,
    a.action_taken     AS text,
    a.submitted_at     AS ts,
    NULL               AS to_position,
    (
      SELECT p.name
        FROM users u
        JOIN positions p ON u.position_id = p.id
       WHERE u.id = a.submitted_by
    )                   AS by_position
  FROM external_letter_actions AS a
  WHERE a.letter_id = ?

  ORDER BY ts ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $letterId, $letterId, $letterId, $letterId);
$stmt->execute();
$res = $stmt->get_result();
$entries = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// 4) Initialize FPDF
$pdf = new FPDF('P','mm','A4');
$pdf->SetAutoPageBreak(true, 10);


// 5) Rasterize every page of the source into temporary JPEGs
if (!extension_loaded('imagick')) {
    http_response_code(500);
    exit('Imagick extension is required');
}

$im = new \Imagick();
$im->setResolution(150,150);
$im->readImage($absPath);

$page = 0;
foreach ($im as $frame) {
    $page++;
    $frame->setImageFormat('jpeg');
    $tmpFile = sys_get_temp_dir() . "/letter_{$letterId}_page_{$page}.jpg";
    $frame->writeImage($tmpFile);

    $pdf->AddPage();
    $pdf->Image($tmpFile, 0, 0, 210);

    @unlink($tmpFile);
}
$im->clear();
$im->destroy();


// 6) Append instruction + history on a new page
$pdf->AddPage();
$pdf->SetTextColor(42, 143, 46); // brand green
$pdf->SetFont('Helvetica','B',14);
$pdf->Cell(0,10,'Instruction & Activity History',0,1,'C');
$pdf->SetDrawColor(221, 221, 221);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(6);

foreach ($entries as $e) {
    switch ($e['kind']) {
        case 'instruction':
            // 1) Recipients (bold + underlined)
            $recps = array_unique(array_column(
              array_filter($entries, fn($x)=>$x['kind']==='delegation'),
              'to_position'
            ));
            $pdf->SetFont('Helvetica','BU',12);
            $pdf->Cell(0,6, implode(', ', $recps), 0,1);
            $pdf->Ln(2);

            // 2) Instruction text
            $pdf->SetFont('Arial','',12);
            $pdf->MultiCell(0,6, $e['text'], 0, 'L');
            $pdf->Ln(2);

            // 3) Sender’s position
            $pdf->SetFont('Helvetica','I',10);
            $pdf->Cell(0,5, $e['by_position'], 0,1);
            $pdf->Ln(2);

            // 4) Timestamp
            $pdf->SetFont('Helvetica','',10);
            $pdf->Cell(0,5, $e['ts'], 0,1);
            $pdf->Ln(6);
            break;

        case 'comment':
        case 'action':
            // 1) Responder’s position
            $pdf->SetFont('Helvetica','BU',12);
            $pdf->Cell(0,6, $e['by_position'], 0,1);
            $pdf->Ln(2);

            // 2) Response text
            $pdf->SetFont('Arial','',12);
            $pdf->MultiCell(0,6, $e['text'], 0, 'L');
            $pdf->Ln(2);

            // 3) Timestamp
            $pdf->SetFont('Helvetica','',10);
            $pdf->Cell(0,5, $e['ts'], 0,1);
            $pdf->Ln(2);

            // 4) Position again
            $pdf->SetFont('Helvetica','U',10);
            $pdf->Cell(0,5, $e['by_position'], 0,1);
            $pdf->Ln(6);
            break;

        case 'delegation':
            // skip: already shown
            break;
    }
}


// 7) Stream the merged PDF inline
header('Content-Type: application/pdf');
$pdf->Output('I', "letter_{$letterId}_full.pdf");
exit;
