<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);
if (!is_array($forms)) {
    $forms = [];
}

$submissionsFile = __DIR__ . '/../../data/form_submissions.json';
$submissions = read_json_file($submissionsFile);
if (!is_array($submissions)) {
    $submissions = [];
}

$submissionCounts = [];
$lastSubmissionPerForm = [];
$latestSubmissionTimestamp = 0;

$extractTimestamp = static function (array $entry): int {
    foreach (['submitted_at', 'created_at', 'timestamp'] as $key) {
        if (empty($entry[$key])) {
            continue;
        }

        $value = $entry[$key];
        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 0) {
                return $numeric < 1000000000000 ? (int) round($numeric) : (int) round($numeric / 1000);
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

foreach ($submissions as $submission) {
    if (!is_array($submission)) {
        continue;
    }

    $formId = isset($submission['form_id']) ? (int) $submission['form_id'] : null;
    if (!$formId) {
        continue;
    }

    $submissionCounts[$formId] = ($submissionCounts[$formId] ?? 0) + 1;

    $timestamp = $extractTimestamp($submission);
    if ($timestamp > 0) {
        if (!isset($lastSubmissionPerForm[$formId]) || $timestamp > $lastSubmissionPerForm[$formId]) {
            $lastSubmissionPerForm[$formId] = $timestamp;
        }

        if ($timestamp > $latestSubmissionTimestamp) {
            $latestSubmissionTimestamp = $timestamp;
        }
    }
}

$totalForms = count($forms);
$totalSubmissions = array_sum($submissionCounts);
$totalFields = 0;
$collectingForms = 0;
$draftForms = 0;
$readyForms = 0;
$formsMeta = [];

foreach ($forms as $form) {
    $formId = isset($form['id']) ? (int) $form['id'] : null;
    if (!$formId) {
        continue;
    }

    $fields = $form['fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }

    $fieldCount = count($fields);
    $totalFields += $fieldCount;

    $submissionCount = $submissionCounts[$formId] ?? 0;
    if ($submissionCount > 0) {
        $collectingForms++;
    }

    if ($fieldCount === 0) {
        $draftForms++;
    } elseif ($submissionCount === 0) {
        $readyForms++;
    }

    $status = 'draft';
    if ($submissionCount > 0) {
        $status = 'collecting';
    } elseif ($fieldCount > 0) {
        $status = 'ready';
    }

    $lastSubmissionIso = isset($lastSubmissionPerForm[$formId]) ? date(DATE_ATOM, $lastSubmissionPerForm[$formId]) : null;

    $formsMeta[$formId] = [
        'fields' => $fieldCount,
        'submissions' => $submissionCount,
        'status' => $status,
        'lastSubmission' => $lastSubmissionIso,
        'name' => (string) ($form['name'] ?? 'Untitled form'),
    ];
}

$avgFields = $totalForms > 0 ? round($totalFields / $totalForms, 1) : 0;
$lastSubmissionLabel = $latestSubmissionTimestamp > 0 ? date('M j, Y g:i A', $latestSubmissionTimestamp) : 'No submissions yet';

$dashboardStats = [
    'totalForms' => $totalForms,
    'totalSubmissions' => $totalSubmissions,
    'activeForms' => $collectingForms,
    'avgFields' => $avgFields,
    'lastSubmission' => $lastSubmissionLabel,
    'lastSubmissionIso' => $latestSubmissionTimestamp > 0 ? date(DATE_ATOM, $latestSubmissionTimestamp) : null,
];

$filterCounts = [
    'all' => $totalForms,
    'collecting' => $collectingForms,
    'ready' => $readyForms,
    'draft' => $draftForms,
];
?>
<div class="content-section" id="forms">
    <div class="forms-dashboard" data-last-submission="<?php echo htmlspecialchars($dashboardStats['lastSubmission'], ENT_QUOTES); ?>">
        <header class="a11y-hero forms-hero">
            <div class="a11y-hero-content">
                <div>
                    <h2 class="a11y-hero-title">Forms &amp; Responses</h2>
                    <p class="a11y-hero-subtitle">Build conversion-ready forms and keep tabs on every submission from one place.</p>
                </div>
                <div class="a11y-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="newFormBtn">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>New form</span>
                    </button>
                    <span class="a11y-hero-meta" id="formsLastSubmissionMeta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last submission: <?php echo htmlspecialchars($dashboardStats['lastSubmission']); ?>
                    </span>
                </div>
            </div>
        </header>

        <div class="a11y-overview-grid forms-overview">
            <div class="a11y-overview-card">
                <div class="a11y-overview-value" id="formsStatTotal"><?php echo (int) $dashboardStats['totalForms']; ?></div>
                <div class="a11y-overview-label">Total forms</div>
            </div>
            <div class="a11y-overview-card">
                <div class="a11y-overview-value" id="formsStatResponses"><?php echo (int) $dashboardStats['totalSubmissions']; ?></div>
                <div class="a11y-overview-label">Responses collected</div>
            </div>
            <div class="a11y-overview-card">
                <div class="a11y-overview-value" id="formsStatActive"><?php echo (int) $dashboardStats['activeForms']; ?></div>
                <div class="a11y-overview-label">Collecting responses</div>
            </div>
            <div class="a11y-overview-card">
                <div class="a11y-overview-value" id="formsStatFields"><?php echo htmlspecialchars($dashboardStats['avgFields']); ?></div>
                <div class="a11y-overview-label">Average fields / form</div>
            </div>
        </div>

        <div class="forms-controls">
            <label class="a11y-search" for="formsSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="formsSearchInput" placeholder="Search forms by name or status" aria-label="Search forms">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="Form filters">
                <button type="button" class="a11y-filter-btn active" data-forms-filter="all">All forms <span class="a11y-filter-count" data-forms-count="all"><?php echo (int) $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-forms-filter="collecting">Collecting responses <span class="a11y-filter-count" data-forms-count="collecting"><?php echo (int) $filterCounts['collecting']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-forms-filter="ready">Ready to publish <span class="a11y-filter-count" data-forms-count="ready"><?php echo (int) $filterCounts['ready']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-forms-filter="draft">Drafts <span class="a11y-filter-count" data-forms-count="draft"><?php echo (int) $filterCounts['draft']; ?></span></button>
            </div>
        </div>

        <div class="forms-content-grid">
            <section class="forms-card forms-card--list" aria-labelledby="formsListTitle">
                <header class="forms-card__header">
                    <div>
                        <h3 id="formsListTitle">Forms</h3>
                        <p class="forms-card__subtitle">Track performance, review responses, and refine every interaction.</p>
                    </div>
                </header>
                <div class="forms-table-wrapper" id="formsTableWrapper">
                    <table class="forms-table" id="formsTable">
                        <thead>
                            <tr>
                                <th scope="col">Form</th>
                                <th scope="col">Fields</th>
                                <th scope="col">Responses</th>
                                <th scope="col">Last activity</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="forms-empty-state" id="formsEmptyState" hidden>
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <h4>No forms match your filters</h4>
                    <p>Try adjusting the search or create a new form to get started.</p>
                </div>
            </section>

            <section class="forms-card forms-card--submissions" id="formSubmissionsCard" aria-labelledby="formsSubmissionsTitle">
                <header class="forms-card__header">
                    <div>
                        <h3 id="formsSubmissionsTitle">Form submissions</h3>
                        <p id="selectedFormName" class="forms-card__subtitle">Select a form to view submissions</p>
                    </div>
                </header>
                <div class="forms-table-wrapper forms-table-wrapper--submissions">
                    <table class="forms-table forms-table--submissions" id="formSubmissionsTable">
                        <thead>
                            <tr>
                                <th scope="col">Submitted</th>
                                <th scope="col">Details</th>
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

        <section class="forms-builder-card" id="formBuilderCard" hidden aria-labelledby="formBuilderTitle">
            <header class="forms-card__header">
                <div>
                    <h3 id="formBuilderTitle">Add form</h3>
                    <p class="forms-card__subtitle">Use the palette to drag fields into your form layout, then fine-tune each input.</p>
                </div>
            </header>
            <form id="formBuilderForm">
                <input type="hidden" name="id" id="formId">
                <div class="form-group">
                    <label class="form-label" for="formName">Form name</label>
                    <input type="text" class="form-input" id="formName" name="name" required>
                </div>
                <div class="builder-container" role="application" aria-label="Form builder">
                    <div id="fieldPalette" class="forms-palette" aria-label="Field palette">
                        <span class="forms-palette__label">Drag fields</span>
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
                            <p>Select a field to edit</p>
                        </div>
                    </div>
                </div>
                <div class="form-actions forms-builder-actions">
                    <button type="submit" class="a11y-btn a11y-btn--primary">Save form</button>
                    <button type="button" class="a11y-btn a11y-btn--ghost" id="cancelFormEdit">Cancel</button>
                </div>
            </form>
        </section>
    </div>
</div>
<script>
window.formsDashboardStats = <?php echo json_encode($dashboardStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.formsFilterCounts = <?php echo json_encode($filterCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.formsFormMeta = <?php echo json_encode($formsMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
