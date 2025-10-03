<?php
// File: list_posts.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/BlogRepository.php';

require_login();

$repository = new BlogRepository();
$posts = $repository->readPosts();

header('Content-Type: application/json');
echo json_encode($posts);
?>
