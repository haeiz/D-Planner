<?php
/**
 * Seed script - adds a realistic demo schedule so the app has something to
 * show immediately. Run from the command line only.
 *
 * Usage:
 *   php scripts/seed.php            Add demo activities/priorities/todos
 *   php scripts/seed.php --reset    Wipe existing planner data first
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

$pdo = getDatabaseConnection();

$reset = in_array('--reset', $argv, true);
if ($reset) {
    foreach (['activities', 'activity_completions', 'priorities', 'todos', 'notes'] as $table) {
        $pdo->exec("DELETE FROM $table");
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '$table'");
    }
    echo "Existing planner data cleared.\n";
}

$existing = (int)$pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn();
if ($existing > 0 && !$reset) {
    echo "Activities already exist - skipping (use --reset to start fresh).\n";
    exit;
}

$today = date('Y-m-d');

$activities = [
    ['title' => 'Morning routine',    'start_time' => '06:30', 'end_time' => '07:15', 'category' => 'Personal', 'repeat_type' => 'daily'],
    ['title' => 'Workout',            'start_time' => '07:15', 'end_time' => '08:00', 'category' => 'Exercise', 'repeat_type' => 'daily'],
    ['title' => 'Breakfast',          'start_time' => '08:00', 'end_time' => '08:30', 'category' => 'Meals',    'repeat_type' => 'daily'],
    ['title' => 'Deep work block',    'start_time' => '09:00', 'end_time' => '11:30', 'category' => 'Work',     'repeat_type' => 'weekdays', 'description' => 'No meetings, phone on silent'],
    ['title' => 'Team standup',       'start_time' => '11:30', 'end_time' => '11:45', 'category' => 'Work',     'repeat_type' => 'weekdays'],
    ['title' => 'Lunch',              'start_time' => '12:30', 'end_time' => '13:15', 'category' => 'Meals',    'repeat_type' => 'daily'],
    ['title' => 'Study session',      'start_time' => '14:00', 'end_time' => '15:30', 'category' => 'Study',    'repeat_type' => 'weekdays'],
    ['title' => 'Family time',        'start_time' => '18:00', 'end_time' => '19:00', 'category' => 'Family',   'repeat_type' => 'daily'],
    ['title' => 'Wind down / read',   'start_time' => '21:30', 'end_time' => '22:15', 'category' => 'Rest',     'repeat_type' => 'daily'],
    ['title' => 'Long weekend hike',  'start_time' => '08:00', 'end_time' => '11:00', 'category' => 'Exercise', 'repeat_type' => 'weekends'],
    ['title' => 'Dentist appointment','start_time' => '10:00', 'end_time' => '11:00', 'category' => 'Personal', 'repeat_type' => 'once', 'specific_date' => date('Y-m-d', strtotime('+2 days'))],
];

$stmt = $pdo->prepare("
    INSERT INTO activities (title, description, start_time, end_time, category, repeat_type, specific_date, active, created_at, updated_at)
    VALUES (:title, :description, :start_time, :end_time, :category, :repeat_type, :specific_date, 1, datetime('now'), datetime('now'))
");
foreach ($activities as $a) {
    $stmt->execute([
        'title'         => $a['title'],
        'description'   => $a['description'] ?? '',
        'start_time'    => $a['start_time'],
        'end_time'      => $a['end_time'],
        'category'      => $a['category'],
        'repeat_type'   => $a['repeat_type'],
        'specific_date' => $a['specific_date'] ?? null,
    ]);
}
echo "Seeded " . count($activities) . " activities.\n";

// Mark a couple of things done earlier today, for a realistic-looking progress bar
$pdo->exec("
    INSERT OR IGNORE INTO activity_completions (activity_id, completion_date, completed_at)
    SELECT id, '$today', datetime('now') FROM activities WHERE title IN ('Morning routine', 'Workout', 'Breakfast')
");

$priorities = [
    [1, 'Finish the quarterly report'],
    [2, 'Reply to client emails'],
    [3, 'Prep tomorrow\'s presentation'],
];
$pstmt = $pdo->prepare("INSERT INTO priorities (date, position, text, completed) VALUES (:date, :pos, :text, 0)");
foreach ($priorities as [$pos, $text]) {
    $pstmt->execute(['date' => $today, 'pos' => $pos, 'text' => $text]);
}
echo "Seeded 3 daily priorities.\n";

$todos = ['Buy groceries', 'Call the bank', 'Book flight for next month', 'Return the package'];
$tstmt = $pdo->prepare("INSERT INTO todos (date, text, completed, position, created_at) VALUES (:date, :text, :completed, :pos, datetime('now'))");
foreach ($todos as $i => $text) {
    $tstmt->execute(['date' => $today, 'text' => $text, 'completed' => $i === 0 ? 1 : 0, 'pos' => $i + 1]);
}
echo "Seeded " . count($todos) . " to-do items.\n";

$nstmt = $pdo->prepare("INSERT INTO notes (date, content, updated_at) VALUES (:date, :content, datetime('now'))");
$nstmt->execute(['date' => $today, 'content' => "Good energy today. Remember to take a proper lunch break instead of eating at the desk."]);
echo "Seeded a note for today.\n";

echo "Done. Database: " . realpath(__DIR__ . '/../database/planner.db') . "\n";
