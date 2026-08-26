<?php
// login.php — Admin login page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been logged out successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM admin WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $admin['id'];
            $_SESSION['admin_username']  = $admin['username'];
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — <?= APP_NAME ?></title>
  <meta name="description" content="Secure admin login for the Website Monitoring System with Telegram Alerts.">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">

    <div class="login-logo">
      <div class="icon-ring">📡</div>
      <h1><?= APP_NAME ?></h1>
      <p>Website Monitoring System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input
          id="username"
          name="username"
          type="text"
          class="form-control"
          placeholder="Enter admin username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          required
          autofocus
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
          <input
            id="password"
            name="password"
            type="password"
            class="form-control"
            placeholder="Enter password"
            required
          >
          <span class="input-icon toggle-password" data-target="#password" id="togglePwd">👁️</span>
        </div>
      </div>

      <button id="loginBtn" type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:6px;padding:11px;">
        🔐 Sign In
      </button>
    </form>

    <p class="text-muted" style="text-align:center;margin-top:18px;font-size:.75rem;">
      Admin access only &bull; <?= APP_NAME ?> v<?= APP_VERSION ?>
    </p>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
