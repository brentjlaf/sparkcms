<!-- File: interactive.form.php -->
<!-- Template: interactive.form -->
<?php
require_once __DIR__ . '/../../../CMS/includes/data.php';

$formsFile = __DIR__ . '/../../../CMS/data/forms.json';
$rawForms = get_cached_json($formsFile);
if (!is_array($rawForms)) {
    $rawForms = [];
}

$forms = [];
foreach ($rawForms as $form) {
    if (!is_array($form)) {
        continue;
    }

    $id = isset($form['id']) ? (int) $form['id'] : 0;
    if ($id <= 0) {
        continue;
    }

    $name = isset($form['name']) ? trim((string) $form['name']) : '';
    if ($name === '') {
        $name = 'Form ' . $id;
    }

    $forms[] = [
        'id' => (string) $id,
        'name' => $name,
    ];
}

$placeholderLabel = 'Select a form...';
$formsJsonValue = json_encode($forms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($formsJsonValue === false) {
    $formsJsonValue = '[]';
}
$formsJson = htmlspecialchars($formsJsonValue, ENT_QUOTES, 'UTF-8');
?>
<templateSetting caption="Form Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Select Form</dt>
        <dd>
            <select
                name="custom_form_id"
                class="form-select"
                data-forms-select
                data-placeholder="<?= htmlspecialchars($placeholderLabel, ENT_QUOTES, 'UTF-8') ?>"
                data-forms-defaults="<?= $formsJson ?>"
            >
                <option value=""><?= htmlspecialchars($placeholderLabel, ENT_QUOTES, 'UTF-8') ?></option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?= htmlspecialchars($form['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="mt-2 small text-muted">Manage forms from the Forms tab in the CMS.</p>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Success Message</dt>
        <dd>
            <input type="text" name="custom_success_message" value="Thank you! We'll be in touch soon." />
        </dd>
    </dl>
</templateSetting>
<div class="spark-form-embed" data-form-id="{custom_form_id}">
    <template data-success-template>{custom_success_message}</template>
    <div class="spark-form-placeholder">Select a form to display.</div>
</div>
