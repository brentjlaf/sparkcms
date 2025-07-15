<?php
// File: list_posts.php
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = read_json_file($postsFile);
header('Content-Type: application/json');
echo json_encode($posts);
?>
