<?php
// File: list_posts.php
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
header('Content-Type: application/json');
echo json_encode($posts);
?>
