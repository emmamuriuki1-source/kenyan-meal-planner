<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__, 2) . '/app');
require_once APP_PATH . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode([]);
    exit;
}

$recipe_id = (int)($_GET['recipe_id'] ?? 0);
if (!$recipe_id) { echo json_encode([]); exit; }

$result = $conn->query("SELECT name, quantity, unit FROM ingredients WHERE recipe_id=$recipe_id ORDER BY id");
$ings = [];
if ($result) while ($row = $result->fetch_assoc()) $ings[] = $row;

echo json_encode($ings);
