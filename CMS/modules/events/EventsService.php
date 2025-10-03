<?php
// File: CMS/modules/events/EventsService.php

require_once __DIR__ . '/EventsRepository.php';

class EventsService
{
    /** @var EventsRepository */
    private $repository;

    public function __construct(EventsRepository $repository)
    {
        $this->repository = $repository;
    }

    public static function createDefault(): self
    {
        return new self(new EventsRepository());
    }

    public function getEventsData(): array
    {
        return $this->repository->getEvents();
    }

    public function getOrdersData(): array
    {
        return $this->repository->getOrders();
    }

    public function formatCurrency(float $value): string
    {
        return '$' . number_format($value, 2);
    }

    public function getUpcomingEvents(int $limit = 5): array
    {
        return array_slice($this->filterUpcoming($this->repository->getEvents()), 0, $limit);
    }

    public function getOverview(): array
    {
        $events = $this->repository->getEvents();
        $orders = $this->repository->getOrders();
        $sales = $this->computeSales($events, $orders);
        $upcoming = [];
        foreach (array_slice($this->filterUpcoming($events), 0, 5) as $event) {
            $id = (string) ($event['id'] ?? '');
            $metrics = $sales[$id] ?? ['tickets_sold' => 0, 'revenue' => 0];
            $upcoming[] = [
                'id' => $id,
                'title' => $event['title'] ?? 'Untitled',
                'image' => $event['image'] ?? '',
                'start' => $event['start'] ?? '',
                'tickets_sold' => $metrics['tickets_sold'] ?? 0,
                'revenue' => $metrics['revenue'] ?? 0,
            ];
        }
        $stats = [
            'total_events' => count($events),
            'total_tickets_sold' => array_sum(array_column($sales, 'tickets_sold')),
            'total_revenue' => array_sum(array_column($sales, 'revenue')),
        ];
        return [
            'upcoming' => $upcoming,
            'stats' => $stats,
        ];
    }

    public function listEvents(): array
    {
        $events = $this->repository->getEvents();
        $orders = $this->repository->getOrders();
        $sales = $this->computeSales($events, $orders);
        $rows = [];
        foreach ($events as $event) {
            $id = (string) ($event['id'] ?? '');
            $metrics = $sales[$id] ?? ['tickets_sold' => 0, 'revenue' => 0];
            $rows[] = [
                'id' => $id,
                'title' => $event['title'] ?? 'Untitled Event',
                'location' => $event['location'] ?? '',
                'start' => $event['start'] ?? '',
                'end' => $event['end'] ?? '',
                'image' => $event['image'] ?? '',
                'status' => $event['status'] ?? 'draft',
                'tickets_sold' => $metrics['tickets_sold'] ?? 0,
                'revenue' => $metrics['revenue'] ?? 0,
                'capacity' => $this->ticketCapacity($event, true),
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
        return ['events' => $rows];
    }

    public function getEvent(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Missing event id.');
        }
        $event = $this->repository->findEvent($this->repository->getEvents(), $id);
        if ($event === null) {
            throw new RuntimeException('Event not found.', 404);
        }
        return ['event' => $event];
    }

    public function saveEvent(array $payload): array
    {
        $events = $this->repository->getEvents();
        $categories = $this->repository->getCategories();
        $eventData = [
            'id' => $payload['id'] ?? null,
            'title' => $payload['title'] ?? '',
            'description' => $payload['description'] ?? '',
            'location' => $payload['location'] ?? '',
            'image' => $payload['image'] ?? '',
            'start' => $payload['start'] ?? '',
            'end' => $payload['end'] ?? '',
            'status' => $payload['status'] ?? 'draft',
            'tickets' => $payload['tickets'] ?? [],
            'categories' => $payload['categories'] ?? [],
        ];
        $normalized = $this->normalizeEvent($eventData, $categories);
        $assoc = [];
        foreach ($events as $event) {
            $assoc[(string) ($event['id'] ?? '')] = $event;
        }
        $assoc[$normalized['id']] = array_merge($assoc[$normalized['id']] ?? [], $normalized);
        $this->repository->saveEvents(array_values($assoc));
        return ['success' => true, 'event' => $normalized];
    }

    public function copyEvent(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Missing event id.');
        }
        $events = $this->repository->getEvents();
        $categories = $this->repository->getCategories();
        $original = $this->repository->findEvent($events, $id);
        if ($original === null) {
            throw new RuntimeException('Event not found.', 404);
        }
        $copy = $original;
        unset($copy['id'], $copy['created_at'], $copy['updated_at'], $copy['published_at']);
        if (isset($copy['tickets']) && is_array($copy['tickets'])) {
            foreach ($copy['tickets'] as &$ticket) {
                if (is_array($ticket)) {
                    unset($ticket['id']);
                }
            }
            unset($ticket);
        }
        $copy['title'] = $this->generateCopyTitle($events, $original['title'] ?? 'Untitled Event', $id);
        $copy['status'] = 'draft';
        $normalized = $this->normalizeEvent($copy, $categories);
        $events[] = $normalized;
        $this->repository->saveEvents($events);
        return ['success' => true, 'event' => $normalized];
    }

    public function deleteEvent(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Missing event id.');
        }
        $events = $this->repository->getEvents();
        $remaining = array_values(array_filter($events, static function ($event) use ($id) {
            return (string) ($event['id'] ?? '') !== $id;
        }));
        if (count($remaining) === count($events)) {
            throw new RuntimeException('Event not found.', 404);
        }
        $this->repository->saveEvents($remaining);
        return ['success' => true];
    }

    public function endEvent(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Missing event id.');
        }
        $events = $this->repository->getEvents();
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
            throw new RuntimeException('Event not found.', 404);
        }
        $this->repository->saveEvents($events);
        return ['success' => true];
    }

    public function listOrders(array $filters = []): array
    {
        $orders = $this->repository->getOrders();
        $events = $this->repository->getEvents();
        $eventFilter = isset($filters['event']) ? trim((string) $filters['event']) : '';
        $statusFilter = isset($filters['status']) ? strtolower(trim((string) $filters['status'])) : '';
        $startDate = isset($filters['start']) ? trim((string) $filters['start']) : '';
        $endDate = isset($filters['end']) ? trim((string) $filters['end']) : '';
        $rows = [];
        foreach ($orders as $order) {
            $summary = $this->orderSummary($order, $events);
            if ($eventFilter !== '' && (string) $summary['event_id'] !== $eventFilter) {
                continue;
            }
            $status = strtolower((string) ($summary['status'] ?? 'paid'));
            if ($statusFilter !== '' && $status !== $statusFilter) {
                continue;
            }
            $orderedAt = isset($summary['ordered_at']) ? strtotime((string) $summary['ordered_at']) : false;
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
            $summary['status'] = $status;
            $rows[] = $summary;
        }
        usort($rows, static function ($a, $b) {
            $aTime = $a['ordered_at'] ? strtotime($a['ordered_at']) : 0;
            $bTime = $b['ordered_at'] ? strtotime($b['ordered_at']) : 0;
            return $bTime <=> $aTime;
        });
        return ['orders' => $rows];
    }

    public function exportOrders(): string
    {
        $orders = $this->repository->getOrders();
        $events = $this->repository->getEvents();
        $rows = [];
        $rows[] = ['Order ID', 'Event', 'Buyer', 'Tickets', 'Ticket Details', 'Amount', 'Status', 'Ordered At'];
        foreach ($orders as $order) {
            $summary = $this->orderSummary($order, $events);
            $ticketDetails = [];
            foreach ($summary['line_items'] ?? [] as $line) {
                $quantity = (int) ($line['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }
                $name = (string) ($line['name'] ?? 'Ticket');
                $ticketDetails[] = sprintf('%d x %s', $quantity, $name);
            }
            $rows[] = [
                $summary['id'] ?? '',
                $summary['event'] ?? 'Event',
                $summary['buyer_name'] ?? '',
                $summary['tickets'] ?? 0,
                implode('; ', $ticketDetails),
                number_format((float) ($summary['amount'] ?? 0), 2, '.', ''),
                strtoupper((string) ($summary['status'] ?? 'paid')),
                $summary['ordered_at'] ?? '',
            ];
        }
        $fh = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fh, $row, ',', '"', '\\');
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        return $csv;
    }

    public function getReportsSummary(): array
    {
        $events = $this->repository->getEvents();
        $orders = $this->repository->getOrders();
        $sales = $this->computeSales($events, $orders);
        $reports = [];
        foreach ($events as $event) {
            $id = (string) ($event['id'] ?? '');
            $metrics = $sales[$id] ?? ['tickets_sold' => 0, 'revenue' => 0, 'refunded' => 0];
            $reports[] = [
                'event_id' => $id,
                'title' => $event['title'] ?? 'Event',
                'tickets_sold' => $metrics['tickets_sold'] ?? 0,
                'revenue' => $metrics['revenue'] ?? 0,
                'refunded' => $metrics['refunded'] ?? 0,
                'status' => $event['status'] ?? 'draft',
            ];
        }
        return ['reports' => $reports];
    }

    public function getOrder(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Missing order id.');
        }
        $orders = $this->repository->getOrders();
        $events = $this->repository->getEvents();
        $order = $this->repository->findOrder($orders, $id);
        if ($order === null) {
            throw new RuntimeException('Order not found.', 404);
        }
        return ['order' => $this->orderDetail($order, $events)];
    }

    public function saveOrder(array $payload): array
    {
        $orders = $this->repository->getOrders();
        $events = $this->repository->getEvents();
        $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
        if ($id === '') {
            throw new InvalidArgumentException('Missing order id.');
        }
        $index = null;
        foreach ($orders as $key => $existing) {
            if ((string) ($existing['id'] ?? '') === $id) {
                $index = $key;
                break;
            }
        }
        if ($index === null) {
            throw new RuntimeException('Order not found.', 404);
        }
        $current = $orders[$index];
        $updated = $current;
        if (array_key_exists('buyer_name', $payload)) {
            $updated['buyer_name'] = $payload['buyer_name'];
        }
        if (array_key_exists('status', $payload)) {
            $updated['status'] = $payload['status'];
        }
        if (array_key_exists('ordered_at', $payload)) {
            $updated['ordered_at'] = $payload['ordered_at'];
        }
        if (isset($payload['tickets']) && is_array($payload['tickets'])) {
            $updated['tickets'] = $payload['tickets'];
        }
        $normalized = $this->normalizeOrder($updated, $events, $current);
        $orders[$index] = array_merge($current, $normalized);
        $this->repository->saveOrders($orders);
        return [
            'success' => true,
            'order' => $this->orderDetail($orders[$index], $events),
        ];
    }

    public function listRoles(): array
    {
        return ['roles' => [
            [
                'role' => 'Admin',
                'description' => 'Full access to events, tickets, orders, and settings.',
            ],
            [
                'role' => 'Event Manager',
                'description' => 'Create and manage events, update tickets, and view sales.',
            ],
            [
                'role' => 'Viewer',
                'description' => 'Read-only access to dashboards and reports.',
            ],
        ]];
    }

    public function listCategories(): array
    {
        return ['categories' => $this->repository->getCategories()];
    }

    public function saveCategory(array $payload): array
    {
        $categories = $this->repository->getCategories();
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.', 422);
        }
        $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
        $slugInput = trim((string) ($payload['slug'] ?? ''));
        $slug = $this->repository->uniqueCategorySlug($slugInput !== '' ? $slugInput : $name, $categories, $id !== '' ? $id : null);
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
                throw new RuntimeException('Category not found.', 404);
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
        $this->repository->saveCategories($categories);
        return [
            'success' => true,
            'category' => $categoryData,
            'categories' => $this->repository->getCategories(),
        ];
    }

    public function deleteCategory(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Missing category id.');
        }
        $categories = $this->repository->getCategories();
        $removed = false;
        foreach ($categories as $index => $category) {
            if ((string) ($category['id'] ?? '') === $id) {
                array_splice($categories, $index, 1);
                $removed = true;
                break;
            }
        }
        if (!$removed) {
            throw new RuntimeException('Category not found.', 404);
        }
        $this->repository->saveCategories($categories);
        $events = $this->repository->getEvents();
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
        if ($eventsUpdated) {
            $this->repository->saveEvents($events);
        }
        return [
            'success' => true,
            'categories' => $this->repository->getCategories(),
        ];
    }

    public function getInitialPayload(): array
    {
        $events = $this->repository->getEvents();
        $orders = $this->repository->getOrders();
        $categories = $this->repository->getCategories();
        $sales = $this->computeSales($events, $orders);
        $orderSummaries = [];
        foreach ($orders as $order) {
            $orderSummaries[] = $this->orderSummary($order, $events);
        }
        return [
            'events' => $events,
            'orders' => $orderSummaries,
            'sales' => $sales,
            'categories' => $categories,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @param array<int,array<string,mixed>> $orders
     */
    private function computeSales(array $events, array $orders): array
    {
        $salesByEvent = [];
        foreach ($events as $event) {
            $eventId = (string) ($event['id'] ?? '');
            if ($eventId === '') {
                continue;
            }
            $salesByEvent[$eventId] = [
                'tickets_sold' => 0,
                'revenue' => 0.0,
                'refunded' => 0.0,
            ];
        }
        foreach ($orders as $order) {
            $eventId = (string) ($order['event_id'] ?? '');
            if ($eventId === '' || !isset($salesByEvent[$eventId])) {
                continue;
            }
            $quantity = 0;
            foreach (($order['tickets'] ?? []) as $ticket) {
                $quantity += max(0, (int) ($ticket['quantity'] ?? 0));
            }
            $amount = (float) ($order['amount'] ?? 0);
            $status = strtolower((string) ($order['status'] ?? 'paid'));
            if ($status === 'refunded') {
                $salesByEvent[$eventId]['refunded'] += $amount;
            } else {
                $salesByEvent[$eventId]['tickets_sold'] += $quantity;
                $salesByEvent[$eventId]['revenue'] += $amount;
            }
        }
        return $salesByEvent;
    }

    /**
     * @param array<int,array<string,mixed>> $events
     */
    private function filterUpcoming(array $events): array
    {
        $now = time();
        $upcoming = array_filter($events, static function ($event) use ($now) {
            $start = isset($event['start']) ? strtotime((string) $event['start']) : false;
            return $start !== false && $start >= $now;
        });
        usort($upcoming, static function ($a, $b) {
            $aTime = isset($a['start']) ? strtotime((string) $a['start']) : 0;
            $bTime = isset($b['start']) ? strtotime((string) $b['start']) : 0;
            if ($aTime === $bTime) {
                return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }
            return $aTime <=> $bTime;
        });
        return array_values($upcoming);
    }

    private function ticketCapacity(array $event, bool $onlyEnabled = false): int
    {
        $tickets = $event['tickets'] ?? [];
        $capacity = 0;
        foreach ($tickets as $ticket) {
            if ($onlyEnabled && empty($ticket['enabled'])) {
                continue;
            }
            $capacity += max(0, (int) ($ticket['quantity'] ?? 0));
        }
        return $capacity;
    }

    private function generateCopyTitle(array $events, string $originalTitle, ?string $excludeId = null): string
    {
        $baseTitle = trim($originalTitle) === '' ? 'Untitled Event' : trim($originalTitle);
        $existing = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $id = (string) ($event['id'] ?? '');
            if ($excludeId !== null && $id === $excludeId) {
                continue;
            }
            $title = trim((string) ($event['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $existing[strtolower($title)] = true;
        }
        $base = $baseTitle . ' Copy';
        $candidate = $base;
        $suffix = 2;
        while (isset($existing[strtolower($candidate)])) {
            $candidate = $base . ' ' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private function normalizeEvent(array $event, array $categories = []): array
    {
        $now = gmdate('c');
        if (empty($event['id'])) {
            $event['id'] = uniqid('evt_', true);
            $event['created_at'] = $now;
        }
        $event['title'] = trim((string) ($event['title'] ?? 'Untitled Event'));
        $event['description'] = (string) ($event['description'] ?? '');
        $event['location'] = trim((string) ($event['location'] ?? ''));
        $event['image'] = trim((string) ($event['image'] ?? ''));
        $event['start'] = (string) ($event['start'] ?? '');
        $event['end'] = (string) ($event['end'] ?? '');
        $status = $event['status'] ?? 'draft';
        $event['status'] = in_array($status, ['draft', 'published', 'ended'], true) ? $status : 'draft';
        $event['tickets'] = array_values(array_map([$this, 'normalizeTicket'], $event['tickets'] ?? []));
        $event['categories'] = $this->filterCategoryIds($event['categories'] ?? [], $categories);
        if (!isset($event['published_at']) && $event['status'] === 'published') {
            $event['published_at'] = $now;
        }
        $event['updated_at'] = $now;
        return $event;
    }

    private function normalizeTicket(array $ticket): array
    {
        $ticket['id'] = $ticket['id'] ?? uniqid('tkt_', true);
        $ticket['name'] = trim((string) ($ticket['name'] ?? ''));
        $ticket['price'] = (float) ($ticket['price'] ?? 0);
        $ticket['quantity'] = max(0, (int) ($ticket['quantity'] ?? 0));
        $ticket['enabled'] = filter_var($ticket['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        return $ticket;
    }

    private function filterCategoryIds($categoryIds, array $categories): array
    {
        if (!is_array($categoryIds)) {
            return [];
        }
        $validIds = [];
        $known = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            if ($id !== '') {
                $known[$id] = true;
            }
        }
        foreach ($categoryIds as $categoryId) {
            $categoryId = (string) $categoryId;
            if ($categoryId === '' || !isset($known[$categoryId])) {
                continue;
            }
            if (!in_array($categoryId, $validIds, true)) {
                $validIds[] = $categoryId;
            }
        }
        return $validIds;
    }

    private function ticketPriceLookup(array $event): array
    {
        $lookup = [];
        foreach ($event['tickets'] ?? [] as $ticket) {
            $ticketId = (string) ($ticket['id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $lookup[$ticketId] = [
                'name' => (string) ($ticket['name'] ?? ''),
                'price' => (float) ($ticket['price'] ?? 0),
            ];
        }
        return $lookup;
    }

    private function eventTicketOptions(?array $event): array
    {
        if (!is_array($event)) {
            return [];
        }
        $options = [];
        foreach ($event['tickets'] ?? [] as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $ticketId = (string) ($ticket['id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $options[] = [
                'ticket_id' => $ticketId,
                'name' => (string) ($ticket['name'] ?? 'Ticket'),
                'price' => (float) ($ticket['price'] ?? 0),
            ];
        }
        usort($options, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return array_values($options);
    }

    private function normalizeOrderTickets(array $tickets, array $events, string $eventId): array
    {
        $lookup = [];
        if ($eventId !== '') {
            $event = $this->repository->findEvent($events, $eventId);
            if ($event) {
                $lookup = $this->ticketPriceLookup($event);
            }
        }
        $normalized = [];
        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $ticketId = (string) ($ticket['ticket_id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $quantity = max(0, (int) ($ticket['quantity'] ?? 0));
            $price = isset($ticket['price']) ? (float) $ticket['price'] : ($lookup[$ticketId]['price'] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            if (!isset($normalized[$ticketId])) {
                $normalized[$ticketId] = [
                    'ticket_id' => $ticketId,
                    'quantity' => $quantity,
                    'price' => $price,
                ];
            } else {
                $normalized[$ticketId]['quantity'] += $quantity;
                $normalized[$ticketId]['price'] = $price;
            }
        }
        return array_values($normalized);
    }

    private function normalizeOrder(array $order, array $events, ?array $original = null): array
    {
        $id = isset($order['id']) ? trim((string) $order['id']) : '';
        if ($id === '' && $original) {
            $id = (string) ($original['id'] ?? '');
        }
        $order['id'] = $id;
        $eventId = isset($order['event_id']) ? trim((string) $order['event_id']) : '';
        if ($eventId === '' && $original) {
            $eventId = (string) ($original['event_id'] ?? '');
        }
        $order['event_id'] = $eventId;
        $order['buyer_name'] = trim((string) ($order['buyer_name'] ?? ($original['buyer_name'] ?? '')));
        $status = strtolower((string) ($order['status'] ?? ($original['status'] ?? 'paid')));
        $allowed = ['paid', 'pending', 'refunded'];
        if (!in_array($status, $allowed, true)) {
            $status = 'paid';
        }
        $order['status'] = $status;
        $orderedAt = $order['ordered_at'] ?? ($original['ordered_at'] ?? '');
        if ($orderedAt !== '') {
            $timestamp = strtotime((string) $orderedAt);
            if ($timestamp !== false) {
                $order['ordered_at'] = gmdate('c', $timestamp);
            } elseif ($original && isset($original['ordered_at'])) {
                $order['ordered_at'] = $original['ordered_at'];
            }
        } elseif ($original && isset($original['ordered_at'])) {
            $order['ordered_at'] = $original['ordered_at'];
        } else {
            $order['ordered_at'] = '';
        }
        $order['tickets'] = $this->normalizeOrderTickets($order['tickets'] ?? [], $events, $eventId);
        $amount = 0.0;
        foreach ($order['tickets'] as $ticket) {
            $amount += (float) $ticket['price'] * (int) $ticket['quantity'];
        }
        $order['amount'] = round($amount, 2);
        $now = gmdate('c');
        if ($original && isset($original['created_at'])) {
            $order['created_at'] = $original['created_at'];
        } elseif (empty($order['created_at'])) {
            $order['created_at'] = $now;
        }
        $order['updated_at'] = $now;
        return $order;
    }

    private function orderLineItems(array $order, array $events): array
    {
        $event = $this->repository->findEvent($events, $order['event_id'] ?? '');
        $lookup = $event ? $this->ticketPriceLookup($event) : [];
        $lines = [];
        foreach ($order['tickets'] ?? [] as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $ticketId = (string) ($ticket['ticket_id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $quantity = max(0, (int) ($ticket['quantity'] ?? 0));
            $price = (float) ($ticket['price'] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            $name = $lookup[$ticketId]['name'] ?? ($ticket['name'] ?? 'Ticket');
            if ($price === 0 && isset($lookup[$ticketId]['price'])) {
                $price = (float) $lookup[$ticketId]['price'];
            }
            $lines[] = [
                'ticket_id' => $ticketId,
                'name' => $name,
                'price' => round($price, 2),
                'quantity' => $quantity,
                'subtotal' => round($price * $quantity, 2),
            ];
        }
        return $lines;
    }

    private function orderSummary(array $order, array $events): array
    {
        $lines = $this->orderLineItems($order, $events);
        $tickets = 0;
        $amount = 0.0;
        foreach ($lines as $line) {
            $tickets += (int) $line['quantity'];
            $amount += (float) $line['subtotal'];
        }
        $status = strtolower((string) ($order['status'] ?? 'paid'));
        $event = $this->repository->findEvent($events, $order['event_id'] ?? '');
        return [
            'id' => (string) ($order['id'] ?? ''),
            'event_id' => (string) ($order['event_id'] ?? ''),
            'event' => $event['title'] ?? 'Event',
            'buyer_name' => (string) ($order['buyer_name'] ?? ''),
            'tickets' => $tickets,
            'amount' => round($amount, 2),
            'status' => $status,
            'ordered_at' => (string) ($order['ordered_at'] ?? ''),
            'line_items' => $lines,
        ];
    }

    private function orderDetail(array $order, array $events): array
    {
        $summary = $this->orderSummary($order, $events);
        $event = $this->repository->findEvent($events, $summary['event_id']);
        $subtotal = (float) $summary['amount'];
        $isRefunded = $summary['status'] === 'refunded';
        $refunds = $isRefunded ? $subtotal : 0.0;
        return [
            'id' => $summary['id'],
            'event_id' => $summary['event_id'],
            'event' => [
                'id' => $event['id'] ?? '',
                'title' => $event['title'] ?? ($summary['event'] ?? 'Event'),
            ],
            'buyer_name' => $summary['buyer_name'],
            'status' => $summary['status'],
            'ordered_at' => $summary['ordered_at'],
            'line_items' => $summary['line_items'],
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'refunds' => round($refunds, 2),
                'net' => round($subtotal - $refunds, 2),
            ],
            'available_tickets' => $this->eventTicketOptions($event),
        ];
    }
}
