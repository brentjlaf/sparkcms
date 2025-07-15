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

/**
 * Load and decode a JSON file without caching.
 *
 * @param string $file Path to the JSON file
 * @return array Decoded JSON data or empty array on failure
 */
function read_json_file($file) {
    return file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}

/**
 * Encode data as pretty printed JSON and save to a file.
 *
 * @param string $file Path to the JSON file
 * @param mixed  $data Data to encode
 */
function write_json_file($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}
?>
