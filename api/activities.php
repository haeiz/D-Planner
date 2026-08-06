<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireApiLogin();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDatabaseConnection();

    switch ($method) {
        case 'GET':    handleList($pdo); break;
        case 'POST':   handleCreate($pdo); break;
        case 'PUT':    handleUpdate($pdo); break;
        case 'DELETE': handleDelete($pdo); break;
        default:       jsonError('Method not allowed', 405);
    }
} catch (Throwable $e) {
    jsonServerError($e, 'activities.php');
}

/**
 * GET ?date=YYYY-MM-DD              -> that day's activities, expanded from templates, with completion state
 * GET ?start=YYYY-MM-DD&end=YYYY-MM-DD -> same, expanded across a date range (used for the weekly view)
 */
function handleList(PDO $pdo): void
{
    if (!empty($_GET['start']) && !empty($_GET['end'])) {
        $start = $_GET['start'];
        $end = $_GET['end'];
    } else {
        $start = $end = $_GET['date'] ?? date('Y-m-d');
    }

    $activities = $pdo->query('SELECT * FROM activities WHERE active = 1')->fetchAll();
    $completions = $pdo->prepare('SELECT activity_id, completion_date FROM activity_completions WHERE completion_date BETWEEN :s AND :e');
    $completions->execute(['s' => $start, 'e' => $end]);
    $completedSet = [];
    foreach ($completions->fetchAll() as $c) {
        $completedSet[$c['activity_id'] . '|' . $c['completion_date']] = true;
    }

    $result = [];
    $cursor = new DateTime($start);
    $endDate = new DateTime($end);
    while ($cursor <= $endDate) {
        $dateStr = $cursor->format('Y-m-d');
        foreach ($activities as $a) {
            if (activityOccursOn($a, $dateStr)) {
                $result[] = [
                    'id'          => (int)$a['id'],
                    'title'       => $a['title'],
                    'description' => $a['description'],
                    'start_time'  => $a['start_time'],
                    'end_time'    => $a['end_time'],
                    'category'    => $a['category'],
                    'color'       => categoryColor($a['category']),
                    'repeat_type' => $a['repeat_type'],
                    'date'        => $dateStr,
                    'completed'   => isset($completedSet[$a['id'] . '|' . $dateStr]),
                ];
            }
        }
        $cursor->modify('+1 day');
    }

    usort($result, fn($a, $b) => [$a['date'], $a['start_time']] <=> [$b['date'], $b['start_time']]);

    jsonResponse(['success' => true, 'activities' => $result, 'categories' => CATEGORIES]);
}

function handleCreate(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $title = sanitizeString($input['title'] ?? '');
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $repeatType = $input['repeat_type'] ?? 'once';
    $specificDate = $input['specific_date'] ?? null;

    if ($title === '') jsonError('Title is required');
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        jsonError('Start and end time are required (HH:MM)');
    }
    if ($endTime <= $startTime) jsonError('End time must be after start time');
    if (!in_array($repeatType, ['once', 'daily', 'weekdays', 'weekends'], true)) jsonError('Invalid repeat type');
    if ($repeatType === 'once' && !$specificDate) jsonError('A date is required for a one-time activity');

    $category = $input['category'] ?? 'Personal';
    if (!array_key_exists($category, CATEGORIES)) $category = 'Personal';

    $stmt = $pdo->prepare("
        INSERT INTO activities (title, description, start_time, end_time, category, repeat_type, specific_date, active, created_at, updated_at)
        VALUES (:title, :description, :start_time, :end_time, :category, :repeat_type, :specific_date, 1, datetime('now'), datetime('now'))
    ");
    $stmt->execute([
        'title'         => $title,
        'description'   => sanitizeString($input['description'] ?? ''),
        'start_time'    => $startTime,
        'end_time'      => $endTime,
        'category'      => $category,
        'repeat_type'   => $repeatType,
        'specific_date' => $repeatType === 'once' ? $specificDate : null,
    ]);

    $id = $pdo->lastInsertId();
    $row = $pdo->query("SELECT * FROM activities WHERE id = $id")->fetch();
    jsonResponse(['success' => true, 'activity' => $row], 201);
}

/**
 * PUT ?id=X                 body: {title, description, start_time, end_time, category, repeat_type, specific_date}
 * PUT ?id=X&action=complete body: {date, completed: true|false}  -> toggles completion for that occurrence
 */
function handleUpdate(PDO $pdo): void
{
    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('Missing id');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (($_GET['action'] ?? '') === 'complete') {
        $date = $input['date'] ?? date('Y-m-d');
        $completed = !empty($input['completed']);

        if ($completed) {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO activity_completions (activity_id, completion_date, completed_at) VALUES (:id, :date, datetime("now"))');
            $stmt->execute(['id' => $id, 'date' => $date]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM activity_completions WHERE activity_id = :id AND completion_date = :date');
            $stmt->execute(['id' => $id, 'date' => $date]);
        }
        jsonResponse(['success' => true]);
        return;
    }

    $fields = [];
    $params = ['id' => $id];
    foreach (['title', 'description', 'start_time', 'end_time', 'category', 'repeat_type', 'specific_date'] as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f = :$f";
            $params[$f] = in_array($f, ['title', 'description'], true) ? sanitizeString($input[$f]) : $input[$f];
        }
    }
    if (!$fields) jsonError('Nothing to update');
    $fields[] = "updated_at = datetime('now')";

    $stmt = $pdo->prepare('UPDATE activities SET ' . implode(', ', $fields) . ' WHERE id = :id');
    $stmt->execute($params);

    $row = $pdo->query('SELECT * FROM activities WHERE id = ' . (int)$id)->fetch();
    if (!$row) jsonError('Activity not found', 404);
    jsonResponse(['success' => true, 'activity' => $row]);
}

function handleDelete(PDO $pdo): void
{
    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('Missing id');

    $stmt = $pdo->prepare('DELETE FROM activities WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() === 0) jsonError('Activity not found', 404);
    jsonResponse(['success' => true]);
}
