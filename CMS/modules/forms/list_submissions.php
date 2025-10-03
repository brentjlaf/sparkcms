<?php
// File: list_submissions.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/FormRepository.php';
require_login();

$formId = isset($_GET['form_id']) ? (int)$_GET['form_id'] : null;
$repository = new FormRepository();
if ($formId !== null && $formId <= 0) {
    $formId = null;
}

$normalized = $repository->getNormalizedSubmissions($formId);

header('Content-Type: application/json');
echo json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
