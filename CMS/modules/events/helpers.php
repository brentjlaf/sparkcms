<?php
// File: modules/events/helpers.php
require_once __DIR__ . '/../../includes/data.php';

if (!function_exists('events_data_paths')) {
    function events_data_paths(): array
    {
        $baseDir = __DIR__ . '/../../data';
        return [
            'events' => $baseDir . '/events.json',
            'orders' => $baseDir . '/event_orders.json',
            'categories' => $baseDir . '/event_categories.json',
        ];
    }
}

if (!function_exists('events_ensure_storage')) {
    function events_ensure_storage(): void
    {
        $paths = events_data_paths();
        foreach ($paths as $path) {
            if (!is_file($path)) {
                file_put_contents($path, "[]\n");
            }
        }
    }
}

if (!function_exists('events_slugify')) {
    function events_slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim((string) $value, '-');
        if ($value === '') {
            return uniqid('category_', false);
        }
        return $value;
    }
}

if (!function_exists('events_unique_slug')) {
    function events_unique_slug(string $desired, array $categories, ?string $currentId = null): string
    {
        $slug = events_slugify($desired);
        $base = $slug;
        if ($base === '') {
            $base = uniqid('category_', false);
        }
        $slug = $base;
        $existing = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            if ($currentId !== null && $id === $currentId) {
                continue;
            }
            $key = strtolower((string) ($category['slug'] ?? ''));
            if ($key !== '') {
                $existing[$key] = true;
            }
        }
        $candidate = strtolower($slug);
        $suffix = 2;
        while ($candidate === '' || isset($existing[$candidate])) {
            $slug = $base . '-' . $suffix;
            $candidate = strtolower($slug);
            $suffix++;
        }
        return $slug;
    }
}

if (!function_exists('events_read_events')) {
    function events_read_events(): array
    {
        events_ensure_storage();
        $paths = events_data_paths();
        $events = read_json_file($paths['events']);
        if (!is_array($events)) {
            return [];
        }
        return array_values(array_filter($events, static function ($item) {
            return is_array($item) && isset($item['id']);
        }));
    }
}

if (!function_exists('events_read_orders')) {
    function events_read_orders(): array
    {
        events_ensure_storage();
        $paths = events_data_paths();
        $orders = read_json_file($paths['orders']);
        if (!is_array($orders)) {
            return [];
        }
        return array_values(array_filter($orders, static function ($item) {
            return is_array($item) && isset($item['id']);
        }));
    }
}

if (!function_exists('events_read_categories')) {
    function events_read_categories(): array
    {
        events_ensure_storage();
        $paths = events_data_paths();
        $categories = read_json_file($paths['categories']);
        if (!is_array($categories)) {
            return [];
        }
        return events_sort_categories($categories);
    }
}

if (!function_exists('events_write_events')) {
    function events_write_events(array $events): bool
    {
        $paths = events_data_paths();
        return write_json_file($paths['events'], array_values($events));
    }
}

if (!function_exists('events_write_orders')) {
    function events_write_orders(array $orders): bool
    {
        $paths = events_data_paths();
        return write_json_file($paths['orders'], array_values($orders));
    }
}

if (!function_exists('events_sort_categories')) {
    function events_sort_categories(array $categories): array
    {
        $normalized = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            $name = trim((string) ($category['name'] ?? ''));
            $slug = (string) ($category['slug'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'created_at' => $category['created_at'] ?? null,
                'updated_at' => $category['updated_at'] ?? null,
            ];
        }

        usort($normalized, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return array_values($normalized);
    }
}

if (!function_exists('events_write_categories')) {
    function events_write_categories(array $categories): bool
    {
        $paths = events_data_paths();
        return write_json_file($paths['categories'], events_sort_categories($categories));
    }
}

if (!function_exists('events_find_event')) {
    function events_find_event(array $events, $id): ?array
    {
        foreach ($events as $event) {
            if ((string) ($event['id'] ?? '') === (string) $id) {
                return $event;
            }
        }
        return null;
    }
}

if (!function_exists('events_normalize_ticket')) {
    function events_normalize_ticket(array $ticket): array
    {
        $ticket['id'] = $ticket['id'] ?? uniqid('tkt_', true);
        $ticket['name'] = trim((string) ($ticket['name'] ?? '')); 
        $ticket['price'] = (float) ($ticket['price'] ?? 0);
        $ticket['quantity'] = max(0, (int) ($ticket['quantity'] ?? 0));
        $ticket['enabled'] = filter_var($ticket['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        return $ticket;
    }
}

if (!function_exists('events_filter_category_ids')) {
    function events_filter_category_ids($categoryIds, array $categories): array
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
}

if (!function_exists('events_normalize_event')) {
    function events_normalize_event(array $event, array $categories = []): array
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
        $event['status'] = in_array($event['status'] ?? '', ['draft', 'published', 'ended'], true)
            ? $event['status']
            : 'draft';
        $event['tickets'] = array_values(array_map('events_normalize_ticket', $event['tickets'] ?? []));
        $event['categories'] = events_filter_category_ids($event['categories'] ?? [], $categories);
        if (!isset($event['published_at']) && $event['status'] === 'published') {
            $event['published_at'] = $now;
        }
        $event['updated_at'] = $now;
        return $event;
    }
}

if (!function_exists('events_ticket_capacity')) {
    function events_ticket_capacity(array $event, bool $onlyEnabled = false): int
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
}

if (!function_exists('events_ticket_price_lookup')) {
    function events_ticket_price_lookup(array $event): array
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
}

if (!function_exists('events_compute_sales')) {
    function events_compute_sales(array $events, array $orders): array
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
            $amount = (float) ($order['amount'] ?? 0);
            foreach (($order['tickets'] ?? []) as $ticket) {
                $quantity += max(0, (int) ($ticket['quantity'] ?? 0));
            }
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
}

if (!function_exists('events_filter_upcoming')) {
    function events_filter_upcoming(array $events): array
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
}

if (!function_exists('events_format_currency')) {
    function events_format_currency(float $value): string
    {
        return '$' . number_format($value, 2);
    }
}

if (!function_exists('events_default_roles')) {
    function events_default_roles(): array
    {
        return [
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
        ];
    }
}
