<?php
// File: forms/get.php
require_once __DIR__ . '/../CMS/includes/data.php';
require_once __DIR__ . '/../CMS/includes/sanitize.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$formsFile = __DIR__ . '/../CMS/data/forms.json';
$forms = read_json_file($formsFile);
if (!is_array($forms)) {
    $forms = [];
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function sanitize_field_name(string $name, string $label, int $index): string
{
    $candidate = $name !== '' ? $name : $label;
    if ($candidate === '') {
        $candidate = 'field_' . ($index + 1);
    }
    $sanitized = preg_replace('/[^a-z0-9_\-]/i', '_', $candidate);
    if ($sanitized === '') {
        $sanitized = 'field_' . ($index + 1);
    }
    return $sanitized;
}

function normalize_form(array $form, bool $includeFields = false): array
{
    $normalized = [
        'id' => isset($form['id']) ? (int) $form['id'] : null,
        'name' => isset($form['name']) ? sanitize_text((string) $form['name']) : '',
    ];

    if ($includeFields) {
        $fields = [];
        $index = 0;
        if (!empty($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $type = isset($field['type']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $field['type']) : 'text';
                $rawName = isset($field['name']) ? (string) $field['name'] : '';
                $label = isset($field['label']) ? sanitize_text((string) $field['label']) : '';
                $name = sanitize_field_name($rawName, $label, $index);
                $required = !empty($field['required']);
                $options = [];
                if (!empty($field['options'])) {
                    $parts = is_array($field['options']) ? $field['options'] : explode(',', (string) $field['options']);
                    foreach ($parts as $part) {
                        $option = trim((string) $part);
                        if ($option !== '') {
                            $options[] = $option;
                        }
                    }
                }
                $fields[] = [
                    'type' => $type ?: 'text',
                    'name' => $name,
                    'label' => $label,
                    'required' => $required,
                    'options' => $options,
                ];
                $index++;
            }
        }
        $normalized['fields'] = $fields;
    }

    return $normalized;
}

if ($id > 0) {
    foreach ($forms as $form) {
        if (!is_array($form)) {
            continue;
        }
        if ((int) ($form['id'] ?? 0) === $id) {
            echo json_encode(normalize_form($form, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Form not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

$list = array_map(function ($form) {
    return is_array($form) ? normalize_form($form, false) : [];
}, $forms);

echo json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
