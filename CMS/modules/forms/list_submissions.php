<?php
// File: list_submissions.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$formId = isset($_GET['form_id']) ? (int)$_GET['form_id'] : null;
$submissionsFile = __DIR__ . '/../../data/form_submissions.json';
$submissions = read_json_file($submissionsFile);

if (!is_array($submissions)) {
    $submissions = [];
}

if ($formId) {
    $submissions = array_values(array_filter($submissions, function ($submission) use ($formId) {
        if (!is_array($submission)) {
            return false;
        }
        if (!isset($submission['form_id'])) {
            return false;
        }
        return (int)$submission['form_id'] === $formId;
    }));
} else {
    $submissions = array_values(array_filter($submissions, 'is_array'));
}

$extractTimestamp = function (array $entry): int {
    $candidates = ['submitted_at', 'created_at', 'timestamp'];
    foreach ($candidates as $key) {
        if (empty($entry[$key])) {
            continue;
        }
        $value = $entry[$key];
        if (is_numeric($value)) {
            $value = (float) $value;
            if ($value > 0) {
                return $value < 1_000_000_000_000 ? (int) round($value) : (int) round($value / 1000);
            }
            continue;
        }
        $time = strtotime((string) $value);
        if ($time !== false) {
            return $time;
        }
    }
    return 0;
};

usort($submissions, function ($a, $b) use ($extractTimestamp) {
    return $extractTimestamp($b) <=> $extractTimestamp($a);
});

$normalized = array_map(function ($submission) use ($extractTimestamp) {
    $normalized = [
        'id' => $submission['id'] ?? null,
        'form_id' => isset($submission['form_id']) ? (int) $submission['form_id'] : null,
    ];

    $data = $submission['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }
    $normalized['data'] = (object) $data;

    $meta = $submission['meta'] ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }

    if (isset($submission['ip']) && !isset($meta['ip'])) {
        $meta['ip'] = $submission['ip'];
    }

    $normalized['meta'] = (object) $meta;

    $timestamp = $extractTimestamp($submission);
    if ($timestamp > 0) {
        $normalized['submitted_at'] = date(DATE_ATOM, $timestamp);
    } else {
        $fallback = null;
        foreach (['submitted_at', 'created_at', 'timestamp'] as $key) {
            if (!empty($submission[$key])) {
                $fallback = (string) $submission[$key];
                break;
            }
        }
        $normalized['submitted_at'] = $fallback;
    }

    if (isset($submission['source'])) {
        $normalized['source'] = $submission['source'];
    }

    return $normalized;
}, $submissions);

header('Content-Type: application/json');
echo json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
