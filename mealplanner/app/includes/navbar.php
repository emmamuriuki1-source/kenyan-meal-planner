<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="brand">
        🍽️ MealPlanner KE
    </div>
    <nav>
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="meals.php"     class="<?= $current === 'meals.php'     ? 'active' : '' ?>">Meal Plan</a>
        <a href="recipes.php"   class="<?= $current === 'recipes.php'   ? 'active' : '' ?>">Recipes</a>
        <a href="budget.php"    class="<?= $current === 'budget.php'    ? 'active' : '' ?>">Budget</a>
        <a href="market_prices.php" class="<?= $current === 'market_prices.php' ? 'active' : '' ?>">Prices</a>
        <a href="shopping_list.php" class="<?= $current === 'shopping_list.php' ? 'active' : '' ?>">Shopping</a>
    </nav>
    <div class="user-info">
        👤 <?= e($_SESSION['user_name'] ?? 'User') ?> &nbsp;|&nbsp;
        <a href="logout.php" style="color:#fff; opacity:0.8;">Logout</a>
    </div>
</nav>
