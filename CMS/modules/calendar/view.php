<?php
// File: modules/calendar/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/helpers.php';
require_login();

$eventsFile = __DIR__ . '/../../data/calendar_events.json';
$categoriesFile = __DIR__ . '/../../data/calendar_categories.json';

if (!is_file($eventsFile)) {
    file_put_contents($eventsFile, "[]\n");
}
if (!is_file($categoriesFile)) {
    file_put_contents($categoriesFile, "[]\n");
}

$events = read_json_file($eventsFile);
if (!is_array($events)) {
    $events = [];
}

$events = array_values(array_filter($events, static function ($item) {
    return is_array($item) && isset($item['id']);
}));

usort($events, static function (array $a, array $b): int {
    $aTime = isset($a['start_date']) ? strtotime((string) $a['start_date']) : 0;
    $bTime = isset($b['start_date']) ? strtotime((string) $b['start_date']) : 0;
    if ($aTime === $bTime) {
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    }
    return $aTime <=> $bTime;
});

$categories = read_json_file($categoriesFile);
if (!is_array($categories)) {
    $categories = [];
}

$categories = array_values(array_filter($categories, static function ($item) {
    return is_array($item) && isset($item['id']);
}));

$message = '';
if (isset($_GET['message'])) {
    $message = trim((string) $_GET['message']);
}

$metrics = compute_calendar_metrics($events, $categories);

$totalEventsCount = (int) ($metrics['total_events'] ?? count($events));
$upcomingEventsCount = (int) ($metrics['upcoming_count'] ?? 0);
$recurringEventsCount = (int) ($metrics['recurring_count'] ?? 0);
$categoryCount = (int) ($metrics['category_count'] ?? count($categories));

$nextEventLabel = 'Next event: none scheduled';
if (!empty($metrics['next_event']) && is_array($metrics['next_event'])) {
    $nextTitle = trim((string) ($metrics['next_event']['title'] ?? ''));
    if ($nextTitle === '') {
        $nextTitle = 'Untitled event';
    }
    $startDate = isset($metrics['next_event']['start_date']) ? (string) $metrics['next_event']['start_date'] : '';
    $formattedDate = '';
    if ($startDate !== '') {
        $timestamp = strtotime($startDate);
        if ($timestamp !== false) {
            $formattedDate = date('M j, Y g:i A', $timestamp);
        }
    }
    $nextEventLabel = 'Next event: ' . $nextTitle;
    if ($formattedDate !== '') {
        $nextEventLabel .= ' • ' . $formattedDate;
    }
}

$initialPayload = [
    'events' => $events,
    'categories' => $categories,
    'message' => $message,
    'metrics' => $metrics,
];
?>
<div class="content-section" id="calendarModule">
    <div class="calendar-dashboard a11y-dashboard">
        <header class="a11y-hero calendar-hero">
            <div class="a11y-hero-content calendar-hero-content">
                <div class="calendar-hero-copy">
                    <p class="a11y-hero-eyebrow calendar-hero-eyebrow">Calendar Overview</p>
                    <h2 class="a11y-hero-title calendar-hero-title">Manage Calendar Data</h2>
                    <p class="a11y-hero-subtitle calendar-hero-subtitle">
                        Coordinate launches, campaigns, and recurring touchpoints without leaving the dashboard.
                    </p>
                    <span class="calendar-hero-badge" data-calendar-next-event><?php echo htmlspecialchars($nextEventLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="a11y-hero-actions calendar-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" data-calendar-open="event">
                        <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                        <span>Add New Event</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary" data-calendar-open="categories">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Manage Categories</span>
                    </button>
                </div>
            </div>
            <div class="a11y-overview-grid calendar-overview-grid">
                <div class="a11y-overview-card calendar-overview-card">
                    <span class="calendar-overview-label">Total Events</span>
                    <strong class="calendar-overview-value" data-calendar-stat="total"><?php echo $totalEventsCount; ?></strong>
                </div>
                <div class="a11y-overview-card calendar-overview-card">
                    <span class="calendar-overview-label">Upcoming</span>
                    <strong class="calendar-overview-value" data-calendar-stat="upcoming"><?php echo $upcomingEventsCount; ?></strong>
                </div>
                <div class="a11y-overview-card calendar-overview-card">
                    <span class="calendar-overview-label">Recurring</span>
                    <strong class="calendar-overview-value" data-calendar-stat="recurring"><?php echo $recurringEventsCount; ?></strong>
                </div>
                <div class="a11y-overview-card calendar-overview-card">
                    <span class="calendar-overview-label">Categories</span>
                    <strong class="calendar-overview-value" data-calendar-stat="categories"><?php echo $categoryCount; ?></strong>
                </div>
            </div>
        </header>

        <div class="calendar-alert" data-calendar-message aria-live="polite"></div>

        <section class="table-card calendar-card">
            <header class="calendar-card-header">
                <div>
                    <h3 class="calendar-card-title">Scheduled events</h3>
                    <p class="calendar-card-description">Stay ahead of important launches, campaigns, and milestones.</p>
                </div>
            </header>
            <div class="calendar-table-wrapper">
                <table class="data-table calendar-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Title</th>
                            <th scope="col">Start</th>
                            <th scope="col">End</th>
                            <th scope="col">Category</th>
                            <th scope="col">Recurrence</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-calendar-events>
                        <tr><td colspan="7" class="calendar-empty">Loading events…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="calendar-modal-backdrop" data-calendar-modal="event">
            <div class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="calendarEventModalTitle">
                <div class="calendar-modal-header">
                    <h2 class="calendar-modal-title" id="calendarEventModalTitle">Add New Event</h2>
                    <button type="button" class="calendar-close" data-calendar-close>&times;</button>
                </div>
                <div class="calendar-modal-body">
                    <form data-calendar-form="event">
                        <input type="hidden" name="evt_id" value="">
                        <div class="calendar-form-grid">
                            <div>
                                <label for="calendarEventTitle">Title*</label>
                                <input type="text" name="title" id="calendarEventTitle" required>
                            </div>
                            <div>
                                <label for="calendarEventCategory">Category</label>
                                <select name="category" id="calendarEventCategory">
                                    <option value="">(None)</option>
                                </select>
                            </div>
                            <div>
                                <label for="calendarEventStart">Start Date/Time*</label>
                                <input type="datetime-local" name="start_date" id="calendarEventStart" required>
                            </div>
                            <div>
                                <label for="calendarEventEnd">End Date/Time</label>
                                <input type="datetime-local" name="end_date" id="calendarEventEnd">
                            </div>
                            <div>
                                <label for="calendarEventRecurrence">Recurrence</label>
                                <select name="recurring_interval" id="calendarEventRecurrence">
                                    <option value="none">None</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div>
                                <label for="calendarEventRecurrenceEnd">Recurrence End</label>
                                <input type="datetime-local" name="recurring_end_date" id="calendarEventRecurrenceEnd">
                            </div>
                            <div class="span-2">
                                <label for="calendarEventDescription">Description</label>
                                <textarea name="description" id="calendarEventDescription"></textarea>
                            </div>
                        </div>
                        <div class="calendar-form-actions">
                            <button type="button" class="calendar-btn-outline" data-calendar-close>Cancel</button>
                            <button type="submit" class="calendar-submit-btn">Save Event</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="calendar-modal-backdrop calendar-modal-small" data-calendar-modal="categories">
            <div class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="calendarCategoriesTitle">
                <div class="calendar-modal-header">
                    <h2 class="calendar-modal-title" id="calendarCategoriesTitle">Manage Categories</h2>
                    <button type="button" class="calendar-close" data-calendar-close>&times;</button>
                </div>
                <div class="calendar-modal-body">
                    <div class="calendar-card calendar-card--embedded">
                        <div class="calendar-table-wrapper">
                            <table class="data-table calendar-table">
                                <thead>
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Color</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody data-calendar-categories>
                                    <tr><td colspan="4" class="calendar-empty">No categories yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <form data-calendar-form="category" class="calendar-form-grid calendar-form-grid--compact">
                        <div>
                            <label for="calendarCategoryName">Category Name*</label>
                            <input type="text" name="cat_name" id="calendarCategoryName" required>
                        </div>
                        <div>
                            <label for="calendarCategoryColor">Color</label>
                            <input type="color" name="cat_color" id="calendarCategoryColor" value="#ffffff">
                        </div>
                        <div class="span-2 calendar-form-footer">
                            <button type="submit" class="calendar-submit-btn">Add Category</button>
                        </div>
                    </form>
                </div>
                <div class="calendar-modal-footer">
                    <button type="button" class="calendar-btn-outline" data-calendar-close>Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
window.sparkCalendarInitial = <?php echo json_encode($initialPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
