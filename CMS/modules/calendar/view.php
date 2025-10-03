<?php
// File: modules/calendar/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/helpers.php';
require_login();

$repository = new CalendarRepository();
[$events, $categories] = $repository->getDataset();
$metrics = CalendarRepository::computeMetrics($events, $categories);

$message = '';
if (isset($_GET['message'])) {
    $message = trim((string) $_GET['message']);
}

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
<div class="content-section" id="calendar">
    <div class="calendar-dashboard a11y-dashboard calendar-module" id="calendarModule">
        <header class="a11y-hero calendar-hero">
            <div class="a11y-hero-content calendar-hero-content">
                <div class="calendar-hero-text">
                    <span class="hero-eyebrow calendar-hero-eyebrow">Calendar Overview</span>
                    <h2 class="a11y-hero-title calendar-hero-title">Manage Calendar Data</h2>
                    <p class="a11y-hero-subtitle calendar-hero-subtitle">
                        Coordinate upcoming events, recurring schedules, and categories without leaving the dashboard.
                    </p>
                </div>
                <div class="a11y-hero-actions calendar-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" data-calendar-open="event">
                        <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                        <span>Add new event</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-calendar-open="categories">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Manage categories</span>
                    </button>
                    <span class="a11y-hero-meta calendar-hero-meta" data-calendar-next-event>
                        <?php echo htmlspecialchars($nextEventLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid calendar-overview">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" data-calendar-stat="total"><?php echo $totalEventsCount; ?></div>
                    <div class="a11y-overview-label">Total events</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" data-calendar-stat="upcoming"><?php echo $upcomingEventsCount; ?></div>
                    <div class="a11y-overview-label">Upcoming</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" data-calendar-stat="recurring"><?php echo $recurringEventsCount; ?></div>
                    <div class="a11y-overview-label">Recurring</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" data-calendar-stat="categories"><?php echo $categoryCount; ?></div>
                    <div class="a11y-overview-label">Categories</div>
                </div>
            </div>
        </header>

        <div class="calendar-alert" data-calendar-message aria-live="polite"></div>

        <section class="a11y-detail-card calendar-table-card">
            <header class="calendar-card-header">
                <div>
                    <h3>Event library</h3>
                    <p>Review upcoming and past events, update details, or remove outdated sessions.</p>
                </div>
                <div class="calendar-table-controls">
                    <label class="calendar-search" for="calendarEventSearch">
                        <span class="sr-only">Search events</span>
                        <input type="search" id="calendarEventSearch" placeholder="Search events" data-calendar-filter="search">
                    </label>
                    <label class="calendar-sort" for="calendarEventSort">
                        <span class="sr-only">Sort events</span>
                        <select id="calendarEventSort" data-calendar-filter="sort">
                            <option value="startAsc">Start date (ascending)</option>
                            <option value="startDesc">Start date (descending)</option>
                            <option value="endAsc">End date (ascending)</option>
                            <option value="endDesc">End date (descending)</option>
                            <option value="titleAsc">Title (A–Z)</option>
                            <option value="titleDesc">Title (Z–A)</option>
                            <option value="categoryAsc">Category (A–Z)</option>
                            <option value="categoryDesc">Category (Z–A)</option>
                            <option value="recurrenceAsc">Recurrence (A–Z)</option>
                            <option value="recurrenceDesc">Recurrence (Z–A)</option>
                        </select>
                    </label>
                </div>
            </header>
            <div class="calendar-table-wrapper">
                <table class="data-table calendar-table">
                    <thead>
                        <tr>
                            <th data-calendar-sortable="title">
                                <button type="button" class="calendar-sortable" data-calendar-sort-trigger>
                                    <span>Title</span>
                                    <span class="calendar-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th data-calendar-sortable="start">
                                <button type="button" class="calendar-sortable" data-calendar-sort-trigger>
                                    <span>Start</span>
                                    <span class="calendar-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th data-calendar-sortable="end">
                                <button type="button" class="calendar-sortable" data-calendar-sort-trigger>
                                    <span>End</span>
                                    <span class="calendar-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th data-calendar-sortable="category">
                                <button type="button" class="calendar-sortable" data-calendar-sort-trigger>
                                    <span>Category</span>
                                    <span class="calendar-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th data-calendar-sortable="recurrence">
                                <button type="button" class="calendar-sortable" data-calendar-sort-trigger>
                                    <span>Recurrence</span>
                                    <span class="calendar-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th class="is-actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-calendar-events>
                        <tr>
                            <td colspan="6" class="calendar-empty">Loading events…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="a11y-detail-card calendar-categories-card">
            <header>
                <h3>Categories</h3>
                <p>Tag events with unique colors to make schedules easier to scan.</p>
            </header>
            <div class="calendar-categories" data-calendar-categories>
                <div class="calendar-category-empty">No categories available. Add one to get started.</div>
            </div>
        </section>

        <template id="calendarData" type="application/json"><?php echo htmlspecialchars(json_encode($initialPayload), ENT_QUOTES, 'UTF-8'); ?></template>
    </div>
</div>
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
                        <label for="calendarEventStartPicker">Start Date/Time*</label>
                        <div class="calendar-datetime-picker" data-calendar-datetime data-calendar-datetime-required="true">
                            <input type="hidden" name="start_date" data-calendar-datetime-input>
                            <button type="button" class="calendar-datetime-toggle" id="calendarEventStartPicker" data-calendar-datetime-toggle aria-haspopup="dialog" aria-expanded="false">
                                <span data-calendar-datetime-value>Select date &amp; time</span>
                            </button>
                            <p class="calendar-datetime-helper" data-calendar-datetime-helper></p>
                        </div>
                    </div>
                    <div>
                        <label for="calendarEventEndPicker">End Date/Time</label>
                        <div class="calendar-datetime-picker" data-calendar-datetime>
                            <input type="hidden" name="end_date" data-calendar-datetime-input>
                            <button type="button" class="calendar-datetime-toggle" id="calendarEventEndPicker" data-calendar-datetime-toggle aria-haspopup="dialog" aria-expanded="false">
                                <span data-calendar-datetime-value>Select date &amp; time</span>
                            </button>
                            <p class="calendar-datetime-helper" data-calendar-datetime-helper></p>
                        </div>
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
                        <label for="calendarEventRecurrenceEndPicker">Recurrence End</label>
                        <div class="calendar-datetime-picker" data-calendar-datetime>
                            <input type="hidden" name="recurring_end_date" data-calendar-datetime-input>
                            <button type="button" class="calendar-datetime-toggle" id="calendarEventRecurrenceEndPicker" data-calendar-datetime-toggle aria-haspopup="dialog" aria-expanded="false">
                                <span data-calendar-datetime-value>Select date &amp; time</span>
                            </button>
                            <p class="calendar-datetime-helper" data-calendar-datetime-helper></p>
                        </div>
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
            <section class="calendar-modal-table">
                <div class="calendar-table-wrapper">
                    <table class="data-table calendar-table">
                        <thead>
                            <tr>
                                <th class="is-id-column">ID</th>
                                <th>Name</th>
                                <th>Color</th>
                                <th class="is-actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody data-calendar-categories>
                            <tr>
                                <td colspan="4" class="calendar-empty">No categories yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <form data-calendar-form="category" class="calendar-form-grid calendar-category-form">
                <div>
                    <label for="calendarCategoryName">Category Name*</label>
                    <input type="text" name="cat_name" id="calendarCategoryName" required>
                </div>
                <div>
                    <label for="calendarCategoryColor">Color</label>
                    <input type="color" name="cat_color" id="calendarCategoryColor" value="#ffffff">
                </div>
                <div class="span-2 calendar-form-actions calendar-form-actions--inline">
                    <button type="submit" class="calendar-submit-btn">Add Category</button>
                </div>
            </form>
        </div>
        <div class="calendar-modal-footer">
            <button type="button" class="calendar-btn-outline" data-calendar-close>Close</button>
        </div>
    </div>
</div>

<div class="calendar-modal-backdrop calendar-modal-small" data-calendar-modal="confirm">
    <div class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="calendarConfirmTitle">
        <div class="calendar-modal-header">
            <h2 class="calendar-modal-title" id="calendarConfirmTitle" data-calendar-confirm-title>Confirm Action</h2>
            <button type="button" class="calendar-close" data-calendar-close>&times;</button>
        </div>
        <div class="calendar-modal-body">
            <p data-calendar-confirm-message>Are you sure?</p>
        </div>
        <div class="calendar-modal-footer">
            <button type="button" class="calendar-btn-outline" data-calendar-close>Cancel</button>
            <button type="button" class="calendar-confirm-btn calendar-confirm-btn--danger" data-calendar-confirm-accept>Confirm</button>
        </div>
    </div>
</div>

<script>
window.sparkCalendarInitial = <?php echo json_encode($initialPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
