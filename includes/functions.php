<?php
/**
 * Shared helper functions used across the API endpoints.
 */

// Fixed starter categories for V1 (custom categories are a V2 feature).
// Each maps to a color used consistently across the UI.
const CATEGORIES = [
    'Work'      => '#4A6FA5',
    'Study'     => '#7B5EA7',
    'Exercise'  => '#D97706',
    'Meals'     => '#4C9A6A',
    'Personal'  => '#C2547B',
    'Family'    => '#2A9D8F',
    'Rest'      => '#6B7280',
];

function categoryColor(string $category): string
{
    return CATEGORIES[$category] ?? '#6B7280';
}

/**
 * Does an activity (by its repeat_type / specific_date) occur on the given date?
 */
function activityOccursOn(array $activity, string $date): bool
{
    $dow = (int)date('N', strtotime($date)); // 1 (Mon) .. 7 (Sun)

    switch ($activity['repeat_type']) {
        case 'once':
            return $activity['specific_date'] === $date;
        case 'daily':
            return true;
        case 'weekdays':
            return $dow >= 1 && $dow <= 5;
        case 'weekends':
            return $dow === 6 || $dow === 7;
        default:
            return false;
    }
}

/**
 * Returns [startDate, endDate] (inclusive, Y-m-d) for the Mon-Sun week containing $date.
 */
function weekRange(string $date): array
{
    $d = new DateTime($date);
    $d->modify('Monday this week');
    $start = $d->format('Y-m-d');
    $d->modify('+6 days');
    $end = $d->format('Y-m-d');
    return [$start, $end];
}

function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400): void
{
    jsonResponse(['success' => false, 'error' => $message], $status);
}

/**
 * For unexpected/internal errors: logs the real exception to the PHP error
 * log but only ever sends a generic message to the client.
 */
function jsonServerError(Throwable $e, string $context = ''): void
{
    error_log(sprintf(
        '[Planner%s] %s in %s:%d',
        $context ? " $context" : '',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    jsonResponse(['success' => false, 'error' => 'Something went wrong on our end. Please try again.'], 500);
}

function sanitizeString(?string $value): ?string
{
    if ($value === null) return null;
    return trim(strip_tags($value));
}
