<?php
// File: modules/events/api.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/helpers.php';

require_login();

$service = events_service();

$action = $_GET['action'] ?? $_POST['action'] ?? 'overview';
$action = strtolower(trim((string) $action));

try {
    switch ($action) {
        case 'overview':
            respond_json($service->getOverview());
            break;
        case 'list_events':
            respond_json($service->listEvents());
            break;
        case 'get_event':
            respond_json($service->getEvent((string) ($_GET['id'] ?? '')));
            break;
        case 'save_event':
            $payload = parse_payload();
            if (isset($payload['tickets']) && is_string($payload['tickets'])) {
                $tickets = json_decode($payload['tickets'], true);
                if (is_array($tickets)) {
                    $payload['tickets'] = $tickets;
                }
            }
            respond_json($service->saveEvent($payload));
            break;
        case 'copy_event':
            $payload = parse_payload();
            $id = $payload['id'] ?? ($_GET['id'] ?? '');
            respond_json($service->copyEvent((string) $id));
            break;
        case 'delete_event':
            $payload = parse_payload();
            $id = $payload['id'] ?? ($_GET['id'] ?? '');
            respond_json($service->deleteEvent((string) $id));
            break;
        case 'end_event':
            $payload = parse_payload();
            $id = $payload['id'] ?? ($_GET['id'] ?? '');
            respond_json($service->endEvent((string) $id));
            break;
        case 'list_orders':
            $filters = [
                'event' => $_GET['event'] ?? '',
                'status' => $_GET['status'] ?? '',
                'start' => $_GET['start'] ?? '',
                'end' => $_GET['end'] ?? '',
            ];
            respond_json($service->listOrders($filters));
            break;
        case 'get_order':
            respond_json($service->getOrder((string) ($_GET['id'] ?? '')));
            break;
        case 'save_order':
            respond_json($service->saveOrder(parse_payload()));
            break;
        case 'export_orders':
            $csv = $service->exportOrders();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="event-orders.csv"');
            echo $csv;
            exit;
        case 'reports_summary':
            respond_json($service->getReportsSummary());
            break;
        case 'list_roles':
            respond_json($service->listRoles());
            break;
        case 'list_categories':
            respond_json($service->listCategories());
            break;
        case 'save_category':
            respond_json($service->saveCategory(parse_payload()));
            break;
        case 'delete_category':
            $payload = parse_payload();
            $id = $payload['id'] ?? ($_GET['id'] ?? '');
            respond_json($service->deleteCategory((string) $id));
            break;
        default:
            respond_json(['error' => 'Unknown action.'], 400);
    }
} catch (InvalidArgumentException $exception) {
    $code = $exception->getCode();
    if ($code < 400 || $code >= 600) {
        $code = 400;
    }
    respond_json(['error' => $exception->getMessage()], $code);
} catch (RuntimeException $exception) {
    $code = $exception->getCode();
    if ($code < 400 || $code >= 600) {
        $code = 500;
    }
    respond_json(['error' => $exception->getMessage()], $code);
}

function respond_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function parse_payload(): array
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }
    return is_array($payload) ? $payload : [];
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
