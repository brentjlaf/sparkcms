<?php
// File: load-draft.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/PageRepository.php';
require_login();

$repository = new PageRepository();

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $draft = $repository->loadDraft($id);
    header('Content-Type: application/json');
    echo json_encode($draft);
} catch (PageRepositoryException $exception) {
    respond_json(['error' => $exception->getMessage()], $exception->getStatusCode());
} catch (Throwable $exception) {
    respond_json(['error' => 'Unexpected error loading draft.'], 500);
}
