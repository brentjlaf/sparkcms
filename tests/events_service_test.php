<?php
require_once __DIR__ . '/../CMS/modules/events/EventsService.php';

function create_events_repository(array $events, array $orders, array $categories): EventsRepository
{
    $eventsFile = tempnam(sys_get_temp_dir(), 'events');
    $ordersFile = tempnam(sys_get_temp_dir(), 'orders');
    $categoriesFile = tempnam(sys_get_temp_dir(), 'categories');
    if ($eventsFile === false || $ordersFile === false || $categoriesFile === false) {
        throw new RuntimeException('Unable to create temporary dataset.');
    }
    file_put_contents($eventsFile, json_encode($events));
    file_put_contents($ordersFile, json_encode($orders));
    file_put_contents($categoriesFile, json_encode($categories));
    return new EventsRepository($eventsFile, $ordersFile, $categoriesFile);
}

$eventsFixture = [
    [
        'id' => 'evt_alpha',
        'title' => 'Launch Party',
        'description' => 'Kickoff celebration',
        'location' => 'Main Hall',
        'status' => 'published',
        'start' => '2030-01-01T18:00:00Z',
        'end' => '2030-01-01T22:00:00Z',
        'created_at' => '2029-01-01T00:00:00Z',
        'updated_at' => '2029-01-01T00:00:00Z',
        'tickets' => [
            ['id' => 'tkt_ga', 'name' => 'General Admission', 'price' => 49.99, 'quantity' => 100, 'enabled' => true],
            ['id' => 'tkt_vip', 'name' => 'VIP', 'price' => 149.5, 'quantity' => 20, 'enabled' => true],
        ],
        'categories' => ['cat_live', 'cat_virtual'],
    ],
];

$ordersFixture = [
    [
        'id' => 'ord_001',
        'event_id' => 'evt_alpha',
        'buyer_name' => 'Avery Collins',
        'status' => 'paid',
        'ordered_at' => '2029-12-01T12:00:00Z',
        'tickets' => [
            ['ticket_id' => 'tkt_ga', 'quantity' => 2, 'price' => 49.99],
            ['ticket_id' => 'tkt_vip', 'quantity' => 1, 'price' => 149.5],
        ],
    ],
    [
        'id' => 'ord_002',
        'event_id' => 'evt_alpha',
        'buyer_name' => 'Jordan Lee',
        'status' => 'refunded',
        'ordered_at' => '2029-12-15T09:30:00Z',
        'tickets' => [
            ['ticket_id' => 'tkt_ga', 'quantity' => 3, 'price' => 49.99],
        ],
    ],
];

$categoriesFixture = [
    ['id' => 'cat_live', 'name' => 'Live', 'slug' => 'live'],
    ['id' => 'cat_virtual', 'name' => 'Virtual', 'slug' => 'virtual'],
];

$repository = create_events_repository($eventsFixture, $ordersFixture, $categoriesFixture);
$service = new EventsService($repository);

// Copy event scenario
$copyResponse = $service->copyEvent('evt_alpha');
if (($copyResponse['success'] ?? false) !== true) {
    throw new RuntimeException('Copy event should return success flag.');
}
$copiedEvent = $copyResponse['event'];
if ($copiedEvent['id'] === 'evt_alpha') {
    throw new RuntimeException('Copied event must receive a new identifier.');
}
if ($copiedEvent['status'] !== 'draft') {
    throw new RuntimeException('Copied event should reset to draft status.');
}
foreach ($copiedEvent['tickets'] as $ticket) {
    if ($ticket['id'] === 'tkt_ga' || $ticket['id'] === 'tkt_vip') {
        throw new RuntimeException('Tickets should be regenerated when copying an event.');
    }
}

$eventsAfterCopy = $repository->getEvents();
if (count($eventsAfterCopy) !== 2) {
    throw new RuntimeException('Copying an event should persist the new event.');
}

// Delete category scenario
$deleteResponse = $service->deleteCategory('cat_live');
if (($deleteResponse['success'] ?? false) !== true) {
    throw new RuntimeException('Deleting a category should return success.');
}
$categoriesAfterDelete = $repository->getCategories();
foreach ($categoriesAfterDelete as $category) {
    if ($category['id'] === 'cat_live') {
        throw new RuntimeException('Deleted category should not remain in repository.');
    }
}
$eventsAfterDelete = $repository->getEvents();
foreach ($eventsAfterDelete as $event) {
    if (in_array('cat_live', $event['categories'], true)) {
        throw new RuntimeException('Removing a category should cascade to associated events.');
    }
}

// Export serialization scenario
$csv = $service->exportOrders();
$lines = array_filter(explode("\n", trim($csv)));
if (count($lines) !== 3) {
    throw new RuntimeException('Export should include header plus one line per order.');
}
$header = str_getcsv($lines[0], ',', '"', '\\');
$expectedHeader = ['Order ID', 'Event', 'Buyer', 'Tickets', 'Ticket Details', 'Amount', 'Status', 'Ordered At'];
if ($header !== $expectedHeader) {
    throw new RuntimeException('CSV header does not match expected format.');
}
if (strpos($csv, 'ord_001') === false || strpos($csv, 'ord_002') === false) {
    throw new RuntimeException('CSV export should contain all orders.');
}
if (strpos($csv, 'REFUNDED') === false) {
    throw new RuntimeException('CSV export should uppercase the status field.');
}

unlink($repository->dataPaths()['events']);
unlink($repository->dataPaths()['orders']);
unlink($repository->dataPaths()['categories']);

echo "EventsService tests passed\n";
