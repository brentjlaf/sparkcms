<?php
// File: helpers.php

if (!function_exists('import_export_get_data_dir')) {
    function import_export_get_data_dir(): string
    {
        $path = __DIR__ . '/../../data';
        $resolved = realpath($path);
        return $resolved !== false ? $resolved : $path;
    }
}

if (!function_exists('import_export_get_dataset_map')) {
    function import_export_get_dataset_map(): array
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
}

if (!function_exists('import_export_get_dataset_metadata')) {
    function import_export_get_dataset_metadata(): array
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

if (!function_exists('import_export_format_dataset_label')) {
    function import_export_format_dataset_label(string $key): string
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
}

if (!function_exists('import_export_get_stats_file')) {
    function import_export_get_stats_file(): string
    {
        return import_export_get_data_dir() . '/import_export_status.json';
    }
}

if (!function_exists('import_export_format_dataset_count_label')) {
    function import_export_format_dataset_count_label(int $count): string
    {
        if ($count === 1) {
            return '1 data set';
        }

        return number_format(max($count, 0)) . ' data sets';
    }
}

if (!function_exists('import_export_append_history_entry')) {
    function import_export_append_history_entry(array $stats, array $entry, int $maxEntries = 20): array
    {
        if (!isset($stats['history']) || !is_array($stats['history'])) {
            $stats['history'] = [];
        }

        array_unshift($stats['history'], $entry);

        if ($maxEntries > 0 && count($stats['history']) > $maxEntries) {
            $stats['history'] = array_slice($stats['history'], 0, $maxEntries);
        }

        return $stats;
    }
}
