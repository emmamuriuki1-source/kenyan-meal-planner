<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$uid        = $_SESSION['user_id'];
$days       = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$slots      = ['Breakfast','Lunch','Dinner','Fruits'];
$week_start = date('Y-m-d', strtotime('monday this week'));

// Household
$hp = $conn->query("SELECT * FROM household_profiles WHERE user_id=$uid");
$hh = ($hp && $hp->num_rows > 0) ? $hp->fetch_assoc() : [];
$adults         = (int)($hh['adults']        ?? 1);
$children       = (int)($hh['children']      ?? 0);
$household_size = $adults + $children;

// Budget: prefer budgets table (current week), fall back to household_profiles.weekly_budget
$week_start = date('Y-m-d', strtotime('monday this week'));
$brow = $conn->query("SELECT total_budget FROM budgets WHERE user_id=$uid AND week_start='$week_start' LIMIT 1");
if ($brow && $brow->num_rows > 0) {
    $weekly_budget = (float)$brow->fetch_assoc()['total_budget'];
} else {
    $weekly_budget = (float)($hh['weekly_budget'] ?? 0);
}

function detectFoodGroups($str) {
    $g = ['Veg'=>false,'Fruit'=>false,'Dairy'=>false,'Protein'=>false,'Carbs'=>false];
    $s = strtolower($str);
    foreach (['sukuma','kale','spinach','tomato','onion','carrot','cabbage','coriander','capsicum','pumpkin','amaranth'] as $k) if (strpos($s,$k)!==false){$g['Veg']=true;break;}
    foreach (['banana','mango','apple','orange','pawpaw','papaya','pineapple','watermelon','lemon','avocado'] as $k) if (strpos($s,$k)!==false){$g['Fruit']=true;break;}
    foreach (['milk','yogurt','yoghurt','cheese','ghee','butter','cream'] as $k) if (strpos($s,$k)!==false){$g['Dairy']=true;break;}
    foreach (['beef','chicken','fish','egg','bean','lentil','liver','goat','meat','sausage','ndengu','pea','green gram'] as $k) if (strpos($s,$k)!==false){$g['Protein']=true;break;}
    foreach (['ugali','rice','potato','chapati','bread','maize','wheat','pasta','noodle','cassava','mandazi','porridge','flour'] as $k) if (strpos($s,$k)!==false){$g['Carbs']=true;break;}
    return $g;
}

// ── Fetch meal plans with costs from meal_ingredient_prices ───────────────
$stmt = $conn->prepare("
    SELECT mp.id AS plan_id, mp.day_of_week AS day, mp.meal_type,
           r.id AS recipe_id, r.name AS recipe_name, r.category, r.image, r.servings AS recipe_servings,
           mp.servings AS planned_servings
    FROM meal_plans mp
    JOIN recipes r ON mp.recipe_id = r.id
    WHERE mp.user_id = ?
    ORDER BY FIELD(mp.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
             FIELD(mp.meal_type,'Breakfast','Lunch','Dinner','Snack','Fruits')
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();

$mealPlan    = [];
$mealCount   = 0;
$totalCost   = 0;
$dailyCosts  = array_fill_keys($days, 0);
$mealsData   = [];
$dailyGroups = [];
foreach ($days as $d) $dailyGroups[$d] = ['Veg'=>false,'Fruit'=>false,'Dairy'=>false,'Protein'=>false,'Carbs'=>false,'meals'=>0];

while ($row = $res->fetch_assoc()) {
    $day  = $row['day'];
    // Map DB value 'Snack' to display value 'Fruits'
    $meal = ($row['meal_type'] === 'Snack') ? 'Fruits' : $row['meal_type'];
    if (!in_array($day,$days) || !in_array($meal,$slots)) continue;

    // Scale factor: planned servings / recipe servings
    $planned_servings = max(1, (int)($row['planned_servings'] ?? $household_size));
    $recipe_servings  = max(1, (int)($row['recipe_servings'] ?? 4));
    $scale_factor = $planned_servings / $recipe_servings;

    // Cost = sum of (price_per_unit * custom_quantity) for all saved ingredients
    // This matches exactly what meals.php shopping list shows
    $mealCost = 0;
    $costQ = $conn->query("SELECT COALESCE(SUM(price_per_unit * custom_quantity),0) AS total
                           FROM meal_ingredient_prices
                           WHERE plan_id={$row['plan_id']}");
    if ($costQ) $mealCost = (float)$costQ->fetch_assoc()['total'];

    // Get ingredient names for food group detection only
    $ingNames = [];
    $ir = $conn->query("SELECT name FROM ingredients WHERE recipe_id={$row['recipe_id']}");
    if ($ir) while ($irow = $ir->fetch_assoc()) {
        $n = strtolower(trim($irow['name']));
        if (substr($n,-1)==='s' && substr($n,-2)!=='ss') $n = substr($n,0,-1);
        $ingNames[] = $n;
    }

    // Detect food groups from ingredient names + recipe name
    $detect = implode(' ', $ingNames) . ' ' . $row['recipe_name'] . ' ' . ($row['category'] ?? '');
    $groups = detectFoodGroups($detect);

    $mealPlan[$day][$meal] = array_merge($row, ['meal_cost' => $mealCost]);
    $mealCount++;
    $totalCost += $mealCost;
    $dailyCosts[$day] += $mealCost;
    foreach ($groups as $g => $p) if ($p) $dailyGroups[$day][$g] = true;
    $dailyGroups[$day]['meals']++;

    $mealsData[] = [
        'plan_id'          => $row['plan_id'],
        'day'              => $day,
        'meal'             => $meal,
        'recipe_name'      => $row['recipe_name'],
        'scaled_cost'      => $mealCost,
        'servings'         => $planned_servings,
        'cost_per_serving' => $mealCost > 0 ? $mealCost / $planned_servings : 0,
    ];
}
$stmt->close();

$remaining = $weekly_budget - $totalCost;
$overspent  = $remaining < 0;
$pct_used   = $weekly_budget > 0 ? min(100, ($totalCost / $weekly_budget) * 100) : 0;

// --- Find the single most expensive meal (by scaled_cost) ---
$most_expensive_meal = null;
if (!empty($mealsData)) {
    usort($mealsData, fn($a, $b) => $b['scaled_cost'] <=> $a['scaled_cost']);
    $most_expensive_meal = $mealsData[0];
}

// Daily nutrient presence for chart
$dailyPresence = [];
foreach ($days as $d) {
    $dailyPresence[$d] = ['protein'=>0,'carbs'=>0,'vegetables'=>0,'fruits'=>0,'dairy'=>0];
    if (isset($mealPlan[$d])) {
        foreach ($mealPlan[$d] as $m) {
            $ings2 = [];
            $ir2 = $conn->query("SELECT name FROM ingredients WHERE recipe_id={$m['recipe_id']}");
            if ($ir2) while ($r2 = $ir2->fetch_assoc()) $ings2[] = $r2['name'];
            $detect2 = implode(' ', $ings2) . ' ' . $m['recipe_name'];
            $g2 = detectFoodGroups($detect2);
            if ($g2['Veg'])     $dailyPresence[$d]['vegetables']++;
            if ($g2['Fruit'])   $dailyPresence[$d]['fruits']++;
            if ($g2['Dairy'])   $dailyPresence[$d]['dairy']++;
            if ($g2['Protein']) $dailyPresence[$d]['protein']++;
            if ($g2['Carbs'])   $dailyPresence[$d]['carbs']++;
        }
    }
}

// Budget alternatives
$alternatives = [];
$ar = $conn->query("SELECT * FROM budget_alternatives");
if ($ar) while ($row = $ar->fetch_assoc()) $alternatives[$row['expensive_ingredient']] = explode(',', $row['cheap_alternatives']);

$suggestions = [];
if ($overspent) {
    foreach ($mealsData as $m) {
        $ings3 = [];
        $ir3 = $conn->query("SELECT name FROM ingredients WHERE recipe_id=(SELECT recipe_id FROM meal_plans WHERE id={$m['plan_id']})");
        if ($ir3) while ($r3 = $ir3->fetch_assoc()) $ings3[] = strtolower($r3['name']);
        foreach ($alternatives as $exp => $cheap) {
            foreach ($ings3 as $ing) {
                if (stripos($ing, $exp) !== false) {
                    $suggestions[] = 'In "'.$m['recipe_name'].'", replace '.ucfirst($exp).' with '.implode(' or ', array_map('trim',$cheap)).' to save money.';
                    break;
                }
            }
        }
    }
    $suggestions = array_slice(array_unique($suggestions), 0, 5);
}

// Nutrition suggestions
$nutritionSuggestions = [];
if ($mealCount > 0) {
    $pr = array_sum(array_column($dailyPresence,'protein'))/$mealCount;
    $vr = array_sum(array_column($dailyPresence,'vegetables'))/$mealCount;
    $fr = array_sum(array_column($dailyPresence,'fruits'))/$mealCount;
    $cr = array_sum(array_column($dailyPresence,'carbs'))/$mealCount;
    if ($pr < 0.3) $nutritionSuggestions[] = 'Add more protein: beans, meat, eggs, fish ('.round($pr*100).'% of meals)';
    if ($vr < 0.3) $nutritionSuggestions[] = 'Add more vegetables: sukuma, spinach, cabbage ('.round($vr*100).'% of meals)';
    if ($fr < 0.2) $nutritionSuggestions[] = 'Add fruits: banana, mango, apple ('.round($fr*100).'% of meals)';
    if ($cr > 0.8) $nutritionSuggestions[] = 'Reduce high-carb meals: ugali, rice, potatoes ('.round($cr*100).'% of meals)';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Budget &amp; Nutrition Report – Kenyan Meal Planner</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#fef9e7;color:#2d3e2f;display:flex;flex-direction:column;min-height:100vh;}
.header{background:linear-gradient(135deg,#2E7D32,#1B5E20);color:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;}
.header h1{font-size:1.8rem;}.header h1 i{margin-right:10px;color:#FB8C00;}
.header .user-info{display:flex;align-items:center;gap:1rem;}
.header a{color:#fff;text-decoration:none;padding:.5rem 1rem;border-radius:30px;background:rgba(255,255,255,.15);transition:.3s;}
.header a:hover{background:#FB8C00;}
.container{display:flex;flex:1;}
.sidebar{width:250px;background:#1B5E20;padding-top:2rem;flex-shrink:0;}
.sidebar ul{list-style:none;}.sidebar li{margin-bottom:.5rem;}
.sidebar a{display:block;padding:.8rem 1.5rem;color:#f0f7e6;text-decoration:none;transition:.3s;border-left:4px solid transparent;}
.sidebar a i{margin-right:10px;color:#FB8C00;width:24px;}
.sidebar a:hover,.sidebar a.active{background:rgba(251,140,0,.2);border-left-color:#FB8C00;color:#fff;}
.sidebar a.active{background:#FB8C00;color:#fff;}
.main{flex:1;padding:1.5rem;overflow-x:auto;}
.card{background:#fff;border-radius:16px;padding:1.2rem;margin-bottom:1.2rem;box-shadow:0 4px 12px rgba(0,0,0,.08);border:1px solid #e0e0e0;position:relative;overflow:hidden;}
.card::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,#2E7D32,#FB8C00);}
.card h3{color:#1B5E20;margin-bottom:1rem;display:flex;align-items:center;gap:8px;font-size:1.1rem;}
.card h3 i{color:#FB8C00;}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1rem;}
.summary-card{background:#f9fff9;padding:1rem;border-radius:12px;text-align:center;border:1px solid #c8e6c9;}
.summary-card h4{font-size:.8rem;color:#5f6b5f;margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.5px;}
.summary-card p{font-size:1.4rem;font-weight:700;color:#2E7D32;}
.summary-card p.neg{color:#E53935;}
.progress-bar{background:#e0e0e0;border-radius:30px;height:18px;overflow:hidden;margin:1rem 0 .5rem;}
.progress-fill{height:100%;background:#2E7D32;text-align:center;color:#fff;font-size:.78rem;line-height:18px;transition:width .5s;}
.progress-fill.warning{background:#FB8C00;}.progress-fill.danger{background:#E53935;}
.status-msg{text-align:right;font-size:.88rem;margin-top:.4rem;}
.pos{color:#2E7D32;font-weight:600;}.neg{color:#E53935;font-weight:600;}
.meal-table{width:100%;border-collapse:collapse;margin-top:.8rem;}
.meal-table th{background:#2E7D32;color:#fff;padding:.7rem;font-size:.88rem;text-align:center;}
.meal-table td{padding:.7rem;border-bottom:1px solid #f0f0f0;vertical-align:middle;text-align:center;font-size:.88rem;}
.meal-table td:first-child{text-align:left;font-weight:600;}
.meal-card-r{display:flex;align-items:center;gap:8px;}
.meal-card-r img{width:44px;height:44px;object-fit:cover;border-radius:8px;}
.checkmark{color:#2E7D32;font-size:1.2rem;}.crossmark{color:#E53935;font-size:1.2rem;}
.chart-container{position:relative;height:280px;width:100%;margin-top:1rem;}
.tips-list{list-style:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.8rem;margin-top:1rem;}
.tips-list li{background:#f1f8e9;padding:.8rem;border-radius:12px;border-left:3px solid #2E7D32;font-size:.88rem;}
.week-info{background:#e8f5e9;border-radius:30px;padding:.5rem 1.2rem;font-size:.85rem;display:inline-flex;align-items:center;gap:8px;margin-bottom:1.2rem;border:1px solid #2E7D32;}
footer{background:#1B5E20;color:#fff;text-align:center;padding:.8rem;margin-top:auto;font-size:.8rem;}
@media(max-width:768px){.container{flex-direction:column;}.sidebar{width:100%;}.main{padding:1rem;}.meal-table{display:block;overflow-x:auto;}}
@media print{
    .sidebar,.header .user-info a,.header a,footer,button{display:none!important;}
    body{background:#fff!important;}
    .header{background:#1B5E20!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .card{break-inside:avoid;box-shadow:none!important;border:1px solid #ccc!important;}
    .progress-fill{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .chart-container{height:220px!important;}
    .container{display:block!important;}
    .main{padding:0!important;}
}
</style>
</head>
<body>
<div class="header">
    <h1><i class="fas fa-utensils"></i> Kenyan Meal Planner</h1>
    <div class="user-info">
        <span><i class="fas fa-user-circle"></i> <?= e($_SESSION['user_name'] ?? 'User') ?></span>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>
<div class="container">
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="recipes.php"><i class="fas fa-book-open"></i> Recipes</a></li>
            <li><a href="meals.php"><i class="fas fa-calendar-week"></i> Meal Planner</a></li>
            <li><a href="report.php" class="active"><i class="fas fa-chart-line"></i> Report</a></li>
        </ul>
    </div>
    <div class="main">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;">
            <h2 style="color:#1B5E20;font-size:1.4rem;"><i class="fas fa-chart-line" style="color:#FB8C00;"></i> Budget &amp; Nutrition Report</h2>
            <button onclick="downloadReport()" style="background:#2E7D32;color:#fff;border:none;padding:.5rem 1rem;border-radius:30px;cursor:pointer;font-size:.88rem;"><i class="fas fa-download"></i> Download Report</button>
        </div>

        <div class="week-info">
            <i class="fas fa-calendar"></i>
            Week of <?= date('d M Y', strtotime($week_start)) ?> &nbsp;|&nbsp;
            Household: <?= $household_size ?> <?= $household_size == 1 ? 'person' : 'people' ?>
        </div>

        <!-- Budget Summary -->
        <div class="card">
            <h3><i class="fas fa-wallet"></i> Budget Summary</h3>
            <div class="summary-grid">
                <div class="summary-card"><h4>Weekly Budget</h4><p>KES <?= number_format($weekly_budget,2) ?></p></div>
                <div class="summary-card"><h4>Meals Planned</h4><p><?= $mealCount ?> / 21</p></div>
                <div class="summary-card"><h4>Total Cost</h4><p>KES <?= number_format($totalCost,2) ?></p></div>
                <div class="summary-card"><h4>Remaining</h4>
                    <p class="<?= $remaining < 0 ? 'neg' : '' ?>">KES <?= number_format(abs($remaining),2) ?><?= $remaining < 0 ? ' (over)' : '' ?></p>
                </div>
            </div>
            <?php if ($weekly_budget > 0): ?>
            <div class="progress-bar">
                <div class="progress-fill <?= $pct_used>=100?'danger':($pct_used>=80?'warning':'') ?>" style="width:<?= $pct_used ?>%;"><?= round($pct_used) ?>%</div>
            </div>
            <div class="status-msg">
                <?php if ($overspent): ?>
                    <span class="neg"><i class="fas fa-exclamation-triangle"></i> Over budget by KES <?= number_format(abs($remaining),2) ?></span>
                <?php else: ?>
                    <span class="pos"><i class="fas fa-check-circle"></i> Within budget — KES <?= number_format($remaining,2) ?> remaining</span>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p style="color:#888;font-size:.88rem;margin-top:.5rem;"><i class="fas fa-info-circle"></i> No weekly budget set. <a href="meals.php" style="color:#2E7D32;">Update household profile</a> to set one.</p>
            <?php endif; ?>
        </div>

        <!-- Weekly Meal Plan with costs -->
        <div class="card">
            <h3><i class="fas fa-calendar-week"></i> Weekly Meal Plan (Costs from Shopping List)</h3>
            <div style="overflow-x:auto;">
            <table class="meal-table">
                <thead><th>Day</th><th>Breakfast</th><th>Lunch</th><th>Dinner</th><th>Fruits</th><th>Daily Cost</th> </thead>
                <tbody>
                <?php foreach ($days as $day): ?>
                 <tr>
                    <td><?= $day ?></td>
                    <?php foreach ($slots as $meal): ?>
                    <?php if (isset($mealPlan[$day][$meal])): $m = $mealPlan[$day][$meal];
                        $img = (!empty($m['image'])) ? $m['image'] : "https://images.unsplash.com/photo-1512058564366-18510be2db19?w=44&h=44&fit=crop";
                    ?>
                    <td>
                        <div class="meal-card-r">
                            <img src="<?= e($img) ?>" alt="" onerror="this.src='https://images.unsplash.com/photo-1512058564366-18510be2db19?w=44&h=44&fit=crop'">
                            <div>
                                <strong style="font-size:.85rem;"><?= e($m['recipe_name']) ?></strong><br>
                                <small style="color:#FB8C00;">KES <?= number_format($m['meal_cost'],2) ?></small>
                            </div>
                        </div>
                    </td>
                    <?php else: ?><td style="color:#ccc;font-size:.8rem;">—</td><?php endif; ?>
                    <?php endforeach; ?>
                    <td><strong>KES <?= number_format($dailyCosts[$day],2) ?></strong></td>
                 </tr>
                <?php endforeach; ?>
                </tbody>
             </table>
            </div>
            <p style="font-size:.78rem;color:#888;margin-top:.8rem;"><i class="fas fa-info-circle"></i> Costs reflect prices entered in the Shopping List section of the Meal Planner.</p>
        </div>

        <!-- Most Expensive Meal (Single) -->
        <?php if ($most_expensive_meal !== null): ?>
        <div class="card">
            <h3><i class="fas fa-coins"></i> Most Expensive Meal</h3>
            <div style="overflow-x:auto;">
            <table class="meal-table">
                <thead><tr><th>Day</th><th>Meal</th><th>Recipe</th><th>Cost (KES)</th><th>Cost/Serving</th></tr></thead>
                <tbody>
                 <tr>
                    <td><?= e($most_expensive_meal['day']) ?></td>
                    <td><?= e($most_expensive_meal['meal']) ?></td>
                    <td><?= e($most_expensive_meal['recipe_name']) ?></td>
                    <td style="color:#E65100;font-weight:700;">KES <?= number_format($most_expensive_meal['scaled_cost'],2) ?></td>
                    <td>KES <?= number_format($most_expensive_meal['cost_per_serving'],2) ?></td>
                 </tr>
                </tbody>
             </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Budget suggestions -->
        <?php if ($overspent && !empty($suggestions)): ?>
        <div class="card" style="background:#f1f8e9;">
            <h3><i class="fas fa-lightbulb"></i> Budget-Saving Suggestions</h3>
            <ul style="margin-left:1.5rem;">
                <?php foreach ($suggestions as $s): ?><li style="margin-bottom:.4rem;font-size:.88rem;"><i class="fas fa-check-circle" style="color:#2E7D32;"></i> <?= e($s) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Nutritional Summary table -->
        <div class="card">
            <h3><i class="fas fa-heartbeat"></i> Nutritional Summary</h3>
            <div style="overflow-x:auto;">
            <table class="meal-table">
                <thead><tr><th>Day</th><th>🥦 Veg</th><th>🍎 Fruit</th><th>🥛 Dairy</th><th>🍗 Protein</th><th>🌾 Carbs</th><th>🍓 Fruits Slot</th></tr></thead>
                <tbody>
                <?php foreach ($days as $day): $g = $dailyGroups[$day]; ?>
                 <tr>
                    <td><?= $day ?></td>
                    <td><?= $g['Veg']     ? '<i class="fas fa-check-circle checkmark"></i>' : '<i class="fas fa-times-circle crossmark"></i>' ?></td>
                    <td><?= $g['Fruit']   ? '<i class="fas fa-check-circle checkmark"></i>' : '<i class="fas fa-times-circle crossmark"></i>' ?></td>
                    <td><?= $g['Dairy']   ? '<i class="fas fa-check-circle checkmark"></i>' : '<i class="fas fa-times-circle crossmark"></i>' ?></td>
                    <td><?= $g['Protein'] ? '<i class="fas fa-check-circle checkmark"></i>' : '<i class="fas fa-times-circle crossmark"></i>' ?></td>
                    <td><?= $g['Carbs']   ? '<i class="fas fa-check-circle checkmark"></i>' : '<i class="fas fa-times-circle crossmark"></i>' ?></td>
                    <td><?= (isset($mealPlan[$day]['Fruits']) || isset($mealPlan[$day]['Snack'])) ? '<i class="fas fa-check-circle checkmark"></i>' : '<i class="fas fa-times-circle crossmark"></i>' ?></td>
                 </tr>
                <?php endforeach; ?>
                </tbody>
             </table>
            </div>
        </div>

        <!-- Daily Nutrient Presence Chart -->
        <div class="card">
            <h3><i class="fas fa-chart-bar"></i> Daily Nutrient Presence</h3>
            <p style="font-size:.82rem;color:#666;margin-bottom:.5rem;">Number of meals containing each food group per day</p>
            <div class="chart-container"><canvas id="nutrientChart"></canvas></div>
        </div>

        <!-- Nutrition suggestions -->
        <?php if (!empty($nutritionSuggestions)): ?>
        <div class="card">
            <h3><i class="fas fa-lightbulb"></i> Nutrition Suggestions</h3>
            <ul style="margin-left:1.5rem;">
                <?php foreach ($nutritionSuggestions as $s): ?><li style="margin-bottom:.4rem;font-size:.88rem;"><i class="fas fa-apple-alt" style="color:#2E7D32;"></i> <?= e($s) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Healthy Tips -->
        <div class="card">
            <h3><i class="fas fa-leaf"></i> Healthy Kenyan Eating Tips</h3>
            <ul class="tips-list">
                <li><strong>Staples:</strong> Ugali, githeri, sweet potatoes, whole-wheat chapati.</li>
                <li><strong>Proteins:</strong> Beans, green grams, lentils, omena, eggs, soya.</li>
                <li><strong>Vegetables:</strong> Sukuma wiki, spinach, cabbage, managu, terere.</li>
                <li><strong>Fruits:</strong> Bananas, mangoes, oranges, pawpaw, avocado.</li>
                <li><strong>Dairy:</strong> Fermented milk, yogurt, fresh milk.</li>
                <li><strong>Tip:</strong> Buy seasonal produce, visit local markets, cook in bulk.</li>
            </ul>
        </div>
    </div>
</div>

<footer><i class="fas fa-leaf"></i> &copy; <?= date('Y') ?> Kenyan Meal Planner | Costs based on prices entered in the Meal Planner</footer>

<script>
function downloadReport() {
    const originalTitle = document.title;
    const userName = <?= json_encode($_SESSION['user_name'] ?? 'User') ?>;
    const weekOf = <?= json_encode(date('d-M-Y', strtotime($week_start))) ?>;
    document.title = 'Meal-Plan-Report_' + userName.replace(/\s+/g, '-') + '_' + weekOf;
    window.print();
    setTimeout(() => { document.title = originalTitle; }, 2000);
}

new Chart(document.getElementById('nutrientChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($days) ?>,
        datasets: [
            {label:'Protein',   data:<?= json_encode(array_column($dailyPresence,'protein'))   ?>, backgroundColor:'#2E7D32'},
            {label:'Vegetables',data:<?= json_encode(array_column($dailyPresence,'vegetables')) ?>, backgroundColor:'#FB8C00'},
            {label:'Fruits',    data:<?= json_encode(array_column($dailyPresence,'fruits'))    ?>, backgroundColor:'#FBC02D'},
            {label:'Carbs',     data:<?= json_encode(array_column($dailyPresence,'carbs'))     ?>, backgroundColor:'#7B1FA2'},
            {label:'Dairy',     data:<?= json_encode(array_column($dailyPresence,'dairy'))     ?>, backgroundColor:'#0288D1'}
        ]
    },
    options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,precision:0,title:{display:true,text:'Meals with food group'}}},plugins:{legend:{position:'bottom'}}}
});
</script>
</body>
</html>
