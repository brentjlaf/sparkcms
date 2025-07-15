<?php
// File: delete_form.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$forms = array_values(array_filter($forms, function($f) use ($id) {
    return $f['id'] != $id;
}));

file_put_contents($formsFile, json_encode($forms, JSON_PRETTY_PRINT));

echo 'OK';
?>
