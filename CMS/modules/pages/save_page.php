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
    if ($old) {
        if ($old['template'] !== $template) {
            $changes[] = 'changed template from ' . $old['template'] . ' to ' . $template;
        }
        if ($old['published'] != $published) {
            $changes[] = $published ? 'published page' : 'unpublished page';
        }
    }
    if (!$changes) $changes[] = 'updated page';
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
        'og_title' => $og_title,
        'og_description' => $og_description,
        'og_image' => $og_image,
        'access' => $access,
        'views' => 0,
        'last_modified' => time()
    ];
    $timestamp = $pages[array_key_last($pages)]['last_modified'];
    $action = 'created page with template ' . $template;
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$historyData[$id][] = ['time' => $timestamp, 'user' => $user, 'action' => $action];
$historyData[$id] = array_slice($historyData[$id], -20);
file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));

file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT));
// Regenerate sitemap whenever pages are modified
require_once __DIR__ . '/../sitemap/generate.php';
echo 'OK';
