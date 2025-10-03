<?php
require_once __DIR__ . '/../CMS/modules/commerce/CommerceService.php';

$fixture = [
    'summary' => [],
    'alerts' => [],
    'catalog' => [
        [
            'sku' => 'SKU-001',
            'name' => 'Decor Lamp',
            'category' => 'Decor',
            'price' => 120,
            'inventory' => 4,
            'status' => 'Active',
            'visibility' => 'Published',
        ],
        [
            'sku' => 'SKU-002',
            'name' => 'Kitchen Bowl',
            'category' => 'Kitchen',
            'price' => 30,
            'inventory' => 15,
            'status' => 'Active',
            'visibility' => 'Published',
        ],
        [
            'sku' => 'SKU-003',
            'name' => 'Decor Vase',
            'category' => 'Decor',
            'price' => 85,
            'inventory' => 9,
            'status' => 'Restock',
            'visibility' => 'Hidden',
        ],
    ],
    'categories' => [
        ['id' => 'decor', 'name' => 'Decor', 'slug' => 'decor'],
        ['id' => 'decor-duplicate', 'name' => 'Decor', 'slug' => 'decor'],
        ['id' => 'kitchen', 'name' => 'Kitchen', 'slug' => 'kitchen'],
    ],
    'orders' => [],
    'customers' => [],
    'reports' => [],
    'settings' => [
        'currency' => 'usd',
        'low_inventory_threshold' => 10,
    ],
];

$tempFile = tempnam(sys_get_temp_dir(), 'commerce');
if ($tempFile === false) {
    throw new RuntimeException('Unable to create temporary commerce dataset.');
}

file_put_contents($tempFile, json_encode($fixture));

$service = new CommerceService($tempFile);
$context = $service->buildDashboardContext();

$categorySlugs = array_map(static function (array $category) {
    return $category['slug'];
}, $context['catalog']['categories']['list']);

if (count($categorySlugs) !== count(array_unique($categorySlugs))) {
    throw new RuntimeException('Category slugs should be deduplicated.');
}

if (!in_array('decor', $categorySlugs, true) || !in_array('kitchen', $categorySlugs, true)) {
    throw new RuntimeException('Expected categories are missing after normalisation.');
}

$stats = $context['catalog']['stats'];
if ($stats['showLowInventory'] !== true) {
    throw new RuntimeException('Low inventory indicator should be enabled when a threshold is configured.');
}

if ($stats['lowInventoryCount'] !== '2') {
    throw new RuntimeException('Low inventory count should include products at or below the threshold.');
}

unlink($tempFile);

echo "CommerceService tests passed\n";
