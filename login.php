<?php
require 'db.php';
require 'functions.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($_POST['password'] ?? '', $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        try { $log = $pdo->prepare('INSERT INTO activity_logs (admin_id, admin_username, action, details) VALUES (?, ?, ?, ?)'); $log->execute([$admin['id'], $admin['username'], 'Admin login', 'Successful sign-in']); } catch (Throwable $ignored) {}
        header('Location: admin.php');
        exit;
    }
    $error = 'Incorrect username or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login | OrderTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="style.css" rel="stylesheet"></head>
<body><main class="login-wrap"><section class="login-card">
<div class="login-logo">🚚</div><h1>Welcome back</h1><p>Sign in to your OrderTrack administrator account.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" placeholder="Enter your username" autocomplete="username" required></div>
<div class="mb-4"><label class="form-label">Password</label><input class="form-control" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required></div>
<button class="btn btn-primary w-100 py-2">Sign In</button>
</form><a class="d-block mt-4 text-center small" href="index.php">← Back to public tracking page</a>
</section></main></body></html>
