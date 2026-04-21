<?php
session_start();

// Adjust the path to your db.php file as needed
require_once __DIR__ . '/../app/config/db.php';

if (!isset($conn)) {
    die('Database connection not established. Check the path to db.php.');
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Helper functions
function e($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function singularize($word) {
    $irr = [
        'beans' => 'bean', 'tomatoes' => 'tomato', 'potatoes' => 'potato',
        'onions' => 'onion', 'carrots' => 'carrot', 'cabbages' => 'cabbage',
        'spinaches' => 'spinach', 'kales' => 'kale', 'bananas' => 'banana',
        'mangoes' => 'mango', 'oranges' => 'orange', 'apples' => 'apple',
        'lemons' => 'lemon', 'limes' => 'lime', 'avocados' => 'avocado',
        'eggs' => 'egg', 'chickens' => 'chicken', 'fishes' => 'fish',
        'goats' => 'goat', 'sausages' => 'sausage'
    ];
    if (isset($irr[$word])) return $irr[$word];
    if (substr($word, -1) === 's' && substr($word, -2) !== 'ss') return substr($word, 0, -1);
    return $word;
}

function detectFoodGroups($recipeName, $category) {
    $groups = ['Veg' => false, 'Fruit' => false, 'Dairy' => false, 'Protein' => false, 'Carbs' => false];
    $text = strtolower($recipeName . ' ' . $category);
    $veg  = ['sukuma', 'kale', 'spinach', 'tomato', 'onion', 'carrot', 'cabbage', 'coriander', 'dhania', 'capsicum', 'pumpkin', 'amaranth', 'mrenda', 'managu', 'terere', 'greens', 'vegetable', 'veg', 'kunde', 'saga', 'mchicha', 'matembele', 'leek', 'celery', 'broccoli', 'pepper', 'beetroot', 'cucumber'];
    $frt  = ['banana', 'mango', 'apple', 'orange', 'pawpaw', 'papaya', 'pineapple', 'watermelon', 'lemon', 'lime', 'avocado', 'passion', 'guava', 'strawberry', 'fruit'];
    $dai  = ['milk', 'yogurt', 'yoghurt', 'cheese', 'ghee', 'butter', 'cream', 'curd', 'tea', 'chai'];
    $pro  = ['beef', 'chicken', 'fish', 'egg', 'bean', 'lentil', 'liver', 'goat', 'mutton', 'pork', 'tofu', 'soya', 'meat', 'sausage', 'ndengu', 'omena', 'tilapia', 'githeri', 'stew'];
    $car  = ['ugali', 'rice', 'potato', 'chapati', 'bread', 'maize', 'wheat', 'pasta', 'noodle', 'cassava', 'mandazi', 'porridge', 'nduma', 'arrowroot', 'sweet potato', 'yam', 'matoke', 'uji', 'millet', 'sorghum'];

    foreach ($veg as $k) if (strpos($text, $k) !== false) { $groups['Veg'] = true; break; }
    foreach ($frt as $k) if (strpos($text, $k) !== false) { $groups['Fruit'] = true; break; }
    foreach ($dai as $k) if (strpos($text, $k) !== false) { $groups['Dairy'] = true; break; }
    foreach ($pro as $k) if (strpos($text, $k) !== false) { $groups['Protein'] = true; break; }
    foreach ($car as $k) if (strpos($text, $k) !== false) { $groups['Carbs'] = true; break; }
    return $groups;
}

// User data
$uid = (int)$_SESSION['user_id'];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$slots = ['Breakfast', 'Lunch', 'Dinner', 'Fruits'];

// Household profile
$household = [];
$hq = $conn->query("SELECT * FROM household_profiles WHERE user_id = $uid");
if ($hq && $hq->num_rows > 0) $household = $hq->fetch_assoc();
$adults = (int)($household['adults'] ?? 1);
$children = (int)($household['children'] ?? 0);
$household_size = $adults + $children;

// --- FIX: Get weekly budget from budgets table (current week) or fallback to household ---
$week_start = date('Y-m-d', strtotime('monday this week'));
$brow = $conn->query("SELECT total_budget FROM budgets WHERE user_id = $uid AND week_start = '$week_start' LIMIT 1");
if ($brow && $brow->num_rows > 0) {
    $weekly_budget = (float)$brow->fetch_assoc()['total_budget'];
} else {
    $weekly_budget = (float)($household['weekly_budget'] ?? 0);
}
// ---------------------------------------------------------------------------------

// Ensure necessary tables (optional, but safe)
$conn->query("CREATE TABLE IF NOT EXISTS meal_ingredient_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    ingredient_name VARCHAR(255) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0,
    custom_quantity DECIMAL(10,2) DEFAULT NULL,
    UNIQUE KEY unique_plan_ingredient (plan_id, ingredient_name, unit)
)");
$conn->query("ALTER TABLE meal_plans ADD COLUMN IF NOT EXISTS servings INT DEFAULT 4");

// Fetch meal plans with recipe details
$stmt = $conn->prepare("
    SELECT mp.id AS plan_id, mp.day_of_week AS day, mp.meal_type, mp.servings AS planned_servings,
           r.id AS recipe_id, r.name AS recipe_name, r.category, r.image, r.servings AS recipe_servings
    FROM meal_plans mp
    JOIN recipes r ON mp.recipe_id = r.id
    WHERE mp.user_id = ?
    ORDER BY FIELD(mp.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
             FIELD(mp.meal_type,'Breakfast','Lunch','Dinner','Fruits')
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$result = $stmt->get_result();

$mealPlan = [];
$mealCount = 0;
$dailyGroups = [];
$dailyCosts = array_fill_keys($days, 0);
$mealsData = [];

foreach ($days as $d) {
    $dailyGroups[$d] = ['Veg' => false, 'Fruit' => false, 'Dairy' => false, 'Protein' => false, 'Carbs' => false, 'meals' => 0];
}

while ($row = $result->fetch_assoc()) {
    $day = ucfirst(strtolower($row['day']));
    $meal = ucfirst(strtolower($row['meal_type']));
    if (!in_array($day, $days) || !in_array($meal, $slots)) continue;

    $recipe_servings = max(1, (int)($row['recipe_servings'] ?? 4));
    $planned_servings = max(1, (int)($row['planned_servings'] ?? $household_size));
    $scale_factor = $planned_servings / $recipe_servings;

    // Fetch ingredients for this recipe
    $ingredients = [];
    $ing_stmt = $conn->prepare("SELECT name, quantity, unit FROM ingredients WHERE recipe_id = ?");
    $ing_stmt->bind_param('i', $row['recipe_id']);
    $ing_stmt->execute();
    $ing_res = $ing_stmt->get_result();
    while ($ing = $ing_res->fetch_assoc()) {
        $ingredients[] = [
            'name'         => singularize(strtolower(trim($ing['name']))),
            'display_name' => ucfirst(singularize(strtolower(trim($ing['name'])))),
            'quantity'     => (float)$ing['quantity'],
            'unit'         => $ing['unit'] ?? 'piece'
        ];
    }
    $ing_stmt->close();

    // Detect food groups
    $groups = detectFoodGroups($row['recipe_name'], $row['category'] ?? '');
    foreach ($groups as $g => $v) if ($v) $dailyGroups[$day][$g] = true;
    $dailyGroups[$day]['meals']++;

    // Get custom prices/quantities for this meal plan – index by ingredient name only
    $custom_prices = [];
    $price_stmt = $conn->prepare("SELECT ingredient_name, unit, price_per_unit, custom_quantity FROM meal_ingredient_prices WHERE plan_id = ?");
    $price_stmt->bind_param('i', $row['plan_id']);
    $price_stmt->execute();
    $price_res = $price_stmt->get_result();
    while ($cp = $price_res->fetch_assoc()) {
        $custom_prices[$cp['ingredient_name']] = [
            'unit'     => $cp['unit'],
            'price'    => (float)$cp['price_per_unit'],
            'quantity' => $cp['custom_quantity'] !== null ? (float)$cp['custom_quantity'] : null
        ];
    }
    $price_stmt->close();

    // Build meal ingredients list for display – use custom data if available
    $mealIngs = [];
    foreach ($ingredients as $ing) {
        $custom = $custom_prices[$ing['name']] ?? null;
        // If a custom record exists, use its unit and price; otherwise fall back to recipe defaults
        $unit = $custom ? $custom['unit'] : $ing['unit'];
        $price = $custom ? $custom['price'] : 0;
        $quantity = ($custom && $custom['quantity'] !== null) ? $custom['quantity'] : ($ing['quantity'] * $scale_factor);
        $mealIngs[] = [
            'raw_name'      => $ing['name'],
            'display_name'  => $ing['display_name'],
            'unit'          => $unit,
            'quantity'      => $quantity,
            'price'         => $price
        ];
    }

    // Store for later use (shopping list)
    $mealsData[] = [
        'plan_id'     => $row['plan_id'],
        'day'         => $day,
        'meal'        => $meal,
        'recipe_name' => $row['recipe_name'],
        'ingredients' => $mealIngs
    ];

    // Store in mealPlan array for table rendering
    $mealPlan[$day][$meal] = $row;
    $mealCount++;
}
$stmt->close();

// Compute costs
$mealCosts = [];
$totalCost = 0;
foreach ($mealsData as $m) {
    $mc = 0;
    foreach ($m['ingredients'] as $ing) {
        $c = $ing['quantity'] * $ing['price'];
        $mc += $c;
        $dailyCosts[$m['day']] += $c;
        $totalCost += $c;
    }
    $mealCosts[$m['plan_id']] = $mc;
}

// Daily missing groups
$dailyMissing = [];
foreach ($days as $day) {
    $g = $dailyGroups[$day];
    if ($g['meals'] == 0) {
        $dailyMissing[$day] = 'Missing: Veg, Fruit, Dairy, Protein, Carbs';
        continue;
    }
    $missing = [];
    foreach (['Veg', 'Fruit', 'Dairy', 'Protein', 'Carbs'] as $grp) {
        if (!$g[$grp]) $missing[] = $grp;
    }
    $dailyMissing[$day] = empty($missing) ? 'Balanced' : 'Missing: ' . implode(', ', $missing);
}
$remaining = $weekly_budget - $totalCost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meal Planner - Kenyan Meal Planner</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Inter', sans-serif;
    background: #fef9e6;
    color: #2d3e2f;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.header {
    background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
    color: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.header h1 {
    font-size: 1.6rem;
    font-weight: 600;
}
.header h1 i {
    margin-right: 10px;
    color: #FB8C00;
}
.header .user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.header a {
    color: white;
    text-decoration: none;
    padding: 0.5rem 1.2rem;
    border-radius: 40px;
    background: rgba(255,255,255,0.15);
    transition: 0.2s;
    font-weight: 500;
}
.header a:hover {
    background: #FB8C00;
}
.container {
    display: flex;
    flex: 1;
}
.sidebar {
    width: 260px;
    background: #1B5E20;
    padding: 2rem 0;
    flex-shrink: 0;
}
.sidebar ul {
    list-style: none;
}
.sidebar li {
    margin-bottom: 0.25rem;
}
.sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.8rem 1.5rem;
    color: #e0f2e0;
    text-decoration: none;
    transition: 0.2s;
    border-left: 4px solid transparent;
    font-weight: 500;
}
.sidebar a i {
    width: 24px;
    color: #FB8C00;
}
.sidebar a:hover, .sidebar a.active {
    background: rgba(251,140,0,0.2);
    border-left-color: #FB8C00;
    color: white;
}
.sidebar a.active {
    background: #FB8C00;
    color: white;
}
.sidebar a.active i {
    color: white;
}
.main {
    flex: 1;
    padding: 1.8rem;
    overflow-x: auto;
}
.card {
    background: white;
    border-radius: 24px;
    padding: 1.5rem;
    margin-bottom: 1.8rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid #e8f0e8;
    transition: 0.2s;
}
.card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.card h3 {
    color: #2E7D32;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.2rem;
    font-weight: 600;
}
.card h3 i {
    color: #FB8C00;
}
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.2rem;
}
.summary-card {
    background: #fff;
    padding: 1rem;
    border-radius: 16px;
    text-align: center;
    border: 1px solid #e8f0e8;
}
.summary-card h4 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #5f6b5f;
    margin-bottom: 0.4rem;
}
.summary-card p {
    font-size: 1.6rem;
    font-weight: 700;
    color: #2E7D32;
}
.meal-table {
    width: 100%;
    border-collapse: collapse;
}
.meal-table th {
    background: #2E7D32;
    color: white;
    padding: 0.8rem;
    font-size: 0.9rem;
}
.meal-table td {
    padding: 0.8rem;
    border-bottom: 1px solid #e8f0e8;
    vertical-align: middle;
}
.meal-card {
    display: flex;
    align-items: center;
    gap: 10px;
}
.meal-card img {
    width: 52px;
    height: 52px;
    object-fit: cover;
    border-radius: 12px;
}
.meal-details strong {
    font-size: 0.9rem;
    color: #1B5E20;
}
.meal-cost {
    font-size: 0.8rem;
    color: #FB8C00;
    font-weight: 700;
    margin-top: 3px;
}
.action-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.action-btn {
    background: #2E7D32;
    color: white;
    padding: 0.6rem 1.2rem;
    border-radius: 40px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: 0.2s;
}
.action-btn:hover {
    background: #FB8C00;
}
.add-btn, .remove-btn {
    padding: 5px 12px;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 500;
    border: none;
    font-size: 0.8rem;
    transition: 0.2s;
}
.add-btn {
    background: #2E7D32;
    color: white;
}
.remove-btn {
    background: #FB8C00;
    color: white;
}
.missing-text {
    font-size: 0.75rem;
    color: #E53935;
    margin-top: 4px;
    font-weight: 500;
}
.balanced-text {
    font-size: 0.75rem;
    color: #2E7D32;
    font-weight: 600;
}
.checkmark {
    color: #2E7D32;
    font-size: 1.2rem;
}
.crossmark {
    color: #E53935;
    font-size: 1.2rem;
}
.day-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-bottom: 1.2rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid #e8f0e8;
}
.filter-btn {
    background: #f0f0f0;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 40px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: 0.2s;
}
.filter-btn:hover {
    background: #FB8C00;
    color: white;
}
.filter-btn.active {
    background: #2E7D32;
    color: white;
}
.day-shopping-group {
    margin-bottom: 2rem;
    background: #FAFAF5;
    border-radius: 20px;
    padding: 0.8rem;
}
.day-heading {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2E7D32;
    margin-bottom: 0.8rem;
    padding-bottom: 0.4rem;
    border-bottom: 3px solid #FB8C00;
    display: inline-block;
}
.meal-shopping-list {
    background: white;
    border-radius: 16px;
    padding: 1rem;
    margin-bottom: 0.8rem;
    border: 1px solid #e8f0e8;
}
.meal-shopping-list h4 {
    color: #1B5E20;
    margin-bottom: 0.7rem;
    font-weight: 600;
    border-left: 4px solid #FB8C00;
    padding-left: 10px;
}
.qty-input, .price-input {
    width: 80px;
    padding: 5px 8px;
    border: 1px solid #ddd;
    border-radius: 30px;
    text-align: center;
    font-family: inherit;
}
.unit-select {
    width: 90px;
    padding: 5px;
    border-radius: 30px;
    border: 1px solid #ddd;
    background: white;
}
.ingredient-table {
    width: 100%;
    border-collapse: collapse;
}
.ingredient-table th {
    background: #2E7D32;
    color: white;
    padding: 0.6rem;
    font-size: 0.85rem;
}
.ingredient-table td {
    padding: 0.6rem;
    border-bottom: 1px solid #e8f0e8;
    vertical-align: middle;
}
.household-note {
    background: #E8F5E9;
    padding: 0.6rem 1.2rem;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 1.2rem;
    font-size: 0.85rem;
    font-weight: 500;
}
.warning-text {
    color: #E53935;
    font-weight: 700;
}
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
.modal-box {
    background: white;
    padding: 1.8rem;
    border-radius: 28px;
    width: 520px;
    max-width: 92%;
    max-height: 82vh;
    overflow-y: auto;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}
#recipeList {
    max-height: 55vh;
    overflow-y: auto;
    padding-right: 4px;
}
.recipe-card-modal {
    display: flex;
    gap: 14px;
    margin-bottom: 12px;
    border-bottom: 1px solid #e8f0e8;
    padding-bottom: 12px;
    align-items: center;
}
.recipe-card-modal:last-child {
    border-bottom: none;
    margin-bottom: 0;
}
.add-to-plan-btn {
    background: #2E7D32;
    color: white;
    padding: 5px 14px;
    border: none;
    border-radius: 40px;
    cursor: pointer;
    transition: 0.2s;
    font-size: 0.82rem;
    font-weight: 600;
}
.add-to-plan-btn:hover {
    background: #FB8C00;
}
.toast {
    position: fixed;
    bottom: 25px;
    right: 25px;
    background: #2E7D32;
    color: white;
    padding: 12px 20px;
    border-radius: 50px;
    z-index: 2000;
    animation: slideIn 0.3s ease;
    font-weight: 500;
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
footer {
    background: #1B5E20;
    color: white;
    text-align: center;
    padding: 0.9rem;j
    margin-top: auto;
    font-size: 0.8rem;
}
@media (max-width: 768px) {
    .container {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
    }
    .sidebar ul {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
    }
    .sidebar a {
        padding: 0.5rem 1rem;
    }
    .main {
        padding: 1rem;
    }
    .meal-table {
        display: block;
        overflow-x: auto;
    }
}
@media print {
    .sidebar, .action-bar, .header .user-info a, footer, .day-filter, .modal,
    .remove-btn, .add-btn, #saveAllPricesBtn, button {
        display: none !important;
    }
    body { background: #fff !important; }
    .header {
        background: #1B5E20 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .container { display: block !important; }
    .main { margin: 0; padding: 0.5rem; }
    .card {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
        break-inside: avoid;
        margin-bottom: 1rem !important;
    }
    .meal-table th {
        background: #2E7D32 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .ingredient-table th {
        background: #2E7D32 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .qty-input, .price-input, .unit-select {
        border: none !important;
        background: transparent !important;
    }
    .household-note {
        background: #e8f5e9 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
</head>
<body>

<div class="header">
    <h1><i class="fas fa-utensils"></i> Kenyan Meal Planner</h1>
    <div class="user-info">
        <span><i class="fas fa-user-circle"></i> <?= e($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'User') ?></span>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container">
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="recipes.php"><i class="fas fa-book-open"></i> Recipes</a></li>
            <li><a href="mealplanner.php" class="active"><i class="fas fa-calendar-week"></i> Meal Planner</a></li>
            <li><a href="report.php"><i class="fas fa-chart-line"></i> Budget &amp; Nutrition Report</a></li>
        </ul>
    </div>

    <div class="main">
        <div class="action-bar">
            <button class="action-btn" onclick="downloadMealPlan()"><i class="fas fa-download"></i> Download</button>
            <button class="action-btn" id="saveAllPricesBtn"><i class="fas fa-save"></i> Save All Prices</button>
            <button class="action-btn" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>

        <div class="household-note">
            <i class="fas fa-users"></i> Household: <strong><?= $household_size ?> <?= $household_size==1 ? 'person' : 'people' ?></strong>
            &nbsp;|&nbsp; Budget: <strong>KES <?= number_format($weekly_budget,2) ?></strong>
            <?php if ($remaining < 0): ?>
                <span class="warning-text"> | Over budget by KES <?= number_format(abs($remaining),2) ?></span>
            <?php endif; ?>
        </div>

        <div class="summary-grid">
            <div class="summary-card"><h4>Weekly Budget</h4><p>KES <?= number_format($weekly_budget,2) ?></p></div>
            <div class="summary-card"><h4>Planned Cost</h4><p id="total-cost">KES 0.00</p></div>
            <div class="summary-card"><h4>Remaining</h4><p id="remaining-budget" style="color:#2E7D32">KES <?= number_format($weekly_budget,2) ?></p></div>
            <div class="summary-card"><h4>Meals</h4><p><?= $mealCount ?>/21</p></div>
        </div>

        <!-- Weekly Meal Plan Table -->
        <div class="card">
            <h3><i class="fas fa-calendar-alt"></i> Weekly Meal Plan</h3>
            <div style="overflow-x:auto;">
                <table class="meal-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <?php foreach ($slots as $s) echo "<th>$s</th>"; ?>
                            <th>Daily Cost &amp; Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($days as $day): ?>
                        <tr>
                            <td><strong><?= $day ?></strong></td>
                            <?php foreach ($slots as $meal):
                                if (isset($mealPlan[$day][$meal])):
                                    $m = $mealPlan[$day][$meal];
                                    $img = !empty($m['image']) ? $m['image'] : "https://images.unsplash.com/photo-1512058564366-18510be2db19?w=56&h=56&fit=crop";

                                    $mc = $mealCosts[$m['plan_id']] ?? 0;

                            ?>
                                <td data-day="<?= $day ?>" data-meal="<?= $meal ?>" data-plan-id="<?= $m['plan_id'] ?>">
                                    <div class="meal-card">
                                        <img src="<?= e($img) ?>" alt="<?= e($m['recipe_name']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:10px;">
                                        <div class="meal-details">
                                            <strong><?= e($m['recipe_name']) ?></strong><br>
                                            <small><?= $m['planned_servings'] ?> <?= $m['planned_servings']==1 ? 'person' : 'people' ?></small>
                                            <div class="meal-cost" data-plan-id="<?= $m['plan_id'] ?>">KES <?= number_format($mc,2) ?></div>
                                        </div>
                                    </div>
                                    <button class="remove-btn" data-id="<?= $m['plan_id'] ?>"><i class="fas fa-trash"></i> Remove</button>
                                </td>
                            <?php else: ?>
                                <td><button class="add-btn" data-day="<?= $day ?>" data-meal="<?= $meal ?>"><i class="fas fa-plus"></i> Add</button></td>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <td class="daily-cost-cell" data-day="<?= $day ?>">
                                <strong>KES <?= number_format($dailyCosts[$day],2) ?></strong><br>
                                <div class="<?= strpos($dailyMissing[$day],'Balanced') !== false ? 'balanced-text' : 'missing-text' ?>">
                                    <?= e($dailyMissing[$day]) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Nutritional Summary -->
        <div class="card">
            <h3><i class="fas fa-chart-line"></i> Nutritional Summary</h3>
            <div style="overflow-x:auto;">
                <table class="meal-table">
                    <thead><tr><th>Day</th><th>🥦 Veg</th><th>🍎 Fruit</th><th>🥛 Dairy</th><th>🍗 Protein</th><th>🌾 Carbs</th></tr></thead>
                    <tbody>
                    <?php foreach ($days as $day):
                        $g = $dailyGroups[$day];
                        $icon = fn($v) => $v ? '<i class="fas fa-check-circle checkmark"></i>' : '<i class="fas fa-times-circle crossmark"></i>';
                    ?>
                        <tr>
                            <td><strong><?= $day ?></strong></td>
                            <td><?= $icon($g['Veg']) ?></td>
                            <td><?= $icon($g['Fruit']) ?></td>
                            <td><?= $icon($g['Dairy']) ?></td>
                            <td><?= $icon($g['Protein']) ?></td>
                            <td><?= $icon($g['Carbs']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shopping List -->
        <div class="card">
            <h3><i class="fas fa-shopping-basket"></i> Shopping List (by Meal)</h3>
            <p style="font-size:.85rem;margin-bottom:1rem;">Adjust ingredient quantities and prices per meal – they are saved independently.</p>
            <div class="day-filter" id="dayFilter">
                <button class="filter-btn active" data-day="all">All</button>
                <?php foreach ($days as $d): ?>
                    <button class="filter-btn" data-day="<?= $d ?>"><?= $d ?></button>
                <?php endforeach; ?>
            </div>
            <div id="shopping-list-container"><p>Loading shopping list…</p></div>
        </div>

        <!-- Healthy Kenyan Suggestions -->
        <div class="card">
            <h3><i class="fas fa-apple-alt"></i> Healthy Kenyan Suggestions</h3>
            <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
                <div style="flex:1"><h4>🍎 Fruits:</h4><p>Banana, mango, apple, pawpaw, oranges</p></div>
                <div style="flex:1"><h4>🥦 Vegetables:</h4><p>Sukuma wiki, spinach, kales, tomatoes, carrots</p></div>
                <div style="flex:1"><h4>💪 Protein:</h4><p>Beans, lentils, eggs, lean meat, fish</p></div>
                <div style="flex:1"><h4>🥛 Dairy:</h4><p>Milk, yogurt, cheese for calcium</p></div>
            </div>
        </div>
    </div>
</div>

<!-- Add Recipe Modal -->
<div id="addModal" class="modal">
    <div class="modal-box">
        <h3><i class="fas fa-plus-circle"></i> Select Recipe</h3>
        <p id="modalDayMeal" style="margin-bottom:1rem;color:#666;"></p>
        <div id="recipeList"></div>
        <button onclick="closeModal()" style="margin-top:1rem;background:#ccc;width:100%;padding:8px;border:none;border-radius:40px;cursor:pointer;">Cancel</button>
    </div>
</div>

<!-- Confirmation Modal for Removal -->
<div id="confirmModal" class="modal">
    <div class="modal-box" style="text-align: center;">
        <h3><i class="fas fa-exclamation-triangle"></i> Confirm Removal</h3>
        <p id="confirmMessage" style="margin: 1rem 0;">Are you sure you want to remove this meal from your plan?</p>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <button id="confirmYesBtn" class="action-btn" style="background: #E53935;">Yes, Remove</button>
            <button id="confirmNoBtn" class="action-btn" style="background: #6c757d;">Cancel</button>
        </div>
    </div>
</div>

<footer><i class="fas fa-leaf"></i> &copy; <?= date('Y') ?> Kenyan Meal Planner | Prices & quantities are saved per meal</footer>

<script>
const mealsData = <?= json_encode($mealsData) ?>;
const weeklyBudget = <?= (float)$weekly_budget ?>;

function esc(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function showToast(msg, isErr = false) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.background = isErr ? '#E53935' : '#2E7D32';
    toast.innerHTML = `<i class="fas ${isErr ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function renderShoppingList() {
    if (!mealsData.length) {
        document.getElementById('shopping-list-container').innerHTML = '<p>No meals planned yet. Use the "+ Add" buttons above.</p>';
        return;
    }
    const grouped = {};
    const order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    for (let m of mealsData) {
        if (!grouped[m.day]) grouped[m.day] = [];
        grouped[m.day].push(m);
    }
    let html = '';
    for (let day of order) {
        if (!grouped[day]) continue;
        html += `<div class="day-shopping-group" data-day="${day}">
                    <h3 class="day-heading"><i class="fas fa-calendar-day"></i> ${day}</h3>`;
        for (let meal of grouped[day]) {
            html += `<div class="meal-shopping-list" data-plan-id="${meal.plan_id}">
                        <h4>${esc(meal.meal)}: ${esc(meal.recipe_name)}</h4>
                        <table class="ingredient-table">
                            <thead><tr><th>Ingredient</th><th>Qty</th><th>Unit</th><th>Price/Unit (KES)</th><th>Total (KES)</th></tr></thead>
                            <tbody>`;
            for (let ing of meal.ingredients) {
                const units = ['kg', 'g', 'bunch', 'piece', 'packet', 'cup', 'tablespoon', 'teaspoon', 'liter'];
                const opts = units.map(u => `<option value="${u}" ${ing.unit === u ? 'selected' : ''}>${u}</option>`).join('');
                html += `<tr data-plan-id="${meal.plan_id}" data-ingredient="${esc(ing.raw_name)}" data-unit="${esc(ing.unit)}">
                               <td>${esc(ing.display_name)}</td>
                               <td><input type="number" class="qty-input" value="${ing.quantity.toFixed(2)}" step="0.1" min="0"></td>
                               <td><select class="unit-select">${opts}</select></td>
                               <td><input type="number" class="price-input" value="${ing.price}" step="5" min="0"></td>
                               <td class="ingredient-cost">KES 0.00</td>
                            </tr>`;
            }
            html += `</tbody>
                     </table>
                     <div style="text-align:right;margin-top:.5rem;">
                        <strong>Meal Total:</strong> <span class="meal-total" data-plan-id="${meal.plan_id}">KES 0.00</span>
                     </div>
                     </div>`;
        }
        html += `</div>`;
    }
    document.getElementById('shopping-list-container').innerHTML = html;
    attachEvents();
    updateAllTotals();
    initFilter();
}

function attachEvents() {
    document.querySelectorAll('.price-input').forEach(inp => {
        inp.addEventListener('input', () => {
            const row = inp.closest('tr');
            const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            savePrice(row.dataset.planId, row.dataset.ingredient, row.dataset.unit, parseFloat(inp.value) || 0, qty);
            updateAllTotals();
        });
    });
    document.querySelectorAll('.qty-input').forEach(inp => {
        inp.addEventListener('input', () => {
            const row = inp.closest('tr');
            const price = parseFloat(row.querySelector('.price-input')?.value) || 0;
            savePrice(row.dataset.planId, row.dataset.ingredient, row.dataset.unit, price, parseFloat(inp.value) || 0);
            updateAllTotals();
        });
    });
    document.querySelectorAll('.unit-select').forEach(sel => {
        sel.addEventListener('change', () => {
            const row = sel.closest('tr');
            const newUnit = sel.value;
            row.dataset.unit = newUnit;
            const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            const price = parseFloat(row.querySelector('.price-input')?.value) || 0;
            savePrice(row.dataset.planId, row.dataset.ingredient, newUnit, price, qty);
            updateAllTotals();
        });
    });
}

function savePrice(planId, ingredient, unit, price, quantity) {
    fetch('save_meal_ingredient_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `plan_id=${planId}&ingredient=${encodeURIComponent(ingredient)}&unit=${encodeURIComponent(unit)}&price=${price}&quantity=${quantity}`
    })
    .then(r => r.json())
    .then(d => { if (!d.success) showToast('Error saving price', true); })
    .catch(() => showToast('Network error', true));
}

function updateAllTotals() {
    const dailyTotals = { Monday: 0, Tuesday: 0, Wednesday: 0, Thursday: 0, Friday: 0, Saturday: 0, Sunday: 0 };
    let weekly = 0;
    document.querySelectorAll('.meal-shopping-list').forEach(div => {
        const pid = parseInt(div.dataset.planId);
        const md = mealsData.find(m => m.plan_id == pid);
        if (!md) return;
        let mealTotal = 0;
        div.querySelectorAll('tr[data-plan-id]').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            const price = parseFloat(row.querySelector('.price-input')?.value) || 0;
            const cost = qty * price;
            row.querySelector('.ingredient-cost').textContent = 'KES ' + cost.toFixed(2);
            mealTotal += cost;
        });
        div.querySelector('.meal-total').textContent = 'KES ' + mealTotal.toFixed(2);
        weekly += mealTotal;
        dailyTotals[md.day] += mealTotal;
        const mealCostSpan = document.querySelector(`.meal-cost[data-plan-id="${pid}"]`);
        if (mealCostSpan) mealCostSpan.textContent = 'KES ' + mealTotal.toFixed(2);
    });
    document.getElementById('total-cost').textContent = 'KES ' + weekly.toFixed(2);
    const remaining = weeklyBudget - weekly;
    const remainingSpan = document.getElementById('remaining-budget');
    remainingSpan.textContent = 'KES ' + remaining.toFixed(2);
    remainingSpan.style.color = remaining < 0 ? '#E53935' : '#2E7D32';
    for (let d in dailyTotals) {
        const cell = document.querySelector(`td.daily-cost-cell[data-day="${d}"] strong`);
        if (cell) cell.textContent = 'KES ' + dailyTotals[d].toFixed(2);
    }
}

function saveAllPrices() {
    const promises = [];
    document.querySelectorAll('.price-input').forEach(inp => {
        const row = inp.closest('tr');
        const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        promises.push(
            fetch('save_meal_ingredient_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `plan_id=${row.dataset.planId}&ingredient=${encodeURIComponent(row.dataset.ingredient)}&unit=${encodeURIComponent(row.dataset.unit)}&price=${parseFloat(inp.value) || 0}&quantity=${qty}`
            })
        );
    });
    Promise.all(promises)
        .then(() => showToast('All prices saved'))
        .catch(() => showToast('Error saving some prices', true));
}

function initFilter() {
    const btns = document.querySelectorAll('#dayFilter .filter-btn');
    const groups = document.querySelectorAll('.day-shopping-group');
    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const day = btn.dataset.day;
            groups.forEach(g => {
                g.style.display = (day === 'all' || g.dataset.day === day) ? '' : 'none';
            });
        });
    });
}

let selDay = '', selMeal = '';
function closeModal() { document.getElementById('addModal').style.display = 'none'; }

// Confirmation modal handlers
let pendingRemovalPlanId = null;
let pendingRemoveBtn = null;

function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    pendingRemovalPlanId = null;
    pendingRemoveBtn = null;
}

document.getElementById('confirmYesBtn').addEventListener('click', function() {
    if (pendingRemovalPlanId && pendingRemoveBtn) {
        // Disable button and show loading state
        pendingRemoveBtn.disabled = true;
        pendingRemoveBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';

        // Send removal request
        fetch('remove_from_mealplan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + pendingRemovalPlanId
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('Meal removed!');
                location.reload();
            } else {
                showToast(d.message || 'Error removing meal', true);
                pendingRemoveBtn.disabled = false;
                pendingRemoveBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
                closeConfirmModal();
            }
        })
        .catch(() => {
            showToast('Network error', true);
            pendingRemoveBtn.disabled = false;
            pendingRemoveBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
            closeConfirmModal();
        });
    }
});

document.getElementById('confirmNoBtn').addEventListener('click', closeConfirmModal);

// Close confirm modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('confirmModal');
    if (event.target === modal) {
        closeConfirmModal();
    }
});

document.addEventListener('click', function(e) {
    const addBtn = e.target.closest('.add-btn');
    if (addBtn) {
        e.preventDefault();
        selDay = addBtn.dataset.day;
        selMeal = addBtn.dataset.meal;
        document.getElementById('modalDayMeal').innerHTML = `<strong>${selDay} – ${selMeal}</strong>`;
        document.getElementById('addModal').style.display = 'flex';
        fetch('fetch_recipes.php?category=' + encodeURIComponent(selMeal.toLowerCase()))
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('recipeList');
                container.innerHTML = '';
                if (!data.length) {
                    container.innerHTML = '<p style="color:#888;padding:1rem;">No recipes found. <a href="recipes.php" style="color:#2E7D32;">Browse all recipes</a></p>';
                    return;
                }
                const fallbacks = {
                    breakfast: 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?w=80&h=80&fit=crop',
                    lunch:     'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=80&h=80&fit=crop',
                    dinner:    'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=80&h=80&fit=crop',
                    snack:     'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=80&h=80&fit=crop',
                    default:   'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=80&h=80&fit=crop'
                };
                data.forEach(r => {
                    const cat = (r.category || '').toLowerCase();
                    const fb = fallbacks[cat] || fallbacks.default;
                    const imgSrc = r.image ? r.image : fb;
                    const div = document.createElement('div');
                    div.className = 'recipe-card-modal';
                    div.innerHTML = `
                        <div style="position:relative;width:80px;height:80px;flex-shrink:0;">
                            <img src="${imgSrc}" onerror="this.src='${fb}'"
                                 style="width:80px;height:80px;object-fit:cover;border-radius:12px;">
                        </div>
                        <div style="flex:1;min-width:0;">
                            <h4 style="font-size:.95rem;color:#1B5E20;margin-bottom:.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(r.recipe_name)}</h4>
                            <small style="color:#888;font-size:.78rem;">${esc(r.category || 'Kenyan')} &bull; ${r.servings || 4} servings &bull; ${r.prep_time || 30} mins</small><br>
                            <button class="add-to-plan-btn" data-recipe="${r.recipe_id}" style="margin-top:.5rem;">
                                <i class="fas fa-plus"></i> Add to Plan
                            </button>
                        </div>`;
                    container.appendChild(div);
                });
            })
            .catch(() => document.getElementById('recipeList').innerHTML = '<p style="color:red;padding:1rem;">Error loading recipes.</p>');
    }

    const addToPlan = e.target.closest('.add-to-plan-btn');
    if (addToPlan) {
        addToPlan.disabled = true;
        addToPlan.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
        fetch('add_to_mealplan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `recipe_id=${addToPlan.dataset.recipe}&day=${selDay}&meal=${selMeal}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('Recipe added!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(d.message || 'Error adding recipe', true);
                addToPlan.disabled = false;
                addToPlan.innerHTML = 'Add to Plan';
            }
        })
        .catch(() => {
            showToast('Network error', true);
            addToPlan.disabled = false;
            addToPlan.innerHTML = 'Add to Plan';
        });
    }

    const removeBtn = e.target.closest('.remove-btn');
    if (removeBtn) {
        e.preventDefault();
        pendingRemovalPlanId = removeBtn.dataset.id;
        pendingRemoveBtn = removeBtn;

        // Get meal name from the DOM
        const mealNameElem = removeBtn.closest('td')?.querySelector('.meal-details strong');
        const mealName = mealNameElem ? mealNameElem.innerText : 'this meal';
        document.getElementById('confirmMessage').innerHTML = `Are you sure you want to remove <strong>${esc(mealName)}</strong> from your plan?`;

        document.getElementById('confirmModal').style.display = 'flex';
    }
});

document.getElementById('saveAllPricesBtn').addEventListener('click', saveAllPrices);
window.onclick = function(e) { if (e.target === document.getElementById('addModal')) closeModal(); };
renderShoppingList();

function downloadMealPlan() {
    const originalTitle = document.title;
    const userName = <?= json_encode($_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'User') ?>;
    const weekOf = <?= json_encode(date('d-M-Y', strtotime($week_start))) ?>;
    document.title = 'Meal-Plan_' + userName.replace(/\s+/g, '-') + '_Week-of-' + weekOf;
    window.print();
    setTimeout(() => { document.title = originalTitle; }, 2000);
}
</script>
</body>
</html>