<?php
// File: list_categories.php
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
$categories = [];
foreach ($posts as $p) {
    if (!empty($p['category']) && !in_array($p['category'], $categories)) {
        $categories[] = $p['category'];
    }
}
header('Content-Type: application/json');
echo json_encode(array_values($categories));
?>
