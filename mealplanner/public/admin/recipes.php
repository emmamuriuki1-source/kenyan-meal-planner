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

$success = '';
$error   = '';

// Delete recipe
if (isset($_GET['delete'])) {
    $rid  = (int)$_GET['delete'];
    $conn->query("DELETE FROM recipes WHERE id=$rid");
    header('Location: recipes.php');
    exit;
}

// Edit recipe (POST) with image upload – now only updates name, category, image, and ingredients
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_recipe') {
    $rid          = (int)$_POST['edit_id'];
    $name         = trim($_POST['name']         ?? '');
    $category     = trim($_POST['category']     ?? '');

    // Get current recipe data to preserve servings, prep_time, instructions
    $current = $conn->query("SELECT servings, prep_time, instructions, image FROM recipes WHERE id=$rid")->fetch_assoc();
    if (!$current) {
        $error = 'Recipe not found.';
    } else {
        $servings     = (int)$current['servings'];
        $prep_time    = (int)$current['prep_time'];
        $instructions = $current['instructions'];
        $current_image = $current['image'];

        // Image upload handling
        $image_path = $current_image;
        $upload_error = false;

        if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__, 2) . '/public/uploads/recipes/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file = $_FILES['edit_image'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'jfif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // MIME type validation (additional security)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (in_array($ext, $allowed) && in_array($mime, $allowed_mimes) && $file['size'] <= 5 * 1024 * 1024) {
                // Normalize jfif to jpg
                if ($ext === 'jfif') $ext = 'jpg';
                $filename = uniqid() . '.' . $ext;
                $dest = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if ($current_image && file_exists(dirname(__DIR__, 2) . '/public/' . $current_image)) {
                        unlink(dirname(__DIR__, 2) . '/public/' . $current_image);
                    }
                    $image_path = 'uploads/recipes/' . $filename;
                } else {
                    $error = 'Failed to upload image.';
                    $upload_error = true;
                }
            } else {
                $error = 'Invalid image file. Only JPG, JPEG, PNG, GIF, JFIF up to 5MB allowed.';
                $upload_error = true;
            }
        }

        if ($name && $rid && !$upload_error) {
            $stmt = $conn->prepare("UPDATE recipes SET name=?, category=?, image=? WHERE id=?");
            $stmt->bind_param('sssi', $name, $category, $image_path, $rid);
            if ($stmt->execute()) {
                // Update ingredients: delete old, insert new
                $conn->query("DELETE FROM ingredients WHERE recipe_id=$rid");
                $ing_names = $_POST['ing_name'] ?? [];
                $ing_qtys  = $_POST['ing_qty']  ?? [];
                $ing_units = $_POST['ing_unit'] ?? [];
                foreach ($ing_names as $k => $iname) {
                    $iname = trim($iname);
                    if (!$iname) continue;
                    $qty  = (float)($ing_qtys[$k] ?? 0);
                    $unit = trim($ing_units[$k] ?? 'piece');
                    $si   = $conn->prepare("INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES (?,?,?,?)");
                    $si->bind_param('isds', $rid, $iname, $qty, $unit);
                    $si->execute();
                }
                $success = 'Recipe updated successfully.';
            } else {
                $error = 'Failed to update recipe.';
            }
        } elseif (empty($error)) {
            $error = 'Recipe name is required.';
        }
    }
}

// Toggle default (user_id NULL = default/global)
if (isset($_GET['make_default'])) {
    $rid  = (int)$_GET['make_default'];
    $conn->query("UPDATE recipes SET user_id=NULL WHERE id=$rid");
    header('Location: recipes.php');
    exit;
}

// Add recipe (simplified: no servings, prep_time, instructions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_recipe') {
    $name         = trim($_POST['name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $servings     = 1;      // default
    $instructions = '';     // empty
    $is_default   = isset($_POST['is_default']) ? null : (int)$_SESSION['user_id'];

    $image_path = null;
    if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__, 2) . '/public/uploads/recipes/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file = $_FILES['recipe_image'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'jfif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // MIME type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($ext, $allowed) && in_array($mime, $allowed_mimes) && $file['size'] <= 5 * 1024 * 1024) {
            // Normalize jfif to jpg
            if ($ext === 'jfif') $ext = 'jpg';
            $filename = uniqid() . '.' . $ext;
            $dest = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $image_path = 'uploads/recipes/' . $filename;
            } else {
                $error = 'Failed to upload image.';
            }
        } else {
            $error = 'Invalid image file. Only JPG, JPEG, PNG, GIF, JFIF up to 5MB allowed.';
        }
    }

    if ($name && empty($error)) {
        $stmt = $conn->prepare("INSERT INTO recipes (user_id, name, category, servings, instructions, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ississ', $is_default, $name, $category, $servings, $instructions, $image_path);
        if ($stmt->execute()) {
            $rid = $conn->insert_id;
            $ing_names = $_POST['ing_name'] ?? [];
            $ing_qtys  = $_POST['ing_qty']  ?? [];
            $ing_units = $_POST['ing_unit'] ?? [];
            foreach ($ing_names as $k => $iname) {
                $iname = trim($iname);
                if (!$iname) continue;
                $qty  = (float)($ing_qtys[$k] ?? 0);
                $unit = trim($ing_units[$k] ?? '');
                $si   = $conn->prepare("INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES (?, ?, ?, ?)");
                $si->bind_param('isds', $rid, $iname, $qty, $unit);
                $si->execute();
            }
            $success = 'Recipe added.';
        } else {
            $error = 'Failed to add recipe.';
        }
    } elseif (empty($error)) {
        $error = 'Recipe name is required.';
    }
}

// Filters
$search   = trim($_GET['search'] ?? '');
$cat_filter = $_GET['category'] ?? 'all';

$where = "WHERE 1=1";
if ($search)              $where .= " AND r.name LIKE '%". $conn->real_escape_string($search) ."%'";
if ($cat_filter !== 'all') $where .= " AND r.category='". $conn->real_escape_string($cat_filter) ."'";

$recipes = $conn->query("
    SELECT r.*, 
        IF(r.user_id IS NULL, 'Default', u.name) AS owner,
        (SELECT COUNT(*) FROM ingredients WHERE recipe_id=r.id) AS ing_count,
        (SELECT COUNT(*) FROM meal_plans WHERE recipe_id=r.id) AS plan_count
    FROM recipes r
    LEFT JOIN users u ON u.id = r.user_id
    $where
    ORDER BY r.user_id IS NULL DESC, r.name
");

$categories = $conn->query("SELECT DISTINCT category FROM recipes ORDER BY category");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Recipes – Admin | Kenyan Meal Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        .admin-container {
            display: flex;
            flex: 1;
        }

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

        .admin-main {
            flex: 1;
            padding: 2rem;
            overflow-x: auto;
        }

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

        .alert {
            padding: 0.8rem 1rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid var(--primary);
            color: var(--primary-dark);
        }
        .alert-error {
            background: #ffebee;
            border-left: 4px solid #e53935;
            color: #c62828;
        }

        .search-bar {
            background: var(--card);
            border-radius: 20px;
            padding: 1rem;
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .search-bar input, .search-bar select {
            padding: 0.6rem 1rem;
            border: 1px solid var(--border);
            border-radius: 40px;
            font-family: inherit;
            flex: 1;
            min-width: 180px;
        }
        .search-bar input:focus, .search-bar select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .card {
            background: var(--card);
            border-radius: 28px;
            padding: 1.2rem 0 0 0;
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            font-size: 0.9rem;
        }
        .badge {
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-green {
            background: #e8f5e9;
            color: var(--primary);
        }
        .badge-blue {
            background: #e3f2fd;
            color: #1565c0;
        }
        .badge-gray {
            background: #f5f5f5;
            color: #6b6b6b;
        }
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            margin: 0 4px;
            padding: 4px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .btn-icon:hover {
            background: var(--border);
        }
        .recipe-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 12px;
            background: #f5f5f5;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: 0.2s;
            z-index: 1000;
        }
        .modal-overlay.open {
            visibility: visible;
            opacity: 1;
        }
        .modal {
            background: var(--card);
            border-radius: 28px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .modal-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-dark);
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
        }
        .modal form {
            padding: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: var(--text);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .ing-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 8px;
            margin-bottom: 8px;
            align-items: center;
        }
        @media (max-width: 768px) {
            .ing-row {
                grid-template-columns: 1fr 1fr 1fr auto;
            }
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
            <li><a href="recipes.php" class="active"><i class="fas fa-book-open"></i> Manage Recipes</a></li>
            <li><a href="meal_plans.php"><i class="fas fa-calendar-alt"></i> Meal Plans</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="top-bar">
            <h2><i class="fas fa-book-open"></i> Manage Recipes</h2>
            <button class="btn-primary" onclick="openModal('addRecipeModal')"><i class="fas fa-plus"></i> Add Recipe</button>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="search-bar">
            <form method="GET" style="display: contents;">
                <input type="text" name="search" placeholder="Search recipe name..." value="<?= htmlspecialchars($search) ?>">
                <select name="category" onchange="this.form.submit()">
                    <option value="all">All Categories</option>
                    <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $cat_filter === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
                <?php if ($search || $cat_filter !== 'all'): ?>
                    <a href="recipes.php" class="btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none;"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Image</th><th>Recipe Name</th><th>Category</th><th>Ingredients</th><th>Times Planned</th><th>Owner</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($recipes->num_rows === 0): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 2rem;">No recipes found.</td></tr>
                        <?php endif; ?>
                        <?php $i = 1; while ($r = $recipes->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <?php if (!empty($r['image'])): ?>
                                    <img src="../<?= htmlspecialchars($r['image']) ?>" class="recipe-thumb" alt="recipe image">
                                <?php else: ?>
                                    <i class="fas fa-utensils" style="font-size: 1.5rem; color: #ccc;"></i>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                            <td><span class="badge badge-blue"><?= htmlspecialchars($r['category']) ?></span></td>
                            <td style="text-align:center;"><?= $r['ing_count'] ?></td>
                            <td style="text-align:center;"><?= $r['plan_count'] ?></td>
                            <td><span class="badge <?= $r['owner'] === 'Default' ? 'badge-green' : 'badge-gray' ?>"><?= htmlspecialchars($r['owner']) ?></span></td>
                            <td style="white-space:nowrap;">
                                <button class="btn-icon btn-edit" title="Edit"
                                    data-id="<?= $r['id'] ?>"
                                    data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                                    data-category="<?= htmlspecialchars($r['category'] ?? '', ENT_QUOTES) ?>"
                                    data-image="<?= htmlspecialchars($r['image'] ?? '', ENT_QUOTES) ?>">
                                    <i class="fas fa-edit" style="color:#1565c0;font-size:1rem;"></i>
                                </button>
                                <a href="recipes.php?delete=<?= $r['id'] ?>" class="btn-icon" title="Delete" onclick="return confirm('Delete this recipe? This cannot be undone.')">
                                    <i class="fas fa-trash-alt" style="color: #e53935;"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add Recipe Modal (simplified) -->
<div class="modal-overlay" id="addRecipeModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Recipe</h3>
            <button class="modal-close" onclick="closeModal('addRecipeModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_recipe">
            <div class="form-group">
                <label>Recipe Name *</label>
                <input type="text" name="name" required placeholder="e.g., Matumbo">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option>Breakfast</option>
                    <option>Lunch</option>
                    <option>Dinner</option>
                    <option>Fruits</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; align-items:center;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
                    <input type="checkbox" name="is_default" checked style="width:16px;height:16px;">
                    Make Default Recipe
                </label>
            </div>
            <div class="form-group">
                <label>Recipe Image</label>
                <input type="file" name="recipe_image" accept="image/*">
                <small style="color: var(--text-light);">JPG, JPEG, PNG, GIF, JFIF up to 5MB</small>
            </div>
            <div style="margin: 1rem 0 0.5rem 0; font-weight: 600; color: var(--primary-dark);">Ingredients</div>
            <div id="ing-list">
                <div class="ing-row">
                    <input type="text"   name="ing_name[]" placeholder="Ingredient">
                    <input type="number" name="ing_qty[]"  placeholder="Qty" step="0.1" min="0">
                    <input type="text"   name="ing_unit[]" placeholder="Unit">
                    <button type="button" class="btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <button type="button" class="btn-outline btn-sm" onclick="addIng()" style="margin-bottom: 14px;"><i class="fas fa-plus"></i> Add Ingredient</button>
            <button type="submit" class="btn-primary btn-block"><i class="fas fa-save"></i> Save Recipe</button>
        </form>
    </div>
</div>

<!-- Edit Recipe Modal (simplified: only name, category, image, ingredients) -->
<div class="modal-overlay" id="editRecipeModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Recipe</h3>
            <button class="modal-close" onclick="closeModal('editRecipeModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_recipe">
            <input type="hidden" name="edit_id" id="editId">
            <div class="form-group">
                <label>Recipe Name *</label>
                <input type="text" name="name" id="editName" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" id="editCategory">
                    <option>Breakfast</option>
                    <option>Lunch</option>
                    <option>Dinner</option>
                    <option>Fruits</option>
                </select>
            </div>
            <div class="form-group">
                <label>Recipe Image</label>
                <input type="file" name="edit_image" accept="image/*">
                <small style="color: var(--text-light);">Leave empty to keep current image. JPG, JPEG, PNG, GIF, JFIF up to 5MB.</small>
            </div>
            <div class="form-group">
                <label>Ingredients</label>
                <div id="editIngList"></div>
                <button type="button" class="btn-outline btn-sm" onclick="addEditIng()" style="margin-top:8px;">+ Add Ingredient</button>
            </div>
            <div style="display:flex; gap:10px; margin-top:1rem;">
                <button type="button" class="btn-sm" style="background:#e0e0e0;color:#333;flex:1;" onclick="closeModal('editRecipeModal')">Cancel</button>
                <button type="submit" class="btn-primary btn-sm" style="flex:2;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    function removeRow(btn) { btn.closest('.ing-row').remove(); }
    function addIng() {
        const list = document.getElementById('ing-list');
        const row = document.createElement('div');
        row.className = 'ing-row';
        row.innerHTML = `
            <input type="text" name="ing_name[]" placeholder="Ingredient">
            <input type="number" name="ing_qty[]" placeholder="Qty" step="0.1" min="0">
            <input type="text" name="ing_unit[]" placeholder="Unit">
            <button type="button" class="btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
        `;
        list.appendChild(row);
    }

    window.onclick = function(event) {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            if (event.target === modal) modal.classList.remove('open');
        });
    }

    document.addEventListener('click', function(e) {
        let btn = e.target.closest('.btn-edit');
        if (btn) {
            openEdit(btn.dataset.id, btn.dataset.name, btn.dataset.category);
        }
    });

    function openEdit(id, name, category) {
        document.getElementById('editId').value = id;
        document.getElementById('editName').value = name;
        let catSelect = document.getElementById('editCategory');
        for (let i = 0; i < catSelect.options.length; i++) {
            if (catSelect.options[i].value === category) { catSelect.selectedIndex = i; break; }
        }
        let list = document.getElementById('editIngList');
        list.innerHTML = '<p style="color:#888;font-size:.85rem;">Loading ingredients...</p>';
        fetch('get_ingredients.php?recipe_id=' + id)
            .then(r => r.json())
            .then(ings => {
                list.innerHTML = '';
                if (ings.length === 0) addEditIng();
                else ings.forEach(ing => addEditIng(ing.name, ing.quantity, ing.unit));
            })
            .catch(() => { list.innerHTML = ''; addEditIng(); });
        document.getElementById('editRecipeModal').classList.add('open');
    }

    function addEditIng(name = '', qty = 1, unit = 'piece') {
        let list = document.getElementById('editIngList');
        let row = document.createElement('div');
        row.className = 'ing-row';
        row.innerHTML = `
            <input type="text" name="ing_name[]" value="${escapeHtml(name)}" placeholder="Ingredient">
            <input type="number" name="ing_qty[]" value="${qty}" step="0.1" min="0" placeholder="Qty">
            <input type="text" name="ing_unit[]" value="${escapeHtml(unit)}" placeholder="Unit">
            <button type="button" style="background:#ffebee;color:#c62828;border:none;border-radius:6px;padding:4px 8px;cursor:pointer;" onclick="this.closest('.ing-row').remove()">✕</button>
        `;
        list.appendChild(row);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
            return c;
        });
    }
</script>
</body>
</html>
