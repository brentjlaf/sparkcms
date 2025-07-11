<?php
// File: save_form.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = file_exists($formsFile) ? json_decode(file_get_contents($formsFile), true) : [];

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name = trim($_POST['name'] ?? '');
$fields = isset($_POST['fields']) ? json_decode($_POST['fields'], true) : [];

if ($name === '') {
    http_response_code(400);
    echo 'Missing name';
    exit;
}

if ($id) {
    foreach ($forms as &$f) {
        if ($f['id'] == $id) {
            $f['name'] = $name;
            $f['fields'] = $fields;
            break;
        }
    }
    unset($f);
} else {
    $id = 1;
    foreach ($forms as $f) {
        if ($f['id'] >= $id) $id = $f['id'] + 1;
    }
    $forms[] = ['id' => $id, 'name' => $name, 'fields' => $fields];
}

file_put_contents($formsFile, json_encode($forms, JSON_PRETTY_PRINT));

echo 'OK';
?>
