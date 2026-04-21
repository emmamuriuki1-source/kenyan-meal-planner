<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">🍽️ Meal<span>Planner</span> KE<br>
        <small style="font-size:0.72rem; color:#666; font-weight:400;">Admin Panel</small>
    </div>
    <nav>
        <a href="dashboard.php"  class="<?= $cur==='dashboard.php'  ? 'active':'' ?>">📊 Dashboard</a>
        <a href="users.php"      class="<?= $cur==='users.php'      ? 'active':'' ?>">👥 Manage Users</a>
        <a href="recipes.php"    class="<?= $cur==='recipes.php'    ? 'active':'' ?>">📖 Manage Recipes</a>
        <a href="meal_plans.php" class="<?= $cur==='meal_plans.php' ? 'active':'' ?>">📅 Meal Plans</a>
        <a href="reports.php"    class="<?= $cur==='reports.php'    ? 'active':'' ?>">📈 Reports</a>
    </nav>
    <div class="sidebar-footer">
        Logged in as<br>
        <strong style="color:#fff;"><?= e($_SESSION['user_name'] ?? 'Admin') ?></strong><br>
        <a href="../logout.php" style="color:#ef9a9a; margin-top:6px; display:inline-block;">⏻ Logout</a>
    </div>
</aside>
