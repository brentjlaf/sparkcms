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

if (!function_exists('import_export_get_stats_file')) {
    function import_export_get_stats_file(): string
    {
        return import_export_get_data_dir() . '/import_export_status.json';
    }
}
