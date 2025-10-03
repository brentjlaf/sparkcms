<?php

class LogFormatter
{
    /**
     * @var array<string|int, string>
     */
    private array $pageTitleCache = [];

    /**
     * @param array<int, array<string, mixed>> $pages
     */
    public function __construct(array $pages)
    {
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            if (!array_key_exists('id', $page)) {
                continue;
            }

            $id = $page['id'];
            if (!is_string($id) && !is_int($id)) {
                continue;
            }

            $title = isset($page['title']) ? (string) $page['title'] : '';
            if ($title === '') {
                $title = 'Untitled';
            }

            $this->pageTitleCache[$id] = $title;
        }
    }

    /**
     * @param array<string|int, array<int, array<string, mixed>>> $history
     *
     * @return array<int, array<string, mixed>>
     */
    public function format(array $history): array
    {
        $logs = [];

        foreach ($history as $entityId => $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $normalized = $this->normalizeEntry($entityId, $entry);
                $logs[] = $normalized;
            }
        }

        usort($logs, static function (array $a, array $b): int {
            return $b['time'] <=> $a['time'];
        });

        return $logs;
    }

    /**
     * @param string|int $entityId
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function normalizeEntry($entityId, array $entry): array
    {
        $actionLabel = $this->normalizeActionLabel($entry['action'] ?? null);
        $context = $this->normalizeContext($entityId, $entry['context'] ?? null);
        $details = $this->normalizeDetails($entry['details'] ?? []);
        $pageTitle = $this->resolvePageTitle($entityId, $context, $entry['page_title'] ?? null);

        return [
            'time' => (int) ($entry['time'] ?? 0),
            'user' => isset($entry['user']) ? (string) $entry['user'] : '',
            'page_title' => $pageTitle,
            'action' => $actionLabel,
            'action_slug' => $this->slugifyActionLabel($actionLabel),
            'details' => $details,
            'context' => $context,
            'meta' => $entry['meta'] ?? new stdClass(),
        ];
    }

    private function normalizeActionLabel(?string $action): string
    {
        $label = trim((string) ($action ?? ''));
        return $label !== '' ? $label : 'Updated content';
    }

    /**
     * @param string|int $entityId
     */
    private function normalizeContext($entityId, ?string $context): string
    {
        $context = trim((string) ($context ?? ''));
        if ($context !== '') {
            return $context;
        }

        return is_numeric($entityId) ? 'page' : 'system';
    }

    /**
     * @param mixed $details
     * @return array<int|string, mixed>
     */
    private function normalizeDetails($details): array
    {
        if (is_array($details)) {
            return $details;
        }

        if ($details === null || $details === '') {
            return [];
        }

        return [$details];
    }

    /**
     * @param string|int $entityId
     */
    private function resolvePageTitle($entityId, string $context, $providedTitle): string
    {
        $providedTitle = $providedTitle ?? '';
        if ($providedTitle !== '') {
            return (string) $providedTitle;
        }

        if ($context === 'system') {
            return 'System activity';
        }

        if (array_key_exists($entityId, $this->pageTitleCache)) {
            return $this->pageTitleCache[$entityId];
        }

        return 'Unknown';
    }

    private function slugifyActionLabel(string $label): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $label));
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'unknown';
    }
}
