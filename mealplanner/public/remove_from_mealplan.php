<?php
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

// Only allow deleting own meal plans
$stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

$stmt->bind_param('ii', $id, $uid);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    // Also clean up saved ingredient prices for this meal plan
    $conn->query("DELETE FROM meal_ingredient_prices WHERE plan_id = $id");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not remove meal or not found.']);
}
