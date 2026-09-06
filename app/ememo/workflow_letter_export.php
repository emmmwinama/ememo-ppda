<?php
// workflow_letter_export.php

// ─── Clear any previous output & turn OFF display_errors ────────────────
if (ob_get_length()) ob_end_clean();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once 'auth.php';
require_once __DIR__ . '/libs/libs/mpdf/autoload.php';
use Mpdf\Mpdf;

// ─── Only accept POST + parse JSON ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    exit;
}

// ─── Robust cleanup of the HTML body ────────────────────────────────────
$bodyHtml = $payload['bodyHtml'] ?? '';

// 1) Strip out any <p> that contains only whitespace, &nbsp;, or <br>
$bodyHtml = preg_replace(
  '/<p[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i',
  '',
  $bodyHtml
);

// 2) Collapse multiple <br> in a row into one
$bodyHtml = preg_replace(
  '#(?:<br\s*/?>\s*){2,}#i',
  '<br>',
  $bodyHtml
);

// 3) Remove any stray class attributes (optional, if you want)
// $bodyHtml = preg_replace('/<p class="[^"]*"/i','<p',$bodyHtml);

// 4) Remove whitespace/newlines between tags
$bodyHtml = preg_replace(
  '/>\s+</', 
  '><', 
  $bodyHtml
);

// 5) Collapse runs of spaces into a single space
$bodyHtml = preg_replace(
  '/ {2,}/', 
  ' ', 
  $bodyHtml
);

// 6) Trim leading/trailing whitespace
$bodyHtml = trim($bodyHtml);

// ─── Shortcut escaper ───────────────────────────────────────────────────
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ─── Extract & sanitize fields ─────────────────────────────────────────
$status       = strtolower($payload['memoStatus']   ?? '');
$letterRef    = h($payload['letterRef']    ?? '');
$letterDate   = h($payload['letterDate']   ?? '');
$headerImg    = h($payload['headerImage']  ?? 'header_letter.png');
$fromPos      = h($payload['from']['position'] ?? '');
$fromAddr     = h($payload['from']['address']  ?? '');
$recipients   = $payload['recipients']          ?? [];
$subject      = h($payload['subject']           ?? '');
$approverName = h($payload['approver']['name']     ?? '');
$approverPos  = h($payload['approver']['position'] ?? '');
$sigPath      = $payload['approver']['signaturePath'] ?? '';

// ─── Instantiate mPDF ──────────────────────────────────────────────────
$mpdf = new Mpdf([
    'format'        => 'A4',
    'margin_top'    => 20,
    'margin_bottom' => 20,
    'margin_left'   => 15,
    'margin_right'  => 15,
]);
$mpdf->SetTitle("Letter {$letterRef}");

// ─── CSS ────────────────────────────────────────────────────────────────
$css = <<<CSS
@page { margin-top: 20mm; }
@page :first { margin-top: 70mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; line-height:1.4; }
p { margin-bottom: 6pt; }
.meta-table { border-bottom: 2px solid #2a8f2e; padding-bottom: 6px; margin-bottom: 8px; }
CSS;
$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

// ─── Header image only on first page ───────────────────────────────────
$headerHtml = "
<htmlpageheader name='first'>
  <img src='{$headerImg}' style='width:100%;' />
  <hr style='border:none;border-bottom:1px solid #ddd;margin:4px 0 8px;'/>
</htmlpageheader>";
$mpdf->SetHTMLHeader($headerHtml, 'O', true);

// ─── Build letter body ─────────────────────────────────────────────────
$body  = '<div>';

// Optional DRAFT watermark
if ($status !== 'approved') {
    $body .= '<div style="text-align:center;color:#888;margin:8px 0;">
                <strong>DRAFT LETTER</strong>
              </div>';
}

// KEEP your header logic here (you already have it in SetHTMLHeader)

// Reference & Date side by side via table
$body .= "
<htmlpageheader name='firstpageheader'>
  <img src='header.png' width='100%'>
</htmlpageheader>
<sethtmlpageheader name='firstpageheader' value='on' show-this-page='1' />
<sethtmlpageheader name='firstpageheader' value='off' />

  <table width=\"100%\" class=\"meta-table\" style=\"border-collapse:collapse; margin:8px 0;\">
    <tr>
      <td style=\"font-weight:bold; text-transform:uppercase; font-size:10pt;\">
        REFERENCE: {$letterRef}
      </td>
      <td style=\"text-align:right; font-weight:bold; text-transform:uppercase; font-size:10pt;\">
        DATE: {$letterDate}
      </td>
    </tr>
  </table>
";

// From / To
$body .= "
  <p><strong>FROM:</strong> {$fromPos}, {$fromAddr}</p>
  <p><strong>TO:</strong></p>";
foreach ($recipients as $r) {
    $line = strtoupper(h($r['position'] ?? ''));
    if (!empty($r['address'])) {
        $line .= ' ' . h($r['address']);
    }
    $body .= "<p style='margin-left:12px;'>{$line}</p>";
}

// Subject
$body .= "
  <h3 style='text-align:center;
             text-transform:uppercase;
             font-weight:bold;
             font-size:12pt;margin:16px 0;'>{$subject}</h3>";

// Body content
$body .= "
  <div style='text-align:justify;margin-bottom:16px;'>
    {$bodyHtml}
  </div>";

// Signature block
$body .= "
  <p style='text-align:center;margin:24px 0 8px;'>Yours faithfully,</p>";
if ($sigPath && file_exists(__DIR__ . '/' . ltrim($sigPath, '/'))) {
    $body .= "<div style='text-align:center;margin-bottom:8px;'>
                <img src='{$sigPath}' style='width:100px;'/>
              </div>";
}
$body .= "
  <p style='text-align:center;font-weight:bold;margin:2px 0;'>{$approverName}</p>
  <p style='text-align:center;margin:2px 0;'>{$approverPos}</p>
</div>";

// ─── Final cleanup of $body itself ───────────────────────────────────────
$body = preg_replace('/>\s+</','><',$body);
$body = preg_replace('/ {2,}/',' ',$body);
$body = trim($body);

// ─── Render & stream PDF ────────────────────────────────────────────────
$mpdf->WriteHTML($body);
$mpdf->Output("letter_{$letterRef}.pdf", 'I');
exit;
