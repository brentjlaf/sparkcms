<?php
// File: data.php
// Utility functions for reading/writing JSON files with simple in-memory caching

/**
 * Read and decode a JSON file.
 *
 * @param string $file Path to the JSON file
 * @return array Decoded JSON data or empty array on failure
 */
function read_json_file($file) {
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return $data ?: [];
}

/**
 * Write an array to a JSON file using pretty print formatting.
 *
 * @param string $file  Path to the JSON file
 * @param mixed  $data  Data to encode
 * @return bool True on success, false on failure
 */
function write_json_file($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Load and decode a JSON file while caching the result within the request.
 *
 * @param string $file Path to the JSON file
 * @return array Decoded JSON data or empty array on failure
 */
function get_cached_json($file) {
    static $cache = [];
    if (!isset($cache[$file])) {
        $cache[$file] = read_json_file($file);
    }
    return $cache[$file];
}
?>
