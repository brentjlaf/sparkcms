<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = file_exists($formsFile) ? json_decode(file_get_contents($formsFile), true) : [];

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$forms = array_values(array_filter($forms, function($f) use ($id) {
    return $f['id'] != $id;
}));

file_put_contents($formsFile, json_encode($forms, JSON_PRETTY_PRINT));

echo 'OK';
?>
