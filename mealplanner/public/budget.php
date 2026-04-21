<?php
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';
requireLogin();

$uid        = $_SESSION['user_id'];
$week_start = date('Y-m-d', strtotime('monday this week'));
$success    = '';
$error      = '';

// Save budget
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $total_budget = (float)($_POST['total_budget'] ?? 0);
    $ws           = $_POST['week_start'] ?? $week_start;
    $ws           = date('Y-m-d', strtotime($ws));

    if ($total_budget > 0) {
        $check = $conn->prepare("SELECT id FROM budgets WHERE user_id=? AND week_start=?");
        $check->bind_param('is', $uid, $ws);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $stmt = $conn->prepare("UPDATE budgets SET total_budget=? WHERE id=?");
            $stmt->bind_param('di', $total_budget, $existing['id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO budgets (user_id, week_start, total_budget) VALUES (?,?,?)");
            $stmt->bind_param('isd', $uid, $ws, $total_budget);
        }
        $stmt->execute();
        $success = 'Budget saved for week of ' . date('d M Y', strtotime($ws));
    } else {
        $error = 'Please enter a valid budget amount.';
    }
}

// Fetch budgets (last 8 weeks)
$budgets = $conn->query("
    SELECT b.*, 
        COALESCE((
            SELECT SUM(i.quantity * mp.price_per_unit)
            FROM meal_plans pl
            JOIN ingredients i ON i.recipe_id = pl.recipe_id
            LEFT JOIN market_prices mp ON mp.item_name = i.name AND mp.user_id = $uid
            WHERE pl.user_id = $uid AND pl.week_start = b.week_start
        ), 0) AS estimated_spend
    FROM budgets b
    WHERE b.user_id = $uid
    ORDER BY b.week_start DESC
    LIMIT 8
");

$budget_rows = [];
while ($row = $budgets->fetch_assoc()) $budget_rows[] = $row;

// Chart data
$chart_weeks    = [];
$chart_budgets  = [];
$chart_spending = [];
foreach (array_reverse($budget_rows) as $b) {
    $chart_weeks[]    = date('d M', strtotime($b['week_start']));
    $chart_budgets[]  = (float)$b['total_budget'];
    $chart_spending[] = round((float)$b['estimated_spend'], 2);
}

// Current week budget
$current_budget = null;
foreach ($budget_rows as $b) {
    if ($b['week_start'] === $week_start) { $current_budget = $b; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget – MealPlanner KE</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include APP_PATH . '/includes/navbar.php'; ?>

<div class="container">
    <h1 class="page-title">💰 Budget Management</h1>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="grid-2">
        <!-- Set Budget Form -->
        <div class="card">
            <div class="card-title">Set Weekly Food Budget</div>
            <form method="POST">
                <div class="form-group">
                    <label>Week Starting</label>
                    <input type="date" name="week_start"
                        value="<?= $week_start ?>"
                        min="<?= date('Y-m-d', strtotime('-4 weeks')) ?>">
                </div>
                <div class="form-group">
                    <label>Total Budget (KES)</label>
                    <input type="number" name="total_budget" required step="50" min="0"
                        placeholder="e.g. 3500"
                        value="<?= $current_budget ? $current_budget['total_budget'] : '' ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Save Budget</button>
            </form>
        </div>

        <!-- Current Week Summary -->
        <div class="card">
            <div class="card-title">This Week's Summary</div>
            <?php if ($current_budget): ?>
                <?php
                    $spend = (float)$current_budget['estimated_spend'];
                    $budget = (float)$current_budget['total_budget'];
                    $remaining = $budget - $spend;
                    $pct = $budget > 0 ? min(100, round(($spend / $budget) * 100)) : 0;
                    $bar_class = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : '');
                ?>
                <div style="margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span>Budget: <strong><?= formatKES($budget) ?></strong></span>
                        <span>Spent: <strong><?= formatKES($spend) ?></strong></span>
                    </div>
                    <div class="budget-bar">
                        <div class="budget-bar-fill <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div style="text-align:right; font-size:0.85rem; margin-top:4px; color:#666;"><?= $pct ?>% used</div>
                </div>
                <div class="stat-card <?= $remaining >= 0 ? '' : 'orange' ?>" style="border-radius:8px;">
                    <div class="stat-value" style="font-size:1.5rem;"><?= formatKES(abs($remaining)) ?></div>
                    <div class="stat-label"><?= $remaining >= 0 ? 'Remaining Budget' : 'Over Budget' ?></div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No budget set for this week. Set one on the left.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Budget vs Spending Chart -->
    <?php if (!empty($chart_weeks)): ?>
    <div class="card">
        <div class="card-title">Budget vs Estimated Spending (Last 8 Weeks)</div>
        <canvas id="budgetChart" height="120"></canvas>
    </div>
    <?php endif; ?>

    <!-- Budget History Table -->
    <div class="card">
        <div class="card-title">Budget History</div>
        <?php if (!empty($budget_rows)): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Week Starting</th>
                        <th>Budget (KES)</th>
                        <th>Est. Spend (KES)</th>
                        <th>Remaining (KES)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budget_rows as $b): ?>
                    <?php
                        $sp  = (float)$b['estimated_spend'];
                        $bg  = (float)$b['total_budget'];
                        $rem = $bg - $sp;
                        $pct = $bg > 0 ? round(($sp / $bg) * 100) : 0;
                    ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($b['week_start'])) ?></td>
                        <td><?= number_format($bg, 2) ?></td>
                        <td><?= number_format($sp, 2) ?></td>
                        <td style="color:<?= $rem >= 0 ? '#2e7d32' : '#c62828' ?>; font-weight:600;">
                            <?= number_format(abs($rem), 2) ?> <?= $rem < 0 ? '(over)' : '' ?>
                        </td>
                        <td>
                            <?php if ($pct >= 90): ?>
                                <span style="color:#c62828; font-weight:600;">⚠ <?= $pct ?>%</span>
                            <?php elseif ($pct >= 70): ?>
                                <span style="color:#f9a825; font-weight:600;">⚡ <?= $pct ?>%</span>
                            <?php else: ?>
                                <span style="color:#2e7d32; font-weight:600;">✓ <?= $pct ?>%</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-info">No budget history yet.</div>
        <?php endif; ?>
    </div>
</div>

<footer>MealPlanner KE &copy; <?= date('Y') ?></footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($chart_weeks)): ?>
new Chart(document.getElementById('budgetChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_weeks) ?>,
        datasets: [
            {
                label: 'Budget (KES)',
                data: <?= json_encode($chart_budgets) ?>,
                backgroundColor: 'rgba(46,125,50,0.6)',
                borderColor: '#2e7d32',
                borderWidth: 1,
                borderRadius: 4
            },
            {
                label: 'Estimated Spend (KES)',
                data: <?= json_encode($chart_spending) ?>,
                backgroundColor: 'rgba(230,81,0,0.6)',
                borderColor: '#e65100',
                borderWidth: 1,
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
    }
});
<?php endif; ?>
</script>
</body>
</html>
