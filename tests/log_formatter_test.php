<?php
require_once __DIR__ . '/../CMS/modules/logs/LogFormatter.php';

$pages = [
    ['id' => 101, 'title' => 'About Us'],
    ['id' => 202, 'title' => 'Contact'],
];

$history = [
    101 => [
        [
            'time' => 1704067200,
            'user' => 'alice',
            'action' => 'Published page',
            'details' => ['status' => 'published'],
        ],
        [
            'time' => 1704153600,
            'user' => 'bob',
            'action' => '',
        ],
    ],
    'system' => [
        [
            'time' => 1704110400,
            'user' => 'system',
            'details' => 'Cache cleared',
        ],
    ],
];

$formatter = new LogFormatter($pages);
$logs = $formatter->format($history);

if (count($logs) !== 3) {
    throw new RuntimeException('Expected three log entries.');
}

if ($logs[0]['time'] !== 1704153600) {
    throw new RuntimeException('Logs should be sorted by timestamp descending.');
}

if ($logs[0]['action'] !== 'Updated content') {
    throw new RuntimeException('Missing action labels should default to "Updated content".');
}

if ($logs[0]['action_slug'] !== 'updated-content') {
    throw new RuntimeException('Slug should be generated from the normalized label.');
}

if ($logs[0]['page_title'] !== 'About Us') {
    throw new RuntimeException('Page title should be resolved from the page dataset.');
}

if ($logs[1]['page_title'] !== 'System activity') {
    throw new RuntimeException('System context should default to the system activity label.');
}

if ($logs[1]['details'] !== ['Cache cleared']) {
    throw new RuntimeException('String details should be normalized into an array.');
}

echo "LogFormatter tests passed\n";
