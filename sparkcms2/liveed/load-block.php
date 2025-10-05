<?php
// File: load-block.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/PageRepository.php';
require_login();

$repository = new PageRepository();

try {
    $block = isset($_GET['file']) ? $_GET['file'] : '';
    $contents = $repository->loadBlock($block);
    header('Content-Type: text/html; charset=utf-8');
    echo $contents;
} catch (PageRepositoryException $exception) {
    respond_json(['error' => $exception->getMessage()], $exception->getStatusCode());
} catch (Throwable $exception) {
    respond_json(['error' => 'Unexpected error loading block.'], 500);
}
