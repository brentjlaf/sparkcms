<!-- File: interactive.calendar.php -->
<!-- Template: interactive.calendar -->
<?php $calendarId = uniqid('calendar-'); ?>
<templateSetting caption="Calendar Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Eyebrow</dt>
        <dd>
            <input type="text" class="form-control" name="custom_eyebrow" value="Marketing Calendar">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Headline</dt>
        <dd>
            <input type="text" class="form-control" name="custom_title" value="Events &amp; Campaign Calendar">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Subheadline</dt>
        <dd>
            <textarea class="form-control" name="custom_subtitle" rows="3">Plan launches, keep teams aligned, and share upcoming milestones with your audience.</textarea>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Default Timezone</dt>
        <dd>
            <input type="text" class="form-control" name="custom_timezone" value="America/Los_Angeles">
            <small class="form-text text-muted">Used for formatting event dates on the front end.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Initial View</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_initial_view" value="grid" checked> Calendar</label>
            <label><input type="radio" name="custom_initial_view" value="list"> List</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Search &amp; Filter?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_filters" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_filters" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Upcoming List?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_upcoming" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_upcoming" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Categories?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_categories" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_categories" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Empty State Message</dt>
        <dd>
            <input type="text" class="form-control" name="custom_empty" value="No events scheduled for this period.">
        </dd>
    </dl>
</templateSetting>
<section id="<?= $calendarId ?>" class="calendar-block" data-tpl-tooltip="Calendar">
    <div class="calendar-widget" data-calendar-widget data-calendar-timezone="{custom_timezone}" data-calendar-initial-view="{custom_initial_view}" data-calendar-empty="{custom_empty}">
        <div class="calendar-dashboard">
            <header class="calendar-hero">
                <div class="calendar-hero__content">
                    <p class="calendar-hero__eyebrow text-uppercase" data-editable>{custom_eyebrow}</p>
                    <h2 class="calendar-hero__title" data-editable>{custom_title}</h2>
                    <p class="calendar-hero__subtitle" data-editable>{custom_subtitle}</p>
                </div>
                <dl class="calendar-hero__metrics">
                    <div>
                        <dt>Events this month</dt>
                        <dd data-calendar-metric="month">0</dd>
                    </div>
                    <div>
                        <dt>Recurring series</dt>
                        <dd data-calendar-metric="recurring">0</dd>
                    </div>
                    <div>
                        <dt>Categories</dt>
                        <dd data-calendar-metric="categories">0</dd>
                    </div>
                    <div>
                        <dt>Last updated</dt>
                        <dd data-calendar-metric="updated">Just now</dd>
                    </div>
                </dl>
            </header>
            <div class="calendar-controls" aria-label="Calendar controls">
                <div class="calendar-controls__primary">
                    <div class="calendar-month-nav" role="group" aria-label="Month navigation">
                        <button type="button" class="calendar-icon-btn" data-calendar-nav="prev" aria-label="Previous month">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="calendar-month-label" data-calendar-month-label>Month YYYY</div>
                        <button type="button" class="calendar-icon-btn" data-calendar-nav="next" aria-label="Next month">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="calendar-view-toggle" role="group" aria-label="View mode">
                        <button type="button" class="calendar-toggle-btn" data-calendar-view="grid">Calendar</button>
                        <button type="button" class="calendar-toggle-btn" data-calendar-view="list">List</button>
                    </div>
                </div>
                <toggle rel="custom_show_filters" value="yes">
                    <div class="calendar-controls__filters">
                        <label class="calendar-search" for="<?= $calendarId ?>-search">
                            <span class="visually-hidden">Search events</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="<?= $calendarId ?>-search" placeholder="Search events, launches, campaigns" aria-label="Search events" data-calendar-search>
                        </label>
                        <label class="calendar-filter" for="<?= $calendarId ?>-filter">
                            <span class="visually-hidden">Filter by category</span>
                            <select id="<?= $calendarId ?>-filter" data-calendar-category-filter>
                                <option value="">All categories</option>
                            </select>
                        </label>
                    </div>
                </toggle>
            </div>
            <div class="calendar-layout">
                <section class="calendar-view" data-calendar-view-panel="grid" aria-label="Calendar view">
                    <div class="calendar-weekdays" aria-hidden="true">
                        <span>Sun</span>
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                    </div>
                    <div class="calendar-grid" data-calendar-grid role="grid" aria-live="polite"></div>
                </section>
                <section class="calendar-list" data-calendar-view-panel="list" aria-label="List view" hidden>
                    <header class="calendar-list__header">
                        <h3>Timeline</h3>
                        <p>Every event across categories in chronological order.</p>
                    </header>
                    <div class="calendar-list__container" data-calendar-list></div>
                    <div class="calendar-empty d-none" data-calendar-list-empty>{custom_empty}</div>
                </section>
                <aside class="calendar-sidebar">
                    <toggle rel="custom_show_upcoming" value="yes">
                        <section class="calendar-upcoming" data-calendar-upcoming-section>
                            <header>
                                <h3>Next up</h3>
                                <p>Upcoming milestones over the next 30 days.</p>
                            </header>
                            <ul class="calendar-upcoming__list" data-calendar-upcoming></ul>
                        </section>
                    </toggle>
                    <toggle rel="custom_show_categories" value="yes">
                        <section class="calendar-categories" data-calendar-categories-section>
                            <header>
                                <h3>Categories</h3>
                                <p>Color-coded tags applied to calendar entries.</p>
                            </header>
                            <ul class="calendar-category-list" data-calendar-category-list></ul>
                        </section>
                    </toggle>
                </aside>
            </div>
        </div>
    </div>
    <div class="calendar-modal" data-calendar-modal="detail" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?= $calendarId ?>-modal-title">
        <div class="calendar-modal__content" role="document" tabindex="-1">
            <header class="calendar-modal__header">
                <h2 id="<?= $calendarId ?>-modal-title" data-calendar-modal-title>Event</h2>
                <button type="button" class="calendar-modal__close" data-calendar-modal-close>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="calendar-modal__body">
                <p class="calendar-event__meta" data-calendar-modal-time></p>
                <p class="calendar-event__category" data-calendar-modal-category></p>
                <p data-calendar-modal-description></p>
            </div>
        </div>
    </div>
</section>
