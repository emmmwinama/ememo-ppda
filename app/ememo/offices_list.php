// File: offices_list.php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, name FROM offices ORDER BY name");
$offices = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $offices]);
