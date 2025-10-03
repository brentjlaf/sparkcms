<?php
// File: list_posts.php
require_once __DIR__ . '/BlogRepository.php';

$repository = new BlogRepository();
$posts = $repository->readPosts();

header('Content-Type: application/json');
echo json_encode($posts);
?>
