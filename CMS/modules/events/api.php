<?php
// File: modules/events/api.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/helpers.php';

require_login();

events_ensure_storage();

$events = events_read_events();
$orders = events_read_orders();
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
        handle_save_event($events);
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
    case 'update_checkin':
        handle_update_checkin($orders, $events);
        break;
    case 'update_attendance':
        handle_update_attendance($events);
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

    $attendance = 0;
    $capacity = 0;
    foreach ($events as $event) {
        $id = (string) ($event['id'] ?? '');
        $capacity += events_ticket_capacity($event, true);
        $attendance += $salesByEvent[$id]['checked_in'] ?? 0;
    }

    respond_json([
        'upcoming' => $upcomingPreview,
        'stats' => $stats,
        'attendance' => [
            'checked_in' => $attendance,
            'capacity' => $capacity,
        ],
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

function handle_save_event(array $events): void
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
        'track_attendance' => $payload['track_attendance'] ?? false,
        'tickets' => $payload['tickets'] ?? [],
    ];

    $eventData = events_normalize_event($eventData);

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

        $quantity = 0;
        foreach (($order['tickets'] ?? []) as $ticket) {
            $quantity += max(0, (int) ($ticket['quantity'] ?? 0));
        }

        $event = events_find_event($events, $order['event_id'] ?? '');

        $rows[] = [
            'id' => $order['id'] ?? '',
            'event' => $event['title'] ?? 'Event',
            'event_id' => $order['event_id'] ?? '',
            'buyer_name' => $order['buyer_name'] ?? '',
            'tickets' => $quantity,
            'amount' => (float) ($order['amount'] ?? 0),
            'status' => $status,
            'ordered_at' => $order['ordered_at'] ?? '',
            'checked_in' => (int) ($order['checked_in'] ?? 0),
        ];
    }

    usort($rows, static function ($a, $b) {
        $aTime = $a['ordered_at'] ? strtotime($a['ordered_at']) : 0;
        $bTime = $b['ordered_at'] ? strtotime($b['ordered_at']) : 0;
        return $bTime <=> $aTime;
    });

    respond_json(['orders' => $rows]);
}

function handle_update_checkin(array $orders, array $events): void
{
    $payload = parse_json_body();
    $orderId = trim((string) ($payload['order_id'] ?? ($_POST['order_id'] ?? '')));
    $checkedIn = isset($payload['checked_in']) ? (int) $payload['checked_in'] : (int) ($_POST['checked_in'] ?? 0);

    if ($orderId === '') {
        respond_json(['error' => 'Missing order id.'], 400);
    }

    $updated = false;
    foreach ($orders as &$order) {
        if ((string) ($order['id'] ?? '') === $orderId) {
            $max = 0;
            foreach (($order['tickets'] ?? []) as $ticket) {
                $max += max(0, (int) ($ticket['quantity'] ?? 0));
            }
            $checkedIn = max(0, min($checkedIn, $max));
            $order['checked_in'] = $checkedIn;
            $updated = true;
            break;
        }
    }
    unset($order);

    if (!$updated) {
        respond_json(['error' => 'Order not found.'], 404);
    }

    if (!events_write_orders($orders)) {
        respond_json(['error' => 'Unable to update order.'], 500);
    }

    respond_json(['success' => true]);
}

function handle_update_attendance(array $events): void
{
    $payload = parse_json_body();
    $eventId = trim((string) ($payload['event_id'] ?? ($_POST['event_id'] ?? '')));
    $attended = isset($payload['attended']) ? (int) $payload['attended'] : (int) ($_POST['attended'] ?? 0);

    if ($eventId === '') {
        respond_json(['error' => 'Missing event id.'], 400);
    }

    $updated = false;
    foreach ($events as &$event) {
        if ((string) ($event['id'] ?? '') === $eventId) {
            $event['attended'] = max(0, $attended);
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
        respond_json(['error' => 'Unable to update attendance.'], 500);
    }

    respond_json(['success' => true]);
}

function handle_export_orders(array $orders, array $events): void
{
    $rows = [];
    $rows[] = ['Order ID', 'Event', 'Buyer', 'Tickets', 'Amount', 'Status', 'Ordered At'];
    foreach ($orders as $order) {
        $event = events_find_event($events, $order['event_id'] ?? '');
        $quantity = 0;
        foreach (($order['tickets'] ?? []) as $ticket) {
            $quantity += max(0, (int) ($ticket['quantity'] ?? 0));
        }
        $rows[] = [
            $order['id'] ?? '',
            $event['title'] ?? 'Event',
            $order['buyer_name'] ?? '',
            $quantity,
            number_format((float) ($order['amount'] ?? 0), 2, '.', ''),
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
        $metrics = $salesByEvent[$id] ?? ['tickets_sold' => 0, 'revenue' => 0, 'checked_in' => 0];
        $capacity = events_ticket_capacity($event, true);
        $reports[] = [
            'event_id' => $id,
            'title' => $event['title'] ?? 'Event',
            'tickets_sold' => $metrics['tickets_sold'] ?? 0,
            'revenue' => $metrics['revenue'] ?? 0,
            'checked_in' => $metrics['checked_in'] ?? 0,
            'attendance_rate' => $capacity > 0 ? round((($metrics['checked_in'] ?? 0) / $capacity) * 100, 1) : 0,
            'status' => $event['status'] ?? 'draft',
        ];
    }

    respond_json(['reports' => $reports]);
}

function handle_list_roles(): void
{
    respond_json(['roles' => events_default_roles()]);
}
