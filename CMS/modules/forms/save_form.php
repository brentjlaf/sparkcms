<?php
// File: save_form.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name = sanitize_text($_POST['name'] ?? '');
$fieldsData = isset($_POST['fields']) ? json_decode($_POST['fields'], true) : [];
$fields = [];
foreach ($fieldsData as $field) {
    if (!is_array($field)) continue;
    $item = [
        'type' => sanitize_text($field['type'] ?? 'text'),
        'label' => sanitize_text($field['label'] ?? ''),
        'name' => sanitize_text($field['name'] ?? '')
    ];
    if (isset($field['required'])) $item['required'] = !empty($field['required']);
    if (isset($field['options'])) $item['options'] = sanitize_text($field['options']);
    $fields[] = $item;
}

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

write_json_file($formsFile, $forms);

echo 'OK';
?>
