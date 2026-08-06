<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDatabaseConnection();

if (hasAnyUsers($pdo)) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($username === '' || strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        createUser($pdo, $username, $password);
        attemptLogin($pdo, $username, $password);
        header('Location: app.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set up your planner</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<link href="assets/css/auth.css" rel="stylesheet">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="text-center mb-4">
        <div class="brand justify-content-center d-flex"><span class="brand-mark">◐</span> Daily</div>
        <p class="text-muted small mt-2 mb-0">Welcome — let's set up your planner.</p>
        <p class="text-muted small">One-time step, since this is your own private copy.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label small text-muted">Username</label>
          <input type="text" name="username" class="form-control" required minlength="3" autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted">Password</label>
          <input type="password" name="password" class="form-control" required minlength="8">
          <div class="form-text">At least 8 characters.</div>
        </div>
        <div class="mb-4">
          <label class="form-label small text-muted">Confirm password</label>
          <input type="password" name="password_confirm" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn w-100 fw-semibold" style="background:var(--ink); color:var(--paper);">
          Create account &amp; open my planner
        </button>
      </form>
    </div>
  </div>
</body>
</html>
