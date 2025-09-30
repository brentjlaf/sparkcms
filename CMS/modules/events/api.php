<?php
// File: modules/events/api.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/helpers.php';

require_login();

events_ensure_storage();

$events = events_read_events();
$orders = events_read_orders();
$categories = events_read_categories();
$salesByEvent = events_compute_sales($events, $orders);

$action = $_GET['action'] ?? $_POST['action'] ?? 'overview';
$action = strtolower(trim((string) $action));

switch ($action) {
    case 'overview':
        handle_overview($events, $orders, $salesByEvent);
        break;
    case 'list_events':
        handle_list_events($events, $salesByEvent);
        break;
    case 'get_event':
        handle_get_event($events);
        break;
    case 'save_event':
        handle_save_event($events, $categories);
        break;
    case 'delete_event':
        handle_delete_event($events);
        break;
    case 'end_event':
        handle_end_event($events);
        break;
    case 'list_orders':
        handle_list_orders($orders, $events);
        break;
    case 'save_order':
        handle_save_order($orders, $events);
        break;
    case 'export_orders':
        handle_export_orders($orders, $events);
        break;
    case 'reports_summary':
        handle_reports_summary($events, $orders, $salesByEvent);
        break;
    case 'list_roles':
        handle_list_roles();
        break;
    case 'list_categories':
        handle_list_categories($categories);
        break;
    case 'save_category':
        handle_save_category($categories);
        break;
    case 'delete_category':
        handle_delete_category($categories, $events);
        break;
    default:
        respond_json(['error' => 'Unknown action.'], 400);
}

function respond_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function handle_overview(array $events, array $orders, array $salesByEvent): void
{
    $upcoming = events_filter_upcoming($events);
    $upcomingPreview = [];
    foreach (array_slice($upcoming, 0, 5) as $event) {
        $id = (string) ($event['id'] ?? '');
        $metrics = $salesByEvent[$id] ?? ['tickets_sold' => 0, 'revenue' => 0];
        $start = isset($event['start']) ? strtotime((string) $event['start']) : false;
        $upcomingPreview[] = [
            'id' => $id,
            'title' => $event['title'] ?? 'Untitled',
            'start' => $event['start'] ?? '',
            'tickets_sold' => $metrics['tickets_sold'] ?? 0,
            'revenue' => $metrics['revenue'] ?? 0,
        ];
    }

    $stats = [
        'total_events' => count($events),
        'total_tickets_sold' => array_sum(array_column($salesByEvent, 'tickets_sold')),
        'total_revenue' => array_sum(array_column($salesByEvent, 'revenue')),
    ];

    respond_json([
        'upcoming' => $upcomingPreview,
        'stats' => $stats,
    ]);
}

function handle_list_events(array $events, array $salesByEvent): void
{
    $rows = [];
    foreach ($events as $event) {
        $id = (string) ($event['id'] ?? '');
        $metrics = $salesByEvent[$id] ?? ['tickets_sold' => 0, 'revenue' => 0];
        $start = $event['start'] ?? '';
        $end = $event['end'] ?? '';
        $rows[] = [
            'id' => $id,
            'title' => $event['title'] ?? 'Untitled Event',
            'location' => $event['location'] ?? '',
            'start' => $start,
            'end' => $end,
            'status' => $event['status'] ?? 'draft',
            'tickets_sold' => $metrics['tickets_sold'] ?? 0,
            'revenue' => $metrics['revenue'] ?? 0,
            'capacity' => events_ticket_capacity($event, true),
            'categories' => array_values($event['categories'] ?? []),
        ];
    }

    usort($rows, static function ($a, $b) {
        $aStart = $a['start'] ? strtotime($a['start']) : 0;
        $bStart = $b['start'] ? strtotime($b['start']) : 0;
        if ($aStart === $bStart) {
            return strcmp($a['title'], $b['title']);
        }
        return $aStart <=> $bStart;
    });

    respond_json(['events' => $rows]);
}

function handle_get_event(array $events): void
{
    $id = $_GET['id'] ?? '';
    $id = trim((string) $id);
    if ($id === '') {
        respond_json(['error' => 'Missing event id.'], 400);
    }

    $event = events_find_event($events, $id);
    if ($event === null) {
        respond_json(['error' => 'Event not found.'], 404);
    }

    respond_json(['event' => $event]);
}

function handle_save_event(array $events, array $categories): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
        if (isset($payload['tickets']) && is_string($payload['tickets'])) {
            $tickets = json_decode($payload['tickets'], true);
            if (is_array($tickets)) {
                $payload['tickets'] = $tickets;
            }
        }
    }

    $eventData = [
        'id' => $payload['id'] ?? null,
        'title' => $payload['title'] ?? '',
        'description' => $payload['description'] ?? '',
        'location' => $payload['location'] ?? '',
        'start' => $payload['start'] ?? '',
        'end' => $payload['end'] ?? '',
        'status' => $payload['status'] ?? 'draft',
        'tickets' => $payload['tickets'] ?? [],
        'categories' => $payload['categories'] ?? [],
    ];

    $eventData = events_normalize_event($eventData, $categories);

    $eventsAssoc = [];
    foreach ($events as $event) {
        $eventsAssoc[(string) ($event['id'] ?? '')] = $event;
    }

    $eventsAssoc[$eventData['id']] = array_merge($eventsAssoc[$eventData['id']] ?? [], $eventData);

    if (!events_write_events(array_values($eventsAssoc))) {
        respond_json(['error' => 'Unable to save event.'], 500);
    }

    respond_json(['success' => true, 'event' => $eventData]);
}

function handle_delete_event(array $events): void
{
    $id = $_POST['id'] ?? ($_GET['id'] ?? '');
    $id = trim((string) $id);
    if ($id === '') {
        respond_json(['error' => 'Missing event id.'], 400);
    }

    $remaining = array_values(array_filter($events, static function ($event) use ($id) {
        return (string) ($event['id'] ?? '') !== $id;
    }));

    if (!events_write_events($remaining)) {
        respond_json(['error' => 'Unable to delete event.'], 500);
    }

    respond_json(['success' => true]);
}

function handle_end_event(array $events): void
{
    $id = $_POST['id'] ?? ($_GET['id'] ?? '');
    $id = trim((string) $id);
    if ($id === '') {
        respond_json(['error' => 'Missing event id.'], 400);
    }

    $updated = false;
    foreach ($events as &$event) {
        if ((string) ($event['id'] ?? '') === $id) {
            $event['status'] = 'ended';
            $event['updated_at'] = gmdate('c');
            $updated = true;
            break;
        }
    }
    unset($event);

    if (!$updated) {
        respond_json(['error' => 'Event not found.'], 404);
    }

    if (!events_write_events($events)) {
        respond_json(['error' => 'Unable to update event.'], 500);
    }

    respond_json(['success' => true]);
}

function handle_list_orders(array $orders, array $events): void
{
    $eventFilter = trim((string) ($_GET['event'] ?? ''));
    $statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
    $startDate = trim((string) ($_GET['start'] ?? ''));
    $endDate = trim((string) ($_GET['end'] ?? ''));

    $rows = [];
    foreach ($orders as $order) {
        if ($eventFilter !== '' && (string) ($order['event_id'] ?? '') !== $eventFilter) {
            continue;
        }
        $status = strtolower((string) ($order['status'] ?? 'paid'));
        if ($statusFilter !== '' && $status !== $statusFilter) {
            continue;
        }
        $orderedAt = isset($order['ordered_at']) ? strtotime((string) $order['ordered_at']) : false;
        if ($startDate !== '') {
            $start = strtotime($startDate);
            if ($orderedAt !== false && $start !== false && $orderedAt < $start) {
                continue;
            }
        }
        if ($endDate !== '') {
            $end = strtotime($endDate . ' 23:59:59');
            if ($orderedAt !== false && $end !== false && $orderedAt > $end) {
                continue;
            }
        }

        $tickets = array_map('events_normalize_order_ticket', is_array($order['tickets'] ?? null) ? $order['tickets'] : []);
        $quantity = events_order_ticket_quantity($tickets);
        $amount = isset($order['amount'])
            ? round((float) $order['amount'], 2)
            : events_calculate_order_amount($tickets);
        $event = events_find_event($events, $order['event_id'] ?? '');

        $rows[] = [
            'id' => $order['id'] ?? '',
            'event' => $event['title'] ?? 'Event',
            'event_id' => $order['event_id'] ?? '',
            'buyer_name' => $order['buyer_name'] ?? '',
            'tickets' => $tickets,
            'ticket_quantity' => $quantity,
            'amount' => $amount,
            'status' => $status,
            'ordered_at' => $order['ordered_at'] ?? '',
        ];
    }

    usort($rows, static function ($a, $b) {
        $aTime = $a['ordered_at'] ? strtotime($a['ordered_at']) : 0;
        $bTime = $b['ordered_at'] ? strtotime($b['ordered_at']) : 0;
        return $bTime <=> $aTime;
    });

    respond_json(['orders' => $rows]);
}

function handle_save_order(array $orders, array $events): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
        if (isset($payload['tickets']) && is_string($payload['tickets'])) {
            $decoded = json_decode($payload['tickets'], true);
            if (is_array($decoded)) {
                $payload['tickets'] = $decoded;
            }
        }
    }

    $id = trim((string) ($payload['id'] ?? ''));
    $eventId = trim((string) ($payload['event_id'] ?? ''));
    if ($id === '' || $eventId === '') {
        respond_json(['error' => 'Order ID and event are required.'], 400);
    }

    $tickets = $payload['tickets'] ?? [];
    if (is_string($tickets)) {
        $decodedTickets = json_decode($tickets, true);
        if (is_array($decodedTickets)) {
            $tickets = $decodedTickets;
        }
    }

    $orderData = [
        'id' => $id,
        'event_id' => $eventId,
        'buyer_name' => $payload['buyer_name'] ?? '',
        'status' => $payload['status'] ?? 'paid',
        'ordered_at' => $payload['ordered_at'] ?? '',
        'tickets' => is_array($tickets) ? $tickets : [],
        'amount' => $payload['amount'] ?? null,
    ];

    $orderData = events_normalize_order($orderData);

    if ($orderData['id'] === '' || $orderData['event_id'] === '') {
        respond_json(['error' => 'Invalid order details.'], 400);
    }

    $originalId = trim((string) ($payload['original_id'] ?? $orderData['id']));
    $ordersAssoc = [];
    foreach ($orders as $order) {
        if (!is_array($order) || !isset($order['id'])) {
            continue;
        }
        $ordersAssoc[(string) $order['id']] = $order;
    }

    if ($originalId !== '' && $originalId !== $orderData['id']) {
        unset($ordersAssoc[$originalId]);
    }

    $ordersAssoc[$orderData['id']] = array_merge($ordersAssoc[$orderData['id']] ?? [], $orderData);

    if (!events_write_orders(array_values($ordersAssoc))) {
        respond_json(['error' => 'Unable to save order.'], 500);
    }

    $event = events_find_event($events, $orderData['event_id']);
    $responseOrder = [
        'id' => $orderData['id'],
        'event_id' => $orderData['event_id'],
        'event' => $event['title'] ?? 'Event',
        'buyer_name' => $orderData['buyer_name'],
        'status' => $orderData['status'],
        'ordered_at' => $orderData['ordered_at'],
        'amount' => $orderData['amount'],
        'ticket_quantity' => events_order_ticket_quantity($orderData['tickets']),
        'tickets' => $orderData['tickets'],
    ];

    respond_json(['success' => true, 'order' => $responseOrder]);
}

function handle_export_orders(array $orders, array $events): void
{
    $rows = [];
    $rows[] = ['Order ID', 'Event', 'Buyer', 'Tickets', 'Amount', 'Status', 'Ordered At'];
    foreach ($orders as $order) {
        $event = events_find_event($events, $order['event_id'] ?? '');
        $tickets = array_map('events_normalize_order_ticket', is_array($order['tickets'] ?? null) ? $order['tickets'] : []);
        $quantity = events_order_ticket_quantity($tickets);
        $amount = events_calculate_order_amount($tickets);
        if (isset($order['amount']) && is_numeric($order['amount'])) {
            $amount = (float) $order['amount'];
        }
        $rows[] = [
            $order['id'] ?? '',
            $event['title'] ?? 'Event',
            $order['buyer_name'] ?? '',
            $quantity,
            number_format($amount, 2, '.', ''),
            strtoupper((string) ($order['status'] ?? 'paid')),
            $order['ordered_at'] ?? '',
        ];
    }

    $fh = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="event-orders.csv"');
    echo $csv;
    exit;
}

function handle_reports_summary(array $events, array $orders, array $salesByEvent): void
{
    $reports = [];
    foreach ($events as $event) {
        $id = (string) ($event['id'] ?? '');
        $metrics = $salesByEvent[$id] ?? ['tickets_sold' => 0, 'revenue' => 0];
        $reports[] = [
            'event_id' => $id,
            'title' => $event['title'] ?? 'Event',
            'tickets_sold' => $metrics['tickets_sold'] ?? 0,
            'revenue' => $metrics['revenue'] ?? 0,
            'status' => $event['status'] ?? 'draft',
        ];
    }

    respond_json(['reports' => $reports]);
}

function handle_list_roles(): void
{
    respond_json(['roles' => events_default_roles()]);
}

function handle_list_categories(array $categories): void
{
    respond_json(['categories' => events_sort_categories($categories)]);
}

function handle_save_category(array $categories): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }

    $name = trim((string) ($payload['name'] ?? ''));
    if ($name === '') {
        respond_json(['error' => 'Category name is required.'], 422);
    }

    $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
    $slugInput = trim((string) ($payload['slug'] ?? ''));
    $slug = events_unique_slug($slugInput !== '' ? $slugInput : $name, $categories, $id !== '' ? $id : null);
    $now = gmdate('c');
    $categoryData = null;

    if ($id !== '') {
        $updated = false;
        foreach ($categories as &$category) {
            if ((string) ($category['id'] ?? '') === $id) {
                $category['name'] = $name;
                $category['slug'] = $slug;
                if (empty($category['created_at'])) {
                    $category['created_at'] = $now;
                }
                $category['updated_at'] = $now;
                $categoryData = $category;
                $updated = true;
                break;
            }
        }
        unset($category);
        if (!$updated) {
            respond_json(['error' => 'Category not found.'], 404);
        }
    } else {
        $id = uniqid('evtcat_', false);
        $categoryData = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $categories[] = $categoryData;
    }

    if (!events_write_categories($categories)) {
        respond_json(['error' => 'Unable to save category.'], 500);
    }

    respond_json([
        'success' => true,
        'category' => $categoryData,
        'categories' => events_sort_categories($categories),
    ]);
}

function handle_delete_category(array $categories, array $events): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }

    $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
    if ($id === '') {
        respond_json(['error' => 'Missing category id.'], 400);
    }

    $removed = false;
    foreach ($categories as $index => $category) {
        if ((string) ($category['id'] ?? '') === $id) {
            array_splice($categories, $index, 1);
            $removed = true;
            break;
        }
    }

    if (!$removed) {
        respond_json(['error' => 'Category not found.'], 404);
    }

    if (!events_write_categories($categories)) {
        respond_json(['error' => 'Unable to delete category.'], 500);
    }

    $eventsUpdated = false;
    $now = gmdate('c');
    foreach ($events as &$event) {
        $original = $event['categories'] ?? [];
        $filtered = array_values(array_filter($original, static function ($categoryId) use ($id) {
            return (string) $categoryId !== $id;
        }));
        if ($filtered !== $original) {
            $event['categories'] = $filtered;
            $event['updated_at'] = $now;
            $eventsUpdated = true;
        }
    }
    unset($event);

    if ($eventsUpdated && !events_write_events($events)) {
        respond_json(['error' => 'Unable to update events.'], 500);
    }

    respond_json([
        'success' => true,
        'categories' => events_sort_categories($categories),
    ]);
}
