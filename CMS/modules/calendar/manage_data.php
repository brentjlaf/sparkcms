<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

date_default_timezone_set('America/Los_Angeles');

$dataFile = __DIR__ . '/../../data/calendar_data.json';
$data = read_json_file($dataFile);
if (!isset($data['events']) || !is_array($data['events'])) {
    $data['events'] = [];
}
if (!isset($data['categories']) || !is_array($data['categories'])) {
    $data['categories'] = [];
}

$action = $_REQUEST['action'] ?? 'fetch';

switch ($action) {
    case 'fetch':
        respond_success([
            'events' => array_values(array_map('format_event_for_response', $data['events'])),
            'categories' => array_values(array_map('format_category_for_response', $data['categories']))
        ]);
        break;

    case 'save_event':
        $result = save_event($data);
        if ($result['status'] === 'success') {
            write_calendar_data($dataFile, $data);
        }
        respond_json($result);
        break;

    case 'delete_event':
        $result = delete_event($data);
        if ($result['status'] === 'success') {
            write_calendar_data($dataFile, $data);
        }
        respond_json($result);
        break;

    case 'save_category':
        $result = save_category($data);
        if ($result['status'] === 'success') {
            write_calendar_data($dataFile, $data);
        }
        respond_json($result);
        break;

    case 'delete_category':
        $result = delete_category($data);
        if ($result['status'] === 'success') {
            write_calendar_data($dataFile, $data);
        }
        respond_json($result);
        break;

    default:
        respond_error('Unsupported action.');
}

function save_event(array &$data): array
{
    $id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
    $title = sanitize_text($_POST['title'] ?? '');
    $description = trim((string) ($_POST['description'] ?? ''));
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate = trim((string) ($_POST['end_date'] ?? ''));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $endTime = trim((string) ($_POST['end_time'] ?? ''));
    $allDay = !empty($_POST['all_day']);
    $categoryId = sanitize_text($_POST['category_id'] ?? '');
    $recurrenceType = sanitize_text($_POST['recurrence_type'] ?? 'none');
    $recurrenceInterval = isset($_POST['recurrence_interval']) ? (int) $_POST['recurrence_interval'] : 1;
    $recurrenceEndDate = trim((string) ($_POST['recurrence_end_date'] ?? ''));

    if ($title === '') {
        return ['status' => 'error', 'message' => 'Title is required.'];
    }
    if ($startDate === '' || $endDate === '') {
        return ['status' => 'error', 'message' => 'Start and end dates are required.'];
    }

    $allowedRecurrence = ['none', 'daily', 'weekly', 'monthly', 'yearly'];
    if (!in_array($recurrenceType, $allowedRecurrence, true)) {
        $recurrenceType = 'none';
    }
    if ($recurrenceType === 'none') {
        $recurrenceInterval = 1;
        $recurrenceEndDate = '';
    } else {
        $recurrenceInterval = max(1, $recurrenceInterval);
    }

    if ($allDay) {
        $startTime = '00:00';
        $endTime = '23:59';
    } else {
        if ($startTime === '') {
            $startTime = '00:00';
        }
        if ($endTime === '') {
            $endTime = $startTime;
        }
    }

    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime);
    $end = DateTimeImmutable::createFromFormat('Y-m-d H:i', $endDate . ' ' . $endTime);
    if (!$start || !$end) {
        return ['status' => 'error', 'message' => 'Invalid dates provided.'];
    }
    if ($end < $start) {
        $end = $start->modify('+1 hour');
        $endDate = $end->format('Y-m-d');
        $endTime = $allDay ? '23:59' : $end->format('H:i');
    }

    $eventData = [
        'id' => $id !== '' ? $id : uniqid('evt_', true),
        'title' => $title,
        'description' => $description,
        'start_date' => $startDate,
        'start_time' => $allDay ? null : $startTime,
        'end_date' => $endDate,
        'end_time' => $allDay ? null : $endTime,
        'all_day' => $allDay,
        'category_id' => $categoryId !== '' ? $categoryId : null,
        'recurrence' => [
            'type' => $recurrenceType,
            'interval' => $recurrenceInterval,
            'end_date' => $recurrenceEndDate !== '' ? $recurrenceEndDate : null
        ],
        'updated_at' => date(DateTime::ATOM)
    ];

    $found = false;
    foreach ($data['events'] as &$event) {
        if (isset($event['id']) && $event['id'] === $eventData['id']) {
            $event = $eventData;
            $found = true;
            break;
        }
    }
    unset($event);

    if (!$found) {
        $data['events'][] = $eventData;
    }

    return ['status' => 'success', 'event' => format_event_for_response($eventData)];
}

function delete_event(array &$data): array
{
    $id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
    if ($id === '') {
        return ['status' => 'error', 'message' => 'Missing event id.'];
    }

    $initialCount = count($data['events']);
    $data['events'] = array_values(array_filter($data['events'], static function ($event) use ($id) {
        return !is_array($event) || ($event['id'] ?? null) !== $id;
    }));

    if (count($data['events']) === $initialCount) {
        return ['status' => 'error', 'message' => 'Event not found.'];
    }

    return ['status' => 'success'];
}

function save_category(array &$data): array
{
    $id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
    $name = sanitize_text($_POST['name'] ?? '');
    $color = trim((string) ($_POST['color'] ?? ''));

    if ($name === '') {
        return ['status' => 'error', 'message' => 'Category name is required.'];
    }

    if ($color === '' || !preg_match('/^#([0-9a-fA-F]{6})$/', $color)) {
        $color = '#2563eb';
    }

    $categoryData = [
        'id' => $id !== '' ? $id : uniqid('cat_', true),
        'name' => $name,
        'color' => $color,
        'updated_at' => date(DateTime::ATOM)
    ];

    $found = false;
    foreach ($data['categories'] as &$category) {
        if (isset($category['id']) && $category['id'] === $categoryData['id']) {
            $category = $categoryData;
            $found = true;
            break;
        }
    }
    unset($category);

    if (!$found) {
        $data['categories'][] = $categoryData;
    }

    return ['status' => 'success', 'category' => format_category_for_response($categoryData)];
}

function delete_category(array &$data): array
{
    $id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
    if ($id === '') {
        return ['status' => 'error', 'message' => 'Missing category id.'];
    }

    $initialCount = count($data['categories']);
    $data['categories'] = array_values(array_filter($data['categories'], static function ($category) use ($id) {
        return !is_array($category) || ($category['id'] ?? null) !== $id;
    }));

    if (count($data['categories']) === $initialCount) {
        return ['status' => 'error', 'message' => 'Category not found.'];
    }

    foreach ($data['events'] as &$event) {
        if (isset($event['category_id']) && $event['category_id'] === $id) {
            $event['category_id'] = null;
        }
    }
    unset($event);

    return ['status' => 'success'];
}

function write_calendar_data(string $file, array $data): void
{
    write_json_file($file, [
        'events' => array_values($data['events']),
        'categories' => array_values($data['categories'])
    ]);
}

function format_event_for_response($event): array
{
    if (!is_array($event)) {
        return [];
    }
    return [
        'id' => $event['id'] ?? '',
        'title' => $event['title'] ?? '',
        'description' => $event['description'] ?? '',
        'start_date' => $event['start_date'] ?? '',
        'start_time' => $event['start_time'] ?? '',
        'end_date' => $event['end_date'] ?? '',
        'end_time' => $event['end_time'] ?? '',
        'all_day' => !empty($event['all_day']),
        'category_id' => $event['category_id'] ?? '',
        'recurrence' => [
            'type' => $event['recurrence']['type'] ?? 'none',
            'interval' => $event['recurrence']['interval'] ?? 1,
            'end_date' => $event['recurrence']['end_date'] ?? null
        ],
        'updated_at' => $event['updated_at'] ?? null
    ];
}

function format_category_for_response($category): array
{
    if (!is_array($category)) {
        return [];
    }
    return [
        'id' => $category['id'] ?? '',
        'name' => $category['name'] ?? '',
        'color' => $category['color'] ?? '#2563eb',
        'updated_at' => $category['updated_at'] ?? null
    ];
}

function respond_success(array $payload = []): void
{
    respond_json(['status' => 'success'] + $payload);
}

function respond_error(string $message): void
{
    respond_json(['status' => 'error', 'message' => $message]);
}

function respond_json(array $payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
}
