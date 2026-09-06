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
    <button class="primary" onclick="window.print()">Print / Save as PDF</button>
    <a href="?id=<?= (int) $id ?>&amp;format=txt">Download .txt</a>
    <a href="javascript:history.back()">Back</a>
  </div>

  <div class="sheet">
    <div class="lh">
      <h1>Public Procurement and Disposal of Assets Authority</h1>
      <p>Private Bag 383, Lilongwe 3, Malawi &nbsp;·&nbsp; www.ppda.mw</p>
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
