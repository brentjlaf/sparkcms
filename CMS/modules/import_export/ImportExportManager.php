<?php
// File: ImportExportManager.php

require_once __DIR__ . '/../../includes/data.php';

class ImportExportManager
{
    private string $dataDir;
    private string $statsFile;
    private array $datasetMap;
    private array $datasetMetadata;
    private int $historyLimit;

    public function __construct(?string $dataDir = null, ?array $datasetMap = null, ?array $datasetMetadata = null, int $historyLimit = 20)
    {
        $this->dataDir = $dataDir !== null ? $this->resolvePath($dataDir) : $this->resolvePath(__DIR__ . '/../../data');
        $this->statsFile = $this->dataDir . '/import_export_status.json';
        $this->datasetMap = $datasetMap ?? $this->getDefaultDatasetMap();
        $this->datasetMetadata = $datasetMetadata ?? $this->getDefaultDatasetMetadata();
        $this->historyLimit = $historyLimit > 0 ? $historyLimit : 20;
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function getStatsFile(): string
    {
        return $this->statsFile;
    }

    public function getDatasetMap(): array
    {
        return $this->datasetMap;
    }

    public function getDatasetMetadata(): array
    {
        return $this->datasetMetadata;
    }

    public function getAvailableDatasets(bool $includeDrafts = true): array
    {
        $datasets = array_keys($this->datasetMap);

        if ($includeDrafts && $this->hasDrafts()) {
            $datasets[] = 'drafts';
        }

        return $datasets;
    }

    public function getDatasetDetails(array $datasetKeys): array
    {
        $details = [];
        foreach ($datasetKeys as $key) {
            $meta = $this->datasetMetadata[$key] ?? [];
            $details[] = [
                'key' => $key,
                'label' => isset($meta['label']) ? (string) $meta['label'] : $this->formatDatasetLabel($key),
                'description' => isset($meta['description']) ? (string) $meta['description'] : '',
            ];
        }

        return $details;
    }

    public function collectExportDatasets(): array
    {
        $datasets = [];
        foreach ($this->datasetMap as $key => $filename) {
            $datasets[$key] = read_json_file($this->dataDir . '/' . $filename);
        }

        $drafts = $this->readDrafts();
        if (!empty($drafts)) {
            $datasets['drafts'] = $drafts;
        }

        return $datasets;
    }

    public function importDatasets(array $datasets): array
    {
        $this->ensureDirectory($this->dataDir);

        $importedKeys = [];
        foreach ($this->datasetMap as $key => $filename) {
            if (!array_key_exists($key, $datasets)) {
                continue;
            }

            $path = $this->dataDir . '/' . $filename;
            if (write_json_file($path, $datasets[$key]) === false) {
                throw new RuntimeException('Failed to update the ' . $this->formatDatasetLabel($key) . ' data set.');
            }
            $importedKeys[] = $key;
        }

        if (array_key_exists('drafts', $datasets)) {
            $draftData = is_array($datasets['drafts']) ? $datasets['drafts'] : [];
            $this->writeDrafts($draftData);
            $importedKeys[] = 'drafts';
        }

        return array_values(array_unique($importedKeys));
    }

    public function readStats(): array
    {
        $stats = read_json_file($this->statsFile);
        return is_array($stats) ? $stats : [];
    }

    public function writeStats(array $stats): bool
    {
        $this->ensureDirectory($this->dataDir);
        return write_json_file($this->statsFile, $stats) !== false;
    }

    public function appendHistoryEntry(array $stats, array $entry, ?int $limit = null): array
    {
        if (!isset($stats['history']) || !is_array($stats['history'])) {
            $stats['history'] = [];
        }

        array_unshift($stats['history'], $this->normalizeHistoryEntry($entry));

        $limit = $limit ?? $this->historyLimit;
        if ($limit > 0 && count($stats['history']) > $limit) {
            $stats['history'] = array_slice($stats['history'], 0, $limit);
        }

        return $stats;
    }

    public function recordExport(string $filename, int $datasetCount): bool
    {
        $timestamp = gmdate('c');
        $stats = $this->readStats();
        $stats['last_export_at'] = $timestamp;
        $stats['last_export_file'] = $filename;
        $stats['export_count'] = isset($stats['export_count']) ? (int) $stats['export_count'] + 1 : 1;

        $stats = $this->appendHistoryEntry($stats, [
            'type' => 'export',
            'timestamp' => $timestamp,
            'label' => 'Export generated',
            'summary' => $filename . ' • ' . $this->formatDatasetCountLabel($datasetCount),
            'file' => $filename,
            'dataset_count' => $datasetCount,
        ]);

        return $this->writeStats($stats);
    }

    public function recordImport(string $filename, int $datasetCount, array $meta = []): bool
    {
        $timestamp = gmdate('c');
        $stats = $this->readStats();

        $stats['last_import_at'] = $timestamp;
        $stats['last_import_file'] = $filename;

        if (isset($meta['available_profiles'])) {
            $stats['available_profiles'] = (int) $meta['available_profiles'];
        }

        $summaryParts = [];
        if ($filename !== '') {
            $summaryParts[] = $filename;
        }

        if ($datasetCount > 0) {
            $summaryParts[] = $this->formatDatasetCountLabel($datasetCount);
        }

        if (isset($meta['site_name']) && (string) $meta['site_name'] !== '') {
            $summaryParts[] = (string) $meta['site_name'];
        }

        $stats = $this->appendHistoryEntry($stats, [
            'type' => 'import',
            'timestamp' => $timestamp,
            'label' => 'Import completed',
            'summary' => implode(' • ', $summaryParts),
            'file' => $filename,
            'dataset_count' => $datasetCount,
        ]);

        return $this->writeStats($stats);
    }

    public function getHistory(?array $stats = null): array
    {
        $stats = $stats ?? $this->readStats();
        if (!isset($stats['history']) || !is_array($stats['history'])) {
            return [];
        }

        $history = [];
        foreach ($stats['history'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $history[] = $this->normalizeHistoryEntry($entry);
        }

        return $history;
    }

    public function formatDatasetLabel(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $parts = preg_split('/[_\s]+/', $key);
        if ($parts === false) {
            return $key;
        }

        $labelParts = array_map(static function ($part) {
            $part = strtolower((string) $part);
            return $part !== '' ? ucfirst($part) : $part;
        }, $parts);

        return trim(implode(' ', $labelParts));
    }

    public function formatDatasetCountLabel(int $count): string
    {
        if ($count === 1) {
            return '1 data set';
        }

        return number_format(max($count, 0)) . ' data sets';
    }

    public function sanitizeDatasetKey(string $key): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $key);
        if ($sanitized === null) {
            $sanitized = $key;
        }

        $sanitized = trim($sanitized, '-_');

        return $sanitized;
    }

    public function hasDrafts(): bool
    {
        $draftsDir = $this->getDraftsDir();
        if (!is_dir($draftsDir)) {
            return false;
        }

        $draftFiles = glob($draftsDir . '/*.json');
        return $draftFiles !== false && count($draftFiles) > 0;
    }

    public function getDraftsDir(): string
    {
        return $this->dataDir . '/drafts';
    }

    private function resolvePath(string $path): string
    {
        $resolved = realpath($path);
        return $resolved !== false ? $resolved : rtrim($path, '/');
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to prepare the data directory for import.');
        }
    }

    private function readDrafts(): array
    {
        $draftsDir = $this->getDraftsDir();
        if (!is_dir($draftsDir)) {
            return [];
        }

        $draftFiles = glob($draftsDir . '/*.json');
        if ($draftFiles === false || count($draftFiles) === 0) {
            return [];
        }

        $drafts = [];
        foreach ($draftFiles as $draftFile) {
            $drafts[basename($draftFile, '.json')] = read_json_file($draftFile);
        }

        return $drafts;
    }

    private function writeDrafts(array $drafts): void
    {
        $draftsDir = $this->getDraftsDir();
        $this->ensureDirectory($draftsDir);

        $existingDrafts = glob($draftsDir . '/*.json');
        if ($existingDrafts !== false) {
            foreach ($existingDrafts as $draftFile) {
                if (is_file($draftFile)) {
                    @unlink($draftFile);
                }
            }
        }

        foreach ($drafts as $draftKey => $draftValue) {
            $safeKey = $this->sanitizeDatasetKey((string) $draftKey);
            if ($safeKey === '') {
                continue;
            }

            $draftPath = $draftsDir . '/' . $safeKey . '.json';
            if (write_json_file($draftPath, $draftValue) === false) {
                throw new RuntimeException('Failed to update draft content during import.');
            }
        }
    }

    private function normalizeHistoryEntry(array $entry): array
    {
        $type = isset($entry['type']) && (string) $entry['type'] !== '' ? (string) $entry['type'] : 'activity';

        $label = 'Activity recorded';
        if (isset($entry['label']) && trim((string) $entry['label']) !== '') {
            $label = (string) $entry['label'];
        } elseif ($type === 'import') {
            $label = 'Import completed';
        } elseif ($type === 'export') {
            $label = 'Export completed';
        }

        $timestamp = isset($entry['timestamp']) && (string) $entry['timestamp'] !== '' ? (string) $entry['timestamp'] : null;
        $summary = isset($entry['summary']) ? (string) $entry['summary'] : '';
        $file = isset($entry['file']) && (string) $entry['file'] !== '' ? (string) $entry['file'] : null;
        $datasetCount = isset($entry['dataset_count']) ? (int) $entry['dataset_count'] : null;

        return [
            'type' => $type,
            'timestamp' => $timestamp,
            'label' => $label,
            'summary' => $summary,
            'file' => $file,
            'dataset_count' => $datasetCount,
        ];
    }

    private function getDefaultDatasetMap(): array
    {
        return [
            'settings' => 'settings.json',
            'pages' => 'pages.json',
            'page_history' => 'page_history.json',
            'menus' => 'menus.json',
            'media' => 'media.json',
            'blog_posts' => 'blog_posts.json',
            'forms' => 'forms.json',
            'form_submissions' => 'form_submissions.json',
            'users' => 'users.json',
            'speed_snapshot' => 'speed_snapshot.json',
        ];
    }

    private function getDefaultDatasetMetadata(): array
    {
        return [
            'settings' => [
                'label' => 'Site settings',
                'description' => 'Site-wide configuration such as the site name, metadata, themes, and integrations.',
            ],
            'pages' => [
                'label' => 'Pages',
                'description' => 'Published page content, layouts, SEO fields, and routing information.',
            ],
            'page_history' => [
                'label' => 'Page history',
                'description' => 'Revision history for pages, enabling rollbacks to previous versions.',
            ],
            'menus' => [
                'label' => 'Navigation menus',
                'description' => 'Menu structures, links, and hierarchy used throughout the site.',
            ],
            'media' => [
                'label' => 'Media library',
                'description' => 'Records for uploaded images, documents, and other media assets.',
            ],
            'blog_posts' => [
                'label' => 'Blog posts',
                'description' => 'Blog post articles, metadata, authorship, and publishing status.',
            ],
            'forms' => [
                'label' => 'Forms',
                'description' => 'Form definitions including fields, validations, and notification settings.',
            ],
            'form_submissions' => [
                'label' => 'Form submissions',
                'description' => 'Entries submitted through site forms with captured response data.',
            ],
            'users' => [
                'label' => 'Users',
                'description' => 'User accounts, roles, and access permissions for the CMS.',
            ],
            'speed_snapshot' => [
                'label' => 'Speed snapshots',
                'description' => 'Performance metrics collected from site speed monitoring tools.',
            ],
            'drafts' => [
                'label' => 'Draft content',
                'description' => 'Unpublished draft items stored for future editing or review.',
            ],
        ];
    }
}
