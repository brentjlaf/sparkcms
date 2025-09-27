<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

date_default_timezone_set('America/Los_Angeles');
?>
<div class="content-section" id="calendar">
    <div class="calendar-dashboard" data-timezone="America/Los_Angeles">
        <header class="calendar-hero">
            <div class="calendar-hero__content">
                <div>
                    <h2 class="calendar-hero__title">Events &amp; Campaign Calendar</h2>
                    <p class="calendar-hero__subtitle">Plan launches, keep teams aligned, and give stakeholders a single source of truth for every upcoming milestone.</p>
                </div>
                <div class="calendar-hero__actions">
                    <button type="button" class="calendar-btn calendar-btn--ghost" id="calendarManageCategoriesBtn">
                        <i class="fa-solid fa-palette" aria-hidden="true"></i>
                        <span>Manage categories</span>
                    </button>
                    <button type="button" class="calendar-btn calendar-btn--primary" id="calendarNewEventBtn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New event</span>
                    </button>
                </div>
            </div>
            <dl class="calendar-hero__metrics">
                <div>
                    <dt>Events this month</dt>
                    <dd id="calendarMetricMonth">0</dd>
                </div>
                <div>
                    <dt>Scheduled campaigns</dt>
                    <dd id="calendarMetricCampaigns">0</dd>
                </div>
                <div>
                    <dt>Categories</dt>
                    <dd id="calendarMetricCategories">0</dd>
                </div>
                <div>
                    <dt>Last updated</dt>
                    <dd id="calendarMetricUpdated">Just now</dd>
                </div>
            </dl>
        </header>

        <section class="calendar-controls" aria-label="Calendar controls">
            <div class="calendar-controls__primary">
                <div class="calendar-month-nav" role="group" aria-label="Month navigation">
                    <button type="button" id="calendarPrevMonth" class="calendar-icon-btn" aria-label="Previous month">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <div class="calendar-month-label" id="calendarCurrentMonth">Month YYYY</div>
                    <button type="button" id="calendarNextMonth" class="calendar-icon-btn" aria-label="Next month">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="calendar-view-toggle" role="group" aria-label="View mode">
                    <button type="button" class="calendar-toggle-btn active" data-view="grid">Calendar</button>
                    <button type="button" class="calendar-toggle-btn" data-view="list">List</button>
                </div>
            </div>
            <div class="calendar-controls__filters">
                <label class="calendar-search" for="calendarSearch">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="calendarSearch" placeholder="Search events, launches, campaigns" aria-label="Search events">
                </label>
                <label class="calendar-filter">
                    <span>Category</span>
                    <select id="calendarCategoryFilter">
                        <option value="">All categories</option>
                    </select>
                </label>
            </div>
        </section>

        <div class="calendar-layout">
            <section class="calendar-view" id="calendarGridView" aria-label="Calendar view">
                <div class="calendar-weekdays" role="row">
                    <span role="columnheader">Sun</span>
                    <span role="columnheader">Mon</span>
                    <span role="columnheader">Tue</span>
                    <span role="columnheader">Wed</span>
                    <span role="columnheader">Thu</span>
                    <span role="columnheader">Fri</span>
                    <span role="columnheader">Sat</span>
                </div>
                <div class="calendar-grid" id="calendarGrid" role="grid" aria-live="polite"></div>
            </section>

            <section class="calendar-list" id="calendarListView" aria-label="List view" hidden>
                <header class="calendar-list__header">
                    <h3>Timeline</h3>
                    <p>Every event across categories in chronological order.</p>
                </header>
                <div class="calendar-list__container" id="calendarList"></div>
            </section>
        </div>

        <aside class="calendar-sidebar">
            <section class="calendar-upcoming">
                <header>
                    <h3>Next up</h3>
                    <p>Upcoming milestones over the next 30 days.</p>
                </header>
                <ul id="calendarUpcomingList" class="calendar-upcoming__list"></ul>
            </section>
            <section class="calendar-categories">
                <header>
                    <h3>Categories</h3>
                    <p>Color-coded tags applied to calendar entries.</p>
                </header>
                <ul id="calendarCategoryList"></ul>
            </section>
        </aside>
    </div>
</div>

<div class="calendar-modal" id="calendarEventDetailModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="calendar-modal__content">
        <header class="calendar-modal__header">
            <h2 id="calendarEventDetailTitle">Event</h2>
            <button type="button" class="calendar-modal__close" data-calendar-close>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <div class="calendar-modal__body">
            <p class="calendar-event__meta" id="calendarEventDetailTime"></p>
            <p class="calendar-event__category" id="calendarEventDetailCategory"></p>
            <p id="calendarEventDetailDescription"></p>
            <a href="#" target="_blank" rel="noopener" id="calendarEventDetailGoogle" class="calendar-btn calendar-btn--ghost">
                <i class="fa-brands fa-google" aria-hidden="true"></i>
                <span>Add to Google Calendar</span>
            </a>
        </div>
        <div class="calendar-modal__actions" id="calendarEventDetailActions">
            <button type="button" class="calendar-btn calendar-btn--ghost" data-calendar-close>Close</button>
            <button type="button" class="calendar-btn calendar-btn--ghost" id="calendarEventDetailEditBtn">Edit</button>
            <button type="button" class="calendar-btn calendar-btn--danger" id="calendarEventDetailDeleteBtn">Delete</button>
        </div>
    </div>
</div>

<div class="calendar-modal" id="calendarEventFormModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="calendar-modal__content">
        <header class="calendar-modal__header">
            <h2 id="calendarEventFormTitle">New event</h2>
            <button type="button" class="calendar-modal__close" data-calendar-close>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <form id="calendarEventForm" class="calendar-form">
            <input type="hidden" name="action" value="save_event">
            <input type="hidden" name="id" id="calendarEventId">
            <div class="calendar-form__group">
                <label for="calendarEventTitle">Title *</label>
                <input type="text" id="calendarEventTitle" name="title" required>
            </div>
            <div class="calendar-form__group">
                <label for="calendarEventDescription">Description</label>
                <textarea id="calendarEventDescription" name="description" rows="4"></textarea>
            </div>
            <div class="calendar-form__row">
                <div class="calendar-form__group">
                    <label for="calendarEventStartDate">Start date *</label>
                    <input type="date" id="calendarEventStartDate" name="start_date" required>
                </div>
                <div class="calendar-form__group">
                    <label for="calendarEventStartTime">Start time</label>
                    <input type="time" id="calendarEventStartTime" name="start_time">
                </div>
            </div>
            <div class="calendar-form__row">
                <div class="calendar-form__group">
                    <label for="calendarEventEndDate">End date *</label>
                    <input type="date" id="calendarEventEndDate" name="end_date" required>
                </div>
                <div class="calendar-form__group">
                    <label for="calendarEventEndTime">End time</label>
                    <input type="time" id="calendarEventEndTime" name="end_time">
                </div>
            </div>
            <div class="calendar-form__group calendar-form__checkbox">
                <input type="checkbox" id="calendarEventAllDay" name="all_day" value="1">
                <label for="calendarEventAllDay">All day event</label>
            </div>
            <div class="calendar-form__group">
                <label for="calendarEventCategory">Category</label>
                <select id="calendarEventCategory" name="category_id">
                    <option value="">No category</option>
                </select>
            </div>
            <fieldset class="calendar-form__fieldset">
                <legend>Recurrence</legend>
                <div class="calendar-form__row">
                    <div class="calendar-form__group">
                        <label for="calendarEventRecurrenceType">Pattern</label>
                        <select id="calendarEventRecurrenceType" name="recurrence_type">
                            <option value="none">Does not repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="calendar-form__group">
                        <label for="calendarEventRecurrenceInterval">Interval</label>
                        <input type="number" min="1" value="1" id="calendarEventRecurrenceInterval" name="recurrence_interval">
                    </div>
                </div>
                <div class="calendar-form__group">
                    <label for="calendarEventRecurrenceEnd">Ends</label>
                    <input type="date" id="calendarEventRecurrenceEnd" name="recurrence_end_date">
                    <p class="calendar-form__help">Leave blank for no end date.</p>
                </div>
            </fieldset>
            <div class="calendar-form__actions">
                <button type="button" class="calendar-btn calendar-btn--danger" id="calendarDeleteEventBtn" hidden>Delete</button>
                <button type="button" class="calendar-btn calendar-btn--ghost" data-calendar-close>Cancel</button>
                <button type="submit" class="calendar-btn calendar-btn--primary">Save event</button>
            </div>
        </form>
    </div>
</div>

<div class="calendar-modal" id="calendarCategoryModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="calendar-modal__content">
        <header class="calendar-modal__header">
            <h2>Manage categories</h2>
            <button type="button" class="calendar-modal__close" data-calendar-close>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <div class="calendar-modal__body">
            <form id="calendarCategoryForm" class="calendar-form calendar-form--compact">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="id" id="calendarCategoryId">
                <div class="calendar-form__row">
                    <div class="calendar-form__group">
                        <label for="calendarCategoryName">Name *</label>
                        <input type="text" id="calendarCategoryName" name="name" required>
                    </div>
                    <div class="calendar-form__group">
                        <label for="calendarCategoryColor">Color</label>
                        <input type="color" id="calendarCategoryColor" name="color" value="#2563eb">
                    </div>
                </div>
                <div class="calendar-form__actions">
                    <button type="submit" class="calendar-btn calendar-btn--primary">Save category</button>
                </div>
            </form>
            <ul id="calendarCategoryManagerList" class="calendar-category-manager"></ul>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" integrity="sha512-5mP4QjPRqCMgfHdCqkfNNpJBWlAbIYW/W2PASi6DPd7OJbRRqtD9h5pz50jdK5Zk90unfG0KBPXn1HULICwhfA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/dayjs.min.js" integrity="sha512-6wZveHFkNjEcNWef39MF4R2tQeM+c/fi91tIbp/BK+f5pFwchAuC8W9jAq56k97X3CJidb8sRP/6IDdnh3oX2w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/plugin/utc.min.js" integrity="sha512-7DcKJ2YMBX1IzBgE4c3OJbGdv8ImZ/Sc7VcRbP5t6dv3Vv4Y20N6l0e6QF6UVx/FuWRzE7dDzIvmhjYQdkWXug==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/plugin/timezone.min.js" integrity="sha512-rP0iDL49myyQeF1P0uohsEiL41Z8nfdXbra+XUl3Bd6mVTRu6YQPGVE3d2rYZRk5v0zzakDx4zY/boYYGr2susg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
if (window.dayjs) {
    dayjs.extend(window.dayjs_plugin_utc);
    dayjs.extend(window.dayjs_plugin_timezone);
    dayjs.tz.setDefault('America/Los_Angeles');
}
</script>
