<?php
// Make sure nothing was already sent (BOM/whitespace from includes, etc.)
if (ob_get_length()) ob_end_clean();

// ---- Error handling (avoid corrupting PDF by echoing warnings/notices) ----
ini_set('display_errors', 0);   // don't echo to output
ini_set('log_errors', 1);       // log instead
error_reporting(E_ALL);

require_once __DIR__ . '/libs/libs/mpdf/autoload.php';
use Mpdf\Mpdf;

// Return a readable JSON error instead of a bare 500 with an empty body.
function export_fail(int $code, string $msg): void {
    if (ob_get_length()) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// Turn real PHP warnings in our own code into catchable exceptions, but let
// notices/deprecations from the mPDF vendor tree pass through harmlessly.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    if (!in_array($severity, [E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR], true)) return false;
    if (strpos($file, '/libs/') !== false || strpos($file, '/vendor/') !== false) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ──── Load & validate input ────
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['memo'])) {
    export_fail(400, 'Invalid or missing memo payload.');
}

// Drop <img> tags whose local source file does not exist — a single missing
// signature file otherwise aborts the whole PDF with an mPDF image exception.
function strip_missing_images(string $html): string {
    return preg_replace_callback('/<img\b[^>]*\bsrc=([\'"])(.*?)\1[^>]*>/i', function ($m) {
        $src = $m[2];
        if ($src === '' || preg_match('#^(https?:)?//#i', $src) || str_starts_with($src, 'data:')) {
            return $m[0]; // leave remote / data URIs alone
        }
        $path = __DIR__ . '/' . ltrim($src, '/');
        return is_file($path) ? $m[0] : '';
    }, $html) ?? $html;
}

// ──── Extract memo fields ────
$memo           = $data['memo'];
$clarifications = $data['memo_clarifications'] ?? [];
$instructions   = $data['memo_instructions'] ?? [];
$finalActions   = $data['final_actions']       ?? [];
$throughUsers   = $data['through_users']       ?? [];
$endorsements   = $data['through_endorsements']?? [];
$originator     = $data['originator']          ?? [];

// Guard optional fields
$to       = strtoupper(($data['to_user']['position'] ?? 'Recipient'));
$dateStr  = $memo['date']    ?? date('Y-m-d');
$date     = strtoupper(date('F j, Y', strtotime($dateStr)));
$ref      = strtoupper($memo['memo_id'] ?? '');
$subject  = strtoupper($memo['subject'] ?? '');

// ──── Clean up empty paragraphs & extra <br> in content ────
$content  = $memo['content'] ?? '';
$content  = preg_replace('/<p>(\s|&nbsp;)*<\/p>/i','', $content);
$content  = preg_replace('/(<br\s*\/?>\s*){2,}/i','<br>', $content);

// Safe HTML escaper that tolerates null/arrays/objects
function clean($s) {
    if ($s === null) $s = '';
    elseif (!is_scalar($s)) $s = json_encode($s, JSON_UNESCAPED_UNICODE);
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ──── Helper to render a left-column block ────
function leftBlock($addressed, $comment, $sender, $time, $sigPath = '') {
    $addr = strtoupper(clean($addressed ?? ''));
    $cmt  = '<em>' . clean($comment ?? '') . '</em>';
    $tsIn = $time ?? date('Y-m-d H:i:s');
    $ts   = date('n/j/Y g:i A', strtotime($tsIn));
    $snd  = clean($sender ?? '');
    $sig  = !empty($sigPath)
        ? "<div><img src='" . clean($sigPath) . "' class='sig-medium'></div>"
        : '';
    return "
    <div class='left-block'>
      <div class='left-position'>{$addr}</div>
      <div class='left-comment'>{$cmt}</div>
      <div class='left-meta'>{$snd}, {$ts}</div>
      {$sig}
    </div>";
}

// ──── Build left column HTML ────
$leftHtml = '';

foreach ($clarifications as $c) {
    $leftHtml .= leftBlock(
        $c['user_position'] ?? '',
        $c['clarification_comment'] ?? '',
        $c['requested_by_position'] ?? '',
        $c['requested_at'] ?? date('Y-m-d H:i:s'),
        $c['requested_by_signature'] ?? ''
    );
    if (!empty($c['response_comment'])) {
        $leftHtml .= leftBlock(
            $c['requested_by_position'] ?? '',
            $c['response_comment'] ?? '',
            $c['user_position'] ?? '',
            $c['responded_at'] ?? ($c['requested_at'] ?? date('Y-m-d H:i:s')),
            $c['user_id_signature'] ?? ''
        );
    }
}

// ✅ Use sender_position for the instruction sender (your payload has it)
foreach ($instructions as $ins) {
    $leftHtml .= leftBlock(
        $ins['recipient_position'] ?? '',
        $ins['instruction'] ?? '',
        $ins['sender_position'] ?? '',  // <-- fix here
        $ins['created_at'] ?? date('Y-m-d H:i:s'),
        $ins['signature_path'] ?? ''
    );
}

foreach ($finalActions as $fa) {
    $comment = trim(($fa['comments'] ?? ($fa['comment'] ?? '')));
    if ($comment === '') $comment = '(No comment provided)';
    $leftHtml .= leftBlock(
        'FINAL COMMENT',
        $comment,
        $fa['from_position'] ?? '',
        $fa['timestamp'] ?? date('Y-m-d H:i:s'),
        $fa['signature_path'] ?? ''
    );
}

// ──── Build right column HTML ────
$rightHtml = "
<htmlpageheader name='firstpageheader'>
  <img src='header.png' width='100%'>
</htmlpageheader>
<sethtmlpageheader name='firstpageheader' value='on' show-this-page='1' />
<sethtmlpageheader name='firstpageheader' value='off' />

<div class='memo-type'><strong>" . clean($memo['communication_type'] ?? '') . "</strong></div>
<table class='meta-table'><tr>
  <td class='meta-left'><strong>REFERENCE:</strong> " . clean($ref) . "</td>
  <td class='meta-right'><strong>DATE:</strong> " . clean($date) . "</td>
</tr></table>
<p><strong>TO:</strong> " . clean($to) . "</p>";

foreach ($throughUsers as $i => $u) {
    $label = $i === 0 ? 'THROUGH:' : '';
    $pos   = strtoupper(clean($u['position'] ?? ''));
    $rightHtml .= "<div class='through-block'>
      <p class='through-position'><strong>{$label}</strong> {$pos}</p>";

    // Match endorsement by user_id if present
    $uid = $u['id'] ?? null;
    if ($uid !== null) {
        $matches = array_values(array_filter($endorsements, function($e) use ($uid) {
            return isset($e['user_id']) && $e['user_id'] == $uid;
        }));
        if ($matches) {
            $e    = $matches[0];
            $comm = clean($e['comment'] ?? '');
            $ts0  = $e['endorsed_at'] ?? date('Y-m-d H:i:s');
            $ts   = date('n/j/Y g:i A', strtotime($ts0));
            $sig  = !empty($e['signature_path'])
                ? "<div><img src='" . clean($e['signature_path']) . "' class='sig-small'></div>"
                : '';
            $rightHtml .= "
            <p class='endorsement-comment'><em>{$comm}</em></p>
            <p class='endorsement-date'>Endorsed: {$ts}</p>
            {$sig}";
        }
    }
    $rightHtml .= "</div>";
}

$rightHtml .= "
<h3 class='subject'>" . clean($subject) . "</h3>
<div class='memo-body'>{$content}</div>
<div class='originator text-center'>";

if (!empty($data['originator_signature'])) {
    $rightHtml .= "<img src='" . clean($data['originator_signature']) . "' class='sig-large'>";
}

$rightHtml .= "
  <p class='mt-2'>" . strtoupper(clean($originator['name'] ?? '')) . "</p>
  <p class='fw-bold'>" . strtoupper(clean($originator['position'] ?? '')) . "</p>
</div>";

// Remove references to signature/header images that aren't on disk
$leftHtml  = strip_missing_images($leftHtml);
$rightHtml = strip_missing_images($rightHtml);

// ──── Instantiate mPDF ────
$tmpDir = __DIR__ . '/libs/libs/mpdf/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }

try {
    $mpdf = new Mpdf([
      'format'          => 'A4',
      'margin_top'      => 20,
      'margin_bottom'   => 20,
      'margin_left'     => 10,
      'margin_right'    => 10,
      'tempDir'         => is_writable($tmpDir) ? $tmpDir : sys_get_temp_dir(),
      'showImageErrors' => false,
    ]);

    $mpdf->SetTitle("Memo " . ($memo['memo_id'] ?? ''));
    $mpdf->SetFooter('{PAGENO} / {nbpg}');

// ──── CSS ────
$css = <<<CSS
body { font-family: DejaVu Sans, sans-serif; font-size:11pt; }
.thin { border-bottom:1px solid #ddd; margin:5px 0; }
.meta-table{ width:100%; border-collapse:collapse; margin-bottom:12px; border-bottom:2px solid #2a8f2e; padding-bottom:6px; }
.meta-left{ text-align:left; font-weight:bold; text-transform:uppercase; }
.meta-right{ text-align:right;font-weight:bold;text-transform:uppercase; }
.memo-type{ text-align:center; font-weight:bold; text-transform:uppercase; font-size:13pt; margin:10px 0; }
.subject  { text-align:center; font-weight:bold; text-transform:uppercase; margin:15px 0; }
.through-block   { margin-bottom:4px; page-break-inside:avoid; }
.through-position{ font-weight:normal; margin-bottom:1px; }
.endorsement-comment { margin-bottom:1px; font-style:italic; }
.endorsement-date{ font-size:9pt; color:#666; margin-bottom:1px; }
.left-block  { margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid #ddd; page-break-inside:avoid; }
.left-position{ font-size:10pt; text-transform:uppercase; margin-bottom:2px; }
.left-comment { font-family:Georgia, serif; font-style:italic; font-size:10pt; margin-bottom:2px; text-align:justify; }
.left-meta    { font-size:8pt; color:#666; margin-bottom:4px; }
.sig-small    { width:  80px; }
.sig-large    { width: 150px; }
.sig-medium   { width: 100px; }
.left-col {
  float: left;
  width: 29%;
  background: #f7f9fa;
  padding: 5px 15px 5px 5px;
  box-sizing: border-box;
}
.right-col {
  margin-left: 31%;
  padding: 5px 10px;
  border-left: 1px solid #ddd;
  box-sizing: border-box;
}
.clearfix  { clear: both; }
CSS;

$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

// ──── Output columns ────
$html = "
  <div style=\"height:55mm;\"></div>
  <div class='left-col'>{$leftHtml}</div>
  <div class='right-col'>{$rightHtml}</div>
  <div class='clearfix'></div>
";
$mpdf->WriteHTML($html);

    // ──── Output PDF ────
    // Ensure absolutely no previous output leaks into the PDF stream
    if (ob_get_length()) { ob_end_clean(); }
    $mpdf->Output('memo_' . ($memo['memo_id'] ?? 'document') . '.pdf', 'I');
    exit;
} catch (\Throwable $e) {
    error_log('workflow_memo_export failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    export_fail(500, 'PDF generation failed: ' . $e->getMessage());
}
