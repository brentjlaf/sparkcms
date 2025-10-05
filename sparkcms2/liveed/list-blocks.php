<?php
// File: list-blocks.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/PageRepository.php';
require_login();

$repository = new PageRepository();

try {
    $blocks = $repository->listBlocks();
    header('Content-Type: application/json');
    echo json_encode(['blocks' => $blocks]);
} catch (PageRepositoryException $exception) {
    respond_json(['error' => $exception->getMessage()], $exception->getStatusCode());
} catch (Throwable $exception) {
    respond_json(['error' => 'Unexpected error listing blocks.'], 500);
}
