<?php
// File: update_order.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];

$order = json_decode($_POST['order'] ?? '[]', true);
if (!is_array($order)) $order = [];
$index = array_flip($order);
foreach ($media as &$item) {
    if (isset($index[$item['id']])) {
        $item['order'] = $index[$item['id']];
    }
}
usort($media, function($a,$b){ return ($a['order'] ?? 0) <=> ($b['order'] ?? 0); });
file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
