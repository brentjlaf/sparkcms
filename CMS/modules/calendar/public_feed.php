<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/calendar_helpers.php';

header('Content-Type: application/json');

date_default_timezone_set('America/Los_Angeles');

$dataFile = __DIR__ . '/../../data/calendar_data.json';
$data = read_json_file($dataFile);
$events = isset($data['events']) && is_array($data['events']) ? $data['events'] : [];
$categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];

$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$timezone = isset($_GET['timezone']) ? trim((string) $_GET['timezone']) : '';

if ($timezone !== '') {
    try {
        $tz = new DateTimeZone($timezone);
        date_default_timezone_set($tz->getName());
    } catch (Exception $e) {
        // Ignore invalid timezone values and continue with default.
    }
}

if ($month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid month.']);
    exit;
}

if ($year < 1970 || $year > 2100) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid year.']);
    exit;
}

$monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
$monthEnd = $monthStart->modify('first day of next month');
$upcomingStart = (new DateTimeImmutable('now'))->setTime(0, 0, 0);
$upcomingEnd = $upcomingStart->modify('+30 days');

$categoryMap = [];
foreach ($categories as $category) {
    if (!is_array($category) || empty($category['id'])) {
        continue;
    }
    $categoryMap[$category['id']] = [
        'id' => $category['id'],
        'name' => $category['name'] ?? 'Category',
        'color' => $category['color'] ?? '#2563eb',
    ];
}

$searchLower = $search !== '' ? mb_strtolower($search) : '';
$results = [];
$upcoming = [];
$recurringSeries = 0;

foreach ($events as $event) {
    if (!is_array($event) || empty($event['id']) || empty($event['title'])) {
        continue;
    }

    $recurrenceType = normalize_recurrence_type($event['recurrence']['type'] ?? ($event['recurrence_type'] ?? 'none'));
    if ($recurrenceType !== 'none') {
        $recurringSeries++;
    }

    if ($categoryFilter !== '' && (!isset($event['category_id']) || $event['category_id'] !== $categoryFilter)) {
        continue;
    }

    if ($searchLower !== '') {
        $haystack = mb_strtolower(($event['title'] ?? '') . ' ' . ($event['description'] ?? ''));
        if (strpos($haystack, $searchLower) === false) {
            continue;
        }
    }

    $category = null;
    if (!empty($event['category_id']) && isset($categoryMap[$event['category_id']])) {
        $category = $categoryMap[$event['category_id']];
    }

    $occurrences = generate_occurrences($event, $monthStart, $monthEnd, $category, $recurrenceType);
    $results = array_merge($results, $occurrences);

    $upcomingOccurrences = generate_occurrences($event, $upcomingStart, $upcomingEnd, $category, $recurrenceType);
    if ($upcomingOccurrences) {
        $upcoming = array_merge($upcoming, $upcomingOccurrences);
    }
}

usort($results, static function ($a, $b) {
    return strcmp($a['start'], $b['start']);
});

usort($upcoming, static function ($a, $b) {
    return strcmp($a['start'], $b['start']);
});

$meta = [
    'eventsThisMonth' => count($results),
    'recurringSeries' => $recurringSeries,
    'categories' => count($categoryMap),
    'lastUpdated' => date('M j, Y g:i A'),
];

echo json_encode([
    'status' => 'success',
    'events' => $results,
    'upcoming' => array_slice($upcoming, 0, 10),
    'categories' => array_values($categoryMap),
    'meta' => $meta,
]);
exit;
