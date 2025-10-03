<?php
require_once __DIR__ . '/../CMS/modules/pages/PageService.php';

function create_temp_file(array $initial = []): string {
    $file = tempnam(sys_get_temp_dir(), 'page-service-');
    if ($file === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }
    file_put_contents($file, json_encode($initial));
    return $file;
}

function create_time_provider(array $timestamps): callable {
    return function () use (&$timestamps): int {
        if (count($timestamps) === 0) {
            return time();
        }
        return (int)array_shift($timestamps);
    };
}

$pagesFile = create_temp_file();
$historyFile = create_temp_file();
$sitemapFile = tempnam(sys_get_temp_dir(), 'sitemap-');
if ($sitemapFile === false) {
    throw new RuntimeException('Unable to create temporary sitemap file.');
}

$service = new PageService(
    $pagesFile,
    $historyFile,
    $sitemapFile,
    create_time_provider([1700000000]),
    function (array $pages): array {
        return [
            'success' => true,
            'message' => 'Sitemap regenerated successfully.',
            'entryCount' => count($pages),
        ];
    }
);

$slugResult = $service->save([
    'title' => 'Sample Page',
    'slug' => '   Fancy TITLE!!  ',
    'content' => '<p>Hello</p><script>alert(1);</script>',
], [
    'user' => ['username' => 'editor'],
]);

if ($slugResult['success'] !== true) {
    throw new RuntimeException('Slug normalization test failed to save page.');
}

if (($slugResult['page']['slug'] ?? '') !== 'fancy-title') {
    throw new RuntimeException('Slug should be normalized and lowercased with hyphens.');
}

$pagesData = json_decode(file_get_contents($pagesFile), true);
if (!is_array($pagesData) || $pagesData[0]['slug'] !== 'fancy-title') {
    throw new RuntimeException('Normalized slug should persist to the pages dataset.');
}

$createdId = (int)$slugResult['page']['id'];

$serviceHistory = new PageService(
    $pagesFile,
    $historyFile,
    $sitemapFile,
    create_time_provider([1700000100]),
    function (array $pages): array {
        return [
            'success' => true,
            'message' => 'Sitemap regenerated successfully.',
            'entryCount' => count($pages),
        ];
    }
);

$historyResult = $serviceHistory->save([
    'id' => $createdId,
    'title' => 'Updated Sample Page',
    'slug' => 'updated-sample-page',
    'content' => '<p>Updated</p>',
    'published' => true,
], [
    'user' => ['username' => 'editor'],
]);

if ($historyResult['success'] !== true) {
    throw new RuntimeException('History diff test failed to update page.');
}

$historyData = json_decode(file_get_contents($historyFile), true);
if (!isset($historyData[$createdId]) || count($historyData[$createdId]) === 0) {
    throw new RuntimeException('Page history should contain entries for the saved page.');
}

$latestHistory = end($historyData[$createdId]);
if (!is_array($latestHistory)) {
    throw new RuntimeException('Latest history entry should be an array.');
}

$detailSet = $latestHistory['details'] ?? [];
if (!in_array('Title: "Sample Page" → "Updated Sample Page"', $detailSet, true)) {
    throw new RuntimeException('History should record title changes.');
}
if (!in_array('Visibility: Unpublished → Published', $detailSet, true)) {
    throw new RuntimeException('History should record changes to published status.');
}

$errorService = new PageService(
    $pagesFile,
    $historyFile,
    $sitemapFile,
    create_time_provider([1700000200]),
    function (array $pages): array {
        return [
            'success' => false,
            'message' => 'Intentional sitemap failure.',
        ];
    }
);

$errorResult = $errorService->save([
    'title' => 'Another Page',
    'slug' => 'Another Page',
    'content' => '<p>Content</p>',
], [
    'user' => ['username' => 'editor'],
]);

if ($errorResult['success'] !== false) {
    throw new RuntimeException('Sitemap error handling should return a failure result.');
}

if (($errorResult['status'] ?? 0) !== 500) {
    throw new RuntimeException('Sitemap error handling should return a 500 status code.');
}

if (($errorResult['sitemap']['message'] ?? '') !== 'Intentional sitemap failure.') {
    throw new RuntimeException('Sitemap error response should expose the underlying message.');
}

unlink($pagesFile);
unlink($historyFile);
unlink($sitemapFile);

echo "PageService tests passed\n";
