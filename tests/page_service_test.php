<?php
require_once __DIR__ . '/../CMS/modules/pages/PageService.php';
require_once __DIR__ . '/../CMS/modules/sitemap/SitemapService.php';

function create_temp_json_file(array $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'sparkcms_');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }
    file_put_contents($path, json_encode($data));
    return $path;
}

$pagesFile = create_temp_json_file([]);
$historyFile = create_temp_json_file([]);
$regenerationCalls = 0;

$service = new PageService(
    $pagesFile,
    $historyFile,
    function () use (&$regenerationCalls) {
        $regenerationCalls++;
        return [
            'success' => true,
            'message' => 'Sitemap regenerated successfully.',
        ];
    }
);

// Slug normalization on create
$createResponse = $service->save([
    'title' => 'Hello World!',
    'slug' => '',
    'content' => '<p>Sample</p>',
    'published' => true,
    'template' => '',
    'meta_title' => '',
    'meta_description' => '',
    'canonical_url' => '',
    'og_title' => '',
    'og_description' => '',
    'og_image' => '',
    'access' => 'public',
], 'tester');

if (($createResponse['success'] ?? false) !== true) {
    throw new RuntimeException('Creating a page should succeed.');
}
if ($createResponse['page']['slug'] !== 'hello-world') {
    throw new RuntimeException('Slug should normalize to "hello-world".');
}
if ($createResponse['status'] !== 201) {
    throw new RuntimeException('Creating a page should return HTTP 201 status.');
}

$pageId = $createResponse['page']['id'];

// History diff generation on update
$updateResponse = $service->save([
    'id' => $pageId,
    'title' => 'Updated World',
    'slug' => 'Updated World Revisited',
    'content' => '<p>Updated content</p>',
    'published' => false,
    'template' => 'landing.php',
    'meta_title' => 'New Meta Title',
    'meta_description' => 'New meta description',
    'canonical_url' => 'https://example.com/new',
    'og_title' => 'New OG',
    'og_description' => 'New OG description',
    'og_image' => 'https://example.com/image.png',
    'access' => 'members',
], 'editor');

if (($updateResponse['success'] ?? false) !== true) {
    throw new RuntimeException('Updating a page should succeed.');
}

$historyData = json_decode(file_get_contents($historyFile), true);
$pageHistory = $historyData[$pageId] ?? [];
if (empty($pageHistory)) {
    throw new RuntimeException('Page history should include entries for the updated page.');
}
$latestHistory = $pageHistory[array_key_last($pageHistory)];
$details = $latestHistory['details'] ?? [];
$expectedSnippets = [
    'Title: "Hello World!" → "Updated World"',
    'Slug: hello-world → updated-world-revisited',
    'Template: page.php → landing.php',
    'Visibility: Published → Unpublished',
    'Access: public → members',
];
foreach ($expectedSnippets as $snippet) {
    $found = false;
    foreach ($details as $detail) {
        if (strpos($detail, $snippet) !== false) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new RuntimeException('Expected history detail missing: ' . $snippet);
    }
}

// Sitemap regeneration error handling
$pagesFileFailure = create_temp_json_file([]);
$historyFileFailure = create_temp_json_file([]);
$serviceWithFailure = new PageService(
    $pagesFileFailure,
    $historyFileFailure,
    function () {
        return [
            'success' => false,
            'message' => 'Simulated failure',
        ];
    }
);

$failureResponse = $serviceWithFailure->save([
    'title' => 'Broken Sitemap',
    'slug' => '',
    'content' => 'Test',
    'published' => false,
    'template' => '',
    'meta_title' => '',
    'meta_description' => '',
    'canonical_url' => '',
    'og_title' => '',
    'og_description' => '',
    'og_image' => '',
    'access' => 'public',
], 'operator');

if (($failureResponse['success'] ?? true) !== false) {
    throw new RuntimeException('Failure response should indicate error.');
}
if (($failureResponse['status'] ?? 0) !== 500) {
    throw new RuntimeException('Sitemap failure should map to HTTP 500.');
}
if (($failureResponse['sitemap']['message'] ?? '') !== 'Simulated failure') {
    throw new RuntimeException('Sitemap error message should be returned.');
}

// Cleanup
foreach ([
    $pagesFile,
    $historyFile,
    $pagesFileFailure,
    $historyFileFailure,
] as $path) {
    if (file_exists($path)) {
        unlink($path);
    }
}

echo "PageService tests passed\n";
