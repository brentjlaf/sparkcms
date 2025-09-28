<?php
// File: modules/calendar/api.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

header('Content-Type: application/json');

$eventsFile = __DIR__ . '/../../data/calendar_events.json';
$categoriesFile = __DIR__ . '/../../data/calendar_categories.json';

if (!is_file($eventsFile)) {
    file_put_contents($eventsFile, "[]\n");
}
if (!is_file($categoriesFile)) {
    file_put_contents($categoriesFile, "[]\n");
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

$allowedRecurrence = ['none', 'daily', 'weekly', 'monthly', 'yearly'];

function load_calendar_dataset(string $eventsFile, string $categoriesFile): array
{
    $events = read_json_file($eventsFile);
    if (!is_array($events)) {
        $events = [];
    }
    $events = array_values(array_filter($events, static function ($item) {
        return is_array($item) && isset($item['id']);
    }));

    $categories = read_json_file($categoriesFile);
    if (!is_array($categories)) {
        $categories = [];
    }
    $categories = array_values(array_filter($categories, static function ($item) {
        return is_array($item) && isset($item['id']);
    }));

    return [$events, $categories];
}

function save_calendar_dataset(string $eventsFile, string $categoriesFile, array $events, array $categories): void
{
    write_json_file($eventsFile, array_values($events));
    write_json_file($categoriesFile, array_values($categories));
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

[$events, $categories] = load_calendar_dataset($eventsFile, $categoriesFile);

switch ($action) {
    case 'save_event':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'message' => 'Invalid request method.'], 405);
        }

        $id = isset($_POST['evt_id']) ? trim((string) $_POST['evt_id']) : '';
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $category = isset($_POST['category']) ? trim((string) $_POST['category']) : '';
        $start = isset($_POST['start_date']) ? trim((string) $_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? trim((string) $_POST['end_date']) : '';
        $recurrence = isset($_POST['recurring_interval']) ? trim((string) $_POST['recurring_interval']) : 'none';
        $recurringEnd = isset($_POST['recurring_end_date']) ? trim((string) $_POST['recurring_end_date']) : '';
        $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';

        if ($title === '' || $start === '') {
            respond(['success' => false, 'message' => 'Title and start date are required.'], 400);
        }

        $startTimestamp = strtotime($start);
        if ($startTimestamp === false) {
            respond(['success' => false, 'message' => 'Invalid start date supplied.'], 400);
        }

        $endTimestamp = null;
        if ($end !== '') {
            $endTimestamp = strtotime($end);
            if ($endTimestamp === false) {
                respond(['success' => false, 'message' => 'Invalid end date supplied.'], 400);
            }
        }

        if (!in_array($recurrence, $allowedRecurrence, true)) {
            $recurrence = 'none';
        }

        $recurringEndTimestamp = null;
        if ($recurringEnd !== '') {
            $recurringEndTimestamp = strtotime($recurringEnd);
            if ($recurringEndTimestamp === false) {
                respond(['success' => false, 'message' => 'Invalid recurrence end supplied.'], 400);
            }
        }

        $categoryName = $category;
        $knownCategories = array_column($categories, 'name');
        if ($categoryName !== '' && !in_array($categoryName, $knownCategories, true)) {
            $categoryName = '';
        }

        $eventPayload = [
            'title' => $title,
            'category' => $categoryName,
            'start_date' => date('c', $startTimestamp),
            'end_date' => $endTimestamp ? date('c', $endTimestamp) : '',
            'recurring_interval' => $recurrence,
            'recurring_end_date' => $recurringEndTimestamp ? date('c', $recurringEndTimestamp) : '',
            'description' => $description,
        ];

        if ($id === '') {
            $nextId = 1;
            if (!empty($events)) {
                $maxId = max(array_map(static function ($event) {
                    return (int) ($event['id'] ?? 0);
                }, $events));
                $nextId = $maxId + 1;
            }
            $eventPayload['id'] = $nextId;
            $events[] = $eventPayload;
            $message = 'Event created successfully.';
        } else {
            $eventId = (int) $id;
            $found = false;
            foreach ($events as &$event) {
                if ((int) ($event['id'] ?? 0) === $eventId) {
                    $event = array_merge(['id' => $eventId], $eventPayload);
                    $found = true;
                    break;
                }
            }
            unset($event);
            if (!$found) {
                respond(['success' => false, 'message' => 'Event not found.'], 404);
            }
            $message = 'Event updated successfully.';
        }

        usort($events, static function (array $a, array $b): int {
            $aTime = isset($a['start_date']) ? strtotime((string) $a['start_date']) : 0;
            $bTime = isset($b['start_date']) ? strtotime((string) $b['start_date']) : 0;
            if ($aTime === $bTime) {
                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            }
            return $aTime <=> $bTime;
        });

        save_calendar_dataset($eventsFile, $categoriesFile, $events, $categories);

        respond([
            'success' => true,
            'message' => $message,
            'events' => $events,
            'categories' => $categories,
        ]);
        break;

    case 'delete_event':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'message' => 'Invalid request method.'], 405);
        }
        $id = isset($_POST['evt_id']) ? (int) $_POST['evt_id'] : 0;
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Missing event ID.'], 400);
        }
        $before = count($events);
        $events = array_values(array_filter($events, static function ($event) use ($id) {
            return (int) ($event['id'] ?? 0) !== $id;
        }));
        if (count($events) === $before) {
            respond(['success' => false, 'message' => 'Event not found.'], 404);
        }

        save_calendar_dataset($eventsFile, $categoriesFile, $events, $categories);

        respond([
            'success' => true,
            'message' => 'Event deleted.',
            'events' => $events,
            'categories' => $categories,
        ]);
        break;

    case 'add_category':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'message' => 'Invalid request method.'], 405);
        }
        $name = isset($_POST['cat_name']) ? trim((string) $_POST['cat_name']) : '';
        $color = isset($_POST['cat_color']) ? trim((string) $_POST['cat_color']) : '#ffffff';

        if ($name === '') {
            respond(['success' => false, 'message' => 'Category name is required.'], 400);
        }

        foreach ($categories as $category) {
            if (strcasecmp((string) ($category['name'] ?? ''), $name) === 0) {
                respond(['success' => false, 'message' => 'Category already exists.'], 409);
            }
        }

        $nextId = 1;
        if (!empty($categories)) {
            $maxId = max(array_map(static function ($category) {
                return (int) ($category['id'] ?? 0);
            }, $categories));
            $nextId = $maxId + 1;
        }

        if ($color === '') {
            $color = '#ffffff';
        }

        $categories[] = [
            'id' => $nextId,
            'name' => $name,
            'color' => $color,
        ];

        save_calendar_dataset($eventsFile, $categoriesFile, $events, $categories);

        respond([
            'success' => true,
            'message' => 'Category added.',
            'events' => $events,
            'categories' => $categories,
        ]);
        break;

    case 'delete_category':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'message' => 'Invalid request method.'], 405);
        }
        $id = isset($_POST['cat_id']) ? (int) $_POST['cat_id'] : 0;
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Missing category ID.'], 400);
        }

        $index = null;
        $removedName = '';
        foreach ($categories as $idx => $category) {
            if ((int) ($category['id'] ?? 0) === $id) {
                $index = $idx;
                $removedName = (string) ($category['name'] ?? '');
                break;
            }
        }
        if ($index === null) {
            respond(['success' => false, 'message' => 'Category not found.'], 404);
        }

        array_splice($categories, $index, 1);

        if ($removedName !== '') {
            foreach ($events as &$event) {
                if (isset($event['category']) && $event['category'] === $removedName) {
                    $event['category'] = '';
                }
            }
            unset($event);
        }

        save_calendar_dataset($eventsFile, $categoriesFile, $events, $categories);

        respond([
            'success' => true,
            'message' => 'Category deleted.',
            'events' => $events,
            'categories' => $categories,
        ]);
        break;

    default:
        respond([
            'success' => true,
            'events' => $events,
            'categories' => $categories,
        ]);
}
