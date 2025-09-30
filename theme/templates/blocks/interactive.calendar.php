<!-- File: interactive.calendar.php -->
<!-- Template: interactive.calendar -->
<templateSetting caption="Calendar Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Display Style</dt>
        <dd>
            <select name="custom_layout">
                <option value="list" selected>Event list</option>
                <option value="cards">Event cards</option>
                <option value="compact">Compact list</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Events to Display</dt>
        <dd>
            <input type="number" name="custom_limit" value="6" min="1" max="50" />
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Category Filter</dt>
        <dd>
            <input type="text" name="custom_category" value="" placeholder="Leave blank for all categories" />
            <p class="mt-2 small text-muted">Match category names exactly to filter results.</p>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Show Descriptions</dt>
        <dd>
            <select name="custom_show_description">
                <option value="yes" selected>Show</option>
                <option value="no">Hide</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Show Categories</dt>
        <dd>
            <select name="custom_show_category">
                <option value="yes" selected>Show</option>
                <option value="no">Hide</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Empty State Message</dt>
        <dd>
            <input type="text" name="custom_empty" value="No upcoming events found." />
        </dd>
    </dl>
</templateSetting>
<div class="calendar-block calendar-block--layout-{custom_layout}" data-calendar-block data-calendar-layout="{custom_layout}" data-calendar-limit="{custom_limit}" data-calendar-category="{custom_category}" data-calendar-description="{custom_show_description}" data-calendar-show-category="{custom_show_category}" data-calendar-empty-message="{custom_empty}">
    <div class="calendar-block__items" data-calendar-items>
        <article class="calendar-block__item calendar-block__item--placeholder" aria-live="polite">
            <div class="calendar-block__date">
                <span class="calendar-block__date-month">--</span>
                <span class="calendar-block__date-day">--</span>
            </div>
            <div class="calendar-block__body">
                <h3 class="calendar-block__title">Loading events…</h3>
                <p class="calendar-block__description">Calendar events will appear here once published.</p>
            </div>
        </article>
    </div>
    <div class="calendar-block__empty d-none" data-calendar-empty>{custom_empty}</div>
</div>
