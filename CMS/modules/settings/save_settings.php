<?php
// File: save_settings.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/SettingsService.php';
require_login();

header('Content-Type: application/json');

try {
    $settingsFile = get_settings_file_path();
    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        throw new RuntimeException('Unable to resolve application path.');
    }

    $uploadHandler = new UploadHandler($baseDir . '/uploads', $baseDir);
    $service = new SettingsService($settingsFile, $uploadHandler);

    $result = $service->save($_POST, $_FILES);

    echo json_encode([
        'status' => 'ok',
        'last_updated' => $result['last_updated'],
        'logo' => $result['logo'],
        'favicon' => $result['favicon'],
        'open_graph' => $result['open_graph'],
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
