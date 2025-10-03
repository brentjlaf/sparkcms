<?php
// File: list_categories.php
require_once __DIR__ . '/BlogRepository.php';

$repository = new BlogRepository();
$categories = $repository->listCategories();

header('Content-Type: application/json');
echo json_encode($categories);
?>
