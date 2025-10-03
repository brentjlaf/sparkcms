<?php
// File: list_categories.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/BlogRepository.php';

require_login();

$repository = new BlogRepository();
$categories = $repository->listCategories();

header('Content-Type: application/json');
echo json_encode($categories);
?>
