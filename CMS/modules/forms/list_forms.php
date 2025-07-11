<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = file_exists($formsFile) ? json_decode(file_get_contents($formsFile), true) : [];

echo json_encode($forms);
?>
