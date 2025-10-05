<?php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/PageRepository.php';
require_login();

$repository = new PageRepository();

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $history = $repository->getHistory($id, $limit);
    header('Content-Type: application/json');
    echo json_encode(['history' => $history]);
} catch (PageRepositoryException $exception) {
    respond_json(['error' => $exception->getMessage()], $exception->getStatusCode());
} catch (Throwable $exception) {
    respond_json(['error' => 'Unexpected error loading history.'], 500);
}
