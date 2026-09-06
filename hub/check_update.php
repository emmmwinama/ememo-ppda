<?php
// check_update.php

// Expects: GET parameter `currentVersion`
// Returns JSON:
// {
//   "updateAvailable": bool,
//   // if true:
//   "versionCode": int,
//   "apkUrl": string,
//   "changelog": string
// }

header('Content-Type: application/json; charset=UTF-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Read the client’s current version
$currentVersion = isset($_GET['currentVersion'])
    ? intval($_GET['currentVersion'])
    : 0;

// 2) Define your latest published version code
//    Bump this whenever you build/upload a new APK
$latestVersionCode = 1;

// 3) Build the public URL to the APK.
//    The APK lives in your domain’s `/downloads/` folder.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$apkUrl = "{$scheme}://{$host}/downloads/ememo.apk";

// 4) Your changelog (could also be loaded from a file or DB)
$changelog = <<<TXT
• Push‐notification support for clarification requests  
• Improved attachment uploads  
• Bug fixes & performance improvements
TXT;

// 5) Compare and emit JSON
if ($currentVersion < $latestVersionCode) {
    echo json_encode([
        'updateAvailable' => true,
        'versionCode'     => $latestVersionCode,
        'apkUrl'          => $apkUrl,
        'changelog'       => $changelog,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'updateAvailable' => false
    ], JSON_PRETTY_PRINT);
}
