<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireApiLogin();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDatabaseConnection();

    if ($method === 'GET') {
        jsonResponse(['success' => true, 'username' => $_SESSION['username'] ?? null]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (($input['action'] ?? '') === 'change_password') {
            $current = $input['current_password'] ?? '';
            $new     = $input['new_password'] ?? '';

            if (strlen($new) < 8) jsonError('New password must be at least 8 characters');

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute(['id' => currentUserId()]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current, $user['password_hash'])) {
                jsonError('Current password is incorrect');
            }

            $update = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = datetime('now') WHERE id = :id");
            $update->execute(['hash' => password_hash($new, PASSWORD_BCRYPT), 'id' => $user['id']]);

            jsonResponse(['success' => true]);
        }

        jsonError('Unknown action', 400);
    }

    jsonError('Method not allowed', 405);
} catch (Throwable $e) {
    jsonServerError($e, 'auth.php');
}
