<?php
// File: save_page.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = [];
if (file_exists($pagesFile)) {
    $pages = read_json_file($pagesFile);
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$title = sanitize_text($_POST['title'] ?? '');
$slug = sanitize_text($_POST['slug'] ?? '');

function slugify($text){
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'page';
}

if ($slug === '') {
    $slug = $title;
}
$slug = slugify($slug);
$content = trim($_POST['content'] ?? '');
// strip script tags to avoid XSS in stored content
$content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
$published = isset($_POST['published']) ? (bool)$_POST['published'] : false;
$template = sanitize_text($_POST['template'] ?? '');
if ($template === '') {
    $template = 'page.php';
}
$meta_title = sanitize_text($_POST['meta_title'] ?? '');
$meta_description = sanitize_text($_POST['meta_description'] ?? '');
$canonical_url = sanitize_url($_POST['canonical_url'] ?? '');
$og_title = sanitize_text($_POST['og_title'] ?? '');
$og_description = sanitize_text($_POST['og_description'] ?? '');
$og_image = sanitize_url($_POST['og_image'] ?? '');
$access = sanitize_text($_POST['access'] ?? 'public');

if ($title === '') {
    http_response_code(400);
    echo 'Missing fields';
    exit;
}

if ($id) {
    // Update existing
    $old = null;
    foreach ($pages as &$p) {
        if ($p['id'] == $id) {
            $old = $p;
            $p['title'] = $title;
            $p['slug'] = $slug;
            $p['content'] = $content;
            $p['published'] = $published;
            $p['template'] = $template;
            $p['meta_title'] = $meta_title;
            $p['meta_description'] = $meta_description;
            $p['canonical_url'] = $canonical_url;
            $p['og_title'] = $og_title;
            $p['og_description'] = $og_description;
            $p['og_image'] = $og_image;
            $p['access'] = $access;
            $p['last_modified'] = time();
            $timestamp = $p['last_modified'];
            break;
        }
    }
    $changes = [];
    $details = [];
    if ($old) {
        if ($old['title'] !== $title) {
            $details[] = 'Title: "' . $old['title'] . '" → "' . $title . '"';
        }
        if ($old['slug'] !== $slug) {
            $details[] = 'Slug: ' . $old['slug'] . ' → ' . $slug;
        }
        if ($old['template'] !== $template) {
            $details[] = 'Template: ' . $old['template'] . ' → ' . $template;
            $changes[] = 'Changed template';
        }
        if ($old['published'] != $published) {
            $details[] = 'Visibility: ' . ($old['published'] ? 'Published' : 'Unpublished') . ' → ' . ($published ? 'Published' : 'Unpublished');
            $changes[] = $published ? 'Published page' : 'Unpublished page';
        }
        if ($old['meta_title'] !== $meta_title) {
            $details[] = 'Meta title updated';
        }
        if (($old['meta_description'] ?? '') !== $meta_description) {
            $details[] = 'Meta description updated';
        }
        if (($old['canonical_url'] ?? '') !== $canonical_url) {
            $details[] = 'Canonical URL: ' . (($old['canonical_url'] ?? '') !== '' ? ($old['canonical_url'] ?? '') : 'none') . ' → ' . ($canonical_url !== '' ? $canonical_url : 'none');
        }
        if ($old['og_title'] !== $og_title) {
            $details[] = 'OG title: "' . $old['og_title'] . '" → "' . $og_title . '"';
        }
        if ($old['og_description'] !== $og_description) {
            $details[] = 'OG description updated';
        }
        if ($old['og_image'] !== $og_image) {
            $details[] = 'OG image: ' . ($old['og_image'] !== '' ? $old['og_image'] : 'none') . ' → ' . ($og_image !== '' ? $og_image : 'none');
        }
        if ($old['access'] !== $access) {
            $details[] = 'Access: ' . $old['access'] . ' → ' . $access;
        }
    }
    if (!$changes) {
        $changes[] = 'Updated page settings';
    }
    if (!$details) {
        $details[] = 'Saved without changing any settings.';
    }
    $action = implode('; ', $changes);
    unset($p);
} else {
    $id = 1;
    foreach ($pages as $p) {
        if ($p['id'] >= $id) $id = $p['id'] + 1;
    }
    $pages[] = [
        'id' => $id,
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'published' => $published,
        'template' => $template,
        'meta_title' => $meta_title,
        'meta_description' => $meta_description,
        'canonical_url' => $canonical_url,
        'og_title' => $og_title,
        'og_description' => $og_description,
        'og_image' => $og_image,
        'access' => $access,
        'views' => 0,
        'last_modified' => time()
    ];
    $timestamp = $pages[array_key_last($pages)]['last_modified'];
    $details = [
        'Initial template: ' . $template,
        'Visibility: ' . ($published ? 'Published' : 'Unpublished'),
        'Access: ' . $access,
    ];
    $action = 'created page with template ' . $template;
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$historyData[$id][] = [
    'time' => $timestamp,
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
        'Automatic sitemap refresh after updating "' . $title . '" (' . $slug . ')',
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

file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT));
// Regenerate sitemap whenever pages are modified
// Capture the output from the sitemap generator so we don't surface raw
// JSON responses in the page editor.
ob_start();
require_once __DIR__ . '/../sitemap/generate.php';
$sitemapOutput = ob_get_clean();

// Reset the response headers to a plain text response for this endpoint
// (generate.php sets JSON headers when run directly).
header('Content-Type: text/plain; charset=UTF-8');

if ($sitemapOutput !== '') {
    $decoded = json_decode($sitemapOutput, true);
    if (is_array($decoded) && isset($decoded['success']) && !$decoded['success']) {
        http_response_code(500);
        echo $decoded['message'] ?? 'Failed to regenerate sitemap.';
        exit;
    }
}

echo 'OK';
