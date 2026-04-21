<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__, 2) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: login.php'); exit;
    }
}
requireAdmin();

// ── SYSTEM OVERVIEW ──────────────────────────────────────────────────────────
$total_users    = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user'")->fetch_assoc()['c'];
$active_users   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user' AND (status IS NULL OR status='active')")->fetch_assoc()['c'];
$total_recipes  = $conn->query("SELECT COUNT(*) AS c FROM recipes")->fetch_assoc()['c'];
$default_recipes= $conn->query("SELECT COUNT(*) AS c FROM recipes WHERE user_id IS NULL")->fetch_assoc()['c'];
$user_recipes   = $conn->query("SELECT COUNT(*) AS c FROM recipes WHERE user_id IS NOT NULL")->fetch_assoc()['c'];
$total_plans    = $conn->query("SELECT COUNT(*) AS c FROM meal_plans")->fetch_assoc()['c'];
$total_ingredients = $conn->query("SELECT COUNT(*) AS c FROM ingredients")->fetch_assoc()['c'];

// ── BUDGET STATS ─────────────────────────────────────────────────────────────
// From budgets table (weekly budgets saved by users on budget page)
$b_row = $conn->query("SELECT COALESCE(SUM(total_budget),0) AS t FROM budgets b JOIN users u ON u.id=b.user_id WHERE u.role='user'")->fetch_assoc();
$b_avg_row = $conn->query("SELECT COALESCE(AVG(user_total),0) AS a FROM (SELECT SUM(total_budget) AS user_total FROM budgets b JOIN users u ON u.id=b.user_id WHERE u.role='user' GROUP BY b.user_id) AS per_user")->fetch_assoc();
// From household_profiles (weekly_budget set in profile)
$hp_row       = $conn->query("SELECT COALESCE(SUM(weekly_budget),0) AS t, COALESCE(AVG(weekly_budget),0) AS a FROM household_profiles hp JOIN users u ON u.id=hp.user_id WHERE u.role='user' AND hp.weekly_budget > 0")->fetch_assoc();
// Use whichever source has data; prefer budgets table
$total_budget = ($b_row['t'] > 0) ? $b_row['t'] : $hp_row['t'];
$avg_budget   = ($b_avg_row['a'] > 0) ? $b_avg_row['a'] : $hp_row['a'];

// Per-user budget breakdown — show budgets table total; fall back to household weekly_budget
$user_budgets = $conn->query("
    SELECT u.name, u.email,
           COALESCE(SUM(b.total_budget),0)                          AS total_spent,
           COUNT(b.id)                                               AS weeks_planned,
           COALESCE(hp.weekly_budget,0)                              AS set_budget,
           CASE WHEN COALESCE(SUM(b.total_budget),0) = 0
                THEN COALESCE(hp.weekly_budget,0)
                ELSE COALESCE(SUM(b.total_budget),0) END             AS effective_budget
    FROM users u
    LEFT JOIN budgets b ON b.user_id = u.id
    LEFT JOIN household_profiles hp ON hp.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id, hp.weekly_budget
    ORDER BY effective_budget DESC
");

// ── USER REGISTRATIONS LAST 7 DAYS ───────────────────────────────────────────
$reg_labels = []; $reg_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $reg_labels[] = date('D d', strtotime($date));
    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE DATE(created_at)='$date' AND role='user'");
    $reg_data[] = (int)$r->fetch_assoc()['c'];
}

// ── TOP 5 MOST PLANNED RECIPES ────────────────────────────────────────────────
$top_recipes = $conn->query("
    SELECT r.name, r.category, COUNT(mp.id) AS cnt
    FROM meal_plans mp JOIN recipes r ON r.id = mp.recipe_id
    GROUP BY mp.recipe_id ORDER BY cnt DESC LIMIT 5
");
$top_list = [];
if ($top_recipes) while ($row = $top_recipes->fetch_assoc()) $top_list[] = $row;
$max_plans = $top_list[0]['cnt'] ?? 1;

// ── MOST ACTIVE USERS ─────────────────────────────────────────────────────────
$active_list = $conn->query("
    SELECT u.name, u.email, COUNT(mp.id) AS plans,
           hp.weekly_budget, hp.adults, hp.children
    FROM users u
    LEFT JOIN meal_plans mp ON mp.user_id = u.id
    LEFT JOIN household_profiles hp ON hp.user_id = u.id
    WHERE u.role='user'
    GROUP BY u.id ORDER BY plans DESC LIMIT 8
");

// ── RECENT REGISTRATIONS ──────────────────────────────────────────────────────
$recent_users = $conn->query("
    SELECT u.name, u.email, u.created_at,
           COALESCE(hp.weekly_budget,0) AS budget,
           COALESCE(hp.adults+hp.children,0) AS household_size,
           COUNT(mp.id) AS plans
    FROM users u
    LEFT JOIN household_profiles hp ON hp.user_id = u.id
    LEFT JOIN meal_plans mp ON mp.user_id = u.id
    WHERE u.role='user'
    GROUP BY u.id ORDER BY u.created_at DESC LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports – Admin | MealPlanner KE</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --primary: #2E7D32;
        --primary-dark: #1B5E20;
        --accent: #FB8C00;
        --accent-dark: #E65100;
        --bg: #FEF9E7;
        --card: #FFFFFF;
        --text: #2D3E2F;
        --text-light: #5F6B5F;
        --border: #E8F0E8;
        --shadow-sm: 0 8px 20px rgba(0,0,0,0.05);
        --shadow-md: 0 12px 28px rgba(0,0,0,0.08);
        --shadow-hover: 0 20px 35px -8px rgba(46,125,50,0.2);
        --transition: all 0.25s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg);
        color: var(--text);
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* Header */
    .admin-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    .admin-header h1 {
        font-size: 1.8rem;
        font-weight: 600;
        letter-spacing: -0.5px;
    }
    .admin-header h1 i {
        margin-right: 10px;
        color: var(--accent);
    }
    .header-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .header-right span {
        font-weight: 500;
    }
    .logout-btn {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 0.5rem 1.2rem;
        border-radius: 40px;
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .logout-btn:hover {
        background: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(251,140,0,0.3);
    }

    /* Layout */
    .admin-container {
        display: flex;
        flex: 1;
    }

    /* Sidebar */
    .admin-sidebar {
        width: 280px;
        background: var(--primary-dark);
        padding: 2rem 0;
        box-shadow: 2px 0 12px rgba(0,0,0,0.08);
    }
    .admin-sidebar ul {
        list-style: none;
    }
    .admin-sidebar li {
        margin-bottom: 0.25rem;
    }
    .admin-sidebar a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0.9rem 1.8rem;
        color: #e0f2e0;
        text-decoration: none;
        transition: var(--transition);
        border-left: 4px solid transparent;
        font-weight: 500;
    }
    .admin-sidebar a i {
        width: 24px;
        font-size: 1.2rem;
        color: var(--accent);
    }
    .admin-sidebar a:hover, .admin-sidebar a.active {
        background: rgba(251,140,0,0.2);
        border-left-color: var(--accent);
        color: white;
    }
    .admin-sidebar a.active {
        background: var(--accent);
        color: white;
    }
    .admin-sidebar a.active i {
        color: white;
    }

    /* Main Content */
    .admin-main {
        flex: 1;
        padding: 2rem;
        overflow-x: auto;
    }

    /* Top bar */
    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .top-bar h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--primary-dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .top-bar h2 i {
        color: var(--accent);
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 40px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(46,125,50,0.3);
    }
    .btn-sm {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }
    .btn-outline {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text);
    }
    .btn-outline:hover {
        background: var(--border);
    }
    .btn-danger {
        background: #e53935;
        color: white;
    }
    .btn-block {
        width: 100%;
    }

    /* Cards */
    .card {
        background: var(--card);
        border-radius: 28px;
        padding: 1.2rem;
        box-shadow: var(--shadow-sm);
        margin-bottom: 1.5rem;
        transition: var(--transition);
    }
    .card:hover {
        box-shadow: var(--shadow-md);
    }
    .card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }
    .card-header i {
        color: var(--accent);
        font-size: 1.2rem;
    }

    /* Stat row */
    .stat-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .stat-box {
        background: var(--card);
        border-radius: 24px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }
    .stat-box:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .si-green { background: #e8f5e9; color: var(--primary); }
    .si-orange { background: #fff3e0; color: var(--accent); }
    .si-blue { background: #e3f2fd; color: #1565c0; }
    .si-purple { background: #f3e5f5; color: #6a1b9a; }
    .si-teal { background: #e0f2f1; color: #00695c; }
    .si-red { background: #ffebee; color: #c62828; }
    .stat-val {
        font-size: 1.7rem;
        font-weight: 800;
        color: var(--primary-dark);
        line-height: 1;
    }
    .stat-lbl {
        font-size: 0.75rem;
        color: var(--text-light);
        margin-top: 4px;
    }

    /* Chart box */
    .chart-box {
        background: var(--card);
        border-radius: 28px;
        padding: 1.2rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        margin-bottom: 1.5rem;
    }
    .chart-box:hover {
        box-shadow: var(--shadow-md);
    }
    .chart-box h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1rem;
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 1rem;
    }
    .chart-box h4 i {
        color: var(--accent);
    }
    .chart-wrap {
        position: relative;
        height: 240px;
    }

    /* Bar chart for top recipes */
    .bar-item {
        margin-bottom: 1rem;
    }
    .bar-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        margin-bottom: 0.3rem;
        color: var(--text);
    }
    .bar-track {
        height: 8px;
        background: #e8f5e9;
        border-radius: 4px;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), #43a047);
        border-radius: 4px;
        transition: width 0.4s;
    }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table th {
        background: var(--primary);
        color: white;
        padding: 0.75rem 1rem;
        text-align: left;
        font-weight: 600;
    }
    .data-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }
    .data-table tr:hover td {
        background: #f9fff9;
    }
    .badge-sm {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .bg-green { background: #e8f5e9; color: var(--primary); }
    .bg-orange { background: #fff3e0; color: var(--accent); }
    .bg-blue { background: #e3f2fd; color: #1565c0; }

    /* Utility */
    .text-center { text-align: center; }
    .mt-4 { margin-top: 1.5rem; }

    @media (max-width: 768px) {
        .admin-sidebar {
            width: 100%;
            padding: 0.5rem;
        }
        .admin-sidebar ul {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.2rem;
        }
        .admin-sidebar a {
            padding: 0.6rem 1.2rem;
        }
        .top-bar {
            flex-direction: column;
            align-items: flex-start;
        }
        .stat-row {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }
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
            <li><a href="meal_plans.php"><i class="fas fa-calendar-alt"></i> Meal Plans</a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="top-bar">
            <h2><i class="fas fa-chart-line"></i> System Reports & Analytics</h2>
            <button onclick="window.print()" class="btn-primary btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>

        <!-- SYSTEM OVERVIEW STATS -->
        <div class="stat-row">
            <div class="stat-box">
                <div class="stat-icon si-green"><i class="fas fa-users"></i></div>
                <div><div class="stat-val"><?= $total_users ?></div><div class="stat-lbl">Registered Users</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon si-orange"><i class="fas fa-book-open"></i></div>
                <div><div class="stat-val"><?= $total_recipes ?></div><div class="stat-lbl">Total Recipes</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon si-blue"><i class="fas fa-calendar-check"></i></div>
                <div><div class="stat-val"><?= $total_plans ?></div><div class="stat-lbl">Meal Plans Created</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon si-teal"><i class="fas fa-user-check"></i></div>
                <div><div class="stat-val"><?= $active_users ?></div><div class="stat-lbl">Active Users</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon si-green"><i class="fas fa-utensils"></i></div>
                <div><div class="stat-val"><?= $default_recipes ?></div><div class="stat-lbl">Default Recipes</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon si-orange"><i class="fas fa-user-edit"></i></div>
                <div><div class="stat-val"><?= $user_recipes ?></div><div class="stat-lbl">User Recipes</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon si-blue"><i class="fas fa-list"></i></div>
                <div><div class="stat-val"><?= $total_ingredients ?></div><div class="stat-lbl">Total Ingredients</div></div>
            </div>
        </div>

        <!-- NEW USER REGISTRATIONS CHART -->
        <div class="chart-box">
            <h4><i class="fas fa-user-plus"></i> New User Registrations (Last 7 Days)</h4>
            <div class="chart-wrap"><canvas id="regChart"></canvas></div>
        </div>

        <!-- TOP RECIPES -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-star"></i> Most Planned Recipes
            </div>
            <?php if (empty($top_list)): ?>
                <p class="text-center" style="color:var(--text-light);">No meal plans yet.</p>
            <?php else: ?>
                <?php foreach ($top_list as $tr): ?>
                <div class="bar-item">
                    <div class="bar-label">
                        <span><?= e($tr['name']) ?> <span class="badge-sm bg-blue"><?= e($tr['category']) ?></span></span>
                        <strong><?= $tr['cnt'] ?> plans</strong>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= round(($tr['cnt']/$max_plans)*100) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- MOST ACTIVE USERS -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-trophy"></i> Most Active Users
            </div>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Email</th><th>Household</th><th>Weekly Budget</th><th>Meal Plans</th></tr>
                </thead>
                <tbody>
                <?php $i=1; while ($u = $active_list->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td style="font-size:0.8rem;color:var(--text-light);"><?= e($u['email']) ?></td>
                    <td><?= (int)($u['adults']??0) + (int)($u['children']??0) ?> people</td>
                    <td>KES <?= number_format($u['weekly_budget']??0,0) ?></td>
                    <td><span class="badge-sm <?= $u['plans']>5?'bg-green':($u['plans']>0?'bg-orange':'') ?>"><?= $u['plans'] ?> plans</span></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- RECENT REGISTRATIONS -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-clock"></i> Recent Registrations
            </div>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Household</th><th>Budget</th><th>Plans</th><th>Joined</th></tr>
                </thead>
                <tbody>
                <?php while ($u = $recent_users->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td style="font-size:0.8rem;color:var(--text-light);"><?= e($u['email']) ?></td>
                    <td><?= $u['household_size'] ?> people</td>
                    <td>KES <?= number_format($u['budget'],0) ?></td>
                    <td><?= $u['plans'] ?></td>
                    <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- HOUSEHOLD BUDGET OVERVIEW                                       -->
        <!-- Shows how much each user has allocated for weekly food spending  -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="card" style="border-left:5px solid #FB8C00;">
            <div class="card-header" style="font-size:1.2rem;">
                <i class="fas fa-wallet" style="color:#FB8C00;font-size:1.4rem;"></i>
                Household Budget Overview
                <span style="font-size:0.78rem;font-weight:400;color:#5f6b5f;margin-left:auto;">
                    Helps admin understand users' spending capacity to add relevant affordable recipes
                </span>
            </div>

            <!-- Budget Summary Stats -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
                <div style="background:#fff8e1;border-radius:16px;padding:1rem;text-align:center;border:1px solid #ffe082;">
                    <div style="font-size:0.75rem;color:#5f6b5f;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;">
                        <i class="fas fa-coins" style="color:#FB8C00;"></i> Total Budget (All Users)
                    </div>
                    <div style="font-size:1.8rem;font-weight:800;color:#e65100;">KES <?= number_format($total_budget, 0) ?></div>
                    <div style="font-size:0.72rem;color:#888;margin-top:4px;">Combined weekly food budget</div>
                </div>
                <div style="background:#e8f5e9;border-radius:16px;padding:1rem;text-align:center;border:1px solid #a5d6a7;">
                    <div style="font-size:0.75rem;color:#5f6b5f;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;">
                        <i class="fas fa-chart-bar" style="color:#2E7D32;"></i> Avg Budget / User
                    </div>
                    <div style="font-size:1.8rem;font-weight:800;color:#2E7D32;">KES <?= number_format($avg_budget, 0) ?></div>
                    <div style="font-size:0.72rem;color:#888;margin-top:4px;">Typical household food budget</div>
                </div>
                <div style="background:#e3f2fd;border-radius:16px;padding:1rem;text-align:center;border:1px solid #90caf9;">
                    <div style="font-size:0.75rem;color:#5f6b5f;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;">
                        <i class="fas fa-users" style="color:#1565c0;"></i> Users with Budget Set
                    </div>
                    <?php
                    $users_with_budget = $conn->query("SELECT COUNT(DISTINCT u.id) AS c FROM users u LEFT JOIN household_profiles hp ON hp.user_id=u.id WHERE u.role='user' AND hp.weekly_budget > 0")->fetch_assoc()['c'];
                    ?>
                    <div style="font-size:1.8rem;font-weight:800;color:#1565c0;"><?= $users_with_budget ?> / <?= $total_users ?></div>
                    <div style="font-size:0.72rem;color:#888;margin-top:4px;">Users actively budgeting</div>
                </div>
            </div>

            <!-- Per-User Budget Table -->
            <div style="font-size:0.85rem;color:#5f6b5f;margin-bottom:0.8rem;">
                <i class="fas fa-info-circle" style="color:#FB8C00;"></i>
                <strong>Profile Weekly Budget</strong> = amount set in household profile &nbsp;|&nbsp;
                <strong>Effective Budget</strong> = actual budget used for meal planning
            </div>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Profile Weekly Budget</th>
                        <th>Effective Budget</th>
                        <th>Weeks Planned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; while ($u = $user_budgets->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <strong><?= e($u['name']) ?></strong><br>
                        <span style="font-size:0.75rem;color:var(--text-light);"><?= e($u['email']) ?></span>
                    </td>
                    <td>KES <?= number_format($u['set_budget'], 0) ?></td>
                    <td><strong style="color:var(--primary);font-size:1rem;">KES <?= number_format($u['effective_budget'], 0) ?></strong></td>
                    <td style="text-align:center;"><?= $u['weeks_planned'] ?></td>
                    <td>
                        <?php if ($u['effective_budget'] > 0): ?>
                            <span class="badge-sm bg-green"><i class="fas fa-check-circle"></i> Budget Set</span>
                        <?php else: ?>
                            <span class="badge-sm" style="background:#ffebee;color:#c62828;"><i class="fas fa-exclamation-circle"></i> No Budget</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>
</div>

<script>
const green = '#2E7D32';
// Registration chart
new Chart(document.getElementById('regChart'), {
    type: 'line',
    data: { labels: <?= json_encode($reg_labels) ?>, datasets: [{
        label: 'New Users', data: <?= json_encode($reg_data) ?>,
        borderColor: green, backgroundColor: 'rgba(46,125,50,.1)',
        borderWidth: 2, fill: true, tension: 0.4, pointBackgroundColor: green
    }]},
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});
</script>
</body>
</html>
