<?php
// File: CalendarRepository.php
// Repository for managing calendar events, categories, and derived metrics.

require_once __DIR__ . '/../../includes/data.php';

class CalendarRepository
{
    public const DEFAULT_COLOR = '#ffffff';

    /** @var string */
    private $eventsFile;

    /** @var string */
    private $categoriesFile;

    /** @var array|null */
    private $eventsCache = null;

    /** @var array|null */
    private $categoriesCache = null;

    /**
     * @param string|null $eventsFile
     * @param string|null $categoriesFile
     */
    public function __construct($eventsFile = null, $categoriesFile = null)
    {
        if ($eventsFile === null) {
            $eventsFile = __DIR__ . '/../../data/calendar_events.json';
        }
        if ($categoriesFile === null) {
            $categoriesFile = __DIR__ . '/../../data/calendar_categories.json';
        }
        $this->eventsFile = $eventsFile;
        $this->categoriesFile = $categoriesFile;
        $this->ensureFilesExist();
    }

    /**
     * Retrieve all events with cached results when possible.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getEvents(): array
    {
        if ($this->eventsCache === null) {
            $data = read_json_file($this->eventsFile);
            if (!is_array($data)) {
                $data = [];
            }

            $normalized = [];
            foreach ($data as $event) {
                if (!is_array($event) || !isset($event['id'])) {
                    continue;
                }
                $normalized[] = $this->normalizePersistedEvent($event);
            }
            $this->sortEvents($normalized);
            $this->eventsCache = $normalized;
        }

        return $this->eventsCache;
    }

    /**
     * Retrieve all categories with cached results when possible.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCategories(): array
    {
        if ($this->categoriesCache === null) {
            $data = read_json_file($this->categoriesFile);
            if (!is_array($data)) {
                $data = [];
            }
            $normalized = [];
            foreach ($data as $category) {
                if (!is_array($category) || !isset($category['id'])) {
                    continue;
                }
                $name = isset($category['name']) ? (string) $category['name'] : '';
                $color = isset($category['color']) ? (string) $category['color'] : self::DEFAULT_COLOR;
                if ($color === '') {
                    $color = self::DEFAULT_COLOR;
                }
                $normalized[] = [
                    'id' => (int) $category['id'],
                    'name' => $name,
                    'color' => $color,
                ];
            }
            $this->categoriesCache = $normalized;
        }

        return $this->categoriesCache;
    }

    /**
     * Get both events and categories in a single call.
     *
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
     */
    public function getDataset(): array
    {
        return [$this->getEvents(), $this->getCategories()];
    }

    /**
     * Persist a new or existing event and return the updated list.
     *
     * @param array<string,mixed> $input
     * @return array<int,array<string,mixed>>
     */
    public function saveEvent(array $input): array
    {
        $events = $this->getEvents();
        $categories = $this->getCategories();

        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $title = $this->requireNonEmptyString($input, 'title');
        $startDate = $this->requireDate($input, 'start_date');
        $endDate = $this->optionalDate($input, 'end_date');
        $recurrence = $this->normalizeRecurrence(isset($input['recurring_interval']) ? (string) $input['recurring_interval'] : 'none');
        $recurringEnd = $this->optionalDate($input, 'recurring_end_date');
        $description = isset($input['description']) ? trim((string) $input['description']) : '';
        $categoryName = $this->normalizeCategoryName(isset($input['category']) ? (string) $input['category'] : '');

        $eventPayload = [
            'title' => $title,
            'category' => $categoryName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'recurring_interval' => $recurrence,
            'recurring_end_date' => $recurringEnd,
            'description' => $description,
        ];

        if ($id <= 0) {
            $nextId = $this->nextEventId($events);
            $eventPayload['id'] = $nextId;
            $events[] = $eventPayload;
        } else {
            $updated = false;
            foreach ($events as &$event) {
                if ((int) ($event['id'] ?? 0) === $id) {
                    $event = array_merge(['id' => $id], $eventPayload);
                    $updated = true;
                    break;
                }
            }
            unset($event);
            if (!$updated) {
                throw new InvalidArgumentException('Event not found.');
            }
        }

        $this->sortEvents($events);
        $this->persist($events, $categories);

        return $events;
    }

    /**
     * Delete an event by identifier.
     *
     * @param int $id
     * @return array<int,array<string,mixed>>
     */
    public function deleteEvent(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid event identifier.');
        }

        $events = $this->getEvents();
        $categories = $this->getCategories();

        $before = count($events);
        $events = array_values(array_filter($events, static function ($event) use ($id) {
            return (int) ($event['id'] ?? 0) !== $id;
        }));

        if ($before === count($events)) {
            throw new InvalidArgumentException('Event not found.');
        }

        $this->sortEvents($events);
        $this->persist($events, $categories);

        return $events;
    }

    /**
     * Add a new category and return the updated list.
     *
     * @param string $name
     * @param string $color
     * @return array<int,array<string,mixed>>
     */
    public function addCategory(string $name, string $color = self::DEFAULT_COLOR): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.');
        }

        $categories = $this->getCategories();
        foreach ($categories as $category) {
            if (strcasecmp((string) ($category['name'] ?? ''), $name) === 0) {
                throw new InvalidArgumentException('Category already exists.');
            }
        }

        $color = trim($color);
        if ($color === '') {
            $color = self::DEFAULT_COLOR;
        }

        $categories[] = [
            'id' => $this->nextCategoryId($categories),
            'name' => $name,
            'color' => $color,
        ];

        $this->persist($this->getEvents(), $categories);

        return $categories;
    }

    /**
     * Delete a category and update events referencing it.
     *
     * @param int $id
     * @return array<int,array<string,mixed>>
     */
    public function deleteCategory(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid category identifier.');
        }

        $events = $this->getEvents();
        $categories = $this->getCategories();

        $index = null;
        $removedName = '';
        foreach ($categories as $idx => $category) {
            if ((int) ($category['id'] ?? 0) === $id) {
                $index = $idx;
                $removedName = isset($category['name']) ? (string) $category['name'] : '';
                break;
            }
        }

        if ($index === null) {
            throw new InvalidArgumentException('Category not found.');
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

        $this->persist($events, $categories);

        return $categories;
    }

    /**
     * Normalize recurrence values.
     */
    public function normalizeRecurrence(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'none';
        }

        $allowed = ['none', 'daily', 'weekly', 'monthly', 'yearly'];
        if (!in_array($value, $allowed, true)) {
            return 'none';
        }

        return $value;
    }

    /**
     * Normalize category names to match known categories.
     */
    public function normalizeCategoryName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        foreach ($this->getCategories() as $category) {
            $name = isset($category['name']) ? (string) $category['name'] : '';
            if ($name !== '' && strcasecmp($name, $value) === 0) {
                return $name;
            }
        }

        return '';
    }

    /**
     * Compute dashboard metrics.
     *
     * @param array<int,array<string,mixed>> $events
     * @param array<int,array<string,mixed>> $categories
     * @return array<string,mixed>
     */
    public static function computeMetrics(array $events, array $categories): array
    {
        $now = time();
        $upcomingCount = 0;
        $recurringCount = 0;
        $nextEvent = null;

        foreach ($events as $event) {
            $startDate = isset($event['start_date']) ? (string) $event['start_date'] : '';
            if ($startDate === '') {
                continue;
            }

            $startTimestamp = strtotime($startDate);
            if ($startTimestamp === false) {
                continue;
            }

            if (isset($event['recurring_interval']) && (string) $event['recurring_interval'] !== 'none') {
                $recurringCount++;
            }

            if ($startTimestamp >= $now) {
                $upcomingCount++;
                if ($nextEvent === null || $startTimestamp < $nextEvent['timestamp'] || (
                    $startTimestamp === $nextEvent['timestamp'] && ((int) ($event['id'] ?? 0)) < $nextEvent['id']
                )) {
                    $nextEvent = [
                        'id' => (int) ($event['id'] ?? 0),
                        'title' => (string) ($event['title'] ?? ''),
                        'start_date' => date('c', $startTimestamp),
                        'timestamp' => $startTimestamp,
                    ];
                }
            }
        }

        $metrics = [
            'total_events' => count($events),
            'upcoming_count' => $upcomingCount,
            'recurring_count' => $recurringCount,
            'category_count' => count($categories),
        ];

        $metrics['next_event'] = $nextEvent !== null ? $nextEvent : null;

        return $metrics;
    }

    /**
     * Persist events and categories to disk while refreshing caches.
     *
     * @param array<int,array<string,mixed>> $events
     * @param array<int,array<string,mixed>> $categories
     */
    private function persist(array $events, array $categories): void
    {
        write_json_file($this->eventsFile, array_values($events));
        write_json_file($this->categoriesFile, array_values($categories));

        $this->eventsCache = null;
        $this->categoriesCache = null;
    }

    /**
     * Ensure backing JSON files exist.
     */
    private function ensureFilesExist(): void
    {
        if (!is_file($this->eventsFile)) {
            file_put_contents($this->eventsFile, "[]\n");
        }
        if (!is_file($this->categoriesFile)) {
            file_put_contents($this->categoriesFile, "[]\n");
        }
    }

    /**
     * Guarantee a non-empty string within the provided input.
     *
     * @param array<string,mixed> $input
     */
    private function requireNonEmptyString(array $input, string $key): string
    {
        $value = isset($input[$key]) ? trim((string) $input[$key]) : '';
        if ($value === '') {
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' is required.');
        }
        return $value;
    }

    /**
     * Parse a required date value and return an ISO 8601 string.
     *
     * @param array<string,mixed> $input
     */
    private function requireDate(array $input, string $key): string
    {
        $value = $this->requireNonEmptyString($input, $key);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Invalid ' . str_replace('_', ' ', $key) . ' supplied.');
        }
        return date('c', $timestamp);
    }

    /**
     * Parse an optional date value and return an ISO 8601 string or an empty string.
     *
     * @param array<string,mixed> $input
     */
    private function optionalDate(array $input, string $key): string
    {
        if (!isset($input[$key])) {
            return '';
        }
        $value = trim((string) $input[$key]);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Invalid ' . str_replace('_', ' ', $key) . ' supplied.');
        }
        return date('c', $timestamp);
    }

    /**
     * Normalize events coming from persisted storage.
     *
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    private function normalizePersistedEvent(array $event): array
    {
        return [
            'id' => (int) ($event['id'] ?? 0),
            'title' => isset($event['title']) ? (string) $event['title'] : '',
            'category' => isset($event['category']) ? (string) $event['category'] : '',
            'start_date' => isset($event['start_date']) ? (string) $event['start_date'] : '',
            'end_date' => isset($event['end_date']) ? (string) $event['end_date'] : '',
            'recurring_interval' => isset($event['recurring_interval']) ? (string) $event['recurring_interval'] : 'none',
            'recurring_end_date' => isset($event['recurring_end_date']) ? (string) $event['recurring_end_date'] : '',
            'description' => isset($event['description']) ? (string) $event['description'] : '',
        ];
    }

    /**
     * Sort events by start date and identifier.
     *
     * @param array<int,array<string,mixed>> $events
     */
    private function sortEvents(array &$events): void
    {
        usort($events, static function (array $a, array $b): int {
            $aTime = isset($a['start_date']) ? strtotime((string) $a['start_date']) : 0;
            $bTime = isset($b['start_date']) ? strtotime((string) $b['start_date']) : 0;
            if ($aTime === $bTime) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return $aTime <=> $bTime;
        });
    }

    /**
     * Determine the next event identifier.
     *
     * @param array<int,array<string,mixed>> $events
     */
    private function nextEventId(array $events): int
    {
        $maxId = 0;
        foreach ($events as $event) {
            $id = (int) ($event['id'] ?? 0);
            if ($id > $maxId) {
                $maxId = $id;
            }
        }
        return $maxId + 1;
    }

    /**
     * Determine the next category identifier.
     *
     * @param array<int,array<string,mixed>> $categories
     */
    private function nextCategoryId(array $categories): int
    {
        $maxId = 0;
        foreach ($categories as $category) {
            $id = (int) ($category['id'] ?? 0);
            if ($id > $maxId) {
                $maxId = $id;
            }
        }
        return $maxId + 1;
    }
}

if (!function_exists('compute_calendar_metrics')) {
    /**
     * @param array<int,array<string,mixed>> $events
     * @param array<int,array<string,mixed>> $categories
     * @return array<string,mixed>
     */
    function compute_calendar_metrics(array $events, array $categories): array
    {
        return CalendarRepository::computeMetrics($events, $categories);
    }
}
