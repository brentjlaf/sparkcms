<?php
require_once __DIR__ . '/../CMS/modules/menus/MenuBuilder.php';

$pages = [
    ['id' => 1, 'slug' => 'home'],
    ['id' => 2, 'slug' => 'about-us'],
    ['id' => 3, 'slug' => 'contact'],
];

$builder = new MenuBuilder();

$rawItems = [
    [
        'label' => ' Main Menu ',
        'type' => 'page',
        'page' => '2',
        'new_tab' => 'yes',
        'children' => [
            [
                'type' => 'custom',
                'link' => ' https://example.com/path ',
            ],
            [
                'label' => 'Invalid',
                'type' => 'page',
                'page' => 999,
            ],
        ],
    ],
    [
        'type' => 'custom',
        'link' => '   ',
    ],
    [
        'type' => 'page',
        'page' => 3,
        'label' => '',
    ],
    'not-an-array',
];

$normalized = $builder->normalizeItems($rawItems, $pages);

if (count($normalized) !== 2) {
    throw new RuntimeException('Normalization should drop invalid top-level entries.');
}

$parent = $normalized[0];
if ($parent['label'] !== 'Main Menu') {
    throw new RuntimeException('Labels should be sanitized and preserved for page items.');
}

if ($parent['link'] !== '/about-us') {
    throw new RuntimeException('Page links should resolve to the page slug.');
}

if ($parent['new_tab'] !== true) {
    throw new RuntimeException('Boolean flags should be normalized to booleans.');
}

if (!isset($parent['children']) || count($parent['children']) !== 1) {
    throw new RuntimeException('Child normalization should recurse and drop invalid entries.');
}

$child = $parent['children'][0];
if ($child['label'] !== 'https://example.com/path') {
    throw new RuntimeException('Custom links should use the link as a default label when blank.');
}

if ($child['new_tab'] !== false) {
    throw new RuntimeException('Missing boolean flags should default to false.');
}

$pageWithDefaultLabel = $normalized[1];
if ($pageWithDefaultLabel['label'] !== 'contact') {
    throw new RuntimeException('Page items without a label should use the slug as a fallback.');
}

if (isset($pageWithDefaultLabel['children'])) {
    throw new RuntimeException('Items without children should not emit an empty children array.');
}

$emptyResult = $builder->normalizeItems('invalid', $pages);
if ($emptyResult !== []) {
    throw new RuntimeException('Normalization should return an empty array for invalid input.');
}

echo "MenuBuilder normalization tests passed\n";
