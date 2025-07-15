<?php
// File: data.php
// Utility functions for loading and saving JSON data.
// The helpers provide a single location for JSON handling and reduce
// repetitive "json_decode(file_get_contents())" calls across the CMS.

/**
 * Read and decode a JSON file.
 *
 * @param string $file Path to the JSON file
 * @return array Decoded JSON data or empty array when the file does not exist
 */
function read_json($file) {
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
 * @param array  $data  Data to encode and write
 * @return void
 */
function write_json($file, array $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
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
        $cache[$file] = read_json($file);
    }
    return $cache[$file];
}
?>
