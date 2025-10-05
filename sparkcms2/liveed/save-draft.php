<?php
// File: save-draft.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/PageRepository.php';
require_login();

$repository = new PageRepository();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$content = $_POST['content'] ?? '';
$timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : null;

try {
    $repository->saveDraft($id, $content, $timestamp);
    echo 'OK';
} catch (PageRepositoryException $exception) {
    respond_json(['error' => $exception->getMessage()], $exception->getStatusCode());
} catch (Throwable $exception) {
    respond_json(['error' => 'Unexpected error saving draft.'], 500);
}
