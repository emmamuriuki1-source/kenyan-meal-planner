<?php
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';
requireLogin();

$uid        = $_SESSION['user_id'];
$week_start = isset($_GET['week']) ? date('Y-m-d', strtotime($_GET['week'])) : date('Y-m-d', strtotime('monday this week'));

// Aggregate ingredients from this week's meal plan
$items = $conn->query("
    SELECT
        i.name AS item,
        i.unit,
        SUM(i.quantity) AS total_qty,
        COALESCE(mp.price_per_unit, 0) AS price_per_unit,
        COALESCE(SUM(i.quantity) * mp.price_per_unit, 0) AS estimated_cost
    FROM meal_plans pl
    JOIN ingredients i ON i.recipe_id = pl.recipe_id
    LEFT JOIN market_prices mp ON mp.item_name = i.name AND mp.user_id = $uid
    WHERE pl.user_id = $uid AND pl.week_start = '$week_start'
    GROUP BY i.name, i.unit, mp.price_per_unit
    ORDER BY i.name
");

$shopping_items = [];
$total_cost     = 0;
while ($row = $items->fetch_assoc()) {
    $shopping_items[] = $row;
    $total_cost += (float)$row['estimated_cost'];
}

// Budget for this week
$b = $conn->query("SELECT total_budget FROM budgets WHERE user_id=$uid AND week_start='$week_start' LIMIT 1")->fetch_assoc();
$weekly_budget = $b ? (float)$b['total_budget'] : 0;
$remaining     = $weekly_budget - $total_cost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping List – MealPlanner KE</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include APP_PATH . '/includes/navbar.php'; ?>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <h1 class="page-title" style="margin:0;">📋 Shopping List</h1>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <span style="color:#666; font-size:0.9rem; align-self:center;">
                Week of <?= date('d M Y', strtotime($week_start)) ?>
            </span>
            <button class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
            <a href="meals.php?week=<?= $week_start ?>" class="btn btn-outline btn-sm">← Back to Planner</a>
        </div>
    </div>

    <?php if (empty($shopping_items)): ?>
        <div class="alert alert-info">
            No meals planned for this week yet.
            <a href="meals.php" style="color:#1565c0; font-weight:600;">Plan your meals first →</a>
        </div>
    <?php else: ?>

    <div class="grid-2">
        <!-- Shopping List -->
        <div class="card">
            <div class="card-title">Items to Buy</div>
            <div id="shopping-list">
                <?php foreach ($shopping_items as $i => $item): ?>
                <div class="shopping-item" id="item-<?= $i ?>">
                    <input type="checkbox" id="chk-<?= $i ?>"
                        onchange="toggleItem(<?= $i ?>)">
                    <label for="chk-<?= $i ?>">
                        <strong><?= e($item['item']) ?></strong>
                        – <?= number_format($item['total_qty'], 2) ?> <?= e($item['unit']) ?>
                        <?php if (!$item['price_per_unit']): ?>
                            <span style="color:#f9a825; font-size:0.8rem;">(no price set)</span>
                        <?php endif; ?>
                    </label>
                    <span class="item-cost">
                        <?= $item['estimated_cost'] > 0 ? formatKES($item['estimated_cost']) : '–' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="border-top:2px solid #2e7d32; margin-top:16px; padding-top:12px; display:flex; justify-content:space-between; font-weight:700; font-size:1rem;">
                <span>Total Estimated Cost</span>
                <span style="color:#2e7d32;"><?= formatKES($total_cost) ?></span>
            </div>
        </div>

        <!-- Budget Summary -->
        <div>
            <div class="card">
                <div class="card-title">Budget Summary</div>
                <?php if ($weekly_budget > 0): ?>
                    <?php $pct = min(100, round(($total_cost / $weekly_budget) * 100)); ?>
                    <?php $bar_class = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : ''); ?>
                    <div class="budget-bar-wrap">
                        <div class="budget-bar-label">
                            <span>Estimated: <?= formatKES($total_cost) ?></span>
                            <span>Budget: <?= formatKES($weekly_budget) ?></span>
                        </div>
                        <div class="budget-bar">
                            <div class="budget-bar-fill <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <div style="margin-top:16px; text-align:center;">
                        <div style="font-size:1.4rem; font-weight:700; color:<?= $remaining >= 0 ? '#2e7d32' : '#c62828' ?>;">
                            <?= formatKES(abs($remaining)) ?>
                        </div>
                        <div style="color:#666; font-size:0.85rem;">
                            <?= $remaining >= 0 ? 'Under budget' : 'Over budget' ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No budget set. <a href="budget.php" style="color:#1565c0;">Set a budget →</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Missing Prices Alert -->
            <?php $missing = array_filter($shopping_items, fn($i) => !$i['price_per_unit']); ?>
            <?php if (!empty($missing)): ?>
            <div class="card">
                <div class="card-title" style="color:#e65100;">⚠ Missing Prices</div>
                <p style="font-size:0.85rem; color:#666; margin-bottom:10px;">
                    These items have no price set. Add them to get accurate estimates.
                </p>
                <?php foreach ($missing as $m): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #eee;">
                        <span style="font-size:0.9rem;"><?= e($m['item']) ?></span>
                        <a href="market_prices.php" class="btn btn-outline btn-sm">Add Price</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Cost Breakdown Chart -->
            <?php if (count($shopping_items) > 0): ?>
            <div class="card">
                <div class="card-title">Cost Breakdown</div>
                <canvas id="costChart" height="220"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<footer>MealPlanner KE &copy; <?= date('Y') ?></footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleItem(i) {
    const el  = document.getElementById('item-' + i);
    const chk = document.getElementById('chk-' + i);
    el.classList.toggle('checked', chk.checked);
}

<?php if (!empty($shopping_items)): ?>
const chartItems = <?= json_encode(array_column($shopping_items, 'item')) ?>;
const chartCosts = <?= json_encode(array_map(fn($i) => round((float)$i['estimated_cost'], 2), $shopping_items)) ?>;

const colors = chartItems.map((_, i) => `hsl(${(i * 47) % 360}, 60%, 55%)`);

new Chart(document.getElementById('costChart'), {
    type: 'pie',
    data: {
        labels: chartItems,
        datasets: [{
            data: chartCosts,
            backgroundColor: colors,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ` KES ${ctx.parsed.toFixed(2)}`
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<style>
@media print {
    .navbar, footer, .btn, canvas { display: none !important; }
    .grid-2 { display: block; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>
</body>
</html>
