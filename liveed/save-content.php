<?php
// File: save-content.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/PageRepository.php';
require_login();

$repository = new PageRepository();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$content = $_POST['content'] ?? '';
$user = $_SESSION['user']['username'] ?? 'Unknown';

try {
    $repository->updatePageContent($id, $content, $user);
    require_once __DIR__ . '/../CMS/modules/sitemap/generate.php';
    $repository->deleteDraft($id);
    echo 'OK';
} catch (PageRepositoryException $exception) {
    respond_json(['error' => $exception->getMessage()], $exception->getStatusCode());
} catch (Throwable $exception) {
    respond_json(['error' => 'Unexpected error saving content.'], 500);
}
