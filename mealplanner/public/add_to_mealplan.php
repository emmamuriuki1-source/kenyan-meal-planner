<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$recipe_id = (int)($_POST['recipe_id'] ?? 0);
$day       = $_POST['day']  ?? '';
$meal_type = $_POST['meal'] ?? '';

$allowed_days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$allowed_meals = ['Breakfast','Lunch','Dinner','Snack','Fruits'];

if (!$recipe_id || !in_array($day, $allowed_days) || !in_array($meal_type, $allowed_meals)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$week_start = date('Y-m-d', strtotime('monday this week'));

// Get household size for default servings
$hp = $conn->query("SELECT adults+children AS hs FROM household_profiles WHERE user_id=$user_id");
$servings = ($hp && $hp->num_rows > 0) ? (int)$hp->fetch_assoc()['hs'] : 4;
$servings = max(1, $servings);

// Upsert
$chk = $conn->prepare("SELECT id FROM meal_plans WHERE user_id=? AND week_start=? AND day_of_week=? AND meal_type=?");
$chk->bind_param('isss', $user_id, $week_start, $day, $meal_type);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

if ($existing) {
    $stmt = $conn->prepare("UPDATE meal_plans SET recipe_id=?, servings=? WHERE id=?");
    $stmt->bind_param('iii', $recipe_id, $servings, $existing['id']);
} else {
    $stmt = $conn->prepare("INSERT INTO meal_plans (user_id,week_start,day_of_week,meal_type,recipe_id,servings) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isssii', $user_id, $week_start, $day, $meal_type, $recipe_id, $servings);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
