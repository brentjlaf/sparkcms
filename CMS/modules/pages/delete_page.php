<?php
// File: delete_page.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
$pagesFile = __DIR__ . '/../../data/pages.json';
if (!file_exists($pagesFile)) {
    exit('No pages');
}
$pages = read_json_file($pagesFile);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$deletedPage = null;
foreach ($pages as $p) {
    if ($p['id'] == $id) { $deletedPage = $p; break; }
}
$pages = array_filter($pages, function($p) use ($id) { return $p['id'] != $id; });
$pages = array_values($pages);
file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT));

// Remove the deleted page from any menus
$menusFile = __DIR__ . '/../../data/menus.json';
if (file_exists($menusFile)) {
    $menus = read_json_file($menusFile);
    $pageSlug = $deletedPage['slug'] ?? '';

    $removePageFromItems = function (array $items) use (&$removePageFromItems, $id, $pageSlug) {
        $cleaned = [];
        foreach ($items as $item) {
            $itemType = $item['type'] ?? '';
            $itemPage = isset($item['page']) ? (int)$item['page'] : null;
            $itemLink = isset($item['link']) ? rtrim($item['link'], '/') : null;
            $pageLink = $pageSlug !== '' ? '/' . trim($pageSlug, '/') : null;

            $matchesPage = ($itemType === 'page' && $itemPage === $id);
            if (!$matchesPage && $pageLink !== null && $itemLink !== null) {
                $matchesPage = $itemLink === $pageLink;
            }

            if ($matchesPage) {
                continue;
            }

            if (!empty($item['children']) && is_array($item['children'])) {
                $item['children'] = $removePageFromItems($item['children']);
                if (empty($item['children'])) {
                    unset($item['children']);
                }
            }

            $cleaned[] = $item;
        }
        return $cleaned;
    };

    $menusUpdated = false;
    foreach ($menus as &$menu) {
        $originalItems = $menu['items'] ?? [];
        $menu['items'] = $removePageFromItems($originalItems);
        if ($menu['items'] !== $originalItems) {
            $menusUpdated = true;
        }
    }
    unset($menu);

    if ($menusUpdated) {
        file_put_contents($menusFile, json_encode($menus, JSON_PRETTY_PRINT));
    }
}

// Update sitemap after a page is deleted
require_once __DIR__ . '/../sitemap/SitemapRegenerator.php';
$sitemapResult = regenerate_sitemap($pages, __DIR__ . '/../../../sitemap.xml');
if (($sitemapResult['success'] ?? false) !== true) {
    $message = $sitemapResult['message'] ?? 'Failed to regenerate sitemap.';
    if (!is_string($message) || $message === '') {
        $message = 'Failed to regenerate sitemap.';
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$action = 'deleted page';
$details = [];
if ($deletedPage) {
    $details[] = 'Title: ' . ($deletedPage['title'] ?? 'Unknown');
    $details[] = 'Slug: ' . ($deletedPage['slug'] ?? '');
    if (!empty($deletedPage['template'])) {
        $details[] = 'Template: ' . $deletedPage['template'];
    }
    $details[] = 'Previous visibility: ' . (!empty($deletedPage['published']) ? 'Published' : 'Unpublished');
}
if ($deletedPage && !empty($deletedPage['template'])) {
    $action .= ' (' . $deletedPage['template'] . ')';
}
$historyData[$id][] = [
    'time' => time(),
    'user' => $user,
    'action' => $action,
    'details' => $details,
    'context' => 'page',
    'page_id' => $id,
];
$historyData[$id] = array_slice($historyData[$id], -20);

if (!isset($historyData['__system__'])) {
    $historyData['__system__'] = [];
}
$historyData['__system__'][] = [
    'time' => time(),
    'user' => '',
    'action' => 'Regenerated sitemap',
    'details' => [
        'Automatic sitemap refresh after deleting page ID ' . $id,
    ],
    'context' => 'system',
    'meta' => [
        'trigger' => 'sitemap_regeneration',
        'page_id' => $id,
    ],
    'page_title' => 'CMS Backend',
];
$historyData['__system__'] = array_slice($historyData['__system__'], -50);
file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));

echo 'OK';
