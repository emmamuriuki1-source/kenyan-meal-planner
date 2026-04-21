<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $loc = ($_SESSION['user_role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'dashboard.php';
    ob_end_clean();
    header('Location: ' . $loc);
    exit;
}

function e($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        // Fetch user including status (active/inactive)
        $stmt = $conn->prepare('SELECT id, name, password, role, status FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $error = 'No account found with that email.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif (isset($user['status']) && $user['status'] !== 'active') {
            // Only allow active users to log in
            $error = 'Your account is inactive. Please contact support.';
        } else {
            // Login successful
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';

            $loc = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'dashboard.php';
            ob_end_clean();
            header('Location: ' . $loc);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kenyan Meal Planner - Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #fafcf7;
    color: #2d3e2f;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
.navbar {
    background: #1B5E20;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 70px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.navbar .brand {
    color: #fff;
    font-size: 1.4rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
.navbar .brand i {
    color: #FB8C00;
    font-size: 1.5rem;
}
.navbar nav {
    display: flex;
    align-items: center;
    gap: 2rem;
}
.navbar nav a {
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
    transition: 0.2s;
    padding: 0.4rem 0;
    border-bottom: 2px solid transparent;
}
.navbar nav a:hover {
    color: #fff;
    border-bottom-color: #FB8C00;
}
.container {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
}
.card {
    background: #fff;
    border-radius: 28px;
    box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 460px;
    padding: 2.2rem 2rem 2.5rem;
}
.card h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1B5E20;
    margin-bottom: 0.25rem;
    text-align: center;
}
.card .sub {
    text-align: center;
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 1.8rem;
}
.alert {
    background: #fff5f5;
    border-left: 4px solid #e53e3e;
    padding: 0.8rem 1rem;
    border-radius: 16px;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #c53030;
}
.form-group {
    margin-bottom: 1.2rem;
}
.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #2c3e2f;
    margin-bottom: 0.4rem;
}
.input-wrapper {
    position: relative;
}
.input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9aa69a;
    font-size: 0.9rem;
}
.input-wrapper input {
    width: 100%;
    padding: 0.85rem 1rem 0.85rem 2.5rem;
    border: 1.5px solid #e2e8e0;
    border-radius: 20px;
    font-size: 0.9rem;
    font-family: inherit;
    transition: all 0.2s;
    background: #fff;
}
.input-wrapper input:focus {
    outline: none;
    border-color: #1B5E20;
    box-shadow: 0 0 0 3px rgba(27,94,32,0.1);
}
.password-toggle {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #9aa69a;
    cursor: pointer;
    font-size: 0.9rem;
}
.btn {
    width: 100%;
    padding: 0.9rem;
    border: none;
    border-radius: 40px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 0.5rem;
    transition: 0.2s;
    font-family: inherit;
    background: linear-gradient(105deg, #1B5E20, #2E7D32);
    color: white;
    box-shadow: 0 6px 14px rgba(27,94,32,0.25);
}
.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.02);
}
.signup-link {
    text-align: center;
    margin-top: 1.8rem;
    font-size: 0.85rem;
    color: #5f6b5f;
}
.signup-link a {
    color: #1B5E20;
    font-weight: 600;
    text-decoration: none;
}
.signup-link a:hover {
    text-decoration: underline;
}
footer {
    background: #1B5E20;
    color: rgba(255,255,255,0.85);
    text-align: center;
    padding: 1rem 2rem;
    font-size: 0.8rem;
    margin-top: auto;
}
footer strong {
    color: #FB8C00;
}
@media (max-width: 520px) {
    .navbar {
        padding: 0 1rem;
    }
    .navbar nav {
        gap: 1rem;
    }
    .card {
        padding: 1.8rem;
    }
}
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="brand"><i class="fas fa-utensils"></i> Kenyan Meal Planner</a>
    <nav>
        <a href="index.php">Home</a>
        <a href="#about">About</a>
        <a href="#contact">Contact</a>
    </nav>
</nav>

<div class="container">
    <div class="card">
        <h2>Welcome Back</h2>
        <p class="sub">Login to continue planning your meals</p>

        <?php if ($error): ?>
        <div class="alert">
            <i class="fas fa-exclamation-triangle"></i> <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleEye"></i>
                    </button>
                </div>
            </div>
            <div style="text-align:right; margin:-0.5rem 0 1rem;"><a href="forgot_password.php" style="font-size:0.82rem; color:#2E7D32; text-decoration:none; font-weight:500;">Forgot password?</a></div>
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="signup-link">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
    </div>
</div>

<footer>
    &copy; <?= date('Y') ?> <strong>Kenyan Meal Planner</strong>. All Rights Reserved.<br>
    Developed by <strong>Emma Nyawira Muriuki</strong> | Murang'a University of Technology
</footer>

<script>
function togglePassword() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('toggleEye');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>
