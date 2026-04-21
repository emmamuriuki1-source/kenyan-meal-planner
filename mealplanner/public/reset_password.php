<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';

$error   = '';
$success = '';
$token   = trim($_GET['token'] ?? '');
$valid   = false;
$user_id = null;

// Validate token
if ($token) {
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $valid   = true;
        $user_id = $row['user_id'];
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hashed, $user_id);
        $upd->execute();

        // Mark token as used
        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $mark->bind_param('s', $token);
        $mark->execute();

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – Kenyan Meal Planner</title>
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
        .form-group { margin-bottom:1.2rem; position:relative; }
        .form-group label { display:block; font-size:0.85rem; font-weight:600; color:var(--text); margin-bottom:0.4rem; }
        .form-group input { width:100%; padding:0.75rem 2.8rem 0.75rem 1rem; border:1.5px solid var(--border); border-radius:12px; font-family:inherit; font-size:0.95rem; transition:border 0.2s; }
        .form-group input:focus { outline:none; border-color:var(--primary); }
        .toggle-pw { position:absolute; right:12px; top:34px; background:none; border:none; cursor:pointer; color:var(--text-light); font-size:1rem; }
        .btn { width:100%; padding:0.85rem; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:white; border:none; border-radius:12px; font-size:1rem; font-weight:600; cursor:pointer; transition:opacity 0.2s; }
        .btn:hover { opacity:0.9; }
        .alert { padding:0.9rem 1rem; border-radius:12px; margin-bottom:1.2rem; font-size:0.88rem; }
        .alert-error { background:#ffebee; border-left:4px solid #e53935; color:#c62828; }
        .alert-success { background:#e8f5e9; border-left:4px solid var(--primary); color:var(--primary-dark); }
        .back-link { text-align:center; margin-top:1.2rem; font-size:0.85rem; color:var(--text-light); }
        .back-link a { color:var(--primary); font-weight:600; text-decoration:none; }
        .back-link a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <i class="fas fa-lock"></i>
        <h1>Reset Password</h1>
        <p>Enter your new password below</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Password reset successfully!
        </div>
        <div class="back-link" style="margin-top:0;">
            <a href="login.php" class="btn" style="display:inline-block;text-decoration:none;text-align:center;padding:0.85rem;">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        </div>
    <?php elseif (!$token || !$valid): ?>
        <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error ?: 'Invalid reset link.') ?></div>
        <div class="back-link"><a href="forgot_password.php">Request a new reset link</a></div>
    <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" id="pw1" placeholder="Min. 6 characters" required>
                <button type="button" class="toggle-pw" onclick="togglePw('pw1',this)"><i class="fas fa-eye"></i></button>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="password2" id="pw2" placeholder="Repeat password" required>
                <button type="button" class="toggle-pw" onclick="togglePw('pw2',this)"><i class="fas fa-eye"></i></button>
            </div>
            <button type="submit" class="btn"><i class="fas fa-save"></i> Reset Password</button>
        </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>
<script>
function togglePw(id, btn) {
    const f = document.getElementById(id);
    const show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}
</script>
</body>
</html>
