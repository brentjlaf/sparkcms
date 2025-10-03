<?php
require_once __DIR__ . '/../CMS/includes/data.php';
require_once __DIR__ . '/../CMS/includes/sanitize.php';
require_once __DIR__ . '/../CMS/includes/settings.php';
require_once __DIR__ . '/../CMS/includes/UploadHandler.php';
require_once __DIR__ . '/../CMS/modules/settings/SettingsService.php';

function create_settings_test_environment(array $initialSettings = []): array
{
    $baseDir = sys_get_temp_dir() . '/sparkcms_settings_' . uniqid('', true);
    $uploadsDir = $baseDir . '/uploads';
    if (!@mkdir($uploadsDir, 0777, true) && !is_dir($uploadsDir)) {
        throw new RuntimeException('Unable to create uploads fixture directory.');
    }

    $settingsFile = $baseDir . '/settings.json';
    file_put_contents($settingsFile, json_encode($initialSettings, JSON_PRETTY_PRINT));

    $handler = new UploadHandler(
        $uploadsDir,
        $baseDir,
        static function () {
            return true;
        },
        static function ($from, $to) {
            return rename($from, $to);
        }
    );

    $service = new SettingsService($settingsFile, $handler);

    return [
        'baseDir' => $baseDir,
        'uploadsDir' => $uploadsDir,
        'settingsFile' => $settingsFile,
        'handler' => $handler,
        'service' => $service,
    ];
}

function cleanup_settings_test_environment(string $baseDir): void
{
    if (!is_dir($baseDir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }

    @rmdir($baseDir);
}

$environment = create_settings_test_environment();
$invalidFile = tempnam($environment['baseDir'], 'upload');
if ($invalidFile === false) {
    throw new RuntimeException('Unable to create invalid upload fixture.');
}
file_put_contents($invalidFile, 'not an image');

try {
    $environment['handler']->validateImageUpload(
        [
            'tmp_name' => $invalidFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($invalidFile),
            'name' => 'bad.txt',
        ],
        ['image/png'],
        1024,
        'Logo'
    );
    throw new RuntimeException('Invalid upload should fail validation.');
} catch (InvalidArgumentException $e) {
    if ($e->getMessage() !== 'Logo must be a valid image file.') {
        throw new RuntimeException('Unexpected validation error message: ' . $e->getMessage());
    }
} finally {
    @unlink($invalidFile);
    cleanup_settings_test_environment($environment['baseDir']);
}

$originalLogo = 'uploads/original.png';
$environment = create_settings_test_environment([
    'site_name' => 'Existing',
    'logo' => $originalLogo,
]);
file_put_contents($environment['uploadsDir'] . '/original.png', 'dummy');

$environment['service']->save([
    'clear_logo' => '1',
], []);

if (file_exists($environment['uploadsDir'] . '/original.png')) {
    throw new RuntimeException('Clearing the logo should delete the previous asset.');
}

$updated = read_json_file($environment['settingsFile']);
if (isset($updated['logo'])) {
    throw new RuntimeException('Logo entry should be removed after clearing.');
}

cleanup_settings_test_environment($environment['baseDir']);

$environment = create_settings_test_environment();
$environment['service']->save([
    'facebook' => ' https://example.com/page ',
    'instagram' => '  ',
], []);

$updated = read_json_file($environment['settingsFile']);
$facebook = $updated['social']['facebook'] ?? null;
$instagram = $updated['social']['instagram'] ?? null;

if ($facebook !== 'https://example.com/page') {
    throw new RuntimeException('Facebook URL should be trimmed and sanitized.');
}

if ($instagram !== '') {
    throw new RuntimeException('Empty social links should sanitize to an empty string.');
}

cleanup_settings_test_environment($environment['baseDir']);

echo "SettingsService tests passed\n";
