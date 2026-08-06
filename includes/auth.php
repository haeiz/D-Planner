<?php
/**
 * Authentication helpers. Same pattern used in the spending tracker:
 * PHP sessions + password_hash/password_verify + CSRF token on every
 * state-changing request + login rate limiting.
 */

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
    if (($_SERVER['SERVER_PORT'] ?? '') == 443) return true;
    return false;
}

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isHttpsRequest(),
    ]);
    session_start();
}

function isLoggedIn(): bool
{
    startAppSession();
    return !empty($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    startAppSession();
    return $_SESSION['user_id'] ?? null;
}

function hasAnyUsers(PDO $pdo): bool
{
    return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

function createUser(PDO $pdo, string $username, string $password): int
{
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, created_at, updated_at)
        VALUES (:username, :hash, datetime('now'), datetime('now'))
    ");
    $stmt->execute([
        'username' => $username,
        'hash'     => password_hash($password, PASSWORD_BCRYPT),
    ]);
    return (int)$pdo->lastInsertId();
}

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;

function attemptLogin(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }
    if (!empty($user['locked_until']) && $user['locked_until'] > date('Y-m-d H:i:s')) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int)$user['failed_attempts'] + 1;
        $lockUntil = null;
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
        }
        $upd = $pdo->prepare('UPDATE users SET failed_attempts = :a, locked_until = :l WHERE id = :id');
        $upd->execute(['a' => $attempts, 'l' => $lockUntil, 'id' => $user['id']]);
        return false;
    }

    $reset = $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id');
    $reset->execute(['id' => $user['id']]);

    startAppSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    unset($_SESSION['csrf_token']);
    return true;
}

function getLoginLockoutSeconds(PDO $pdo, string $username): ?int
{
    $stmt = $pdo->prepare('SELECT locked_until FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $lockedUntil = $stmt->fetchColumn();

    if (!$lockedUntil || $lockedUntil <= date('Y-m-d H:i:s')) {
        return null;
    }
    return max(0, strtotime($lockedUntil) - time());
}

function logout(): void
{
    startAppSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function getCsrfToken(): string
{
    startAppSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenValid(string $token): bool
{
    startAppSession();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requirePageLogin(): void
{
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDatabaseConnection();

    if (!hasAnyUsers($pdo)) {
        header('Location: setup.php');
        exit;
    }
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireApiLogin(): void
{
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!csrfTokenValid($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token']);
            exit;
        }
    }
}
