<?php
// File: save_category.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/helpers.php';
require_login();

header('Content-Type: application/json');

$commerceFile = __DIR__ . '/../../data/commerce.json';
$commerceData = read_json_file($commerceFile);
$catalog = isset($commerceData['catalog']) && is_array($commerceData['catalog']) ? $commerceData['catalog'] : [];
$categories = commerce_prepare_categories($commerceData['categories'] ?? [], $catalog);

$id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
$name = sanitize_text($_POST['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Category name is required.'
    ]);
    exit;
}

foreach ($categories as $category) {
    if (($id === '' || $category['id'] !== $id) && strcasecmp($category['name'], $name) === 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A category with that name already exists.'
        ]);
        exit;
    }
}

$slug = commerce_slugify($name);
$previousName = null;
$updated = false;

if ($id !== '') {
    foreach ($categories as $index => $category) {
        if ($category['id'] === $id) {
            $previousName = $category['name'];
            $categories[$index]['name'] = $name;
            $categories[$index]['slug'] = $slug;
            $updated = true;
            break;
        }
    }
}

if (!$updated) {
    $baseId = $slug !== '' ? $slug : uniqid('category_', false);
    $existingIds = array_column($categories, 'id');
    $newId = $baseId;
    $suffix = 1;
    while (in_array($newId, $existingIds, true)) {
        $newId = $baseId . '-' . $suffix;
        $suffix++;
    }
    $categories[] = [
        'id' => $newId,
        'name' => $name,
        'slug' => $slug !== '' ? $slug : $newId,
    ];
    $id = $newId;
}

if ($previousName !== null && $previousName !== $name) {
    foreach ($catalog as &$product) {
        if (isset($product['category']) && (string) $product['category'] === $previousName) {
            $product['category'] = $name;
        }
    }
    unset($product);
}

$categories = commerce_prepare_categories($categories, $catalog);
$commerceData['categories'] = $categories;
$commerceData['catalog'] = array_values($catalog);

if (write_json_file($commerceFile, $commerceData) === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save commerce categories.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $updated ? 'Category updated.' : 'Category added.',
    'category' => [
        'id' => $id,
        'name' => $name,
        'slug' => $slug !== '' ? $slug : $id,
    ],
    'categories' => $categories,
    'catalog' => $commerceData['catalog'],
]);
