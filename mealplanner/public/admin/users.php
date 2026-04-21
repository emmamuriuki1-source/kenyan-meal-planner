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

// Toggle status (suspend / activate)
if (isset($_GET['toggle'])) {
    $uid  = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=? AND role='user'");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    header('Location: users.php');
    exit;
}

// Delete user
if (isset($_GET['delete'])) {
    $uid  = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='user'");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    header('Location: users.php');
    exit;
}

// Add user by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $hsize = (int)($_POST['household_size'] ?? 1);
    $role  = in_array($_POST['role'] ?? '', ['user','admin']) ? $_POST['role'] : 'user';

    if (!$name || !$email || !$pass) {
        $error = 'Name, email and password are required.';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = 'Email already exists.';
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("INSERT INTO users (name,email,password,household_size,role) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $name, $email, $hashed, $hsize, $role);
            $stmt->execute() ? $success = 'User added.' : $error = 'Failed to add user.';
        }
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$filter = $_GET['status'] ?? 'all';

$where = "WHERE role='user'";
if ($search) $where .= " AND (name LIKE '%". $conn->real_escape_string($search) ."%' OR email LIKE '%". $conn->real_escape_string($search) ."%')";
if ($filter === 'active')    $where .= " AND status='active'";
if ($filter === 'suspended') $where .= " AND status='suspended'";

$users = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC");
$total = $conn->query("SELECT COUNT(*) AS c FROM users $where")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users – Admin | Kenyan Meal Planner</title>
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
        .btn-orange {
            background: var(--accent);
            color: white;
        }
        .btn-danger {
            background: #e53935;
            color: white;
        }
        .btn-block {
            width: 100%;
        }

        /* Alerts */
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

        /* Search bar */
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

        /* Card */
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
        .badge-red {
            background: #ffebee;
            color: #c62828;
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

        /* Modal */
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
            max-width: 500px;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .form-row {
            display: flex;
            gap: 1rem;
        }
        .form-row .form-group {
            flex: 1;
        }

        @media (max-width: 900px) {
            .admin-sidebar {
                width: 240px;
            }
        }
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
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
            .search-bar input, .search-bar select {
                width: 100%;
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
                <li><a href="users.php" class="active"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="recipes.php"><i class="fas fa-book-open"></i> Manage Recipes</a></li>
                <li><a href="meal_plans.php"><i class="fas fa-calendar-alt"></i> Meal Plans</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </aside>

        <main class="admin-main">
            <div class="top-bar">
                <h2><i class="fas fa-users"></i> Manage Users</h2>
                <button class="btn-primary" onclick="openModal('addUserModal')"><i class="fas fa-user-plus"></i> Add User</button>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Filters & Search -->
            <div class="search-bar">
                <form method="GET" style="display: contents;">
                    <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                    <button type="submit" class="btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search || $filter !== 'all'): ?>
                        <a href="users.php" class="btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none;"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- User Table -->
            <div class="card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Household</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Plans</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users->num_rows === 0): ?>
                                <tr><td colspan="8" style="text-align:center; padding: 2rem;">No users found.</td></tr>
                            <?php endif; ?>
                            <?php $i = 1; while ($u = $users->fetch_assoc()): ?>
                                <?php
                                    $plans = $conn->query("SELECT COUNT(*) AS c FROM meal_plans WHERE user_id={$u['id']}")->fetch_assoc()['c'];
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td style="text-align:center;"><?= $u['household_size'] ?></td>
                                    <td><span class="badge <?= $u['status'] === 'active' ? 'badge-green' : 'badge-red' ?>"><?= $u['status'] ?></span></td>
                                    <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                    <td style="text-align:center;"><?= $plans ?></td>
                                    <td>
                                        <a href="users.php?toggle=<?= $u['id'] ?>" class="btn-icon" title="<?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>" onclick="return confirm('<?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?> this user?')">
                                            <i class="fas <?= $u['status'] === 'active' ? 'fa-pause-circle' : 'fa-play-circle' ?>" style="color: <?= $u['status'] === 'active' ? '#e53935' : '#2e7d32' ?>;"></i>
                                        </a>
                                        <a href="users.php?delete=<?= $u['id'] ?>" class="btn-icon" title="Delete" onclick="return confirm('Permanently delete this user and all their data?')">
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

    <!-- Add User Modal -->
    <div class="modal-overlay" id="addUserModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Jane Wanjiku">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="jane@example.com">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="Min 6 chars">
                    </div>
                    <div class="form-group">
                        <label>Household Size</label>
                        <select name="household_size">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> <?= $i === 1 ? 'person' : 'people' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary btn-block"><i class="fas fa-save"></i> Create User</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('open');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal-overlay');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('open');
                }
            });
        }
    </script>
</body>
</html>