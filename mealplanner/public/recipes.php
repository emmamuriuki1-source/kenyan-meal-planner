<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$uid = $_SESSION['user_id'];

// ========== ADD RECIPE PROCESSING (AJAX) ==========
$response = ['success' => false, 'message' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $name         = trim($_POST['name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $ing_names    = $_POST['ing_name'] ?? [];
    $ing_qtys     = $_POST['ing_qty'] ?? [];
    $ing_units    = $_POST['ing_unit'] ?? [];

    if (!$name) {
        $response['message'] = 'Recipe name is required.';
        echo json_encode($response);
        exit;
    }

    // Handle image upload
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg','jpeg','png','webp','gif','jfif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        // MIME type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($ext, $allowed) || !in_array($mime, $allowed_mimes) || $_FILES['image']['size'] > 3 * 1024 * 1024) {
            $response['message'] = 'Invalid image file. Only JPG, JPEG, PNG, GIF, WEBP, or JFIF up to 3MB allowed.';
            echo json_encode($response);
            exit;
        }
        
        // Normalize jfif to jpg
        if ($ext === 'jfif') $ext = 'jpg';
        
        $upload_dir = __DIR__ . '/assets/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $image_name = uniqid('recipe_') . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name)) {
            // Store full relative path from the public directory
            $image_path = 'assets/uploads/' . $image_name;
        } else {
            $response['message'] = 'Failed to upload image.';
            echo json_encode($response);
            exit;
        }
    }

    // Insert recipe with default values for removed fields
    $stmt = $conn->prepare("INSERT INTO recipes (user_id, name, category, servings, instructions, image, prep_time, market_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $servings = 1;
        $instructions = '';
        $prep_time = 0;
        $market_price = 0;
        $stmt->bind_param('ississii', $uid, $name, $category, $servings, $instructions, $image_path, $prep_time, $market_price);
        if ($stmt->execute()) {
            $rid = $conn->insert_id;

            // Insert ingredients
            foreach ($ing_names as $k => $iname) {
                $iname = trim($iname);
                if (!$iname) continue;
                $qty  = (float)($ing_qtys[$k] ?? 0);
                $unit = trim($ing_units[$k] ?? 'piece');
                $si   = $conn->prepare("INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES (?, ?, ?, ?)");
                if ($si) {
                    $si->bind_param('isds', $rid, $iname, $qty, $unit);
                    $si->execute();
                    $si->close();
                }
            }

            $response['success'] = true;
            $response['message'] = 'Recipe created successfully!';
        } else {
            $response['message'] = 'Database error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }

    echo json_encode($response);
    exit;
}
// ========== END ADD RECIPE PROCESSING ==========

$search   = trim($_GET['search']   ?? '');
$category = strtolower(trim($_GET['category'] ?? 'all'));

$mc = $conn->query("SELECT COUNT(*) AS c FROM meal_plans WHERE user_id=$uid");
$meal_count = $mc ? (int)$mc->fetch_assoc()['c'] : 0;

$where = "WHERE (r.user_id=$uid OR r.user_id IS NULL)";
if ($category !== 'all') $where .= " AND LOWER(r.category)='".$conn->real_escape_string($category)."'";
if ($search)             $where .= " AND r.name LIKE '%".$conn->real_escape_string($search)."%'";

$result = $conn->query("SELECT r.* FROM recipes r $where ORDER BY FIELD(LOWER(r.category),'breakfast','lunch','dinner','fruits'), r.name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recipes – Kenyan Meal Planner</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* All styles remain unchanged from previous version */
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Poppins','Segoe UI',sans-serif;background:#fef9e7;color:#2d3e2f;display:flex;flex-direction:column;min-height:100vh;}
.header{background:linear-gradient(135deg,#2E7D32,#1B5E20);color:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 12px rgba(0,40,0,.2);}
.header h1{font-size:2rem;font-weight:600;letter-spacing:1px;}.header h1 i{margin-right:10px;color:#FB8C00;}
.header .user-info{display:flex;align-items:center;gap:1.5rem;}
.header a{color:#fff;text-decoration:none;font-weight:500;padding:.5rem 1rem;border-radius:30px;background:rgba(255,255,255,.15);transition:.3s;}
.header a:hover{background:#FB8C00;}
.container{display:flex;flex:1;}
.sidebar{width:260px;background:#1B5E20;padding-top:2rem;box-shadow:2px 0 10px rgba(0,0,0,.1);}
.sidebar ul{list-style:none;}.sidebar li{margin-bottom:.5rem;}
.sidebar a{display:block;padding:1rem 1.8rem;color:#f0f7e6;text-decoration:none;transition:.3s;font-weight:500;border-left:4px solid transparent;}
.sidebar a i{margin-right:12px;width:24px;text-align:center;color:#FB8C00;}
.sidebar a:hover,.sidebar a.active{background:rgba(251,140,0,.2);border-left-color:#FB8C00;color:#fff;}
.sidebar a.active{background:#FB8C00;color:#fff;}.sidebar a.active i{color:#fff;}
.main{flex:1;padding:2rem;}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;}
.top-bar h2{font-size:2rem;color:#1B5E20;border-bottom:4px solid #FB8C00;padding-bottom:.5rem;display:inline-block;}
.create-btn{background:#FB8C00;color:#fff;padding:.8rem 2rem;border-radius:40px;text-decoration:none;font-weight:600;box-shadow:0 4px 10px rgba(0,40,0,.1);transition:.3s;display:inline-flex;align-items:center;gap:8px;cursor:pointer;}
.create-btn:hover{background:#E65100;transform:scale(1.05);}
.category-filters{display:flex;flex-wrap:wrap;gap:.8rem;margin-bottom:1.5rem;}
.category-filters a{padding:.6rem 1.5rem;background:#fff;border-radius:40px;text-decoration:none;color:#2E7D32;font-weight:500;box-shadow:0 2px 6px rgba(0,0,0,.05);transition:.3s;border:1px solid rgba(46,125,50,.2);}
.category-filters a:hover{background:#FB8C00;color:#fff;border-color:#FB8C00;}
.category-filters a.active{background:#2E7D32;color:#fff;border-color:#2E7D32;}
.search-form{display:flex;gap:.8rem;margin-bottom:2rem;}
.search-form input{flex:1;padding:.8rem 1.2rem;border:1px solid #d0e0d0;border-radius:40px;font-size:1rem;outline:none;transition:.3s;}
.search-form input:focus{border-color:#2E7D32;box-shadow:0 0 0 3px rgba(46,125,50,.1);}
.search-form button{background:#2E7D32;color:#fff;border:none;padding:0 2rem;border-radius:40px;font-weight:600;cursor:pointer;transition:.3s;display:flex;align-items:center;gap:8px;}
.search-form button:hover{background:#1B5E20;}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:2rem;}
.card{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 25px rgba(0,40,0,.08);transition:.3s;border:1px solid rgba(46,125,50,.1);display:flex;flex-direction:column;}
.card:hover{transform:translateY(-8px);box-shadow:0 18px 35px rgba(46,125,50,.15);}
.card-img-wrap{position:relative;overflow:hidden;height:200px;}
.card-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .4s;}
.card:hover .card-img-wrap img{transform:scale(1.05);}
.category-badge-overlay{position:absolute;top:10px;left:10px;background:rgba(46,125,50,.88);color:#fff;padding:.25rem .8rem;border-radius:20px;font-size:.72rem;font-weight:600;backdrop-filter:blur(4px);}
.card-content{padding:1.5rem;flex:1;display:flex;flex-direction:column;}
.card-content h3{font-size:1.2rem;color:#1B5E20;margin-bottom:.8rem;}
.add-btn{background:#2E7D32;color:#fff;border:none;padding:.8rem;border-radius:40px;font-weight:600;cursor:pointer;transition:.3s;margin-top:auto;display:flex;align-items:center;justify-content:center;gap:8px;}
.add-btn:hover:not(:disabled){background:#1B5E20;}.add-btn:disabled{background:#b0c4b0;cursor:not-allowed;}
.toast{position:fixed;bottom:20px;right:20px;background:#2E7D32;color:#fff;padding:1rem 2rem;border-radius:40px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,.2);animation:slideIn .3s ease;}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
footer{background:#1B5E20;color:rgba(255,255,255,.9);text-align:center;padding:1rem;margin-top:auto;}

/* Modal for recipe creation */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(5px);justify-content:center;align-items:center;z-index:9999;}
.modal{background:#fff;border-radius:30px;width:650px;max-width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 40px rgba(0,40,0,.3);}
.modal-header{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e0e0e0;padding:1.2rem 1.5rem;}
.modal-header h3{color:#1B5E20;font-size:1.5rem;display:flex;align-items:center;gap:10px;}
.modal-header h3 i{color:#FB8C00;}
.close-modal{background:none;border:none;font-size:1.5rem;cursor:pointer;color:#888;transition:.2s;}
.close-modal:hover{color:#c62828;}
.modal-body{padding:1.5rem;}
.form-group{margin-bottom:1.2rem;}
.form-group label{display:block;font-size:.88rem;font-weight:600;color:#444;margin-bottom:.4rem;}
.form-group label i{color:#FB8C00;margin-right:6px;}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:.8rem 1rem;border:1.5px solid #d0e0d0;border-radius:12px;font-size:.95rem;font-family:inherit;outline:none;transition:.2s;background:#fff;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#2E7D32;box-shadow:0 0 0 3px rgba(46,125,50,.12);}
.img-upload-area{border:2.5px dashed #b0d0b0;border-radius:16px;padding:1.5rem;text-align:center;cursor:pointer;transition:.3s;background:#f9fff9;position:relative;}
.img-upload-area:hover{border-color:#2E7D32;background:#f1f8e9;}
.img-upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.img-upload-area i{font-size:2rem;color:#b0d0b0;display:block;margin-bottom:.6rem;transition:.3s;}
.img-upload-area:hover i{color:#2E7D32;}
.img-upload-area p{color:#7f8c7f;font-size:.9rem;}
.img-upload-area .hint{font-size:.78rem;color:#aaa;margin-top:.3rem;}
#imgPreview{display:none;width:100%;max-height:220px;object-fit:cover;border-radius:12px;margin-top:1rem;border:2px solid #e0f0e0;}
.ing-row{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:.6rem;margin-bottom:.6rem;align-items:center;}
.ing-row input,.ing-row select{padding:.65rem .8rem;border:1.5px solid #d0e0d0;border-radius:10px;font-size:.88rem;font-family:inherit;outline:none;}
.ing-row input:focus,.ing-row select:focus{border-color:#2E7D32;}
.remove-ing{background:#ffebee;color:#c62828;border:none;border-radius:8px;padding:.5rem .7rem;cursor:pointer;font-size:.9rem;transition:.2s;}
.remove-ing:hover{background:#c62828;color:#fff;}
.add-ing-btn{background:#e8f5e9;color:#2E7D32;border:2px solid #2E7D32;border-radius:10px;padding:.6rem 1.2rem;font-weight:600;cursor:pointer;font-size:.88rem;transition:.2s;display:inline-flex;align-items:center;gap:6px;margin-top:.5rem;}
.add-ing-btn:hover{background:#2E7D32;color:#fff;}
.btn-submit{background:linear-gradient(135deg,#2E7D32,#1B5E20);color:#fff;border:none;padding:.9rem 2rem;border-radius:40px;font-size:1rem;font-weight:700;cursor:pointer;transition:.3s;display:inline-flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(46,125,50,.3);margin-top:1rem;}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(46,125,50,.4);}
.btn-cancel{background:#f0f0f0;color:#555;border:none;padding:.9rem 1.8rem;border-radius:40px;font-size:1rem;font-weight:600;cursor:pointer;transition:.2s;margin-right:1rem;}
.btn-cancel:hover{background:#e0e0e0;}
.form-actions{display:flex;justify-content:flex-end;gap:1rem;margin-top:1.5rem;}
.alert{padding:.9rem 1.2rem;border-radius:12px;margin-bottom:1.5rem;font-size:.9rem;display:none;}
.alert-error{background:#ffebee;color:#c62828;border-left:4px solid #c62828;}
.alert-success{background:#e8f5e9;color:#2E7D32;border-left:4px solid #2E7D32;}

/* Meal plan modal (kept existing) */
.meal-modal-box{background:#fff;padding:2rem;border-radius:30px;width:450px;max-width:90%;box-shadow:0 20px 40px rgba(0,40,0,.3);}
.meal-modal-box h3{color:#1B5E20;margin-bottom:1rem;font-size:1.5rem;}
.meal-modal-box p{margin-bottom:1rem;color:#5f6b5f;}
.meal-modal-box label{display:block;margin:.8rem 0 .3rem;font-weight:600;color:#2E7D32;}
.meal-modal-box select{width:100%;padding:.8rem;border:1px solid #d0e0d0;border-radius:40px;font-size:1rem;outline:none;}
.modal-buttons{display:flex;justify-content:space-between;margin-top:1.5rem;gap:10px;}
.modal-buttons button{flex:1;padding:.8rem;border:none;border-radius:40px;font-weight:600;cursor:pointer;transition:.3s;}
.modal-buttons button:first-child{background:#e0e0e0;color:#333;}
.modal-buttons button:last-child{background:#2E7D32;color:#fff;}
.modal-buttons button:hover{transform:scale(1.02);}

@media(max-width:768px){.container{flex-direction:column;}.sidebar{width:100%;}.sidebar ul{display:flex;flex-wrap:wrap;}.sidebar a{padding:1rem;}.header{flex-direction:column;text-align:center;gap:.5rem;}.ing-row{grid-template-columns:1fr 1fr;}.ing-row input:first-child{grid-column:1/-1;}}
</style>
</head>
<body>
<div class="header">
    <h1><i class="fas fa-utensils"></i> Kenyan Meal Planner</h1>
    <div class="user-info">
        <span><i class="fas fa-user-circle"></i> <?= e($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'User') ?></span>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>
<div class="container">
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="recipes.php" class="active"><i class="fas fa-book-open"></i> Recipes</a></li>
            <li><a href="meals.php"><i class="fas fa-calendar-week"></i> Meal Planner <span id="meal-count"><?= $meal_count ?></span></a></li>
            <li><a href="report.php"><i class="fas fa-chart-line"></i> Report</a></li>
        </ul>
    </div>
    <div class="main">
        <div class="top-bar">
            <h2><i class="fas fa-book-open"></i> Kenyan Recipes</h2>
            <button class="create-btn" onclick="openCreateModal()"><i class="fas fa-plus-circle"></i> Create Recipe</button>
        </div>

        <div class="category-filters">
            <a href="recipes.php?category=all"       class="<?= $category==='all'       ?'active':'' ?>">All</a>
            <a href="recipes.php?category=breakfast" class="<?= $category==='breakfast' ?'active':'' ?>">Breakfast</a>
            <a href="recipes.php?category=lunch"     class="<?= $category==='lunch'     ?'active':'' ?>">Lunch</a>
            <a href="recipes.php?category=dinner"    class="<?= $category==='dinner'    ?'active':'' ?>">Dinner</a>
            <a href="recipes.php?category=fruits"    class="<?= $category==='fruits'    ?'active':'' ?>">Fruits</a>
        </div>

        <form method="GET" class="search-form">
            <input type="hidden" name="category" value="<?= e($category) ?>">
            <input type="text" name="search" placeholder="Search recipes by name…" value="<?= e($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>

        <div class="grid">
        <?php
        $fallbacks = [
            'breakfast' => 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?w=400&h=220&fit=crop',
            'lunch'     => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&h=220&fit=crop',
            'dinner'    => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=400&h=220&fit=crop',
            'fruits'    => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=400&h=220&fit=crop',
            'default'   => 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=400&h=220&fit=crop',
        ];

        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $cat_key  = strtolower($row['category'] ?? '');
                $fallback = $fallbacks[$cat_key] ?? $fallbacks['default'];

                // Determine image source – try stored path first, then fallback to old candidates
                $imgSrc = $fallback;
                if (!empty($row['image'])) {
                    // If image path already contains 'uploads/' or 'assets/', use as is
                    if (strpos($row['image'], 'uploads/') === 0 || strpos($row['image'], 'assets/') === 0) {
                        $fullPath = __DIR__ . '/' . $row['image'];
                        if (file_exists($fullPath)) {
                            $imgSrc = $row['image'] . '?v=' . filemtime($fullPath);
                        }
                    } else {
                        // Old format: only filename – try both locations
                        $candidates = [
                            'uploads/recipes/' . $row['image'],
                            'assets/uploads/'  . $row['image'],
                        ];
                        foreach ($candidates as $p) {
                            if (file_exists(__DIR__ . '/' . $p)) {
                                $imgSrc = $p . '?v=' . filemtime(__DIR__ . '/' . $p);
                                break;
                            }
                        }
                    }
                }
        ?>
        <div class="card">
            <div class="card-img-wrap">
                <img src="<?= e($imgSrc) ?>" alt="<?= e($row['name']) ?>"
                     onerror="this.src='<?= $fallback ?>'">
                <span class="category-badge-overlay"><i class="fas fa-tag"></i> <?= e($row['category'] ?? 'Kenyan') ?></span>
            </div>
            <div class="card-content">
                <h3><?= e($row['name']) ?></h3>
                <button class="add-btn" data-id="<?= $row['id'] ?>" data-name="<?= e($row['name']) ?>">
                    <i class="fas fa-plus-circle"></i> Add to Meal Planner
                </button>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="card" style="grid-column:1/-1;text-align:center;padding:3rem;">
            <i class="fas fa-sad-tear" style="font-size:3rem;color:#FB8C00;display:block;margin-bottom:1rem;"></i>
            <p style="font-size:1.2rem;margin-bottom:1rem;">No recipes found.</p>
            <button class="create-btn" onclick="openCreateModal()" style="display:inline-flex;">Create your first recipe</button>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for creating recipe -->
<div id="createRecipeModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Recipe</h3>
            <button class="close-modal" onclick="closeCreateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalAlert" class="alert" style="display:none;"></div>
            <form id="recipeForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-utensil-spoon"></i> Recipe Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Matumbo">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Category</label>
                    <select name="category">
                        <option>Breakfast</option>
                        <option>Lunch</option>
                        <option>Dinner</option>
                        <option>Fruits</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-camera"></i> Recipe Image</label>
                    <div class="img-upload-area" id="uploadArea">
                        <input type="file" name="image" id="imageInput" accept="image/*">
                        <i class="fas fa-cloud-upload-alt" id="uploadIcon"></i>
                        <p id="uploadText">Click or drag to upload a photo</p>
                        <p class="hint">JPG, JPEG, PNG, GIF, JFIF up to 5MB</p>
                    </div>
                    <img id="imgPreview" src="" alt="Preview">
                </div>
                <div style="margin: 1rem 0 0.5rem 0; font-weight: 600; color: #1B5E20;">Ingredients</div>
                <div id="ing-list">
                    <div class="ing-row">
                        <input type="text"   name="ing_name[]" placeholder="Ingredient">
                        <input type="number" name="ing_qty[]"  placeholder="Qty" step="0.1" min="0">
                        <input type="text"   name="ing_unit[]" placeholder="Unit">
                        <button type="button" class="remove-ing" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <button type="button" class="add-ing-btn" onclick="addIng()"><i class="fas fa-plus"></i> Add Ingredient</button>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Recipe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for adding to meal planner (unchanged) -->
<div id="mealModal" class="modal-overlay">
    <div class="meal-modal-box">
        <h3><i class="fas fa-calendar-plus"></i> Add to Meal Planner</h3>
        <p id="recipeText"></p>
        <label><i class="fas fa-sun"></i> Day</label>
        <select id="mealDay">
            <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
            <option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option>
        </select>
        <label><i class="fas fa-utensils"></i> Meal</label>
        <select id="mealType">
            <option value="Breakfast">Breakfast</option>
            <option value="Lunch">Lunch</option>
            <option value="Dinner">Dinner</option>
            <option value="Fruits">Fruits</option>
        </select>
        <div class="modal-buttons">
            <button onclick="closeMealModal()">Cancel</button>
            <button id="confirmAdd"><i class="fas fa-check"></i> Add to Plan</button>
        </div>
    </div>
</div>

<footer><i class="fas fa-leaf"></i> &copy; <?= date('Y') ?> Kenyan Meal Planner. Eat well, live well. <i class="fas fa-leaf"></i></footer>

<script>
// Recipe creation modal controls
function openCreateModal() { document.getElementById('createRecipeModal').style.display = 'flex'; }
function closeCreateModal() { document.getElementById('createRecipeModal').style.display = 'none'; resetForm(); }

function resetForm() {
    document.getElementById('recipeForm').reset();
    document.getElementById('ing-list').innerHTML = `
        <div class="ing-row">
            <input type="text"   name="ing_name[]" placeholder="Ingredient">
            <input type="number" name="ing_qty[]"  placeholder="Qty" step="0.1" min="0">
            <input type="text"   name="ing_unit[]" placeholder="Unit">
            <button type="button" class="remove-ing" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
        </div>
    `;
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imgPreview').src = '';
    document.getElementById('uploadIcon').style.color = '#b0d0b0';
    document.getElementById('uploadText').textContent = 'Click or drag to upload a photo';
    const alertDiv = document.getElementById('modalAlert');
    alertDiv.style.display = 'none';
    alertDiv.className = 'alert';
}

function removeRow(btn) { btn.closest('.ing-row').remove(); }
function addIng() {
    const list = document.getElementById('ing-list');
    const row = document.createElement('div');
    row.className = 'ing-row';
    row.innerHTML = `
        <input type="text"   name="ing_name[]" placeholder="Ingredient">
        <input type="number" name="ing_qty[]"  placeholder="Qty" step="0.1" min="0">
        <input type="text"   name="ing_unit[]" placeholder="Unit">
        <button type="button" class="remove-ing" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
    `;
    list.appendChild(row);
    row.querySelector('input').focus();
}

// Image preview
document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        const preview = document.getElementById('imgPreview');
        preview.src = ev.target.result;
        preview.style.display = 'block';
        document.getElementById('uploadIcon').style.color = '#2E7D32';
        document.getElementById('uploadText').textContent = file.name;
    };
    reader.readAsDataURL(file);
});

// Drag & drop styling
const area = document.getElementById('uploadArea');
area.addEventListener('dragover', e => { e.preventDefault(); area.style.borderColor = '#2E7D32'; area.style.background = '#f1f8e9'; });
area.addEventListener('dragleave', () => { area.style.borderColor = '#b0d0b0'; area.style.background = '#f9fff9'; });

// Form submission via AJAX
document.getElementById('recipeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        const alertDiv = document.getElementById('modalAlert');
        if (data.success) {
            alertDiv.className = 'alert alert-success';
            alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            alertDiv.style.display = 'block';
            setTimeout(() => {
                closeCreateModal();
                location.reload();
            }, 1500);
        } else {
            alertDiv.className = 'alert alert-error';
            alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
            alertDiv.style.display = 'block';
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } catch (err) {
        console.error(err);
        const alertDiv = document.getElementById('modalAlert');
        alertDiv.className = 'alert alert-error';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
        alertDiv.style.display = 'block';
    }
});

// Meal planner modal (unchanged)
function showToast(msg){const t=document.createElement('div');t.className='toast';t.innerHTML=`<i class="fas fa-check-circle"></i> ${msg}`;document.body.appendChild(t);setTimeout(()=>t.remove(),3000);}
let selRecipe=null,selName='',selBtn=null;
document.querySelectorAll('.add-btn').forEach(btn=>{
    btn.addEventListener('click',function(){
        selRecipe=this.dataset.id; selName=this.dataset.name; selBtn=this;
        document.getElementById('recipeText').innerHTML=`<i class="fas fa-utensil-spoon"></i> Adding "<strong>${selName}</strong>" to your meal plan`;
        document.getElementById('mealModal').style.display='flex';
    });
});
function closeMealModal(){document.getElementById('mealModal').style.display='none';}
document.getElementById('confirmAdd').addEventListener('click',function(){
    const day=document.getElementById('mealDay').value;
    const meal=document.getElementById('mealType').value;
    if(!selRecipe)return;
    fetch('add_to_mealplan.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`recipe_id=${selRecipe}&day=${day}&meal=${meal}`})
    .then(r=>r.json()).then(data=>{
        if(data.success){
            if(selBtn){selBtn.innerHTML='<i class="fas fa-check"></i> Added ✓';selBtn.disabled=true;}
            if(document.getElementById('meal-count'))document.getElementById('meal-count').textContent=data.total;
            showToast('Recipe added to Meal Planner');closeMealModal();
        } else showToast(data.message||'Error adding recipe');
    }).catch(()=>showToast('Network error'));
});
window.onclick=e=>{
    if(e.target==document.getElementById('createRecipeModal')) closeCreateModal();
    if(e.target==document.getElementById('mealModal')) closeMealModal();
};
</script>
</body>
</html>
