<?php
// File: settings.php
require_once __DIR__ . '/data.php';

if (!array_key_exists('site_settings_cache', $GLOBALS)) {
    $GLOBALS['site_settings_cache'] = null;
}

/**
 * Get the absolute path to the settings JSON file.
 */
function get_settings_file_path(): string
{
    return __DIR__ . '/../data/settings.json';
}

/**
 * Retrieve the site settings and ensure the timezone is applied.
 *
 * @return array<string, mixed>
 */
function get_site_settings(): array
{
    if (!is_array($GLOBALS['site_settings_cache'])) {
        $loaded = get_cached_json(get_settings_file_path());
        $GLOBALS['site_settings_cache'] = is_array($loaded) ? $loaded : [];
    }

    apply_timezone_from_settings($GLOBALS['site_settings_cache']);

    return $GLOBALS['site_settings_cache'];
}

/**
 * Replace the cached settings for the current request.
 *
 * @param array<string, mixed> $settings
 */
function set_site_settings_cache(array $settings): void
{
    $GLOBALS['site_settings_cache'] = $settings;
    apply_timezone_from_settings($GLOBALS['site_settings_cache']);
}

/**
 * Ensure PHP's default timezone matches the configured site timezone.
 *
 * @param array<string, mixed> $settings
 */
function apply_timezone_from_settings(array $settings): void
{
    static $appliedTimezone = null;

    $timezone = $settings['timezone'] ?? 'America/Denver';
    if (!is_string($timezone) || trim($timezone) === '') {
        $timezone = 'America/Denver';
    }

    if ($appliedTimezone === $timezone) {
        return;
    }

    if (@date_default_timezone_set($timezone)) {
        $appliedTimezone = $timezone;
        return;
    }

    if ($appliedTimezone !== 'UTC') {
        date_default_timezone_set('UTC');
        $appliedTimezone = 'UTC';
    }
}

/**
 * Public helper for scripts that only need the timezone to be applied.
 */
function ensure_site_timezone(): void
{
    get_site_settings();
}
