<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__, 2) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$totalUsers     = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user'")->fetch_assoc()['c'];
$totalRecipes   = $conn->query("SELECT COUNT(*) AS c FROM recipes")->fetch_assoc()['c'];
$totalMealPlans = $conn->query("SELECT COUNT(*) AS c FROM meal_plans")->fetch_assoc()['c'];

// Budget from budgets table (weekly budgets saved by users)
$budgetRow       = $conn->query("SELECT COALESCE(SUM(b.total_budget),0) AS total, COUNT(DISTINCT b.user_id) AS cnt FROM budgets b JOIN users u ON u.id=b.user_id WHERE u.role='user'")->fetch_assoc();
// Budget from household_profiles (weekly_budget set in profile)
$hpBudgetRow     = $conn->query("SELECT COALESCE(SUM(hp.weekly_budget),0) AS total, COUNT(DISTINCT hp.user_id) AS cnt FROM household_profiles hp JOIN users u ON u.id=hp.user_id WHERE u.role='user' AND hp.weekly_budget > 0")->fetch_assoc();
// Use whichever source has data; prefer budgets table if both have data
$totalBudget     = ($budgetRow['total'] > 0) ? $budgetRow['total'] : $hpBudgetRow['total'];
$usersWithBudget = ($budgetRow['cnt'] > 0) ? $budgetRow['cnt'] : $hpBudgetRow['cnt'];

$recentUsers   = $conn->query("SELECT name, email, created_at FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 5");
$recentRecipes = $conn->query("SELECT name, created_at FROM recipes ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Kenyan Meal Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --primary:#2E7D32; --primary-dark:#1B5E20; --accent:#FB8C00; --accent-dark:#E65100; --bg:#FEF9E7; --card:#FFFFFF; --text:#2D3E2F; --text-light:#5F6B5F; --border:#E8F0E8; --shadow-sm:0 8px 20px rgba(0,0,0,0.05); --shadow-md:0 12px 28px rgba(0,0,0,0.08); --shadow-hover:0 20px 35px -8px rgba(46,125,50,0.2); --transition:all 0.25s ease; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); display:flex; flex-direction:column; min-height:100vh; }
        .admin-header { background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%); color:white; padding:1rem 2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; box-shadow:0 4px 12px rgba(0,0,0,0.1); position:sticky; top:0; z-index:1000; }
        .admin-header h1 { font-size:1.8rem; font-weight:600; }
        .admin-header h1 i { margin-right:10px; color:var(--accent); }
        .header-right { display:flex; align-items:center; gap:1.5rem; }
        .logout-btn { background:rgba(255,255,255,0.15); color:white; padding:0.5rem 1.2rem; border-radius:40px; text-decoration:none; font-weight:500; transition:var(--transition); display:inline-flex; align-items:center; gap:8px; }
        .logout-btn:hover { background:var(--accent); }
        .admin-container { display:flex; flex:1; }
        .admin-sidebar { width:280px; background:var(--primary-dark); padding:2rem 0; box-shadow:2px 0 12px rgba(0,0,0,0.08); }
        .admin-sidebar ul { list-style:none; }
        .admin-sidebar li { margin-bottom:0.25rem; }
        .admin-sidebar a { display:flex; align-items:center; gap:12px; padding:0.9rem 1.8rem; color:#e0f2e0; text-decoration:none; transition:var(--transition); border-left:4px solid transparent; font-weight:500; }
        .admin-sidebar a i { width:24px; font-size:1.2rem; color:var(--accent); }
        .admin-sidebar a:hover { background:rgba(251,140,0,0.2); border-left-color:var(--accent); color:white; }
        .admin-sidebar a.active { background:var(--accent); color:white; border-left-color:var(--accent); }
        .admin-sidebar a.active i { color:white; }
        .admin-main { flex:1; padding:2rem; overflow-x:auto; }
        .section-title { font-size:1.5rem; font-weight:600; color:var(--primary-dark); margin-bottom:1.5rem; display:flex; align-items:center; gap:10px; border-bottom:3px solid var(--accent); padding-bottom:0.5rem; width:fit-content; }
        .section-title i { color:var(--accent); }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.5rem; margin-bottom:2.5rem; }
        .stat-card { background:var(--card); border-radius:24px; padding:1.6rem; box-shadow:var(--shadow-sm); border:1px solid var(--border); transition:var(--transition); position:relative; overflow:hidden; text-align:center; }
        .stat-card::before { content:''; position:absolute; top:0; left:0; width:100%; height:5px; background:linear-gradient(90deg,var(--primary),var(--accent)); }
        .stat-card:hover { transform:translateY(-5px); box-shadow:var(--shadow-hover); }
        .stat-card i { font-size:2.4rem; color:var(--accent); margin-bottom:0.6rem; display:block; }
        .stat-card h3 { font-size:0.9rem; font-weight:500; color:var(--text-light); margin-bottom:0.4rem; }
        .stat-card .number { font-size:2.2rem; font-weight:700; color:var(--primary); line-height:1.2; }
        .stat-card .sub { font-size:0.75rem; color:var(--text-light); margin-top:4px; }
        .actions-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1.5rem; margin-bottom:2.5rem; }
        .action-card { background:var(--card); border-radius:20px; padding:1.4rem; text-align:center; text-decoration:none; color:var(--text); border:1px solid var(--border); transition:var(--transition); box-shadow:var(--shadow-sm); }
        .action-card:hover { transform:translateY(-5px); box-shadow:var(--shadow-hover); border-color:var(--accent); }
        .action-card i { font-size:2.2rem; color:var(--accent); margin-bottom:0.8rem; display:block; }
        .action-card h4 { font-size:1.1rem; font-weight:600; color:var(--primary-dark); margin-bottom:0.4rem; }
        .action-card p { font-size:0.82rem; color:var(--text-light); }
        .recent-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1.5rem; margin-bottom:2rem; }
        .recent-card { background:var(--card); border-radius:20px; padding:1.4rem; box-shadow:var(--shadow-sm); border:1px solid var(--border); }
        .recent-card h3 { font-size:1.1rem; font-weight:600; color:var(--primary-dark); margin-bottom:1rem; display:flex; align-items:center; gap:8px; border-left:4px solid var(--accent); padding-left:10px; }
        .recent-card h3 i { color:var(--accent); }
        .recent-table { width:100%; border-collapse:collapse; }
        .recent-table td { padding:0.65rem 0; border-bottom:1px solid var(--border); font-size:0.88rem; }
        .recent-table tr:last-child td { border-bottom:none; }
        .recent-table .time { font-size:0.78rem; color:#9aa9a0; text-align:right; }
        .export-btn { display:inline-flex; align-items:center; gap:10px; background:var(--accent); color:white; padding:0.8rem 2rem; border-radius:50px; text-decoration:none; font-weight:600; transition:var(--transition); box-shadow:0 4px 12px rgba(251,140,0,0.3); margin-top:0.5rem; }
        .export-btn:hover { background:var(--accent-dark); transform:translateY(-2px); }
        .admin-footer { background:var(--primary-dark); color:rgba(255,255,255,0.9); text-align:center; padding:1.2rem; font-size:0.85rem; margin-top:auto; }
        @media(max-width:768px) { .admin-container{flex-direction:column;} .admin-sidebar{width:100%;padding:0.5rem;} .admin-sidebar ul{display:flex;flex-wrap:wrap;justify-content:center;gap:0.2rem;} .admin-sidebar a{padding:0.6rem 1rem;} .stats-grid,.actions-grid,.recent-grid{grid-template-columns:1fr;} }
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="recipes.php"><i class="fas fa-book-open"></i> Manage Recipes</a></li>
            <li><a href="meal_plans.php"><i class="fas fa-calendar-alt"></i> Meal Plans</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <div class="section-title"><i class="fas fa-chart-pie"></i> Dashboard Overview</div>
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Total Users</h3>
                <div class="number"><?= $totalUsers ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-book-open"></i>
                <h3>Total Recipes</h3>
                <div class="number"><?= $totalRecipes ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-alt"></i>
                <h3>Meal Plans</h3>
                <div class="number"><?= $totalMealPlans ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-wallet"></i>
                <h3>Total Budget (All Users)</h3>
                <div class="number" style="font-size:1.5rem;">KES <?= number_format($totalBudget, 0) ?></div>
                <div class="sub"><?= $usersWithBudget ?> user<?= $usersWithBudget != 1 ? 's' : '' ?> with budgets set</div>
            </div>
        </div>
        <div class="section-title"><i class="fas fa-bolt"></i> Quick Actions</div>
        <div class="actions-grid">
            <a href="users.php" class="action-card"><i class="fas fa-users"></i><h4>Manage Users</h4><p>Add, edit or remove users</p></a>
            <a href="recipes.php" class="action-card"><i class="fas fa-book-open"></i><h4>Manage Recipes</h4><p>Create and update recipes</p></a>
            <a href="meal_plans.php" class="action-card"><i class="fas fa-calendar-alt"></i><h4>Meal Plans</h4><p>View and manage user meal plans</p></a>
            <a href="reports.php" class="action-card"><i class="fas fa-chart-line"></i><h4>View Reports</h4><p>Analyse system activity</p></a>
        </div>
        <div class="section-title"><i class="fas fa-history"></i> Recent Activity</div>
        <div class="recent-grid">
            <div class="recent-card">
                <h3><i class="fas fa-user-plus"></i> New Users</h3>
                <table class="recent-table">
                    <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>
                        <?php while ($u = $recentUsers->fetch_assoc()): ?>
                            <tr><td><?= htmlspecialchars($u['name']) ?></td><td class="time"><?= date('d M', strtotime($u['created_at'])) ?></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?><tr><td colspan="2">No recent users</td></tr><?php endif; ?>
                </table>
            </div>
            <div class="recent-card">
                <h3><i class="fas fa-book"></i> New Recipes</h3>
                <table class="recent-table">
                    <?php if ($recentRecipes && $recentRecipes->num_rows > 0): ?>
                        <?php while ($r = $recentRecipes->fetch_assoc()): ?>
                            <tr><td><?= htmlspecialchars($r['name']) ?></td><td class="time"><?= date('d M', strtotime($r['created_at'])) ?></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?><tr><td colspan="2">No recent recipes</td></tr><?php endif; ?>
                </table>
            </div>
        </div>
        <a href="export_reports.php" class="export-btn"><i class="fas fa-file-export"></i> Export Reports to PDF</a>
    </main>
</div>
<footer class="admin-footer"><i class="fas fa-leaf"></i> &copy; 2026 Kenyan Meal Planner - Admin Panel. All rights reserved.</footer>
</body>
</html>
