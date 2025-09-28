<?php
// File: modules/calendar/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
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

$totalEvents = count($events);
$categoryCount = count($categories);
$now = time();
$upcomingCount = 0;
$recurringCount = 0;
$nextEventName = '';
$nextEventTimestamp = null;

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }

    $recurring = strtolower((string) ($event['recurring_interval'] ?? ''));
    if ($recurring !== '' && $recurring !== 'none') {
        $recurringCount++;
    }

    if (isset($event['start_date'])) {
        $startTimestamp = strtotime((string) $event['start_date']);
        if ($startTimestamp !== false && $startTimestamp >= $now) {
            $upcomingCount++;
            if ($nextEventTimestamp === null || $startTimestamp < $nextEventTimestamp) {
                $nextEventTimestamp = $startTimestamp;
                $title = trim((string) ($event['title'] ?? ''));
                $nextEventName = $title !== '' ? $title : 'Untitled event';
            }
        }
    }
}

$hasUpcomingEvent = $nextEventTimestamp !== null;
$nextEventDisplay = 'No upcoming events scheduled';
$nextEventTitle = 'No upcoming events scheduled';
if ($hasUpcomingEvent) {
    $formattedDate = date('M j, Y g:i A', $nextEventTimestamp);
    $longDate = date('l, F j, Y g:i A', $nextEventTimestamp);
    $nextEventDisplay = $nextEventName . ' • ' . $formattedDate;
    $nextEventTitle = $nextEventName . ' on ' . $longDate;
}
$nextEventEmptyLabel = 'No upcoming events scheduled';

$message = '';
if (isset($_GET['message'])) {
    $message = trim((string) $_GET['message']);
}

$initialPayload = [
    'events' => $events,
    'categories' => $categories,
    'message' => $message,
];
?>
<div class="content-section calendar-admin" id="calendarModule">
    <style>
        .calendar-admin {
            font-family: "Inter", "Helvetica Neue", Arial, sans-serif;
            color: #1f2937;
        }
        .calendar-admin .calendar-dashboard {
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }
        .calendar-admin .calendar-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
        }
        .calendar-admin .calendar-hero::before {
            content: '';
            position: absolute;
            inset: auto -80px -90px auto;
            width: 260px;
            height: 260px;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            filter: blur(1px);
        }
        .calendar-admin .calendar-hero-content {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1.5rem;
            z-index: 1;
        }
        .calendar-admin .calendar-hero-content > div:first-child {
            max-width: 36rem;
        }
        .calendar-admin .calendar-hero-title {
            margin: 0 0 0.5rem;
        }
        .calendar-admin .calendar-hero-subtitle {
            margin: 0;
        }
        .calendar-admin .calendar-hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }
        .calendar-admin .calendar-hero-actions .a11y-btn {
            gap: 0.5rem;
        }
        .calendar-admin .calendar-hero-actions .a11y-btn--secondary {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }
        .calendar-admin .calendar-hero-actions .a11y-btn--secondary:hover {
            background: rgba(255, 255, 255, 0.24);
            color: #fff;
        }
        .calendar-admin .calendar-hero-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.28);
            font-size: 0.9rem;
        }
        .calendar-admin .calendar-hero-meta.is-empty {
            background: rgba(255, 255, 255, 0.18);
            color: rgba(255, 255, 255, 0.85);
        }
        .calendar-admin .calendar-hero-meta i {
            font-size: 1rem;
        }
        .calendar-admin .calendar-overview-grid {
            position: relative;
            z-index: 1;
        }
        .calendar-admin .calendar-overview-grid .a11y-overview-card {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 1.1rem;
            padding: 1.2rem 1.3rem;
            backdrop-filter: blur(6px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.15);
        }
        .calendar-admin .calendar-overview-grid .a11y-overview-label {
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.78);
            margin-bottom: 0.2rem;
        }
        .calendar-admin .calendar-overview-grid .a11y-overview-value {
            font-size: 1.55rem;
            font-weight: 600;
            color: #fff;
        }
        .calendar-admin .calendar-btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 0.75rem;
            padding: 0.65rem 1.2rem;
            cursor: pointer;
            font-size: 0.95rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .calendar-admin .calendar-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(37, 99, 235, 0.25);
        }
        .calendar-admin .calendar-btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            border-radius: 0.75rem;
            padding: 0.6rem 1.1rem;
            font-size: 0.95rem;
            cursor: pointer;
            color: #1f2937;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .calendar-admin .calendar-btn-outline:hover {
            background: #f9fafb;
            color: #111827;
        }
        .calendar-admin .calendar-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .calendar-admin table {
            width: 100%;
            border-collapse: collapse;
        }
        .calendar-admin thead {
            background: #f9fafb;
        }
        .calendar-admin th,
        .calendar-admin td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 0.95rem;
        }
        .calendar-admin th {
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.8rem;
        }
        .calendar-admin tbody tr:hover {
            background: #f3f4f6;
        }
        .calendar-admin .calendar-table-actions {
            display: flex;
            gap: 0.5rem;
        }
        .calendar-admin .calendar-table-actions button {
            padding: 0.35rem 0.75rem;
            border-radius: 0.45rem;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .calendar-admin .calendar-edit-btn {
            background: #2563eb;
            color: #fff;
        }
        .calendar-admin .calendar-delete-btn {
            background: #ef4444;
            color: #fff;
        }
        .calendar-admin .calendar-empty {
            text-align: center;
            padding: 2rem 1rem;
            color: #6b7280;
        }
        .calendar-admin .calendar-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #e0e7ff;
            color: #4338ca;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.8rem;
        }
        .calendar-admin .calendar-badge::before {
            content: '';
            width: 0.65rem;
            height: 0.65rem;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.7;
        }
        .calendar-admin .calendar-alert {
            display: none;
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 0.65rem;
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
        }
        .calendar-admin .calendar-alert.is-visible {
            display: block;
        }
        .calendar-admin .calendar-alert[data-type="success"] {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }
        .calendar-admin .calendar-alert[data-type="error"] {
            background: #fee2e2;
            border-color: #fecaca;
            color: #991b1b;
        }
        body.calendar-modal-open {
            overflow: hidden;
        }
        .calendar-admin .calendar-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .calendar-admin .calendar-modal-backdrop.show {
            display: flex;
        }
        .calendar-admin .calendar-modal {
            background: #fff;
            border-radius: 1rem;
            width: min(640px, 94vw);
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.2);
            animation: calendarModalIn 0.25s ease;
        }
        @keyframes calendarModalIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .calendar-admin .calendar-modal-header,
        .calendar-admin .calendar-modal-footer {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .calendar-admin .calendar-modal-footer {
            border-top: 1px solid #e5e7eb;
            border-bottom: none;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        .calendar-admin .calendar-modal-body {
            padding: 1.5rem;
        }
        .calendar-admin .calendar-modal-title {
            font-size: 1.2rem;
            margin: 0;
            font-weight: 600;
        }
        .calendar-admin .calendar-close {
            border: none;
            background: transparent;
            font-size: 1.3rem;
            cursor: pointer;
            color: #6b7280;
        }
        .calendar-admin .calendar-form-grid {
            display: grid;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .calendar-admin .calendar-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .calendar-admin .calendar-form-grid .span-2 {
                grid-column: span 2 / span 2;
            }
        }
        .calendar-admin label {
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: #374151;
        }
        .calendar-admin input[type="text"],
        .calendar-admin input[type="datetime-local"],
        .calendar-admin input[type="color"],
        .calendar-admin textarea,
        .calendar-admin select {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.65rem 0.75rem;
            font-size: 0.95rem;
            background: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .calendar-admin textarea {
            min-height: 120px;
            resize: vertical;
        }
        .calendar-admin input:focus,
        .calendar-admin textarea:focus,
        .calendar-admin select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .calendar-admin .calendar-submit-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.7rem 1.4rem;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .calendar-admin .calendar-submit-btn:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
        }
        .calendar-admin .calendar-category-color {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .calendar-admin .calendar-category-color span {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border-radius: 0.3rem;
            border: 1px solid rgba(15, 23, 42, 0.15);
        }
        .calendar-admin .calendar-modal-small .calendar-modal {
            width: min(480px, 92vw);
        }
        .calendar-admin .calendar-muted {
            color: #6b7280;
        }
        .calendar-admin .calendar-recurring {
            text-transform: capitalize;
        }
    </style>

    <div class="calendar-dashboard a11y-dashboard">
        <header class="a11y-hero calendar-hero">
            <div class="a11y-hero-content calendar-hero-content">
                <div>
                    <h2 class="a11y-hero-title calendar-hero-title">Calendar &amp; Events</h2>
                    <p class="a11y-hero-subtitle calendar-hero-subtitle">Plan programming, track key milestones, and keep your public schedule aligned across the site.</p>
                </div>
                <div class="a11y-hero-actions calendar-hero-actions">
                    <a class="a11y-btn a11y-btn--ghost" href="index.html" target="_blank" rel="noopener">
                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                        <span>View calendar</span>
                    </a>
                    <button type="button" class="a11y-btn a11y-btn--primary" data-calendar-open="event">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New event</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary" data-calendar-open="categories">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Manage categories</span>
                    </button>
                    <span class="a11y-hero-meta calendar-hero-meta<?php echo $hasUpcomingEvent ? '' : ' is-empty'; ?>" data-calendar-next-event title="<?php echo htmlspecialchars($nextEventTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <span data-calendar-next-event-text data-empty-label="<?php echo htmlspecialchars($nextEventEmptyLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($nextEventDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid calendar-overview-grid">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Total events</div>
                    <div class="a11y-overview-value" data-calendar-stat="total"><?php echo (int) $totalEvents; ?></div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Upcoming events</div>
                    <div class="a11y-overview-value" data-calendar-stat="upcoming"><?php echo (int) $upcomingCount; ?></div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Recurring events</div>
                    <div class="a11y-overview-value" data-calendar-stat="recurring"><?php echo (int) $recurringCount; ?></div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Categories</div>
                    <div class="a11y-overview-value" data-calendar-stat="categories"><?php echo (int) $categoryCount; ?></div>
                </div>
            </div>
        </header>

        <div class="calendar-alert" data-calendar-message aria-live="polite"></div>

        <div class="calendar-card">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Title</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Category</th>
                        <th>Recurrence</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody data-calendar-events>
                    <tr><td colspan="7" class="calendar-empty">Loading events…</td></tr>
                </tbody>
            </table>
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
                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
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
                <div class="calendar-card" style="box-shadow:none; border:1px solid #e5e7eb; margin-bottom:1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Name</th>
                                <th>Color</th>
                                <th style="width:100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody data-calendar-categories>
                            <tr><td colspan="4" class="calendar-empty">No categories yet.</td></tr>
                        </tbody>
                    </table>
                </div>
                <form data-calendar-form="category" class="calendar-form-grid">
                    <div>
                        <label for="calendarCategoryName">Category Name*</label>
                        <input type="text" name="cat_name" id="calendarCategoryName" required>
                    </div>
                    <div>
                        <label for="calendarCategoryColor">Color</label>
                        <input type="color" name="cat_color" id="calendarCategoryColor" value="#ffffff">
                    </div>
                    <div class="span-2" style="display:flex; justify-content:flex-end;">
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
<script>
window.sparkCalendarInitial = <?php echo json_encode($initialPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
