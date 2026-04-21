<?php
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$uid        = (int)$_SESSION['user_id'];
$plan_id    = (int)($_POST['plan_id']    ?? 0);
$ingredient = trim($_POST['ingredient']  ?? '');
$unit       = trim($_POST['unit']        ?? '');
$price      = (float)($_POST['price']    ?? 0);
$quantity   = isset($_POST['quantity']) ? (float)$_POST['quantity'] : null;

if (!$plan_id || !$ingredient) {
    echo json_encode(['success' => false, 'message' => 'Missing fields.']);
    exit;
}

// Verify this plan belongs to the user
$chk = $conn->prepare("SELECT id FROM meal_plans WHERE id = ? AND user_id = ?");
$chk->bind_param('ii', $plan_id, $uid);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// Ensure table exists with quantity column
$conn->query("CREATE TABLE IF NOT EXISTS meal_ingredient_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    ingredient_name VARCHAR(255) NOT NULL,
    unit VARCHAR(50) NOT NULL DEFAULT 'piece',
    price_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0,
    custom_quantity DECIMAL(10,2) DEFAULT NULL,
    UNIQUE KEY unique_plan_ingredient (plan_id, ingredient_name, unit)
)");
// Add custom_quantity column if missing (for existing installs)
$conn->query("ALTER TABLE meal_ingredient_prices ADD COLUMN IF NOT EXISTS custom_quantity DECIMAL(10,2) DEFAULT NULL");

$stmt = $conn->prepare("INSERT INTO meal_ingredient_prices (plan_id, ingredient_name, unit, price_per_unit, custom_quantity)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE price_per_unit = VALUES(price_per_unit), custom_quantity = VALUES(custom_quantity), unit = VALUES(unit)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

// First delete any existing rows for this plan+ingredient (regardless of unit)
// This prevents stale duplicates from accumulating when unit changes
$del = $conn->prepare("DELETE FROM meal_ingredient_prices WHERE plan_id = ? AND ingredient_name = ?");
$del->bind_param('is', $plan_id, $ingredient);
$del->execute();

$stmt->bind_param('issdd', $plan_id, $ingredient, $unit, $price, $quantity);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
