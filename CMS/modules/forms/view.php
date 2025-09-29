<?php
// File: modules/forms/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);
if (!is_array($forms)) {
    $forms = [];
}

$forms = array_values(array_filter($forms, static function ($item) {
    return is_array($item);
}));

$submissionsFile = __DIR__ . '/../../data/form_submissions.json';
$submissions = read_json_file($submissionsFile);
if (!is_array($submissions)) {
    $submissions = [];
}

$submissions = array_values(array_filter($submissions, static function ($item) {
    return is_array($item);
}));

$extractTimestamp = static function (array $entry): int {
    $candidates = ['submitted_at', 'created_at', 'timestamp'];
    foreach ($candidates as $key) {
        if (empty($entry[$key])) {
            continue;
        }
        $value = $entry[$key];
        if (is_numeric($value)) {
            $value = (float) $value;
            if ($value > 0) {
                return $value < 1_000_000_000_000 ? (int) round($value) : (int) round($value / 1000);
            }
            continue;
        }
        $time = strtotime((string) $value);
        if ($time !== false) {
            return $time;
        }
    }
    return 0;
};

$totalForms = count($forms);
$totalSubmissions = count($submissions);
$recentSubmissions = 0;
$activeForms = [];
$latestSubmission = 0;
$thirtyDaysAgo = time() - (30 * 24 * 60 * 60);

foreach ($submissions as $submission) {
    if (isset($submission['form_id'])) {
        $activeForms[(int) $submission['form_id']] = true;
    }
    $timestamp = $extractTimestamp($submission);
    if ($timestamp > 0) {
        if ($timestamp > $latestSubmission) {
            $latestSubmission = $timestamp;
        }
        if ($timestamp >= $thirtyDaysAgo) {
            $recentSubmissions++;
        }
    }
}

$activeFormsCount = count($activeForms);
$lastSubmissionLabel = $latestSubmission > 0
    ? date('M j, Y g:i A', $latestSubmission)
    : 'No submissions yet';
?>
<div class="content-section" id="forms">
    <div class="forms-dashboard a11y-dashboard"
         data-total-forms="<?php echo (int) $totalForms; ?>"
         data-total-submissions="<?php echo (int) $totalSubmissions; ?>"
         data-recent-submissions="<?php echo (int) $recentSubmissions; ?>"
         data-active-forms="<?php echo (int) $activeFormsCount; ?>"
         data-last-submission="<?php echo htmlspecialchars($lastSubmissionLabel, ENT_QUOTES); ?>">
        <header class="a11y-hero forms-hero">
            <div class="a11y-hero-content">
                <div>
                    <span class="hero-eyebrow forms-hero-eyebrow">Submission Health</span>
                    <h2 class="a11y-hero-title">Form Builder &amp; Intake</h2>
                    <p class="a11y-hero-subtitle">Design conversion-ready forms, monitor submissions, and manage intake without leaving the dashboard.</p>
                </div>
                <div class="a11y-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="newFormBtn">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>New form</span>
                    </button>
                    <span class="a11y-hero-meta forms-last-submission">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        Last submission <span id="formsLastSubmission"><?php echo htmlspecialchars($lastSubmissionLabel); ?></span>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid forms-overview">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatForms"><?php echo (int) $totalForms; ?></div>
                    <div class="a11y-overview-label">Published forms</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatActive"><?php echo (int) $activeFormsCount; ?></div>
                    <div class="a11y-overview-label">Collecting responses</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatSubmissions"><?php echo (int) $totalSubmissions; ?></div>
                    <div class="a11y-overview-label">Total submissions</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatRecent"><?php echo (int) $recentSubmissions; ?></div>
                    <div class="a11y-overview-label">Last 30 days</div>
                </div>
            </div>
        </header>

        <div class="forms-main-grid">
            <section class="a11y-detail-card forms-table-card">
                <header class="forms-card-header">
                    <div>
                        <h3>Forms library</h3>
                        <p>Click any form to review submissions, edit the layout, or remove outdated capture points.</p>
                    </div>
                </header>
                <div class="forms-table-wrapper">
                    <table class="data-table forms-table" id="formsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Fields</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>

            <section class="a11y-detail-card forms-submissions-card" id="formSubmissionsCard">
                <header class="forms-card-header">
                    <div>
                        <h3>Submission activity</h3>
                        <p id="selectedFormName" class="form-submissions-label">Select a form to view submissions</p>
                    </div>
                </header>
                <div class="forms-table-wrapper">
                    <table class="data-table forms-table" id="formSubmissionsTable">
                        <thead>
                            <tr>
                                <th>Submitted</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="placeholder-row">
                                <td colspan="2">Select a form to view submissions.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="a11y-page-detail forms-drawer" id="formBuilderDrawer" hidden role="dialog" aria-modal="true" aria-labelledby="formBuilderTitle" aria-describedby="formBuilderDescription">
            <div class="a11y-detail-content">
                <button type="button" class="a11y-detail-close" id="closeFormBuilder" aria-label="Close form builder">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
                <header class="a11y-detail-modal-header forms-drawer-header">
                    <span class="forms-drawer-subtitle">Form builder</span>
                    <h2 id="formBuilderTitle">Add form</h2>
                    <p class="forms-drawer-description" id="formBuilderDescription">Drag inputs from the palette to build your ideal flow, then fine-tune settings on the right.</p>
                </header>
                <form id="formBuilderForm" class="forms-builder-form">
                    <input type="hidden" name="id" id="formId">
                    <div class="form-group">
                        <label class="form-label" for="formName">Form name</label>
                        <input type="text" class="form-input" id="formName" name="name" required aria-describedby="formNameHint">
                        <p class="form-hint" id="formNameHint">Use a descriptive name so teammates can quickly identify the form.</p>
                    </div>
                    <div class="form-alert" id="formBuilderAlert" role="alert" aria-live="assertive" style="display:none;"></div>
                    <p class="builder-tip">Drag inputs from the palette or press Enter on a field type to add it instantly.</p>
                    <div class="builder-container">
                        <div id="fieldPalette" aria-label="Form fields palette">
                            <div class="palette-heading">Field types</div>
                            <div class="palette-item" data-type="text" role="button" tabindex="0">Text input</div>
                            <div class="palette-item" data-type="email" role="button" tabindex="0">Email</div>
                            <div class="palette-item" data-type="password" role="button" tabindex="0">Password</div>
                            <div class="palette-item" data-type="number" role="button" tabindex="0">Number</div>
                            <div class="palette-item" data-type="date" role="button" tabindex="0">Date</div>
                            <div class="palette-item" data-type="textarea" role="button" tabindex="0">Textarea</div>
                            <div class="palette-item" data-type="select" role="button" tabindex="0">Select</div>
                            <div class="palette-item" data-type="checkbox" role="button" tabindex="0">Checkbox</div>
                            <div class="palette-item" data-type="radio" role="button" tabindex="0">Radio</div>
                            <div class="palette-item" data-type="file" role="button" tabindex="0">File upload</div>
                            <div class="palette-item" data-type="submit" role="button" tabindex="0">Submit button</div>
                        </div>
                        <div class="builder-columns">
                            <ul id="formPreview" class="field-list" aria-label="Form preview" data-placeholder="Drop fields here"></ul>
                            <div id="fieldSettings" class="field-settings">
                                <p>Select a field in the preview to edit its settings.</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions forms-builder-actions">
                        <button type="submit" class="a11y-btn a11y-btn--primary">Save form</button>
                        <button type="button" class="a11y-btn a11y-btn--ghost" id="cancelFormEdit">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
