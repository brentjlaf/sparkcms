<?php
require_once __DIR__ . '/../CMS/modules/media/MediaLibrary.php';

function create_sample_image(): string {
    $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Yz4yfsAAAAASUVORK5CYII=';
    return base64_decode($base64);
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

$rootDir = sys_get_temp_dir() . '/media_library_' . uniqid();
$uploadsDir = $rootDir . '/uploads';
$generalDir = $uploadsDir . '/general';
$generalThumbs = $generalDir . '/thumbs';
$galleryDir = $uploadsDir . '/gallery';
$galleryThumbs = $galleryDir . '/thumbs';
$samplesThumbs = $uploadsDir . '/samples/thumbs';

mkdir($generalThumbs, 0777, true);
mkdir($galleryThumbs, 0777, true);
mkdir($samplesThumbs, 0777, true);

$imageData = create_sample_image();
file_put_contents($generalThumbs . '/alpha.png', $imageData);
file_put_contents($samplesThumbs . '/example.jpg', $imageData);

$bravoPath = $generalDir . '/bravo.pdf';
file_put_contents($bravoPath, 'Sample document');
touch($bravoPath, 1700000005);

$mediaFile = $rootDir . '/media.json';
$dataset = [
    [
        'id' => 1,
        'name' => 'Bravo doc',
        'folder' => 'general',
        'type' => 'documents',
        'file' => 'uploads/general/bravo.pdf',
        'size' => 1200,
        'tags' => ['report'],
        'order' => 2,
        'uploaded_at' => 1700000002,
    ],
    [
        'id' => 2,
        'name' => 'Alpha',
        'folder' => 'general',
        'type' => 'images',
        'file' => 'uploads/general/alpha.png',
        'thumbnail' => 'uploads/general/thumbs/alpha.png',
        'size' => 500,
        'tags' => ['first', 'featured'],
        'order' => 1,
        'uploaded_at' => 1700000000,
        'width' => 100,
        'height' => 100,
    ],
    [
        'id' => 3,
        'name' => 'Charlie',
        'folder' => 'gallery',
        'type' => 'images',
        'file' => 'uploads/gallery/charlie.png',
        'size' => 800,
        'tags' => ['gallery'],
        'order' => 3,
        'uploaded_at' => 1700000005,
        'width' => 50,
        'height' => 60,
    ],
];

file_put_contents($mediaFile, json_encode($dataset));

$library = new MediaLibrary($mediaFile, $rootDir);

$sortExpectations = [
    'custom' => ['Alpha', 'Bravo doc', 'Charlie'],
    'name' => ['Alpha', 'Bravo doc', 'Charlie'],
    'date' => ['Alpha', 'Bravo doc', 'Charlie'],
    'type' => ['Bravo doc', 'Alpha', 'Charlie'],
    'size' => ['Alpha', 'Charlie', 'Bravo doc'],
    'tags' => ['Alpha', 'Charlie', 'Bravo doc'],
    'dimensions' => ['Bravo doc', 'Charlie', 'Alpha'],
];

foreach ($sortExpectations as $sort => $expectedNames) {
    $result = $library->listMedia(['sort' => $sort]);
    $names = array_column($result['media'], 'name');
    if ($names !== $expectedNames) {
        rrmdir($rootDir);
        throw new RuntimeException("Unexpected order for sort '{$sort}'.");
    }
}

$descResult = $library->listMedia(['sort' => 'size', 'order' => 'desc']);
$descNames = array_column($descResult['media'], 'name');
if ($descNames !== array_reverse($sortExpectations['size'])) {
    rrmdir($rootDir);
    throw new RuntimeException('Descending sort order failed.');
}

$baseResult = $library->listMedia(['sort' => 'custom']);
if ($baseResult['total'] !== 3) {
    rrmdir($rootDir);
    throw new RuntimeException('Total count mismatch.');
}

if ($baseResult['total_size'] !== (500 + 1200 + 800)) {
    rrmdir($rootDir);
    throw new RuntimeException('Total size calculation is incorrect.');
}

if ($baseResult['last_modified'] !== 1700000005) {
    rrmdir($rootDir);
    throw new RuntimeException('Last modified timestamp should reflect the latest file update.');
}

$folders = $library->listFolders();
$folderMap = [];
foreach ($folders as $folderInfo) {
    $folderMap[$folderInfo['name']] = $folderInfo['thumbnail'];
}

if (!isset($folderMap['general']) || $folderMap['general'] !== 'uploads/general/thumbs/alpha.png') {
    rrmdir($rootDir);
    throw new RuntimeException('General folder thumbnail should use the media thumbnail.');
}

if (!isset($folderMap['gallery']) || $folderMap['gallery'] !== 'uploads/gallery/charlie.png') {
    rrmdir($rootDir);
    throw new RuntimeException('Gallery folder thumbnail should fall back to the media file.');
}

if (!isset($folderMap['samples']) || $folderMap['samples'] !== 'uploads/samples/thumbs/example.jpg') {
    rrmdir($rootDir);
    throw new RuntimeException('Samples folder thumbnail should use the filesystem fallback.');
}

rrmdir($rootDir);

echo "MediaLibrary tests passed\n";
