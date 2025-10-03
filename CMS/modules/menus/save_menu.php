<?php
// File: save_menu.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/MenuBuilder.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name = sanitize_text($_POST['name'] ?? '');
$itemsData = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

$builder = new MenuBuilder();
$items = $builder->normalizeItems($itemsData, $pages);

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
