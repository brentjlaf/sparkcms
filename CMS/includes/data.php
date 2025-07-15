<?php
// File: data.php
// Utility functions for loading JSON data with simple in-memory caching

/**
 * Load and decode a JSON file while caching the result within the request.
 *
 * @param string $file Path to the JSON file
 * @return array Decoded JSON data or empty array on failure
 */
function get_cached_json($file) {
    static $cache = [];
    if (!isset($cache[$file])) {
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $cache[$file] = $data ?: [];
        } else {
            $cache[$file] = [];
        }
    }
    return $cache[$file];
}
?>
