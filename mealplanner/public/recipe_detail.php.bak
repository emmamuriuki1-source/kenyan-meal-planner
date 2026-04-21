<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM recipes WHERE id=? AND (user_id=? OR user_id IS NULL)");
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();

if (!$recipe) { header('Location: recipes.php'); exit; }

$ingredients = $conn->query("SELECT * FROM ingredients WHERE recipe_id=$id ORDER BY id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($recipe['name']) ?> – Kenyan Meal Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',sans-serif;background:#fef9e7;color:#2d3e2f;display:flex;flex-direction:column;min-height:100vh;}
        .header{background:linear-gradient(135deg,#2E7D32,#1B5E20);color:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;}
        .header h1{font-size:1.8rem;font-weight:600;}.header h1 i{margin-right:10px;color:#FB8C00;}
        .header .user-info{display:flex;align-items:center;gap:1.5rem;}
        .header a{color:#fff;text-decoration:none;padding:.5rem 1rem;border-radius:30px;background:rgba(255,255,255,.15);transition:.3s;font-weight:500;}
        .header a:hover{background:#FB8C00;}
        .container{display:flex;flex:1;}
        .sidebar{width:260px;background:#1B5E20;padding-top:2rem;flex-shrink:0;}
        .sidebar ul{list-style:none;}.sidebar li{margin-bottom:.3rem;}
        .sidebar a{display:block;padding:1rem 1.8rem;color:#f0f7e6;text-decoration:none;transition:.3s;font-weight:500;border-left:4px solid transparent;}
        .sidebar a i{margin-right:12px;width:22px;color:#FB8C00;}
        .sidebar a:hover,.sidebar a.active{background:rgba(251,140,0,.2);border-left-color:#FB8C00;color:#fff;}
        .sidebar a.active{background:#FB8C00;color:#fff;}.sidebar a.active i{color:#fff;}
        .main{flex:1;padding:2rem;max-width:900px;}
        .back-btn{display:inline-flex;align-items:center;gap:8px;color:#2E7D32;text-decoration:none;font-weight:600;margin-bottom:1.5rem;padding:.5rem 1rem;border-radius:30px;background:#e8f5e9;transition:.2s;}
        .back-btn:hover{background:#2E7D32;color:#fff;}
        .recipe-card{background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 8px 24px rgba(0,40,0,.1);}
        .recipe-hero{position:relative;height:280px;overflow:hidden;}
        .recipe-hero img{width:100%;height:100%;object-fit:cover;}
        .recipe-hero-overlay{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.7));padding:1.5rem;}
        .recipe-hero-overlay h2{color:#fff;font-size:1.8rem;font-weight:700;}
        .category-badge{display:inline-block;background:#FB8C00;color:#fff;padding:.3rem .9rem;border-radius:20px;font-size:.8rem;font-weight:600;margin-top:.4rem;}
        .recipe-body{padding:2rem;}
        .meta-row{display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #e8f0e8;}
        .meta-item{display:flex;align-items:center;gap:8px;color:#5f6b5f;font-size:.9rem;}
        .meta-item i{color:#FB8C00;font-size:1.1rem;}
        .section-title{font-size:1.1rem;font-weight:700;color:#1B5E20;margin-bottom:1rem;display:flex;align-items:center;gap:8px;}
        .section-title i{color:#FB8C00;}
        .ing-table{width:100%;border-collapse:collapse;margin-bottom:1.5rem;}
        .ing-table th{background:#2E7D32;color:#fff;padding:.7rem 1rem;text-align:left;font-size:.85rem;}
        .ing-table td{padding:.7rem 1rem;border-bottom:1px solid #f0f0f0;font-size:.9rem;}
        .ing-table tr:last-child td{border-bottom:none;}
        .instructions{background:#f9fff9;border-radius:16px;padding:1.2rem;line-height:1.8;color:#444;font-size:.95rem;border-left:4px solid #2E7D32;}
        .add-plan-btn{background:#2E7D32;color:#fff;border:none;padding:.9rem 2rem;border-radius:40px;font-weight:600;font-size:1rem;cursor:pointer;transition:.3s;display:inline-flex;align-items:center;gap:8px;margin-top:1.5rem;}
        .add-plan-btn:hover{background:#1B5E20;transform:translateY(-2px);}
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);justify-content:center;align-items:center;z-index:9999;}
        .modal.open{display:flex;}
        .modal-box{background:#fff;padding:2rem;border-radius:24px;width:420px;max-width:92%;}
        .modal-box h3{color:#1B5E20;margin-bottom:1rem;}
        .modal-box label{display:block;font-weight:600;color:#2E7D32;margin:.8rem 0 .3rem;font-size:.9rem;}
        .modal-box select{width:100%;padding:.8rem;border:1px solid #d0e0d0;border-radius:40px;font-size:.95rem;outline:none;}
        .modal-btns{display:flex;gap:10px;margin-top:1.5rem;}
        .modal-btns button{flex:1;padding:.8rem;border:none;border-radius:40px;font-weight:600;cursor:pointer;transition:.2s;}
        .btn-cancel{background:#e0e0e0;color:#333;}.btn-save{background:#2E7D32;color:#fff;}
        .toast{position:fixed;bottom:20px;right:20px;background:#2E7D32;color:#fff;padding:1rem 1.8rem;border-radius:40px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,.2);animation:slideIn .3s ease;display:flex;align-items:center;gap:8px;}
        @keyframes slideIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
        footer{background:#1B5E20;color:#fff;text-align:center;padding:1rem;margin-top:auto;font-size:.85rem;}
        @media(max-width:768px){.container{flex-direction:column;}.sidebar{width:100%;}.sidebar ul{display:flex;flex-wrap:wrap;}.sidebar a{padding:.7rem 1rem;}.recipe-hero{height:200px;}}
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
            <li><a href="recipes.php" class="active"><i class="fas fa-book-open"></i> Recipes</a></li>
            <li><a href="meals.php"><i class="fas fa-calendar-week"></i> Meal Planner</a></li>
            <li><a href="report.php"><i class="fas fa-chart-line"></i> Report</a></li>
        </ul>
    </div>

    <div class="main">
        <a href="recipes.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Recipes</a>

        <div class="recipe-card">
            <?php
            $fallbacks = [
                'breakfast' => 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?w=900&h=280&fit=crop',
                'lunch'     => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=900&h=280&fit=crop',
                'dinner'    => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=900&h=280&fit=crop',
                'default'   => 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=900&h=280&fit=crop',
            ];
            $cat_key  = strtolower($recipe['category'] ?? '');
            $fallback = $fallbacks[$cat_key] ?? $fallbacks['default'];
            $imgSrc   = !empty($recipe['image']) ? $recipe['image'] : $fallback;
            ?>
            <div class="recipe-hero">
                <img src="<?= e($imgSrc) ?>" alt="<?= e($recipe['name']) ?>" onerror="this.src='<?= $fallback ?>'">
                <div class="recipe-hero-overlay">
                    <h2><?= e($recipe['name']) ?></h2>
                    <span class="category-badge"><i class="fas fa-tag"></i> <?= e($recipe['category'] ?? 'Kenyan') ?></span>
                </div>
            </div>

            <div class="recipe-body">
                <div class="meta-row">
                    <div class="meta-item"><i class="fas fa-users"></i> <?= (int)$recipe['servings'] ?> servings</div>
                    <?php if (!empty($recipe['prep_time'])): ?>
                    <div class="meta-item"><i class="fas fa-clock"></i> <?= (int)$recipe['prep_time'] ?> mins</div>
                    <?php endif; ?>
                    <div class="meta-item"><i class="fas fa-calendar"></i> Added <?= date('d M Y', strtotime($recipe['created_at'])) ?></div>
                </div>

                <?php if ($ingredients && $ingredients->num_rows > 0): ?>
                <div class="section-title"><i class="fas fa-list"></i> Ingredients</div>
                <table class="ing-table">
                    <thead><tr><th>Ingredient</th><th>Quantity</th><th>Unit</th></tr></thead>
                    <tbody>
                    <?php while ($ing = $ingredients->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($ing['name']) ?></td>
                            <td><?= $ing['quantity'] ?></td>
                            <td><?= e($ing['unit']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($recipe['instructions'])): ?>
                <div class="section-title"><i class="fas fa-book"></i> Instructions</div>
                <div class="instructions"><?= nl2br(e($recipe['instructions'])) ?></div>
                <?php endif; ?>

                <button class="add-plan-btn" id="addToPlanBtn">
                    <i class="fas fa-plus-circle"></i> Add to Meal Planner
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add to Plan Modal -->
<div id="planModal" class="modal">
    <div class="modal-box">
        <h3><i class="fas fa-calendar-plus" style="color:#FB8C00;"></i> Add to Meal Planner</h3>
        <p style="color:#5f6b5f;font-size:.9rem;margin-bottom:.5rem;">Adding: <strong><?= e($recipe['name']) ?></strong></p>
        <label>Day</label>
        <select id="planDay">
            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                <option><?= $d ?></option>
            <?php endforeach; ?>
        </select>
        <label>Meal Type</label>
        <select id="planMeal">
            <option>Breakfast</option>
            <option>Lunch</option>
            <option>Dinner</option>
            <option>Fruits</option>
        </select>
        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('planModal').classList.remove('open')">Cancel</button>
            <button class="btn-save" id="confirmPlan"><i class="fas fa-check"></i> Add</button>
        </div>
    </div>
</div>

<footer><i class="fas fa-leaf"></i> &copy; <?= date('Y') ?> Kenyan Meal Planner. Eat well, live well.</footer>

<script>
document.getElementById('addToPlanBtn').addEventListener('click', () => {
    document.getElementById('planModal').classList.add('open');
});
window.addEventListener('click', e => {
    if (e.target === document.getElementById('planModal'))
        document.getElementById('planModal').classList.remove('open');
});

document.getElementById('confirmPlan').addEventListener('click', function() {
    const day  = document.getElementById('planDay').value;
    const meal = document.getElementById('planMeal').value;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';

    fetch('add_to_mealplan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `recipe_id=<?= $recipe['id'] ?>&day=${encodeURIComponent(day)}&meal=${encodeURIComponent(meal)}`
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('planModal').classList.remove('open');
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.background = data.success ? '#2E7D32' : '#d32f2f';
        t.innerHTML = `<i class="fas ${data.success ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${data.success ? 'Added to ' + day + ' ' + meal : (data.message || 'Error')}`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    })
    .catch(() => {})
    .finally(() => {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-check"></i> Add';
    });
});
</script>
</body>
</html>
