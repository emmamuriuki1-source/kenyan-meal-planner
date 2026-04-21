<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__, 2) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: login.php');
        exit;
    }
}
requireAdmin();

// --- Stats: top recipes per meal type ---
$meal_types = ['Breakfast', 'Lunch', 'Dinner', 'Fruits'];

$stats = [];foreach ($meal_types as $type) {
    $t = $conn->real_escape_string($type);
    $res = $conn->query("
        SELECT r.name AS recipe_name, r.image, COUNT(*) AS total
        FROM meal_plans mp
        JOIN recipes r ON r.id = mp.recipe_id
        WHERE mp.meal_type = '$t'
        GROUP BY mp.recipe_id, r.name, r.image
        ORDER BY total DESC
        LIMIT 5
    ");
    $stats[$type] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// --- Summary counts per meal type ---
$summary = $conn->query("
    SELECT meal_type, COUNT(*) AS total
    FROM meal_plans
    WHERE meal_type IN ('Breakfast','Lunch','Dinner','Fruits')
    GROUP BY meal_type
")->fetch_all(MYSQLI_ASSOC);
$summary_map = array_column($summary, 'total', 'meal_type');

// --- Most active users ---
$active_users = $conn->query("
    SELECT u.name, COUNT(*) AS plan_count
    FROM meal_plans mp
    JOIN users u ON u.id = mp.user_id
    GROUP BY mp.user_id
    ORDER BY plan_count DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// --- Weekly trend (last 8 weeks) ---
$weekly = $conn->query("
    SELECT week_start, meal_type, COUNT(*) AS total
    FROM meal_plans
    WHERE meal_type IN ('Breakfast','Lunch','Dinner','Fruits')
      AND week_start >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
    GROUP BY week_start, meal_type
    ORDER BY week_start ASC
")->fetch_all(MYSQLI_ASSOC);

// Build chart data
$weeks = [];
$chart_data = ['Breakfast' => [], 'Lunch' => [], 'Dinner' => [], 'Fruits' => []];
foreach ($weekly as $row) {
    $w = $row['week_start'];
    if (!in_array($w, $weeks)) $weeks[] = $w;
}
foreach ($weeks as $w) {
    foreach ($meal_types as $t) {
        $chart_data[$t][$w] = 0;
    }
}
foreach ($weekly as $row) {
    if (isset($chart_data[$row['meal_type']])) {
        $chart_data[$row['meal_type']][$row['week_start']] = (int)$row['total'];
    }
}

// --- Filter: optional week filter ---
$filter_week = $_GET['week'] ?? '';
$filter_type = $_GET['meal_type'] ?? 'all';

$where = "WHERE mp.meal_type IN ('Breakfast','Lunch','Dinner','Fruits')";
if ($filter_week) $where .= " AND mp.week_start = '" . $conn->real_escape_string($filter_week) . "'";
if ($filter_type !== 'all') $where .= " AND mp.meal_type = '" . $conn->real_escape_string($filter_type) . "'";

$plans = $conn->query("
    SELECT mp.*, r.name AS recipe_name, r.image, u.name AS user_name, r.category
    FROM meal_plans mp
    LEFT JOIN recipes r ON r.id = mp.recipe_id
    LEFT JOIN users u ON u.id = mp.user_id
    $where
    ORDER BY mp.week_start DESC, mp.day_of_week, mp.meal_type
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

$week_options = $conn->query("
    SELECT DISTINCT week_start FROM meal_plans ORDER BY week_start DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Meal Plans – Admin | Kenyan Meal Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #2E7D32;
            --primary-dark: #1B5E20;
            --accent: #FB8C00;
            --bg: #FEF9E7;
            --card: #FFFFFF;
            --text: #2D3E2F;
            --text-light: #5F6B5F;
            --border: #E8F0E8;
            --shadow-sm: 0 8px 20px rgba(0,0,0,0.05);
            --transition: all 0.25s ease;
            --breakfast: #FB8C00;
            --lunch: #2E7D32;
            --dinner: #1565C0;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); display:flex; flex-direction:column; min-height:100vh; }

        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; padding: 1rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 1000;
        }
        .admin-header h1 { font-size:1.8rem; font-weight:600; }
        .admin-header h1 i { margin-right:10px; color:var(--accent); }
        .header-right { display:flex; align-items:center; gap:1.5rem; }
        .logout-btn {
            background:rgba(255,255,255,0.15); color:white;
            padding:0.5rem 1.2rem; border-radius:40px; text-decoration:none;
            font-weight:500; transition:var(--transition);
            display:inline-flex; align-items:center; gap:8px;
        }
        .logout-btn:hover { background:var(--accent); }

        .admin-container { display:flex; flex:1; }

        .admin-sidebar {
            width:280px; background:var(--primary-dark);
            padding:2rem 0; box-shadow:2px 0 12px rgba(0,0,0,0.08);
        }
        .admin-sidebar ul { list-style:none; }
        .admin-sidebar li { margin-bottom:0.25rem; }
        .admin-sidebar a {
            display:flex; align-items:center; gap:12px;
            padding:0.9rem 1.8rem; color:#e0f2e0; text-decoration:none;
            transition:var(--transition); border-left:4px solid transparent; font-weight:500;
        }
        .admin-sidebar a i { width:24px; font-size:1.2rem; color:var(--accent); }
        .admin-sidebar a:hover { background:rgba(251,140,0,0.2); border-left-color:var(--accent); color:white; }
        .admin-sidebar a.active { background:var(--accent); color:white; border-left-color:var(--accent); }
        .admin-sidebar a.active i { color:white; }

        .admin-main { flex:1; padding:2rem; overflow-x:auto; }

        .page-title {
            display:flex; align-items:center; gap:10px;
            font-size:1.5rem; font-weight:600; color:var(--primary-dark);
            margin-bottom:1.5rem;
        }
        .page-title i { color:var(--accent); }

        /* Summary cards */
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .summary-card {
            background:var(--card); border-radius:20px; padding:1.2rem 1.5rem;
            box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1rem;
            transition: transform 0.2s;
        }
        .summary-card:hover { transform: translateY(-2px); }
        .summary-icon {
            width:48px; height:48px; border-radius:14px;
            display:flex; align-items:center; justify-content:center;
            font-size:1.4rem; flex-shrink:0;
            color: var(--primary-dark);
        }
        .summary-card h3 { font-size:1.6rem; font-weight:700; }
        .summary-card p { font-size:0.8rem; color:var(--text-light); margin-top:2px; }

        /* Top recipes grid */
        .meal-section-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1.2rem; margin-bottom:1.5rem; }
        .meal-section {
            background:var(--card); border-radius:20px; padding:1.2rem;
            box-shadow:var(--shadow-sm);
            transition: transform 0.2s;
        }
        .meal-section:hover { transform: translateY(-2px); }
        .meal-section-header {
            display:flex; align-items:center; gap:8px;
            font-weight:600; font-size:1rem; margin-bottom:1rem;
            padding-bottom:0.6rem; border-bottom:2px solid var(--border);
        }
        .dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
        .recipe-rank { display:flex; align-items:center; gap:10px; padding:0.5rem 0; border-bottom:1px solid var(--border); }
        .recipe-rank:last-child { border-bottom:none; }
        .rank-num { font-size:0.75rem; font-weight:700; color:var(--text-light); width:20px; }
        .rank-thumb { width:36px; height:36px; border-radius:10px; object-fit:cover; background:#f0f0f0; flex-shrink:0; }
        .rank-info { flex:1; min-width:0; }
        .rank-info strong { font-size:0.85rem; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rank-info span { font-size:0.75rem; color:var(--text-light); }
        .rank-bar-wrap { width:60px; }
        .rank-bar { height:6px; border-radius:3px; }
        .empty-state { text-align:center; color:var(--text-light); font-size:0.85rem; padding:1rem 0; }

        /* Chart */
        .chart-card { background:var(--card); border-radius:20px; padding:1.5rem; box-shadow:var(--shadow-sm); margin-bottom:1.5rem; }
        .chart-card h3 { font-size:1rem; font-weight:600; color:var(--primary-dark); margin-bottom:1rem; }

        /* Active users */
        .users-card { background:var(--card); border-radius:20px; padding:1.2rem; box-shadow:var(--shadow-sm); margin-bottom:1.5rem; }
        .users-card h3 { font-size:1rem; font-weight:600; color:var(--primary-dark); margin-bottom:1rem; }
        .user-row { display:flex; align-items:center; gap:10px; padding:0.5rem 0; border-bottom:1px solid var(--border); }
        .user-row:last-child { border-bottom:none; }
        .user-avatar { width:34px; height:34px; border-radius:50%; background:var(--primary); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; flex-shrink:0; }
        .user-row strong { font-size:0.85rem; }
        .user-row span { font-size:0.75rem; color:var(--text-light); }
        .badge { padding:0.2rem 0.6rem; border-radius:30px; font-size:0.7rem; font-weight:600; display:inline-block; }
        .badge-breakfast { background:#fff3e0; color:#e65100; }
        .badge-lunch { background:#e8f5e9; color:#1b5e20; }
        .badge-dinner { background:#e3f2fd; color:#0d47a1; }
        .badge-fruits { background:#f3e5f5; color:#6a1b9a; }

        /* Filter + table */
        .filter-bar {
            background:var(--card); border-radius:16px; padding:1rem;
            display:flex; gap:0.8rem; flex-wrap:wrap; align-items:center;
            margin-bottom:1rem; box-shadow:var(--shadow-sm);
        }
        .filter-bar select, .filter-bar input { padding:0.5rem 1rem; border:1px solid var(--border); border-radius:40px; font-family:inherit; background: white; }
        .btn-primary { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:white; border:none; padding:0.5rem 1.2rem; border-radius:40px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; font-size:0.85rem; transition:opacity 0.2s; }
        .btn-primary:hover { opacity:0.9; }
        .btn-outline { background:transparent; border:1px solid var(--border); color:var(--text); padding:0.5rem 1rem; border-radius:40px; text-decoration:none; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; transition: all 0.2s; }
        .btn-outline:hover { background: var(--border); }

        .table-card { background:var(--card); border-radius:20px; box-shadow:var(--shadow-sm); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width: 600px; }
        th, td { padding:0.85rem 1rem; text-align:left; border-bottom:1px solid var(--border); }
        th { color:var(--text-light); font-weight:600; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        td { font-size:0.88rem; }
        .recipe-thumb { width:40px; height:40px; object-fit:cover; border-radius:10px; background:#f5f5f5; margin-right:8px; vertical-align: middle; }
        .recipe-cell { display: flex; align-items: center; gap: 8px; }

        @media(max-width:768px) {
            .admin-sidebar { width:100%; padding:0.5rem; }
            .admin-sidebar ul { display:flex; flex-wrap:wrap; justify-content:center; gap:0.2rem; }
            .admin-sidebar a { padding:0.6rem 1rem; }
            .admin-main { padding:1rem; }
        }
    </style>
</head>
<body>
<header class="admin-header">
    <h1><i class="fas fa-utensils"></i> Kenyan Meal Planner</h1>
    <div class="header-right">
        <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>

<div class="admin-container">
    <aside class="admin-sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="recipes.php"><i class="fas fa-book-open"></i> Manage Recipes</a></li>
            <li><a href="meal_plans.php" class="active"><i class="fas fa-calendar-alt"></i> Meal Plans</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="page-title">
            <i class="fas fa-calendar-alt"></i> Manage Meal Plans
        </div>

        <!-- Summary Cards with Font Awesome icons (emojis removed) -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon" style="background:#fff3e0;"><i class="fas fa-sun"></i></div>
                <div>
                    <h3><?= number_format($summary_map['Breakfast'] ?? 0) ?></h3>
                    <p>Breakfast Plans</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background:#e8f5e9;"><i class="fas fa-utensils"></i></div>
                <div>
                    <h3><?= number_format($summary_map['Lunch'] ?? 0) ?></h3>
                    <p>Lunch Plans</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background:#e3f2fd;"><i class="fas fa-moon"></i></div>
                <div>
                    <h3><?= number_format($summary_map['Dinner'] ?? 0) ?></h3>
                    <p>Dinner Plans</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background:#f3e5f5;"><i class="fas fa-apple-alt"></i></div>
                <div>
                    <h3><?= number_format($summary_map['Fruits'] ?? 0) ?></h3>
                    <p>Fruits Plans</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background:#e8eaf6;"><i class="fas fa-chart-line"></i></div>
                <div>
                    <h3><?= number_format(array_sum($summary_map)) ?></h3>
                    <p>Total Plans</p>
                </div>
            </div>
        </div>

        <!-- Top Recipes per Meal Type (emojis removed, clean icons) -->
        <div class="meal-section-grid">
            <?php
            $type_config = [
                'Breakfast' => ['color' => '#FB8C00', 'label' => 'Breakfast', 'icon' => 'fa-sun'],
                'Lunch'     => ['color' => '#2E7D32', 'label' => 'Lunch', 'icon' => 'fa-utensils'],
                'Dinner'    => ['color' => '#1565C0', 'label' => 'Dinner', 'icon' => 'fa-moon'],
                'Fruits'    => ['color' => '#8E24AA', 'label' => 'Fruits', 'icon' => 'fa-apple-alt'],
            ];
            foreach ($meal_types as $type):
                $cfg = $type_config[$type];
                $rows = $stats[$type];
                $max = $rows[0]['total'] ?? 1;
            ?>
            <div class="meal-section">
                <div class="meal-section-header">
                    <div class="dot" style="background:<?= $cfg['color'] ?>;"></div>
                    <i class="fas <?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;"></i>
                    <span>Top <?= $cfg['label'] ?> Recipes</span>
                </div>
                <?php if (empty($rows)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><br>No data yet</div>
                <?php else: ?>
                    <?php foreach ($rows as $i => $row): ?>
                    <div class="recipe-rank">
                        <span class="rank-num">#<?= $i+1 ?></span>
                        <?php if (!empty($row['image'])): ?>
                            <img src="../<?= htmlspecialchars($row['image']) ?>" class="rank-thumb" alt="">
                        <?php else: ?>
                            <div class="rank-thumb" style="display:flex;align-items:center;justify-content:center;color:#ccc;"><i class="fas fa-utensils"></i></div>
                        <?php endif; ?>
                        <div class="rank-info">
                            <strong><?= htmlspecialchars($row['recipe_name']) ?></strong>
                            <span><?= $row['total'] ?> plan<?= $row['total'] != 1 ? 's' : '' ?></span>
                        </div>
                        <div class="rank-bar-wrap">
                            <div class="rank-bar" style="background:<?= $cfg['color'] ?>; width:<?= round(($row['total']/$max)*100) ?>%; opacity:0.7;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Weekly Trend Chart -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-bar" style="color:var(--accent);"></i> Weekly Planning Trend (Last 8 Weeks)</h3>
            <canvas id="trendChart" height="80"></canvas>
        </div>

        <!-- Most Active Users -->
        <?php if (!empty($active_users)): ?>
        <div class="users-card">
            <h3><i class="fas fa-fire" style="color:var(--accent);"></i> Most Active Planners</h3>
            <?php foreach ($active_users as $u): ?>
            <div class="user-row">
                <div class="user-avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                <div style="flex:1;">
                    <strong><?= htmlspecialchars($u['name']) ?></strong><br>
                    <span><?= $u['plan_count'] ?> meal plans</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Detailed Plans Table -->
        <div class="filter-bar">
            <form method="GET" style="display:contents;">
                <select name="meal_type">
                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Meal Types</option>
                    <?php foreach ($meal_types as $t): ?>
                        <option value="<?= $t ?>" <?= $filter_type === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="week">
                    <option value="">All Weeks</option>
                    <?php foreach ($week_options as $w): ?>
                        <option value="<?= $w['week_start'] ?>" <?= $filter_week === $w['week_start'] ? 'selected' : '' ?>>
                            Week of <?= date('M j, Y', strtotime($w['week_start'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($filter_week || $filter_type !== 'all'): ?>
                    <a href="meal_plans.php" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Recipe</th>
                        <th>Meal Type</th>
                        <th>Day</th>
                        <th>Week Of</th>
                        <th>Servings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-light);">No meal plans found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($plans as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($p['user_name'] ?? '—') ?></td>
                        <td class="recipe-cell">
                            <?php if (!empty($p['image'])): ?>
                                <img src="../<?= htmlspecialchars($p['image']) ?>" class="recipe-thumb" alt="">
                            <?php endif; ?>
                            <?= htmlspecialchars($p['recipe_name'] ?? 'Deleted Recipe') ?>
                        </td>
                        <td>
                            <?php $mt = strtolower($p['meal_type']); ?>
                            <span class="badge badge-<?= $mt ?>"><?= $p['meal_type'] === 'Fruits' ? 'Fruits' : htmlspecialchars($p['meal_type']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($p['day_of_week'] ?? '—') ?></td>
                        <td><?= $p['week_start'] ? date('M j, Y', strtotime($p['week_start'])) : '—' ?></td>
                        <td><?= (int)$p['servings'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
const weeks = <?= json_encode(array_values($weeks)) ?>;
const chartData = <?= json_encode([
    'Breakfast' => array_values($chart_data['Breakfast']),
    'Lunch'     => array_values($chart_data['Lunch']),
    'Dinner'    => array_values($chart_data['Dinner']),
    'Fruits'     => array_values($chart_data['Fruits']),
]) ?>;

const labels = weeks.map(w => {
    const d = new Date(w);
    return d.toLocaleDateString('en-KE', { month: 'short', day: 'numeric' });
});

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: labels.length ? labels : ['No data'],
        datasets: [
            { label: 'Breakfast', data: chartData.Breakfast, backgroundColor: '#FB8C00', borderRadius: 6 },
            { label: 'Lunch',     data: chartData.Lunch,     backgroundColor: '#2E7D32', borderRadius: 6 },
            { label: 'Dinner',    data: chartData.Dinner,    backgroundColor: '#1565C0', borderRadius: 6 },
            { label: 'Fruits', data: chartData.Fruits,     backgroundColor: '#8E24AA', borderRadius: 6 },
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
        }
    }
});
</script>
</body>
</html>