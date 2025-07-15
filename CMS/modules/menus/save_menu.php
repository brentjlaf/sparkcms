<?php
// File: save_menu.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name = sanitize_text($_POST['name'] ?? '');
$itemsData = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

$items = process_items($itemsData, $pages);

function process_items($itemsData, $pages){
    $items = [];
    if(!is_array($itemsData)) return $items;
    foreach ($itemsData as $it) {
        $label = sanitize_text($it['label'] ?? '');
        $type = sanitize_text($it['type'] ?? 'custom');
        $newTab = !empty($it['new_tab']);
        if ($type === 'page') {
            $pageId = (int)($it['page'] ?? 0);
            $slug = '';
            foreach ($pages as $p) {
                if ($p['id'] == $pageId) { $slug = $p['slug']; break; }
            }
            if ($slug === '') continue;
            $item = [
                'label' => $label !== '' ? $label : $slug,
                'type' => 'page',
                'page' => $pageId,
                'link' => '/' . $slug,
                'new_tab' => $newTab
            ];
        } else {
            $link = sanitize_url($it['link'] ?? '');
            if ($link === '') continue;
            $item = [
                'label' => $label !== '' ? $label : $link,
                'type' => 'custom',
                'link' => $link,
                'new_tab' => $newTab
            ];
        }
        if (!empty($it['children'])) {
            $children = process_items($it['children'], $pages);
            if (!empty($children)) $item['children'] = $children;
        }
        $items[] = $item;
    }
    return $items;
}

if ($name === '') {
    http_response_code(400);
    echo 'Missing name';
    exit;
}

if ($id) {
    foreach ($menus as &$m) {
        if ($m['id'] == $id) {
            $m['name'] = $name;
            $m['items'] = $items;
            break;
        }
    }
    unset($m);
} else {
    $id = 1;
    foreach ($menus as $m) {
        if ($m['id'] >= $id) $id = $m['id'] + 1;
    }
    $menus[] = ['id' => $id, 'name' => $name, 'items' => $items];
}

file_put_contents($menusFile, json_encode($menus, JSON_PRETTY_PRINT));
echo 'OK';
?>
