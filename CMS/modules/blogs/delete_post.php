<?php
// File: delete_post.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';

require_login();

header('Content-Type: application/json');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid post ID is required.'
    ]);
    exit;
}

$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = read_json_file($postsFile);
if (!is_array($posts)) {
    $posts = [];
}

$removed = false;
foreach ($posts as $index => $post) {
    if (!is_array($post)) {
        continue;
    }
    $postId = isset($post['id']) ? (int) $post['id'] : 0;
    if ($postId === (int) $id) {
        unset($posts[$index]);
        $removed = true;
    }
}

if (!$removed) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Post not found.'
    ]);
    exit;
}

$posts = array_values($posts);

if (!write_json_file($postsFile, $posts)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete the post. Please try again.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Post deleted.'
]);
