<?php
// File: forms/submit.php
require_once __DIR__ . '/../CMS/includes/data.php';
require_once __DIR__ . '/../CMS/includes/sanitize.php';
require_once __DIR__ . '/../CMS/includes/settings.php';

ensure_site_timezone();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    return;
}

$formsFile = __DIR__ . '/../CMS/data/forms.json';
$forms = read_json_file($formsFile);
if (!is_array($forms)) {
    $forms = [];
}

$formId = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
if ($formId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'errors' => [
            ['field' => 'form_id', 'message' => 'Invalid form identifier.'],
        ],
    ]);
    return;
}

$formDefinition = null;
foreach ($forms as $form) {
    if (!is_array($form)) {
        continue;
    }
    if ((int) ($form['id'] ?? 0) === $formId) {
        $formDefinition = $form;
        break;
    }
}

if (!$formDefinition) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'errors' => [
            ['field' => 'form_id', 'message' => 'Form not found.'],
        ],
    ]);
    return;
}

$fields = [];
if (!empty($formDefinition['fields']) && is_array($formDefinition['fields'])) {
    $fields = $formDefinition['fields'];
}

$errors = [];
$data = [];
$fileMeta = [];
$pendingFiles = [];

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

foreach ($fields as $index => $field) {
    if (!is_array($field)) {
        continue;
    }
    $type = isset($field['type']) ? strtolower(trim((string) $field['type'])) : 'text';
    $name = isset($field['name']) ? (string) $field['name'] : '';
    $label = isset($field['label']) ? sanitize_text((string) $field['label']) : '';
    $required = !empty($field['required']);
    $optionsRaw = $field['options'] ?? [];
    if (!is_array($optionsRaw)) {
        $optionsRaw = explode(',', (string) $optionsRaw);
    }
    $options = [];
    foreach ($optionsRaw as $opt) {
        $option = trim((string) $opt);
        if ($option !== '') {
            $options[] = $option;
        }
    }

    $normalizedName = sanitize_field_name($name, $label, $index);

    switch ($type) {
        case 'select':
        case 'radio':
            $value = isset($_POST[$normalizedName]) ? sanitize_text((string) $_POST[$normalizedName]) : '';
            if ($required && $value === '') {
                $errors[] = ['field' => $normalizedName, 'message' => ($label ?: 'This field') . ' is required.'];
                break;
            }
            if ($value !== '' && $options && !in_array($value, $options, true)) {
                $errors[] = ['field' => $normalizedName, 'message' => 'Please select a valid option.'];
                break;
            }
            if ($value !== '') {
                $data[$normalizedName] = $value;
            }
            break;
        case 'checkbox':
            if ($options) {
                $raw = $_POST[$normalizedName] ?? [];
                if (!is_array($raw)) {
                    $raw = [$raw];
                }
                $selected = [];
                foreach ($raw as $val) {
                    $clean = sanitize_text((string) $val);
                    if ($clean !== '' && in_array($clean, $options, true)) {
                        $selected[] = $clean;
                    }
                }
                if ($required && !$selected) {
                    $errors[] = ['field' => $normalizedName, 'message' => 'Please choose at least one option.'];
                    break;
                }
                if ($selected) {
                    $data[$normalizedName] = $selected;
                }
            } else {
                $checked = isset($_POST[$normalizedName]);
                if ($required && !$checked) {
                    $errors[] = ['field' => $normalizedName, 'message' => ($label ?: 'This checkbox') . ' must be checked.'];
                    break;
                }
                if ($checked) {
                    $data[$normalizedName] = 'Yes';
                }
            }
            break;
        case 'file':
            $file = $_FILES[$normalizedName] ?? null;
            if ($file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = ['field' => $normalizedName, 'message' => 'Unable to upload file.'];
                    break;
                }
                $originalName = (string) ($file['name'] ?? 'upload');
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $baseName = preg_replace('/[^a-z0-9_\-]/i', '', pathinfo($originalName, PATHINFO_FILENAME));
                if ($baseName === '') {
                    $baseName = 'upload';
                }
                $filename = $baseName . '-' . bin2hex(random_bytes(8));
                if ($ext) {
                    $filename .= '.' . $ext;
                }
                $targetDir = __DIR__ . '/../CMS/uploads/forms';
                $relativePath = 'uploads/forms/' . $filename;
                $pendingFiles[] = [
                    'tmp_name' => $file['tmp_name'],
                    'target_dir' => $targetDir,
                    'target_path' => $targetDir . '/' . $filename,
                    'relative' => $relativePath,
                    'name' => $normalizedName,
                    'original' => $originalName,
                    'size' => (int) ($file['size'] ?? 0),
                    'type' => isset($file['type']) ? (string) $file['type'] : '',
                ];
            } elseif ($required) {
                $errors[] = ['field' => $normalizedName, 'message' => 'Please upload a file.'];
            }
            break;
        case 'email':
            $value = isset($_POST[$normalizedName]) ? sanitize_text((string) $_POST[$normalizedName]) : '';
            if ($required && $value === '') {
                $errors[] = ['field' => $normalizedName, 'message' => 'Email address is required.'];
                break;
            }
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['field' => $normalizedName, 'message' => 'Please enter a valid email address.'];
                break;
            }
            if ($value !== '') {
                $data[$normalizedName] = $value;
            }
            break;
        case 'textarea':
        case 'text':
        case 'password':
        case 'number':
        case 'date':
        default:
            $value = isset($_POST[$normalizedName]) ? sanitize_text((string) $_POST[$normalizedName]) : '';
            if ($required && $value === '') {
                $errors[] = ['field' => $normalizedName, 'message' => ($label ?: 'This field') . ' is required.'];
                break;
            }
            if ($value !== '') {
                $data[$normalizedName] = $value;
            }
            break;
    }
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    return;
}

$filesMoved = [];
if ($pendingFiles) {
    foreach ($pendingFiles as $fileInfo) {
        if (!is_dir($fileInfo['target_dir'])) {
            if (!mkdir($fileInfo['target_dir'], 0775, true) && !is_dir($fileInfo['target_dir'])) {
                $errors[] = ['field' => $fileInfo['name'], 'message' => 'Unable to store uploaded file.'];
                continue;
            }
        }
        if (!move_uploaded_file($fileInfo['tmp_name'], $fileInfo['target_path'])) {
            $errors[] = ['field' => $fileInfo['name'], 'message' => 'Unable to store uploaded file.'];
            continue;
        }
        $filesMoved[] = $fileInfo;
        $data[$fileInfo['name']] = $fileInfo['relative'];
        $fileMeta[$fileInfo['name']] = [
            'original_name' => $fileInfo['original'],
            'size' => $fileInfo['size'],
            'type' => $fileInfo['type'],
        ];
    }
    if ($errors) {
        foreach ($filesMoved as $fileInfo) {
            if (is_file($fileInfo['target_path'])) {
                @unlink($fileInfo['target_path']);
            }
            unset($data[$fileInfo['name']]);
            unset($fileMeta[$fileInfo['name']]);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }
}

$meta = [
    'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text((string) $_SERVER['REMOTE_ADDR']) : null,
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text((string) $_SERVER['HTTP_USER_AGENT']), 0, 255) : null,
];

$referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url((string) $_SERVER['HTTP_REFERER']) : '';
if ($referer !== '') {
    $meta['referer'] = $referer;
}
if ($fileMeta) {
    $meta['files'] = $fileMeta;
}

$submission = [
    'id' => bin2hex(random_bytes(8)),
    'form_id' => $formId,
    'data' => $data,
    'meta' => array_filter($meta, static function ($value) {
        return $value !== null && $value !== '' && $value !== [];
    }),
    'submitted_at' => date(DATE_ATOM),
    'source' => $referer,
];

$submissionsFile = __DIR__ . '/../CMS/data/form_submissions.json';
if (!file_exists($submissionsFile)) {
    $dir = dirname($submissionsFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($submissionsFile, '[]');
}

$handle = fopen($submissionsFile, 'c+');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => [['field' => null, 'message' => 'Unable to store submission.']]]);
    return;
}

flock($handle, LOCK_EX);
try {
    rewind($handle);
    $existing = stream_get_contents($handle);
    $submissions = $existing ? json_decode($existing, true) : [];
    if (!is_array($submissions)) {
        $submissions = [];
    }
    $submissions[] = $submission;
    $encoded = json_encode($submissions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Encoding error');
    }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, $encoded);
} finally {
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

echo json_encode(['success' => true]);
