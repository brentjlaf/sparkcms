<?php
require_once __DIR__ . '/../CMS/modules/dashboard/DashboardAggregator.php';

$fixtureDir = sys_get_temp_dir() . '/dashboard_fixture_' . bin2hex(random_bytes(4));
if (!mkdir($fixtureDir) && !is_dir($fixtureDir)) {
    throw new RuntimeException('Unable to create dashboard fixture directory.');
}

$write = static function (string $filename, array $data) use ($fixtureDir): void {
    $path = $fixtureDir . '/' . $filename;
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Failed to encode fixture data for ' . $filename);
    }
    file_put_contents($path, $json);
};

$write('pages.json', [
    [
        'title' => 'Home',
        'slug' => 'home',
        'content' => '<h1>Home</h1><p>Welcome to the site.</p>',
        'meta_title' => 'Home',
        'meta_description' => 'Welcome to the home page.',
        'published' => true,
        'views' => 42,
    ],
    [
        'title' => 'Draft Page',
        'slug' => 'draft-page',
        'content' => '<h1>Draft</h1><p>Needs work.</p>',
        'meta_title' => '',
        'meta_description' => '',
        'published' => false,
        'views' => 0,
    ],
]);

$write('media.json', [
    ['size' => 1024],
    ['size' => 2048],
]);

$write('users.json', [
    ['role' => 'admin'],
    ['role' => 'editor'],
]);

$write('menus.json', [
    ['items' => [
        ['title' => 'Home'],
    ]],
]);

$write('forms.json', []);
$write('blog_posts.json', [
    ['status' => 'draft'],
    ['status' => 'published'],
]);
$write('page_history.json', []);
$write('events.json', [
    ['status' => 'draft'],
]);
$write('event_orders.json', []);
$write('calendar_events.json', []);
$write('calendar_categories.json', []);
$aggregator = new DashboardAggregator(
    $fixtureDir,
    '/cms',
    null,
    ['social' => []]
);
$snapshot = $aggregator->aggregate();

if ($snapshot->getPagesCount() !== 2) {
    throw new RuntimeException('Aggregator did not count pages correctly.');
}

if ($snapshot->getMediaLibraryBytes() !== 3072) {
    throw new RuntimeException('Aggregator did not sum media bytes correctly.');
}

$modules = $snapshot->getModuleSummaries();
if (!is_array($modules) || $modules === []) {
    throw new RuntimeException('Module summaries should not be empty.');
}

$pagesModule = null;
foreach ($modules as $module) {
    if (($module['id'] ?? '') === 'pages') {
        $pagesModule = $module;
        break;
    }
}

if ($pagesModule === null) {
    throw new RuntimeException('Pages module summary not found.');
}

if (($pagesModule['status'] ?? '') !== 'warning') {
    throw new RuntimeException('Pages module status should flag drafts.');
}

$files = glob($fixtureDir . '/*.json');
if ($files !== false) {
    array_map('unlink', $files);
}
rmdir($fixtureDir);
