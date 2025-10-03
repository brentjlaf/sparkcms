<?php
// File: modules/events/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/helpers.php';

require_login();

$service = events_service();

$initialPayload = $service->getInitialPayload();
$events = $initialPayload['events'];
$salesByEvent = $initialPayload['sales'];
$categories = $initialPayload['categories'];
$orderSummaries = $initialPayload['orders'];

$totalEvents = count($events);
$totalTicketsSold = array_sum(array_column($salesByEvent, 'tickets_sold'));
$totalRevenue = array_sum(array_column($salesByEvent, 'revenue'));
$upcoming = $service->getUpcomingEvents();
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
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-events-open="categories">
                        <i class="fa-solid fa-tags" aria-hidden="true"></i>
                        <span>Manage Categories</span>
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
                    <div class="events-overview-value" data-events-stat="revenue"><?php echo $service->formatCurrency((float) $totalRevenue); ?></div>
                    <div class="a11y-overview-label">Total Revenue</div>
                </div>
            </div>
        </header>

        <nav class="events-tabs" aria-label="Events sections" data-events-tabs>
            <button type="button" class="events-tab is-active" id="eventsTabUpcoming" data-events-tab="upcoming" role="tab" aria-selected="true" aria-controls="eventsPanelUpcoming">Upcoming events</button>
            <button type="button" class="events-tab" id="eventsTabManagement" data-events-tab="management" role="tab" aria-selected="false" aria-controls="eventsPanelManagement">Event management</button>
            <button type="button" class="events-tab" id="eventsTabOrders" data-events-tab="orders" role="tab" aria-selected="false" aria-controls="eventsPanelOrders">Orders &amp; sales</button>
            <button type="button" class="events-tab" id="eventsTabReports" data-events-tab="reports" role="tab" aria-selected="false" aria-controls="eventsPanelReports">Reports</button>
        </nav>

        <section class="events-section events-tabpanel is-active" id="eventsPanelUpcoming" role="tabpanel" aria-labelledby="eventsTabUpcoming" data-events-panel="upcoming">
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
                                <span class="events-upcoming-stat" data-label="Revenue"><?php echo $service->formatCurrency((float) ($metrics['revenue'] ?? 0)); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="events-section events-tabpanel" id="eventsPanelManagement" role="tabpanel" aria-labelledby="eventsTabManagement" data-events-panel="management">
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

        <section class="events-section events-tabpanel" id="eventsPanelOrders" role="tabpanel" aria-labelledby="eventsTabOrders" data-events-panel="orders">
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
                            <option value="pending">Pending</option>
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
                            <th scope="col">Event</th>
                            <th scope="col">Buyer</th>
                            <th scope="col">Tickets</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Order Date</th>
                            <th scope="col" class="is-actions">Manage</th>
                        </tr>
                    </thead>
                    <tbody data-events-orders>
                        <tr>
                            <td colspan="8" class="events-empty">Loading orders…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="events-section events-tabpanel" id="eventsPanelReports" role="tabpanel" aria-labelledby="eventsTabReports" data-events-panel="reports">
            <header class="events-section-header">
                <div>
                    <h3 class="events-section-title" id="eventsReportsTitle">Reports</h3>
                    <p class="events-section-description">Track topline revenue, surface insights, and export data for your team.</p>
                </div>
            </header>
            <div class="events-report-metrics" data-events-report-metrics>
                <article class="events-report-metric" data-events-report-metric="revenue">
                    <span class="events-report-metric-label">Total revenue</span>
                    <span class="events-report-metric-value" data-value>$0.00</span>
                    <p class="events-report-metric-meta" data-meta>Revenue across all paid orders.</p>
                </article>
                <article class="events-report-metric" data-events-report-metric="average_order">
                    <span class="events-report-metric-label">Average order value</span>
                    <span class="events-report-metric-value" data-value>$0.00</span>
                    <p class="events-report-metric-meta" data-meta>Calculated from paid orders.</p>
                </article>
                <article class="events-report-metric" data-events-report-metric="refunds">
                    <span class="events-report-metric-label">Refunded</span>
                    <span class="events-report-metric-value" data-value>$0.00</span>
                    <p class="events-report-metric-meta" data-meta>Value of refunded orders.</p>
                </article>
            </div>
            <div class="events-insights-grid" data-events-insights>
                <article class="events-insight-card" data-insight="top-event">
                    <h4 class="events-insight-title">Top event</h4>
                    <div class="events-insight-value" data-insight-value>—</div>
                    <p class="events-insight-meta" data-insight-meta>Revenue leader across all events.</p>
                </article>
                <article class="events-insight-card" data-insight="top-ticket">
                    <h4 class="events-insight-title">Best-selling ticket</h4>
                    <div class="events-insight-value" data-insight-value>—</div>
                    <p class="events-insight-meta" data-insight-meta>Highest quantity ticket type sold.</p>
                </article>
                <article class="events-insight-card" data-insight="top-buyer">
                    <h4 class="events-insight-title">Top customer</h4>
                    <div class="events-insight-value" data-insight-value>—</div>
                    <p class="events-insight-meta" data-insight-meta>Most revenue generated by a single buyer.</p>
                </article>
            </div>
            <div class="events-download-grid" data-events-downloads>
                <article class="events-download-card" data-events-download="tickets">
                    <div class="events-download-body">
                        <h4>Ticket sales export</h4>
                        <p>Detailed ticket-level breakdown for finance and operations.</p>
                    </div>
                    <button type="button" class="a11y-btn a11y-btn--ghost events-download-action" data-events-report-download="tickets">Download CSV</button>
                </article>
                <article class="events-download-card" data-events-download="revenue">
                    <div class="events-download-body">
                        <h4>Revenue summary export</h4>
                        <p>Share net revenue by event with stakeholders in seconds.</p>
                    </div>
                    <button type="button" class="a11y-btn a11y-btn--ghost events-download-action" data-events-report-download="revenue">Download CSV</button>
                </article>
            </div>
            <div class="events-table-wrapper">
                <table class="data-table events-table events-reports-table">
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Tickets sold</th>
                            <th scope="col">Revenue</th>
                            <th scope="col">Refunded</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody data-events-reports-table>
                        <tr>
                            <td colspan="5" class="events-empty">Loading report data…</td>
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
                    <div class="events-form-field">
                        <span>Featured image</span>
                        <div class="events-image-picker" data-events-image-picker>
                            <input type="hidden" name="image" value="">
                            <div class="events-image-preview" data-events-image-preview aria-live="polite">
                                <span class="events-image-placeholder">No image selected yet.</span>
                            </div>
                            <div class="events-image-actions">
                                <button type="button" class="a11y-btn a11y-btn--secondary" data-events-image-open>
                                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                                    <span>Choose image</span>
                                </button>
                                <button type="button" class="a11y-btn a11y-btn--ghost" data-events-image-clear hidden>
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                    <span>Remove image</span>
                                </button>
                            </div>
                        </div>
                    </div>
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
                    <fieldset class="events-form-field span-2">
                        <legend>Categories</legend>
                        <p class="events-form-help">Tag this event to improve filtering and reporting.</p>
                        <div class="events-category-options" data-events-category-options>
                            <p class="events-category-empty">No categories yet. Manage categories to create one.</p>
                        </div>
                        <button type="button" class="a11y-btn a11y-btn--ghost events-category-manage-btn" data-events-open="categories">
                            <i class="fa-solid fa-tags" aria-hidden="true"></i>
                            <span>Manage categories</span>
                        </button>
                    </fieldset>
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

<div class="events-modal-backdrop" data-events-modal="order">
    <div class="events-modal events-order-modal" role="dialog" aria-modal="true" aria-labelledby="eventsOrderTitle">
        <header class="events-modal-header">
            <h2 class="events-modal-title" id="eventsOrderTitle">Manage order</h2>
            <button type="button" class="events-modal-close" data-events-close>&times;<span class="sr-only">Close</span></button>
        </header>
        <div class="events-modal-body">
            <form data-events-form="order">
                <input type="hidden" name="id" value="">
                <input type="hidden" name="event_id" value="">
                <div class="events-order-layout">
                    <div class="events-order-details">
                        <div class="events-order-meta">
                            <div class="events-order-meta-primary">
                                <h3 class="events-order-meta-title" data-events-order-title>Order</h3>
                                <p class="events-order-meta-event" data-events-order-event></p>
                            </div>
                            <label class="events-order-status">
                                <span>Status</span>
                                <select name="status" data-events-order-status>
                                    <option value="paid">Paid</option>
                                    <option value="pending">Pending</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </label>
                        </div>
                        <div class="events-form-grid events-order-form-grid">
                            <label class="events-form-field">
                                <span>Buyer name</span>
                                <input type="text" name="buyer_name" required>
                            </label>
                            <label class="events-form-field">
                                <span>Order date</span>
                                <input type="datetime-local" name="ordered_at">
                            </label>
                        </div>
                        <div class="events-order-add">
                            <label class="events-order-add-select">
                                <span class="sr-only">Select ticket type</span>
                                <select data-events-order-add-select></select>
                            </label>
                            <button type="button" class="a11y-btn a11y-btn--secondary" data-events-order-add>
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                <span>Add ticket</span>
                            </button>
                        </div>
                        <div class="events-order-lines" data-events-order-lines>
                            <p class="events-order-empty">No tickets on this order yet.</p>
                        </div>
                    </div>
                    <aside class="events-order-summary" data-events-order-summary>
                        <h3 class="events-order-summary-title">Order summary</h3>
                        <dl class="events-order-summary-stats">
                            <div class="events-order-summary-item">
                                <dt>Subtotal</dt>
                                <dd data-order-total="subtotal">$0.00</dd>
                            </div>
                            <div class="events-order-summary-item">
                                <dt>Refunds</dt>
                                <dd data-order-total="refunds">$0.00</dd>
                            </div>
                            <div class="events-order-summary-item">
                                <dt>Net total</dt>
                                <dd data-order-total="net">$0.00</dd>
                            </div>
                        </dl>
                        <div class="events-order-breakdown" data-events-order-breakdown>
                            <p class="events-order-empty">Ticket breakdown will appear here.</p>
                        </div>
                    </aside>
                </div>
                <footer class="events-form-actions events-order-actions">
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-events-close>Cancel</button>
                    <button type="submit" class="a11y-btn a11y-btn--primary">Save order</button>
                </footer>
            </form>
        </div>
    </div>
</div>

<div class="events-modal-backdrop" data-events-modal="categories">
    <div class="events-modal" role="dialog" aria-modal="true" aria-labelledby="eventsCategoriesTitle">
        <header class="events-modal-header">
            <h2 class="events-modal-title" id="eventsCategoriesTitle">Manage categories</h2>
            <button type="button" class="events-modal-close" data-events-close>&times;<span class="sr-only">Close</span></button>
        </header>
        <div class="events-modal-body">
            <div class="events-category-manage">
                <form class="events-category-form" data-events-form="category" aria-labelledby="eventsCategoriesFormTitle">
                    <h3 class="events-category-form-title" id="eventsCategoriesFormTitle" data-events-category-form-title>Create category</h3>
                    <input type="hidden" name="id" value="">
                    <div class="events-form-grid">
                        <label class="events-form-field">
                            <span>Name</span>
                            <input type="text" name="name" required>
                        </label>
                        <label class="events-form-field">
                            <span>Slug</span>
                            <input type="text" name="slug" placeholder="Auto-generated if left blank">
                        </label>
                    </div>
                    <p class="events-category-hint">Slugs are used in URLs and filters. Leave blank to auto-generate.</p>
                    <div class="events-form-actions events-category-actions">
                        <button type="button" class="a11y-btn a11y-btn--ghost" data-events-category-reset>Clear</button>
                        <button type="submit" class="a11y-btn a11y-btn--primary" data-events-category-submit>Save category</button>
                    </div>
                </form>
                <div class="events-category-table">
                    <table class="data-table events-table events-categories-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Updated</th>
                                <th scope="col" class="is-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody data-events-categories-list>
                            <tr>
                                <td colspan="4" class="events-empty">No categories yet. Create one above.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
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

<div class="events-modal-backdrop" data-events-modal="media">
    <div class="events-modal events-media-modal" role="dialog" aria-modal="true" aria-labelledby="eventsMediaModalTitle">
        <header class="events-modal-header">
            <h2 class="events-modal-title" id="eventsMediaModalTitle">Select featured image</h2>
            <button type="button" class="events-modal-close" data-events-close>&times;<span class="sr-only">Close</span></button>
        </header>
        <div class="events-modal-body">
            <div class="events-media-picker">
                <label class="events-media-search">
                    <span class="sr-only">Search media library</span>
                    <input type="search" placeholder="Search media" data-events-media-search>
                </label>
                <div class="events-media-grid" data-events-media-grid role="listbox" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>

<div class="events-toast" role="status" aria-live="polite" hidden data-events-toast>
    <span data-events-toast-message>Saved</span>
</div>
