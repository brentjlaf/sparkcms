<?php
// Demonstration script for BlogRepository unique categories output.
require_once __DIR__ . '/../CMS/modules/blogs/BlogRepository.php';

$repository = new BlogRepository(__DIR__ . '/../CMS/data/blog_posts.json');
$categories = $repository->listCategories();

$uniqueCheck = array_values(array_unique($categories));
$isUnique = $categories === $uniqueCheck;

echo "Categories (" . count($categories) . ")\n";
foreach ($categories as $category) {
    echo " - {$category}\n";
}

echo "\nCategories are unique: " . ($isUnique ? 'yes' : 'no') . "\n";
