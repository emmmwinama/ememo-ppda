<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../hub/login.php');
    exit();
}

// Every authenticated page gets a CSRF token; every POST through here is verified.
csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// Check if user uploaded signature
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT COUNT(*) FROM signatures WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($signatureCount);
$stmt->fetch();
$stmt->close();

// Save signature status to session
$_SESSION['has_signature'] = $signatureCount > 0;
?>
