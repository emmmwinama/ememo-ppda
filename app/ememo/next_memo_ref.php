<?php
// next_memo_ref.php

header('Content-Type: application/json');
session_start();

require_once 'auth.php';   // sets $_SESSION['user_id']
require_once 'db.php';     // gives $conn

// 1) Ensure user is logged in
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(401);
    exit(json_encode(['status'=>'error','message'=>'Not authenticated']));
}

// 2) Fetch this user’s department_id
$stmt = $conn->prepare("
  SELECT department_id
    FROM users
   WHERE id = ?
   LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($deptId);
if (!$stmt->fetch() || !$deptId) {
    exit(json_encode(['status'=>'error','message'=>'No department assigned']));
}
$stmt->close();

// 3) Load the full department name
$stmt = $conn->prepare("
  SELECT name
    FROM departments
   WHERE id = ?
   LIMIT 1
");
$stmt->bind_param('i', $deptId);
$stmt->execute();
$stmt->bind_result($deptName);
if (!$stmt->fetch()) {
    exit(json_encode(['status'=>'error','message'=>'Department not found']));
}
$stmt->close();

// 4) Generate deptCode acronym, skipping “department”, “directorate”, “and”, and any ≤2-letter words
$skip = ['department','directorate','and'];
$words    = preg_split('/\s+/', trim($deptName));
$deptCode = '';
foreach ($words as $w) {
    $wLower = strtolower($w);
    if (strlen($w) > 2 && !in_array($wLower, $skip, true)) {
        $deptCode .= strtoupper($w[0]);
    }
}
// Fallback to full slug if nothing built
if ($deptCode === '') {
    $deptCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $deptName));
}

// 5) Determine current two-digit year
$year = date('y');

// 6) Find highest existing sequence for this pattern
$pattern = $conn->real_escape_string("PPDA/{$deptCode}/{$year}/%");
$sql     = "
  SELECT MAX(
    CAST(SUBSTRING_INDEX(memo_id, '/', -1) AS UNSIGNED)
  ) AS maxseq
    FROM memos
   WHERE memo_id LIKE ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $pattern);
$stmt->execute();
$stmt->bind_result($maxseq);
$stmt->fetch();
$stmt->close();

// 7) Compute next sequence (zero-pad to 3 digits)
$nextSeq    = ($maxseq ?: 0) + 1;
$nextPadded = str_pad($nextSeq, 3, '0', STR_PAD_LEFT);

// 8) Compose the new reference
$newRef = "PPDA/{$deptCode}/{$year}/{$nextPadded}";

// 9) Return JSON
echo json_encode([
    'status' => 'success',
    'ref'    => $newRef
]);
