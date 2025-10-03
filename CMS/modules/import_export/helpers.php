<?php
// File: helpers.php

require_once __DIR__ . '/ImportExportManager.php';

if (!function_exists('import_export_manager')) {
    function import_export_manager(): ImportExportManager
    {
        static $manager = null;

        if ($manager === null) {
            $manager = new ImportExportManager();
        }

        return $manager;
    }
}

if (!function_exists('import_export_get_data_dir')) {
    function import_export_get_data_dir(): string
    {
        return import_export_manager()->getDataDir();
    }
}

if (!function_exists('import_export_get_dataset_map')) {
    function import_export_get_dataset_map(): array
    {
        return import_export_manager()->getDatasetMap();
    }
}

if (!function_exists('import_export_get_dataset_metadata')) {
    function import_export_get_dataset_metadata(): array
    {
        return import_export_manager()->getDatasetMetadata();
    }
}

if (!function_exists('import_export_format_dataset_label')) {
    function import_export_format_dataset_label(string $key): string
    {
        return import_export_manager()->formatDatasetLabel($key);
    }
}

if (!function_exists('import_export_get_stats_file')) {
    function import_export_get_stats_file(): string
    {
        return import_export_manager()->getStatsFile();
    }
}

if (!function_exists('import_export_format_dataset_count_label')) {
    function import_export_format_dataset_count_label(int $count): string
    {
        return import_export_manager()->formatDatasetCountLabel($count);
    }
}

if (!function_exists('import_export_append_history_entry')) {
    function import_export_append_history_entry(array $stats, array $entry, int $maxEntries = 20): array
    {
        return import_export_manager()->appendHistoryEntry($stats, $entry, $maxEntries);
    }
}
