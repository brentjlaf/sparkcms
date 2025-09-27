<?php
// Simple sanitization helpers
// Returns a trimmed string with tags stripped
function sanitize_text(string $str): string {
    return trim(strip_tags($str));
}
// Sanitizes url
function sanitize_url(string $url): string {
    return filter_var(trim($url), FILTER_SANITIZE_URL) ?: '';
}

// Validates a social profile URL. Only allows HTTPS URLs that pass FILTER_VALIDATE_URL.
function validate_social_url(string $url): string {
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    $validated = filter_var($url, FILTER_VALIDATE_URL);
    if ($validated === false) {
        return '';
    }

    $scheme = parse_url($validated, PHP_URL_SCHEME);
    if (strtolower((string) $scheme) !== 'https') {
        return '';
    }

    return $validated;
}
// Sanitizes an array of tags by running sanitize_text on each
function sanitize_tags($tags) {
    if (!is_array($tags)) return [];
    return array_values(array_filter(array_map('sanitize_text', $tags)));
}
?>
