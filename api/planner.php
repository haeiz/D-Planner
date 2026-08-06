<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireApiLogin();
$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? '';

try {
    $pdo = getDatabaseConnection();

    switch ($resource) {
        case 'priorities': handlePriorities($pdo, $method); break;
        case 'todos':       handleTodos($pdo, $method); break;
        case 'notes':        handleNotes($pdo, $method); break;
        default: jsonError('Unknown resource. Use ?resource=priorities|todos|notes');
    }
} catch (Throwable $e) {
    jsonServerError($e, 'planner.php');
}

/* ------------------------------------------------------- priorities --- */

function handlePriorities(PDO $pdo, string $method): void
{
    if ($method === 'GET') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare('SELECT * FROM priorities WHERE date = :date ORDER BY position');
        $stmt->execute(['date' => $date]);
        $rows = $stmt->fetchAll();

        // Always return exactly 3 slots, even if empty
        $byPos = [];
        foreach ($rows as $r) $byPos[$r['position']] = $r;
        $result = [];
        for ($p = 1; $p <= 3; $p++) {
            $result[] = $byPos[$p] ?? ['position' => $p, 'text' => '', 'completed' => 0];
        }
        jsonResponse(['success' => true, 'priorities' => $result]);
        return;
    }

    if ($method === 'PUT') {
        // Upsert a single priority slot: {date, position, text, completed}
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $date = $input['date'] ?? date('Y-m-d');
        $position = (int)($input['position'] ?? 0);
        if ($position < 1 || $position > 3) jsonError('Position must be 1-3');

        $stmt = $pdo->prepare("
            INSERT INTO priorities (date, position, text, completed)
            VALUES (:date, :position, :text, :completed)
            ON CONFLICT(date, position) DO UPDATE SET text = excluded.text, completed = excluded.completed
        ");
        $stmt->execute([
            'date' => $date,
            'position' => $position,
            'text' => sanitizeString($input['text'] ?? ''),
            'completed' => !empty($input['completed']) ? 1 : 0,
        ]);
        jsonResponse(['success' => true]);
        return;
    }

    jsonError('Method not allowed', 405);
}

/* -------------------------------------------------------------- todos --- */

function handleTodos(PDO $pdo, string $method): void
{
    if ($method === 'GET') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare('SELECT * FROM todos WHERE date = :date ORDER BY position, id');
        $stmt->execute(['date' => $date]);
        jsonResponse(['success' => true, 'todos' => $stmt->fetchAll()]);
        return;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $text = sanitizeString($input['text'] ?? '');
        $date = $input['date'] ?? date('Y-m-d');
        if ($text === '') jsonError('Text is required');

        $maxPos = (int)$pdo->query("SELECT COALESCE(MAX(position), 0) FROM todos WHERE date = " . $pdo->quote($date))->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO todos (date, text, completed, position, created_at) VALUES (:date, :text, 0, :position, datetime('now'))");
        $stmt->execute(['date' => $date, 'text' => $text, 'position' => $maxPos + 1]);

        $id = $pdo->lastInsertId();
        jsonResponse(['success' => true, 'todo' => $pdo->query("SELECT * FROM todos WHERE id = $id")->fetch()], 201);
        return;
    }

    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null;
        if (!$id) jsonError('Missing id');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields = [];
        $params = ['id' => $id];
        if (array_key_exists('text', $input)) { $fields[] = 'text = :text'; $params['text'] = sanitizeString($input['text']); }
        if (array_key_exists('completed', $input)) { $fields[] = 'completed = :completed'; $params['completed'] = !empty($input['completed']) ? 1 : 0; }
        if (!$fields) jsonError('Nothing to update');

        $stmt = $pdo->prepare('UPDATE todos SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
        jsonResponse(['success' => true]);
        return;
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) jsonError('Missing id');
        $stmt = $pdo->prepare('DELETE FROM todos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(['success' => true]);
        return;
    }

    jsonError('Method not allowed', 405);
}

/* -------------------------------------------------------------- notes --- */

function handleNotes(PDO $pdo, string $method): void
{
    if ($method === 'GET') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare('SELECT content FROM notes WHERE date = :date');
        $stmt->execute(['date' => $date]);
        $content = $stmt->fetchColumn();
        jsonResponse(['success' => true, 'content' => $content !== false ? $content : '']);
        return;
    }

    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $date = $input['date'] ?? date('Y-m-d');
        $content = sanitizeString($input['content'] ?? '');

        $stmt = $pdo->prepare("
            INSERT INTO notes (date, content, updated_at) VALUES (:date, :content, datetime('now'))
            ON CONFLICT(date) DO UPDATE SET content = excluded.content, updated_at = datetime('now')
        ");
        $stmt->execute(['date' => $date, 'content' => $content]);
        jsonResponse(['success' => true]);
        return;
    }

    jsonError('Method not allowed', 405);
}
