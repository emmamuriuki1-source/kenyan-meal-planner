<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $error = 'No account found with that email address.';
        } else {
            // Generate a secure token
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Store token (create table if needed)
            $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");

            // Delete old tokens for this user
            $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $del->bind_param('i', $user['id']);
            $del->execute();

            // Insert new token
            $ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param('iss', $user['id'], $token, $expires);
            $ins->execute();

            $reset_link = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
            $success = $reset_link;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password â€“ Kenyan Meal Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --primary:#2E7D32; --primary-dark:#1B5E20; --accent:#FB8C00; --bg:#FEF9E7; --card:#fff; --text:#2D3E2F; --text-light:#5F6B5F; --border:#E8F0E8; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:1rem; }
        .card { background:var(--card); border-radius:24px; padding:2.5rem 2rem; width:100%; max-width:420px; box-shadow:0 12px 40px rgba(0,0,0,0.1); }
        .logo { text-align:center; margin-bottom:1.5rem; }
        .logo i { font-size:2.5rem; color:var(--accent); }
        .logo h1 { font-size:1.5rem; font-weight:700; color:var(--primary-dark); margin-top:0.5rem; }
        .logo p { font-size:0.85rem; color:var(--text-light); margin-top:0.3rem; }
        .form-group { margin-bottom:1.2rem; }
        .form-group label { display:block; font-size:0.85rem; font-weight:600; color:var(--text); margin-bottom:0.4rem; }
        .form-group input { width:100%; padding:0.75rem 1rem; border:1.5px solid var(--border); border-radius:12px; font-family:inherit; font-size:0.95rem; transition:border 0.2s; }
        .form-group input:focus { outline:none; border-color:var(--primary); }
        .btn { width:100%; padding:0.85rem; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:white; border:none; border-radius:12px; font-size:1rem; font-weight:600; cursor:pointer; transition:opacity 0.2s; }
        .btn:hover { opacity:0.9; }
        .alert { padding:0.9rem 1rem; border-radius:12px; margin-bottom:1.2rem; font-size:0.88rem; }
        .alert-error { background:#ffebee; border-left:4px solid #e53935; color:#c62828; }
        .alert-success { background:#e8f5e9; border-left:4px solid var(--primary); color:var(--primary-dark); }
        .reset-link { word-break:break-all; font-size:0.82rem; background:#f5f5f5; padding:0.6rem 0.8rem; border-radius:8px; margin-top:0.6rem; display:block; color:var(--primary); font-weight:600; }
        .back-link { text-align:center; margin-top:1.2rem; font-size:0.85rem; color:var(--text-light); }
        .back-link a { color:var(--primary); font-weight:600; text-decoration:none; }
        .back-link a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <i class="fas fa-utensils"></i>
        <h1>Forgot Password?</h1>
        <p>Enter your email to get a reset link</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Reset link generated. Copy and open it in your browser:
            <a class="reset-link" href="<?= htmlspecialchars($success) ?>"><?= htmlspecialchars($success) ?></a>
            <small style="display:block;margin-top:6px;color:#555;">This link expires in 24 hours.</small>
        </div>
    <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
        </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>
</body>
</html>

