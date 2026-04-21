<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// AJAX: update household profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_household_ajax'])) {
    $adults        = (int)($_POST['adults'] ?? 1);
    $children      = (int)($_POST['children'] ?? 0);
    $weekly_budget = (float)($_POST['weekly_budget'] ?? 0);

    $chk = $conn->query("SELECT id FROM household_profiles WHERE user_id=$user_id");
    if ($chk && $chk->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE household_profiles SET adults=?,children=?,weekly_budget=? WHERE user_id=?");
        $stmt->bind_param('iidi', $adults, $children, $weekly_budget, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO household_profiles (user_id,adults,children,weekly_budget) VALUES (?,?,?,?)");
        $stmt->bind_param('iiid', $user_id, $adults, $children, $weekly_budget);
    }
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

// Household profile
$hp = $conn->query("SELECT * FROM household_profiles WHERE user_id=$user_id");
$household = ($hp && $hp->num_rows > 0) ? $hp->fetch_assoc() : ['adults'=>1,'children'=>0,'weekly_budget'=>0];
$adults         = (int)$household['adults'];
$children       = (int)$household['children'];
$weeklyBudget   = (float)$household['weekly_budget'];
$household_size = $adults + $children;

// Budget alternatives
$alternatives = [];
$ar = $conn->query("SELECT * FROM budget_alternatives");
if ($ar) while ($row = $ar->fetch_assoc())
    $alternatives[$row['expensive_ingredient']] = explode(',', $row['cheap_alternatives']);

// This week's meal plan
$week_start  = date('Y-m-d', strtotime('monday this week'));
$days        = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$slots       = ['Breakfast','Lunch','Dinner'];

$mealPlan      = [];
$totalCost     = 0;
$allIngredients = [];

$stmt = $conn->prepare("
    SELECT mp.day_of_week AS day, mp.meal_type, mp.servings AS planned_servings,
           r.id AS recipe_id, r.name AS recipe_name, r.market_price,
           r.calories, r.protein, r.carbs, r.fat, r.servings AS recipe_servings
    FROM meal_plans mp
    JOIN recipes r ON r.id = mp.recipe_id
    WHERE mp.user_id = ? AND mp.week_start = ?
    ORDER BY FIELD(mp.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
             FIELD(mp.meal_type,'Breakfast','Lunch','Dinner','Snack')
");
$stmt->bind_param('is', $user_id, $week_start);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $day  = $row['day'];
    $meal = $row['meal_type'];
    if (!in_array($day, $days) || !in_array($meal, $slots)) continue;
    $mealPlan[$day][$meal] = $row;
    $rs = max(1, (int)$row['recipe_servings']);
    $ps = max(1, (int)($row['planned_servings'] ?: $household_size));
    $scale = $ps / $rs;
    $totalCost += (float)$row['market_price'] * $scale;
    // collect ingredients for suggestions
    $ings = $conn->query("SELECT name FROM ingredients WHERE recipe_id={$row['recipe_id']}");
    if ($ings) while ($i = $ings->fetch_assoc()) $allIngredients[] = strtolower(trim($i['name']));
}
$stmt->close();

$remainingBudget = $weeklyBudget - $totalCost;
$overspent       = $remainingBudget < 0;
$mealsPlanned    = array_sum(array_map('count', $mealPlan));

// Budget-saving suggestions
$budgetSuggestions = [];
if ($overspent && !empty($alternatives)) {
    foreach ($allIngredients as $ing) {
        foreach ($alternatives as $expensive => $cheapList) {
            if (stripos($ing, $expensive) !== false) {
                $budgetSuggestions[] = 'Replace ' . ucfirst($expensive) . ' with ' . implode(' or ', array_map('trim', $cheapList)) . ' to save money.';
            }
        }
    }
    $budgetSuggestions = array_slice(array_unique($budgetSuggestions), 0, 3);
}

// Recipes for display (limit 6, same as before)
$recipe_result = $conn->query("
    SELECT * FROM recipes
    WHERE user_id = $user_id OR user_id IS NULL
    ORDER BY id DESC LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – Kenyan Meal Planner</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#fef9e7;color:#2d3e2f;display:flex;flex-direction:column;min-height:100vh;}

/* HEADER */
.header{background:linear-gradient(135deg,#2E7D32 0%,#1B5E20 100%);color:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 12px rgba(0,40,0,.2);}
.header h1{font-size:1.8rem;font-weight:700;letter-spacing:1px;}
.header h1 i{margin-right:10px;color:#FB8C00;}
.header .user-info{display:flex;align-items:center;gap:1.5rem;}
.header a{color:#fff;text-decoration:none;font-weight:500;padding:.5rem 1rem;border-radius:30px;background:rgba(255,255,255,.15);transition:.3s;}
.header a:hover{background:#FB8C00;}

/* LAYOUT */
.container{display:flex;flex:1;}

/* SIDEBAR */
.sidebar{width:260px;background:#1B5E20;padding-top:2rem;box-shadow:2px 0 10px rgba(0,0,0,.1);flex-shrink:0;}
.sidebar ul{list-style:none;}
.sidebar li{margin-bottom:.3rem;}
.sidebar a{display:block;padding:1rem 1.8rem;color:#f0f7e6;text-decoration:none;transition:.3s;font-weight:500;border-left:4px solid transparent;}
.sidebar a i{margin-right:12px;width:22px;text-align:center;color:#FB8C00;}
.sidebar a:hover,.sidebar a.active{background:rgba(251,140,0,.2);border-left-color:#FB8C00;color:#fff;}
.sidebar a.active{background:#FB8C00;color:#fff;box-shadow:0 4px 8px rgba(0,0,0,.2);}
.sidebar a.active i{color:#fff;}

/* MAIN */
.main{flex:1;padding:2rem;overflow-x:hidden;}

/* QUICK LINKS */
.quick-links{display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;}
.quick-links a,.quick-links button{background:#fff;color:#1B5E20;padding:.8rem 1.8rem;border-radius:40px;text-decoration:none;font-weight:600;box-shadow:0 4px 10px rgba(0,40,0,.1);transition:.3s;border:1px solid rgba(46,125,50,.3);display:inline-flex;align-items:center;gap:8px;font-size:.95rem;cursor:pointer;font-family:inherit;}
.quick-links a i,.quick-links button i{color:#FB8C00;}
.quick-links a:hover,.quick-links button:hover{background:#FB8C00;color:#fff;transform:scale(1.05);}
.quick-links a:hover i,.quick-links button:hover i{color:#fff;}

/* SUGGESTION BANNER */
.suggestions-card{background:#f1f8e9;border-left:4px solid #FB8C00;padding:1rem 1.2rem;border-radius:16px;margin-bottom:1.5rem;}
.suggestions-card h4{color:#2E7D32;margin-bottom:.5rem;}
.suggestions-card ul{margin-left:1.5rem;}
.suggestions-card li{margin-bottom:.3rem;font-size:.9rem;}

/* STAT CARDS */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;margin-bottom:2rem;}
.card{background:#fff;border-radius:20px;padding:1.8rem 1.5rem;box-shadow:0 10px 25px rgba(0,40,0,.08);transition:.3s;border:1px solid rgba(46,125,50,.1);position:relative;overflow:hidden;}
.card::before{content:'';position:absolute;top:0;left:0;width:100%;height:6px;background:linear-gradient(90deg,#2E7D32,#FB8C00);}
.card:hover{transform:translateY(-4px);box-shadow:0 18px 35px rgba(46,125,50,.15);}
.card h3{font-size:1.1rem;color:#1B5E20;margin-bottom:1rem;display:flex;align-items:center;gap:8px;}
.card h3 i{color:#FB8C00;}
.value{font-size:2rem;font-weight:700;color:#2E7D32;}
.small{font-size:.85rem;color:#5f6b5f;margin-top:.4rem;}
.progress-bar{background:#e0e0e0;border-radius:30px;height:12px;overflow:hidden;margin:.5rem 0;}
.progress-fill{height:100%;background:#2E7D32;transition:width .5s;border-radius:30px;}
.progress-fill.warning{background:#FB8C00;}
.progress-fill.danger{background:#E53935;}

/* SECTION TITLE */
.section-title{font-size:1.5rem;margin:2rem 0 1.2rem;color:#1B5E20;border-bottom:4px solid #FB8C00;padding-bottom:.4rem;display:inline-block;font-weight:700;}
.section-title i{color:#FB8C00;margin-right:8px;}

/* RECIPE GRID — matching recipes.php exactly */
.recipe-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:2rem;margin-bottom:2rem;}
.card{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 25px rgba(0,40,0,.08);transition:.3s;border:1px solid rgba(46,125,50,.1);display:flex;flex-direction:column;}
.card:hover{transform:translateY(-8px);box-shadow:0 18px 35px rgba(46,125,50,.15);}
.card-img-wrap{position:relative;overflow:hidden;height:200px;}
.card-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .4s;}
.card:hover .card-img-wrap img{transform:scale(1.05);}
.category-badge-overlay{position:absolute;top:10px;left:10px;background:rgba(46,125,50,.88);color:#fff;padding:.25rem .8rem;border-radius:20px;font-size:.72rem;font-weight:600;backdrop-filter:blur(4px);}
.card-content{padding:1.2rem;flex:1;display:flex;flex-direction:column;}
.card-content h4{font-size:1.2rem;color:#1B5E20;margin-bottom:.8rem;font-weight:700;}
.card-content p{color:#5f6b5f;margin-bottom:.8rem;line-height:1.5;flex:1;font-size:.88rem;}
.recipe-meta{display:flex;gap:.8rem;font-size:.75rem;color:#7f8c7f;margin-bottom:1rem;flex-wrap:wrap;}
.recipe-meta i{margin-right:3px;color:#FB8C00;}
.add-recipe-btn{background:#2E7D32;color:#fff;border:none;padding:.8rem;border-radius:40px;font-weight:600;font-size:.88rem;cursor:pointer;transition:.3s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:auto;}
.add-recipe-btn:hover:not(:disabled){background:#1B5E20;transform:scale(1.02);}
.add-recipe-btn:disabled{background:#b0c4b0;cursor:not-allowed;}

/* EMPTY STATE */
.empty-state{text-align:center;padding:3rem;background:#fff;border-radius:20px;grid-column:1/-1;}
.empty-state i{font-size:3rem;color:#FB8C00;display:block;margin-bottom:1rem;}

/* MODAL */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);justify-content:center;align-items:center;z-index:9999;}
.modal.open{display:flex;}
.modal-box{background:#fff;padding:2rem;border-radius:24px;width:440px;max-width:92%;box-shadow:0 20px 40px rgba(0,40,0,.3);}
.modal-box h3{color:#1B5E20;margin-bottom:1rem;font-size:1.4rem;}
.modal-box label{display:block;margin:.8rem 0 .3rem;font-weight:600;color:#2E7D32;font-size:.9rem;}
.modal-box input,.modal-box select{width:100%;padding:.8rem 1rem;border:1px solid #d0e0d0;border-radius:40px;font-size:.95rem;outline:none;font-family:inherit;}
.modal-box input:focus,.modal-box select:focus{border-color:#2E7D32;box-shadow:0 0 0 3px rgba(46,125,50,.15);}
.modal-buttons{display:flex;justify-content:space-between;margin-top:1.5rem;gap:10px;}
.modal-buttons button{flex:1;padding:.8rem;border:none;border-radius:40px;font-weight:600;cursor:pointer;transition:.3s;font-family:inherit;font-size:.95rem;}
.modal-buttons .btn-cancel{background:#e0e0e0;color:#333;}
.modal-buttons .btn-save{background:#2E7D32;color:#fff;}
.modal-buttons button:hover{transform:scale(1.02);}

/* TOAST */
.toast{position:fixed;bottom:20px;right:20px;background:#2E7D32;color:#fff;padding:1rem 1.8rem;border-radius:40px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,.2);animation:slideIn .3s ease;display:flex;align-items:center;gap:8px;}
@keyframes slideIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

/* FOOTER */
footer{background:#1B5E20;color:#fff;text-align:center;padding:1.5rem 2rem;margin-top:auto;}
.footer-mission{display:flex;justify-content:center;align-items:center;gap:2.5rem;flex-wrap:wrap;margin-bottom:.8rem;}
.footer-mission-item{display:flex;align-items:center;gap:8px;font-size:.95rem;}
.footer-mission-item i{font-size:1.3rem;color:#FB8C00;background:rgba(255,255,255,.1);padding:.5rem;border-radius:50%;}
.footer-copyright{opacity:.8;font-size:.85rem;}

@media(max-width:768px){
    .container{flex-direction:column;}
    .sidebar{width:100%;}
    .sidebar ul{display:flex;flex-wrap:wrap;}
    .sidebar a{padding:.7rem 1rem;font-size:.85rem;}
    .header{flex-direction:column;gap:.5rem;text-align:center;}
    .recipe-grid{grid-template-columns:1fr;}
    .cards{grid-template-columns:1fr;}
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
    <!-- SIDEBAR -->
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="recipes.php"><i class="fas fa-book-open"></i> Recipes</a></li>
            <li><a href="meals.php"><i class="fas fa-calendar-week"></i> Meal Planner</a></li>
            <li><a href="report.php"><i class="fas fa-chart-line"></i> Report</a></li>
        </ul>
    </div>

    <!-- MAIN -->
    <div class="main">

        <!-- Quick Links -->
        <div class="quick-links">
            <button id="openHouseholdModalBtn"><i class="fas fa-edit"></i> Update Household</button>
            <a href="recipes.php"><i class="fas fa-book-open"></i> Browse Recipes</a>
            <a href="meals.php"><i class="fas fa-plus-circle"></i> Add Meals</a>
            <a href="report.php"><i class="fas fa-chart-line"></i> View Report</a>
        </div>

        <!-- Budget suggestions -->
        <?php if ($overspent && !empty($budgetSuggestions)): ?>
        <div class="suggestions-card">
            <h4><i class="fas fa-lightbulb"></i> Budget-Saving Ideas</h4>
            <ul>
                <?php foreach ($budgetSuggestions as $s): ?>
                    <li><i class="fas fa-check-circle" style="color:#2E7D32;"></i> <?= e($s) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="cards">
            <div class="card">
                <h3><i class="fas fa-wallet"></i> Weekly Budget</h3>
                <div class="value" id="weeklyBudgetDisplay"><?= formatKES($weeklyBudget) ?></div>
                <?php
                    $pct = $weeklyBudget > 0 ? min(100, ($totalCost / $weeklyBudget) * 100) : 0;
                    $barClass = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : '');
                ?>
                <div class="progress-bar">
                    <div class="progress-fill <?= $barClass ?>" id="budgetProgressFill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="small" id="budgetSummary">
                    Spent: <?= formatKES($totalCost) ?> &nbsp;|&nbsp; Left: <?= formatKES($remainingBudget) ?>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-users"></i> Household Size</h3>
                <div class="value" id="householdSizeDisplay"><?= $household_size ?></div>
                <div class="small" id="householdComposition"><?= $adults ?> Adult(s) · <?= $children ?> Child(ren)</div>
            </div>

            <div class="card">
                <h3><i class="fas fa-utensils"></i> Meals Planned</h3>
                <div class="value"><?= $mealsPlanned ?> / 21</div>
                <div class="small">This week (<?= date('d M', strtotime($week_start)) ?>)</div>
            </div>
        </div>

        <!-- Recipes Section -->
        <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:1rem;">
            <h2 class="section-title"><i class="fas fa-utensil-spoon"></i> Kenyan Recipes</h2>
            <a href="recipes.php" style="color:#FB8C00;text-decoration:none;font-weight:600;background:#fff;padding:.5rem 1rem;border-radius:30px;box-shadow:0 2px 6px rgba(0,0,0,.05);">
                <i class="fas fa-arrow-right"></i> View All
            </a>
        </div>

        <div class="recipe-grid">
        <?php 
        $fallbacks = [
            'breakfast' => 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?w=400&h=220&fit=crop',
            'lunch'     => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&h=220&fit=crop',
            'dinner'    => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=400&h=220&fit=crop',
            'fruits'    => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=400&h=220&fit=crop',
            'default'   => 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=400&h=220&fit=crop',
        ];

        if ($recipe_result && $recipe_result->num_rows > 0):
            while ($recipe = $recipe_result->fetch_assoc()):
                $cat_key  = strtolower($recipe['category'] ?? '');
                $fallback = $fallbacks[$cat_key] ?? $fallbacks['default'];

                // Robust image path detection (same as recipes.php)
                $imgSrc = $fallback;
                if (!empty($recipe['image'])) {
                    $candidates = [
                        'uploads/recipes/' . $recipe['image'],
                        'assets/uploads/'  . $recipe['image'],
                        $recipe['image'],
                    ];
                    foreach ($candidates as $p) {
                        if (file_exists(__DIR__ . '/' . $p)) {
                            $imgSrc = $p . '?v=' . filemtime(__DIR__ . '/' . $p);
                            break;
                        }
                    }
                }
        ?>
        <div class="card">
            <div class="card-img-wrap">
                <img src="<?= e($imgSrc) ?>" alt="<?= e($recipe['name']) ?>"
                     onerror="this.src='<?= $fallback ?>'">
                <span class="category-badge-overlay"><i class="fas fa-tag"></i> <?= e($recipe['category'] ?? 'Kenyan') ?></span>
            </div>
            <div class="card-content">
                <h4><?= e($recipe['name']) ?></h4>
                <button class="add-recipe-btn"
                    data-id="<?= $recipe['id'] ?>"
                    data-name="<?= e($recipe['name']) ?>">
                    <i class="fas fa-plus-circle"></i> Add to Meal Planner
                </button>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state">
            <i class="fas fa-utensils"></i>
            <p style="margin-bottom:1rem;">No recipes yet.</p>
            <a href="recipes.php" style="background:#FB8C00;color:#fff;padding:.8rem 1.5rem;border-radius:40px;text-decoration:none;font-weight:600;">
                <i class="fas fa-plus-circle"></i> Add Your First Recipe
            </a>
        </div>
        <?php endif; ?>
        </div>

    </div><!-- /main -->
</div><!-- /container -->

<!-- Household Modal -->
<div id="householdModal" class="modal">
    <div class="modal-box">
        <h3><i class="fas fa-users" style="color:#FB8C00;"></i> Update Household Profile</h3>
        <form id="householdForm">
            <label><i class="fas fa-user"></i> Adults (18+)</label>
            <input type="number" name="adults" id="modalAdults" min="0" value="<?= $adults ?>" required>
            <label><i class="fas fa-child"></i> Children (under 18)</label>
            <input type="number" name="children" id="modalChildren" min="0" value="<?= $children ?>" required>
            <label><i class="fas fa-coins"></i> Weekly Budget (KES)</label>
            <input type="number" name="weekly_budget" id="modalBudget" step="50" min="0" value="<?= $weeklyBudget ?>" required>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeHouseholdModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Add to Meal Planner Modal -->
<div id="mealModal" class="modal">
    <div class="modal-box">
        <h3><i class="fas fa-calendar-plus" style="color:#FB8C00;"></i> Add to Meal Planner</h3>
        <p id="recipeText" style="color:#5f6b5f;margin-bottom:1rem;font-size:.9rem;"></p>
        <label><i class="fas fa-calendar-day"></i> Select Day</label>
        <select id="mealDay">
            <?php foreach ($days as $d): ?>
                <option value="<?= $d ?>"><?= $d ?></option>
            <?php endforeach; ?>
        </select>
        <label><i class="fas fa-utensils"></i> Meal Type</label>
        <select id="mealType">
            <option>Breakfast</option>
            <option>Lunch</option>
            <option>Dinner</option>
            <option>Fruits</option>
        </select>
        <div class="modal-buttons">
            <button class="btn-cancel" onclick="closeMealModal()"><i class="fas fa-times"></i> Cancel</button>
            <button class="btn-save" id="confirmAdd"><i class="fas fa-check"></i> Add to Plan</button>
        </div>
    </div>
</div>

<footer>
    <div class="footer-mission">
        <div class="footer-mission-item"><i class="fas fa-trash-alt"></i><span>Reduce Food Waste</span></div>
        <div class="footer-mission-item"><i class="fas fa-balance-scale"></i><span>Eat Balanced Meals</span></div>
        <div class="footer-mission-item"><i class="fas fa-coins"></i><span>Budget Wisely</span></div>
    </div>
    <div class="footer-copyright"><i class="fas fa-leaf"></i> &copy; <?= date('Y') ?> Kenyan Meal Planner. Eat well, live well. <i class="fas fa-leaf"></i></div>
</footer>

<script>
// Toast
function showToast(msg, isError = false) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.style.background = isError ? '#d32f2f' : '#2E7D32';
    t.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Household modal
const householdModal = document.getElementById('householdModal');

document.getElementById('openHouseholdModalBtn').addEventListener('click', () => {
    householdModal.classList.add('open');
});

function closeHouseholdModal() { householdModal.classList.remove('open'); }

document.getElementById('householdForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('update_household_ajax', '1');
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const a  = parseInt(document.getElementById('modalAdults').value);
                const c  = parseInt(document.getElementById('modalChildren').value);
                const bg = parseFloat(document.getElementById('modalBudget').value);
                const spent = <?= $totalCost ?>;
                const left  = bg - spent;
                const pct   = bg > 0 ? Math.min(100, (spent / bg) * 100) : 0;
                const fill  = document.getElementById('budgetProgressFill');
                fill.style.width = pct + '%';
                fill.className   = 'progress-fill' + (pct >= 100 ? ' danger' : pct >= 80 ? ' warning' : '');
                document.getElementById('weeklyBudgetDisplay').textContent = 'KES ' + bg.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                document.getElementById('budgetSummary').textContent = `Spent: KES ${spent.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})} | Left: KES ${left.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`;
                document.getElementById('householdSizeDisplay').textContent = a + c;
                document.getElementById('householdComposition').textContent = `${a} Adult(s) · ${c} Child(ren)`;
                showToast('Household profile updated.');
                closeHouseholdModal();
            } else {
                showToast('Update failed.', true);
            }
        })
        .catch(() => showToast('Network error.', true));
});

// Meal planner modal
const mealModal = document.getElementById('mealModal');
let selectedRecipeId = null, selectedButton = null;

document.querySelectorAll('.add-recipe-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        selectedRecipeId = this.dataset.id;
        selectedButton   = this;
        document.getElementById('recipeText').innerHTML =
            `Adding "<strong>${escapeHtml(this.dataset.name)}</strong>" to your meal plan`;
        mealModal.classList.add('open');
    });
});

function closeMealModal() {
    mealModal.classList.remove('open');
    selectedRecipeId = null;
    selectedButton   = null;
}

document.getElementById('confirmAdd').addEventListener('click', function() {
    if (!selectedRecipeId) return;
    const day  = document.getElementById('mealDay').value;
    const meal = document.getElementById('mealType').value;
    const btn  = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Adding…';

    fetch('add_to_mealplan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `recipe_id=${encodeURIComponent(selectedRecipeId)}&day=${encodeURIComponent(day)}&meal=${encodeURIComponent(meal)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (selectedButton) {
                selectedButton.innerHTML = '<i class="fas fa-check"></i> Added ✓';
                selectedButton.disabled  = true;
            }
            showToast(`Added to ${day}'s ${meal}`);
            closeMealModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Error adding recipe.', true);
        }
    })
    .catch(() => showToast('Network error.', true))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Add to Plan';
    });
});

// Close modals on backdrop click / Escape
window.addEventListener('click', e => {
    if (e.target === householdModal) closeHouseholdModal();
    if (e.target === mealModal)      closeMealModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeHouseholdModal(); closeMealModal(); }
});
</script>
</body>
</html>
