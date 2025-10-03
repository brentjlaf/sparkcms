<?php
// File: CMS/modules/events/EventsRepository.php

require_once __DIR__ . '/../../includes/data.php';

class EventsRepository
{
    /** @var string */
    private $eventsFile;

    /** @var string */
    private $ordersFile;

    /** @var string */
    private $categoriesFile;

    public function __construct($eventsFile = null, $ordersFile = null, $categoriesFile = null)
    {
        $baseDir = __DIR__ . '/../../data';
        $this->eventsFile = $eventsFile ?? $baseDir . '/events.json';
        $this->ordersFile = $ordersFile ?? $baseDir . '/event_orders.json';
        $this->categoriesFile = $categoriesFile ?? $baseDir . '/event_categories.json';
        $this->ensureStorage();
    }

    public function dataPaths(): array
    {
        return [
            'events' => $this->eventsFile,
            'orders' => $this->ordersFile,
            'categories' => $this->categoriesFile,
        ];
    }

    public function ensureStorage(): void
    {
        foreach ($this->dataPaths() as $path) {
            if (!is_file($path)) {
                file_put_contents($path, "[]\n");
            }
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getEvents(): array
    {
        $events = read_json_file($this->eventsFile);
        if (!is_array($events)) {
            return [];
        }
        $normalized = [];
        foreach ($events as $event) {
            if (is_array($event) && isset($event['id'])) {
                $normalized[] = $event;
            }
        }
        return array_values($normalized);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getOrders(): array
    {
        $orders = read_json_file($this->ordersFile);
        if (!is_array($orders)) {
            return [];
        }
        $normalized = [];
        foreach ($orders as $order) {
            if (is_array($order) && isset($order['id'])) {
                $normalized[] = $order;
            }
        }
        return array_values($normalized);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getCategories(): array
    {
        $categories = read_json_file($this->categoriesFile);
        if (!is_array($categories)) {
            return [];
        }
        return $this->sortCategories($categories);
    }

    /**
     * @param array<int,array<string,mixed>> $events
     */
    public function saveEvents(array $events): void
    {
        if (!write_json_file($this->eventsFile, array_values($events))) {
            throw new RuntimeException('Unable to persist events dataset.');
        }
    }

    /**
     * @param array<int,array<string,mixed>> $orders
     */
    public function saveOrders(array $orders): void
    {
        if (!write_json_file($this->ordersFile, array_values($orders))) {
            throw new RuntimeException('Unable to persist orders dataset.');
        }
    }

    /**
     * @param array<int,array<string,mixed>> $categories
     */
    public function saveCategories(array $categories): void
    {
        if (!write_json_file($this->categoriesFile, $this->sortCategories($categories))) {
            throw new RuntimeException('Unable to persist categories dataset.');
        }
    }

    public function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim((string) $value, '-');
        return $value === '' ? uniqid('category_', false) : $value;
    }

    /**
     * @param array<int,array<string,mixed>> $categories
     */
    public function uniqueCategorySlug(string $desired, array $categories, ?string $currentId = null): string
    {
        $base = $this->slugify($desired);
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

    /**
     * @param array<int,array<string,mixed>> $categories
     * @return array<int,array<string,mixed>>
     */
    public function sortCategories(array $categories): array
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

    /**
     * @param array<int,array<string,mixed>> $events
     */
    public function findEvent(array $events, string $id): ?array
    {
        foreach ($events as $event) {
            if ((string) ($event['id'] ?? '') === $id) {
                return $event;
            }
        }
        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $orders
     */
    public function findOrder(array $orders, string $id): ?array
    {
        foreach ($orders as $order) {
            if ((string) ($order['id'] ?? '') === $id) {
                return $order;
            }
        }
        return null;
    }
}
