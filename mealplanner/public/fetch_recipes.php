<?php
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$uid      = (int)$_SESSION['user_id'];
$category = trim($_GET['category'] ?? '');

// Map meal type to category loosely
$where = "WHERE (r.user_id = $uid OR r.user_id IS NULL)";
if ($category && $category !== 'all') {
    $cat = $conn->real_escape_string($category);
    $where .= " AND LOWER(r.category) = '$cat'";
}

$result = $conn->query("SELECT id AS recipe_id, name AS recipe_name, category, image, servings, prep_time
                        FROM recipes r $where ORDER BY r.name LIMIT 50");

$recipes = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recipes[] = $row;
    }
}

echo json_encode($recipes);
