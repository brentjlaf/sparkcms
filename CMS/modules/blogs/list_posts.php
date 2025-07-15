<?php
// File: list_posts.php
$postsFile = __DIR__ . '/../../data/blog_posts.json';
require_once __DIR__ . '/../../includes/data.php';
$posts = read_json_file($postsFile);
header('Content-Type: application/json');
echo json_encode($posts);
?>
