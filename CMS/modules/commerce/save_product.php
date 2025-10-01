<?php
// File: save_product.php
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

$originalSku = sanitize_text($_POST['original_sku'] ?? '');
$sku = sanitize_text($_POST['sku'] ?? '');
$name = sanitize_text($_POST['name'] ?? '');
$categoryName = sanitize_text($_POST['category'] ?? '');
$priceInput = $_POST['price'] ?? '';
$inventoryInput = $_POST['inventory'] ?? '';
$statusInput = sanitize_text($_POST['status'] ?? 'Active');
$visibilityInput = sanitize_text($_POST['visibility'] ?? 'Published');
$featuredImageInput = $_POST['featured_image'] ?? '';
$imagesInput = $_POST['images'] ?? '';
$updatedInput = sanitize_text($_POST['updated'] ?? '');

if ($sku === '' || $name === '' || $categoryName === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'SKU, name, and category are required to save a product.'
    ]);
    exit;
}

$price = is_numeric($priceInput) ? round((float) $priceInput, 2) : 0.0;
if ($price < 0) {
    $price = 0.0;
}
$inventory = is_numeric($inventoryInput) ? (int) $inventoryInput : 0;
if ($inventory < 0) {
    $inventory = 0;
}

$status = $statusInput !== '' ? ucwords(strtolower($statusInput)) : 'Active';
$visibility = strtolower($visibilityInput) === 'hidden' ? 'Hidden' : 'Published';
$featuredImage = is_string($featuredImageInput) ? sanitize_url($featuredImageInput) : '';
$images = [];
if (is_array($imagesInput)) {
    $candidateImages = $imagesInput;
} else {
    $candidateImages = is_string($imagesInput) ? preg_split('/\r\n|\r|\n|,/', $imagesInput) : [];
}
if (is_array($candidateImages)) {
    foreach ($candidateImages as $imageUrl) {
        if (!is_string($imageUrl)) {
            continue;
        }
        $sanitized = sanitize_url($imageUrl);
        if ($sanitized !== '') {
            $images[] = $sanitized;
        }
    }
}
$images = array_values(array_unique($images));

if ($updatedInput !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $updatedInput);
    if ($date && $date->format('Y-m-d') === $updatedInput) {
        $updated = $updatedInput;
    } else {
        $updated = date('Y-m-d');
    }
} else {
    $updated = date('Y-m-d');
}

$existingIndex = null;
$normalizedSku = strtolower($sku);
$normalizedOriginal = strtolower($originalSku);

foreach ($catalog as $index => $product) {
    $productSku = isset($product['sku']) ? strtolower((string) $product['sku']) : '';
    if ($productSku === $normalizedOriginal) {
        $existingIndex = $index;
    }
    if ($productSku === $normalizedSku && ($existingIndex === null || $index !== $existingIndex)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Another product already uses that SKU.'
        ]);
        exit;
    }
}

$newProduct = [
    'sku' => $sku,
    'name' => $name,
    'category' => $categoryName,
    'price' => $price,
    'inventory' => $inventory,
    'status' => $status,
    'visibility' => $visibility,
    'featured_image' => $featuredImage,
    'images' => $images,
    'updated' => $updated,
];

if ($existingIndex !== null) {
    $catalog[$existingIndex] = $newProduct;
} else {
    $catalog[] = $newProduct;
}

$categorySlug = commerce_slugify($categoryName);
$categoryExists = false;
foreach ($categories as $category) {
    if (isset($category['name']) && strcasecmp((string) $category['name'], $categoryName) === 0) {
        $categoryExists = true;
        break;
    }
}

if (!$categoryExists) {
    $newCategoryId = $categorySlug !== '' ? $categorySlug : uniqid('category_', false);
    $categories[] = [
        'id' => $newCategoryId,
        'name' => $categoryName,
        'slug' => $categorySlug !== '' ? $categorySlug : $newCategoryId,
    ];
}

$catalog = array_values($catalog);
$categories = commerce_prepare_categories($categories, $catalog);

$commerceData['catalog'] = $catalog;
$commerceData['categories'] = $categories;

if (write_json_file($commerceFile, $commerceData) === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save product information.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $existingIndex !== null ? 'Product updated.' : 'Product created.',
    'product' => $newProduct,
    'catalog' => $catalog,
    'categories' => $categories,
]);
