<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDatabaseConnection();

if (!hasAnyUsers($pdo)) {
    header('Location: setup.php');
    exit;
}
if (isLoggedIn()) {
    header('Location: app.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $lockedSeconds = getLoginLockoutSeconds($pdo, $username);
    if ($lockedSeconds !== null) {
        $minutes = max(1, ceil($lockedSeconds / 60));
        $error = "Too many failed attempts. Please try again in about {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.';
    } elseif (attemptLogin($pdo, $username, $password)) {
        header('Location: app.php');
        exit;
    } else {
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — Daily</title>
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
        <p class="text-muted small mt-2 mb-0">Log in to your planner.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label small text-muted">Username</label>
          <input type="text" name="username" class="form-control" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label small text-muted">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn w-100 fw-semibold" style="background:var(--ink); color:var(--paper);">
          Log in
        </button>
      </form>
    </div>
  </div>
</body>
</html>
