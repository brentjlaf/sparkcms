<?php
// File: modules/events/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/helpers.php';

require_login();

events_ensure_storage();

$events = events_read_events();
$orders = events_read_orders();
$salesByEvent = events_compute_sales($events, $orders);

$totalEvents = count($events);
$totalTicketsSold = array_sum(array_column($salesByEvent, 'tickets_sold'));
$totalRevenue = array_sum(array_column($salesByEvent, 'revenue'));
$upcoming = array_slice(events_filter_upcoming($events), 0, 5);

$initialPayload = [
    'events' => $events,
    'orders' => $orders,
    'sales' => $salesByEvent,
];
?>
<div class="content-section" id="events">
    <div class="events-dashboard a11y-dashboard" data-events-endpoint="modules/events/api.php" data-events-initial='<?php echo json_encode($initialPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'>
        <header class="a11y-hero events-hero">
            <div class="a11y-hero-content events-hero-content">
                <div class="events-hero-text">
                    <span class="hero-eyebrow">Event Operations</span>
                    <h2 class="a11y-hero-title">Dashboard Overview</h2>
                    <p class="a11y-hero-subtitle">Quick glance at everything happening across your live and upcoming experiences.</p>
                </div>
                <div class="events-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" data-events-open="event">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>Create New Event</span>
                    </button>
                </div>
            </div>
            <div class="events-overview-grid">
                <div class="a11y-overview-card events-overview-card">
                    <div class="events-overview-value" data-events-stat="events"><?php echo (int) $totalEvents; ?></div>
                    <div class="a11y-overview-label">Total Events</div>
                </div>
                <div class="a11y-overview-card events-overview-card">
                    <div class="events-overview-value" data-events-stat="tickets"><?php echo (int) $totalTicketsSold; ?></div>
                    <div class="a11y-overview-label">Tickets Sold</div>
                </div>
                <div class="a11y-overview-card events-overview-card">
                    <div class="events-overview-value" data-events-stat="revenue"><?php echo events_format_currency((float) $totalRevenue); ?></div>
                    <div class="a11y-overview-label">Total Revenue</div>
                </div>
            </div>
        </header>

        <section class="events-section" aria-labelledby="eventsUpcomingTitle">
            <header class="events-section-header">
                <div>
                    <h3 class="events-section-title" id="eventsUpcomingTitle">Upcoming events</h3>
                    <p class="events-section-description">Track go-live dates, tickets sold, and revenue at a glance.</p>
                </div>
            </header>
            <div class="events-upcoming">
                <?php if (empty($upcoming)): ?>
                    <p class="events-empty">No upcoming events scheduled. Create one to get started.</p>
                <?php else: ?>
                    <ul class="events-upcoming-list" data-events-upcoming>
                        <?php foreach ($upcoming as $event):
                            $id = (string) ($event['id'] ?? '');
                            $metrics = $salesByEvent[$id] ?? ['tickets_sold' => 0, 'revenue' => 0];
                            $timestamp = isset($event['start']) ? strtotime((string) $event['start']) : false;
                            $dateLabel = $timestamp ? date('M j, Y g:i A', $timestamp) : 'Date TBD';
                        ?>
                        <li class="events-upcoming-item" data-event-id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="events-upcoming-primary">
                                <span class="events-upcoming-title"><?php echo htmlspecialchars($event['title'] ?? 'Untitled event', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="events-upcoming-date"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="events-upcoming-meta">
                                <span class="events-upcoming-stat" data-label="Tickets sold"><?php echo (int) ($metrics['tickets_sold'] ?? 0); ?></span>
                                <span class="events-upcoming-stat" data-label="Revenue"><?php echo events_format_currency((float) ($metrics['revenue'] ?? 0)); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="events-section" aria-labelledby="eventsManagementTitle">
            <header class="events-section-header">
                <div>
                    <h3 class="events-section-title" id="eventsManagementTitle">Event management</h3>
                    <p class="events-section-description">List view of every event, with quick actions for editing, ending, or reviewing sales.</p>
                </div>
                <div class="events-section-actions">
                    <label class="events-filter" for="eventsStatusFilter">
                        <span class="sr-only">Filter by status</span>
                        <select id="eventsStatusFilter" data-events-filter="status">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="ended">Ended</option>
                        </select>
                    </label>
                    <label class="events-search" for="eventsSearch">
                        <span class="sr-only">Search events</span>
                        <input type="search" id="eventsSearch" placeholder="Search events" data-events-filter="search">
                    </label>
                </div>
            </header>
            <div class="events-table-wrapper">
                <table class="data-table events-table">
                    <thead>
                        <tr>
                            <th scope="col">Event Name</th>
                            <th scope="col">Date &amp; Time</th>
                            <th scope="col">Venue</th>
                            <th scope="col">Tickets Sold</th>
                            <th scope="col">Revenue</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="is-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-events-table>
                        <tr>
                            <td colspan="7" class="events-empty">Loading events…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="events-section" aria-labelledby="eventsOrdersTitle">
            <header class="events-section-header">
                <div>
                    <h3 class="events-section-title" id="eventsOrdersTitle">Orders &amp; sales</h3>
                    <p class="events-section-description">Monitor purchases, refund status, and export reports for finance.</p>
                </div>
                <div class="events-section-actions">
                    <label class="events-filter">
                        <span class="sr-only">Filter by event</span>
                        <select data-events-orders-filter="event"></select>
                    </label>
                    <label class="events-filter">
                        <span class="sr-only">Filter by status</span>
                        <select data-events-orders-filter="status">
                            <option value="">All statuses</option>
                            <option value="paid">Paid</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </label>
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-events-export>
                        <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                        <span>Export CSV</span>
                    </button>
                </div>
            </header>
            <div class="events-table-wrapper">
                <table class="data-table events-table">
                    <thead>
                        <tr>
                            <th scope="col">Order ID</th>
                            <th scope="col">Buyer</th>
                            <th scope="col">Tickets</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Order Date</th>
                            <th scope="col">Checked In</th>
                        </tr>
                    </thead>
                    <tbody data-events-orders>
                        <tr>
                            <td colspan="7" class="events-empty">Loading orders…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="events-section" aria-labelledby="eventsReportsTitle">
            <header class="events-section-header">
                <div>
                    <h3 class="events-section-title" id="eventsReportsTitle">Reports</h3>
                    <p class="events-section-description">Download ticket sales, attendance, and revenue summaries whenever you need them.</p>
                </div>
                <div class="events-section-actions">
                    <button type="button" class="a11y-btn a11y-btn--secondary" data-events-report="tickets">Ticket sales report</button>
                    <button type="button" class="a11y-btn a11y-btn--secondary" data-events-report="attendance">Attendance report</button>
                    <button type="button" class="a11y-btn a11y-btn--secondary" data-events-report="revenue">Revenue report</button>
                </div>
            </header>
            <div class="events-reports" data-events-reports>
                <article class="events-report-card">
                    <h4>Ticket sales</h4>
                    <p>Download totals per event and ticket type to share with finance.</p>
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-events-report-download="tickets">Export CSV</button>
                </article>
                <article class="events-report-card">
                    <h4>Attendance</h4>
                    <p>Review how many guests checked in versus tickets sold across events.</p>
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-events-report-download="attendance">Export CSV</button>
                </article>
                <article class="events-report-card">
                    <h4>Revenue</h4>
                    <p>Share top-line revenue with leadership by event and ticket type.</p>
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-events-report-download="revenue">Export CSV</button>
                </article>
            </div>
            <div class="events-table-wrapper">
                <table class="data-table events-table events-reports-table">
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Tickets sold</th>
                            <th scope="col">Revenue</th>
                            <th scope="col">Checked in</th>
                            <th scope="col">Attendance rate</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody data-events-reports-table>
                        <tr>
                            <td colspan="6" class="events-empty">Loading report data…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div class="events-modal-backdrop" data-events-modal="event">
    <div class="events-modal" role="dialog" aria-modal="true" aria-labelledby="eventsModalTitle">
        <header class="events-modal-header">
            <h2 class="events-modal-title" id="eventsModalTitle">Create event</h2>
            <button type="button" class="events-modal-close" data-events-close>&times;<span class="sr-only">Close</span></button>
        </header>
        <div class="events-modal-body">
            <form data-events-form="event">
                <input type="hidden" name="id" value="">
                <div class="events-form-grid">
                    <label class="events-form-field">
                        <span>Title</span>
                        <input type="text" name="title" required>
                    </label>
                    <label class="events-form-field">
                        <span>Location / Venue</span>
                        <input type="text" name="location" placeholder="Venue or meeting link">
                    </label>
                    <div class="events-form-field span-2">
                        <span>Description</span>
                        <div class="events-editor-toolbar" role="toolbar" aria-label="Formatting">
                            <button type="button" data-editor-command="bold" aria-label="Bold"><i class="fa-solid fa-bold"></i></button>
                            <button type="button" data-editor-command="italic" aria-label="Italic"><i class="fa-solid fa-italic"></i></button>
                            <button type="button" data-editor-command="insertUnorderedList" aria-label="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
                        </div>
                        <div class="events-editor" contenteditable="true" data-events-editor></div>
                        <textarea name="description" class="sr-only" data-events-editor-target></textarea>
                    </div>
                    <label class="events-form-field">
                        <span>Start date &amp; time</span>
                        <input type="datetime-local" name="start" required>
                    </label>
                    <label class="events-form-field">
                        <span>End date &amp; time</span>
                        <input type="datetime-local" name="end">
                    </label>
                    <fieldset class="events-form-field">
                        <legend>Status</legend>
                        <label class="events-radio">
                            <input type="radio" name="status" value="draft" checked>
                            <span>Save as draft</span>
                        </label>
                        <label class="events-radio">
                            <input type="radio" name="status" value="published">
                            <span>Publish now</span>
                        </label>
                    </fieldset>
                    <label class="events-toggle">
                        <input type="checkbox" name="track_attendance">
                        <span>Track attendance for this event</span>
                    </label>
                </div>

                <section class="events-ticketing" aria-labelledby="eventsTicketsTitle">
                    <header class="events-ticketing-header">
                        <div>
                            <h3 id="eventsTicketsTitle">Ticket types</h3>
                            <p>Add multiple ticket tiers with pricing and availability.</p>
                        </div>
                        <button type="button" class="a11y-btn a11y-btn--secondary" data-events-ticket-add>
                            <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                            <span>Add ticket type</span>
                        </button>
                    </header>
                    <div class="events-ticket-list" data-events-tickets>
                        <div class="events-ticket-empty">No ticket types yet. Add one to begin selling.</div>
                    </div>
                </section>

                <footer class="events-form-actions">
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-events-close>Cancel</button>
                    <button type="submit" class="a11y-btn a11y-btn--primary">Save event</button>
                </footer>
            </form>
        </div>
    </div>
</div>

<div class="events-modal-backdrop" data-events-modal="confirm">
    <div class="events-modal events-modal-small" role="dialog" aria-modal="true" aria-labelledby="eventsConfirmTitle">
        <header class="events-modal-header">
            <h2 class="events-modal-title" id="eventsConfirmTitle">Confirm action</h2>
            <button type="button" class="events-modal-close" data-events-close>&times;<span class="sr-only">Close</span></button>
        </header>
        <div class="events-modal-body">
            <p data-events-confirm-message>Are you sure?</p>
        </div>
        <footer class="events-form-actions">
            <button type="button" class="a11y-btn a11y-btn--ghost" data-events-close>Cancel</button>
            <button type="button" class="a11y-btn a11y-btn--danger" data-events-confirm>Confirm</button>
        </footer>
    </div>
</div>

<div class="events-toast" role="status" aria-live="polite" hidden data-events-toast>
    <span data-events-toast-message>Saved</span>
</div>
