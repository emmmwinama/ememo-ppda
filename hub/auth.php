<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
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
