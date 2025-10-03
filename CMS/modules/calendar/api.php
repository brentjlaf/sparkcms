<?php
// File: modules/calendar/api.php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/CalendarController.php';

require_login();

header('Content-Type: application/json');

$repository = new CalendarRepository();
$controller = new CalendarController($repository);

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

try {
    $payload = $controller->handle($action, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST);
    respond_json($payload, 200);
} catch (InvalidArgumentException $exception) {
    $message = $exception->getMessage();
    $status = 400;
    if ($message === 'Event not found.' || $message === 'Category not found.') {
        $status = 404;
    } elseif ($message === 'Category already exists.') {
        $status = 409;
    }
    respond_json(['success' => false, 'message' => $message], $status);
} catch (RuntimeException $exception) {
    respond_json(['success' => false, 'message' => $exception->getMessage()], 405);
}

/**
 * Emit a JSON response payload and terminate execution.
 *
 * @param array<string,mixed> $payload
 */
function respond_json(array $payload, int $status): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
