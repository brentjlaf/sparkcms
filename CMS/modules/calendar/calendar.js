(function ($) {
    const container = $('#calendar');
    if (!container.length) {
        return;
    }

    const endpoints = {
        manage: 'modules/calendar/manage_data.php',
        month: 'modules/calendar/calendar_backend.php'
    };

    const timezone = 'America/Los_Angeles';
    let dayjsLib = null;

    const state = {
        today: null,
        currentMonth: null,
        events: [],
        upcoming: [],
        categories: [],
        rawEvents: [],
        filters: {
            search: '',
            category: ''
        }
    };

    function bootstrap() {
        if (!window.dayjs) {
            setTimeout(bootstrap, 50);
            return;
        }
        dayjsLib = window.dayjs;
        state.today = dayjsLib.tz ? dayjsLib().tz(timezone) : dayjsLib();
        state.currentMonth = state.today.startOf('month');
        bindEvents();
        fetchData().then(function () {
            updateMonthLabel();
            refreshMonth();
        });
    }

    function bindEvents() {
        $('#calendarPrevMonth').on('click', function () {
            state.currentMonth = state.currentMonth.subtract(1, 'month');
            refreshMonth();
        });
        $('#calendarNextMonth').on('click', function () {
            state.currentMonth = state.currentMonth.add(1, 'month');
            refreshMonth();
        });
        $('.calendar-toggle-btn').on('click', function () {
            const view = $(this).data('view');
            $('.calendar-toggle-btn').removeClass('active');
            $(this).addClass('active');
            if (view === 'list') {
                $('#calendarGridView').attr('hidden', true);
                $('#calendarListView').removeAttr('hidden');
            } else {
                $('#calendarListView').attr('hidden', true);
                $('#calendarGridView').removeAttr('hidden');
            }
        });
        $('#calendarSearch').on('input', debounce(function () {
            state.filters.search = $(this).val().trim();
            refreshMonth();
        }, 250));
        $('#calendarCategoryFilter').on('change', function () {
            state.filters.category = $(this).val();
            refreshMonth();
        });
        $('#calendarNewEventBtn').on('click', function () {
            openEventForm();
        });
        $('#calendarManageCategoriesBtn').on('click', function () {
            openCategoryModal();
        });
        $('body').on('click', '[data-calendar-close]', function () {
            closeModal($(this).closest('.calendar-modal'));
        });
        $('.calendar-modal').on('click', function (e) {
            if ($(e.target).is('.calendar-modal')) {
                closeModal($(this));
            }
        });
        $('#calendarEventForm').on('submit', handleEventSave);
        $('#calendarDeleteEventBtn').on('click', handleEventDelete);
        $('#calendarCategoryForm').on('submit', handleCategorySave);
    }

    function fetchData() {
        return $.getJSON(endpoints.manage, { action: 'fetch' })
            .done(function (response) {
                if (!response || response.status !== 'success') {
                    console.error(response && response.message ? response.message : 'Unable to load calendar data');
                    return;
                }
                state.categories = response.categories || [];
                state.rawEvents = response.events || [];
                populateCategoryOptions();
                renderCategorySidebar();
                updateMetrics();
            })
            .fail(function () {
                console.error('Failed to load calendar data');
            });
    }

    function refreshMonth() {
        updateMonthLabel();
        const params = {
            month: state.currentMonth.month() + 1,
            year: state.currentMonth.year()
        };
        if (state.filters.search) {
            params.search = state.filters.search;
        }
        if (state.filters.category) {
            params.category = state.filters.category;
        }
        $.getJSON(endpoints.month, params)
            .done(function (response) {
                if (response.status !== 'success') {
                    console.error(response.message || 'Unable to load events');
                    return;
                }
                state.events = response.events || [];
                state.upcoming = response.upcoming || buildUpcoming(state.events);
                renderCalendar();
                renderList();
                renderUpcoming();
                updateMetrics(response.meta);
            })
            .fail(function () {
                console.error('Unable to fetch month events');
            });
    }

    function updateMonthLabel() {
        $('#calendarCurrentMonth').text(state.currentMonth.format('MMMM YYYY'));
    }

    function renderCalendar() {
        const grid = $('#calendarGrid');
        grid.empty();
        const firstDayOfMonth = state.currentMonth.startOf('month');
        const daysInMonth = state.currentMonth.daysInMonth();
        const startWeekday = firstDayOfMonth.day();
        const today = state.today.startOf('day');

        for (let i = 0; i < startWeekday; i++) {
            grid.append('<div class="calendar-cell calendar-cell--empty" role="presentation"></div>');
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const date = firstDayOfMonth.date(day);
            const cell = $('<div>', {
                class: 'calendar-cell',
                role: 'gridcell',
                'data-date': date.format('YYYY-MM-DD')
            });
            const header = $('<div>', { class: 'calendar-cell__header' });
            const label = $('<span>', { text: day, class: 'calendar-cell__day' });
            if (date.isSame(today, 'day')) {
                cell.addClass('calendar-cell--today');
                label.attr('aria-label', 'Today');
            }
            header.append(label);
            cell.append(header);

            const eventsForDay = state.events.filter(function (event) {
                return dayjsLib(event.start).isSame(date, 'day');
            });

            const eventList = $('<div>', { class: 'calendar-cell__events' });
            eventsForDay.forEach(function (event) {
                const eventItem = $('<button>', {
                    type: 'button',
                    class: 'calendar-event-chip',
                    text: event.title,
                    css: {
                        backgroundColor: event.category && event.category.color ? event.category.color : '#2563eb'
                    }
                }).on('click', function () {
                    openEventDetail(event);
                });
                eventList.append(eventItem);
            });

            cell.append(eventList);
            grid.append(cell);
        }
    }

    function renderList() {
        const list = $('#calendarList');
        list.empty();
        if (!state.events.length) {
            list.append('<p class="calendar-empty">No events scheduled for this period.</p>');
            return;
        }
        const sorted = state.events.slice().sort(function (a, b) {
            return dayjsLib(a.start).diff(dayjsLib(b.start));
        });
        sorted.forEach(function (event) {
            const item = $('<article>', { class: 'calendar-list__item' });
            const header = $('<header>', { class: 'calendar-list__item-header' });
            const title = $('<h4>', { text: event.title });
            if (event.category) {
                const badge = $('<span>', {
                    class: 'calendar-category-badge',
                    text: event.category.name || 'Category'
                }).css('background-color', event.category.color || '#2563eb');
                header.append(badge);
            }
            header.append(title);
            item.append(header);
            const meta = $('<p>', {
                class: 'calendar-event__meta',
                text: formatEventRange(event)
            });
            item.append(meta);
            if (event.description) {
                item.append($('<p>', { text: event.description }));
            }
            const actions = $('<div>', { class: 'calendar-list__actions' });
            $('<button>', {
                type: 'button',
                class: 'calendar-btn calendar-btn--ghost',
                text: 'View details'
            }).on('click', function () {
                openEventDetail(event);
            }).appendTo(actions);
            $('<button>', {
                type: 'button',
                class: 'calendar-btn calendar-btn--ghost',
                text: 'Edit'
            }).on('click', function () {
                openEventForm(event.sourceId);
            }).appendTo(actions);
            item.append(actions);
            list.append(item);
        });
    }

    function renderUpcoming() {
        const container = $('#calendarUpcomingList');
        container.empty();
        if (!state.upcoming.length) {
            container.append('<li class="calendar-empty">No upcoming events</li>');
            return;
        }
        state.upcoming.slice(0, 6).forEach(function (event) {
            const item = $('<li>');
            const title = $('<div>', { class: 'calendar-upcoming__title', text: event.title });
            const date = $('<div>', { class: 'calendar-upcoming__date', text: formatEventRange(event) });
            if (event.category) {
                const dot = $('<span>', { class: 'calendar-upcoming__dot' }).css('background-color', event.category.color || '#2563eb');
                title.prepend(dot);
            }
            item.append(title, date);
            item.on('click', function () {
                openEventDetail(event);
            });
            container.append(item);
        });
    }

    function renderCategorySidebar() {
        const list = $('#calendarCategoryList');
        list.empty();
        if (!state.categories.length) {
            list.append('<li class="calendar-empty">No categories yet</li>');
            return;
        }
        state.categories.forEach(function (category) {
            const item = $('<li>');
            const marker = $('<span>', { class: 'calendar-category__marker' }).css('background-color', category.color || '#2563eb');
            item.append(marker).append($('<span>', { text: category.name }));
            list.append(item);
        });
    }

    function populateCategoryOptions() {
        const selectFilter = $('#calendarCategoryFilter');
        const selectForm = $('#calendarEventCategory');
        selectFilter.find('option:not(:first)').remove();
        selectForm.find('option:not(:first)').remove();
        state.categories.forEach(function (category) {
            const option = $('<option>', { value: category.id, text: category.name });
            selectFilter.append(option.clone());
            selectForm.append(option.clone());
        });
    }

    function formatEventRange(event) {
        const start = dayjsLib(event.start);
        const end = dayjsLib(event.end);
        if (event.allDay) {
            if (start.isSame(end, 'day')) {
                return start.format('dddd, MMMM D');
            }
            return start.format('MMM D') + ' – ' + end.format('MMM D');
        }
        if (start.isSame(end, 'day')) {
            return start.format('MMM D, h:mm A') + ' – ' + end.format('h:mm A');
        }
        return start.format('MMM D, h:mm A') + ' – ' + end.format('MMM D, h:mm A');
    }

    function openEventDetail(event) {
        $('#calendarEventDetailTitle').text(event.title);
        $('#calendarEventDetailDescription').text(event.description || '');
        $('#calendarEventDetailTime').text(formatEventRange(event));
        if (event.category) {
            const category = $('<span>', {
                class: 'calendar-category-badge',
                text: event.category.name
            }).css('background-color', event.category.color || '#2563eb');
            $('#calendarEventDetailCategory').empty().append(category);
        } else {
            $('#calendarEventDetailCategory').text('No category');
        }
        $('#calendarEventDetailGoogle').attr('href', buildGoogleCalendarLink(event));
        openModal($('#calendarEventDetailModal'));
    }

    function openEventForm(eventId) {
        const formElement = $('#calendarEventForm')[0];
        formElement.reset();
        $('#calendarEventId').val('');
        $('#calendarEventRecurrenceInterval').val('1');
        $('#calendarDeleteEventBtn').prop('hidden', true).removeData('eventId');
        $('#calendarEventFormTitle').text(eventId ? 'Edit event' : 'New event');

        if (eventId) {
            const event = state.rawEvents.find(function (item) { return item.id === eventId; });
            if (event) {
                $('#calendarEventId').val(event.id);
                $('#calendarEventTitle').val(event.title);
                $('#calendarEventDescription').val(event.description || '');
                $('#calendarEventStartDate').val(event.start_date);
                $('#calendarEventStartTime').val(event.start_time || '');
                $('#calendarEventEndDate').val(event.end_date);
                $('#calendarEventEndTime').val(event.end_time || '');
                $('#calendarEventAllDay').prop('checked', !!event.all_day);
                $('#calendarEventCategory').val(event.category_id || '');
                $('#calendarEventRecurrenceType').val(event.recurrence && event.recurrence.type ? event.recurrence.type : 'none');
                $('#calendarEventRecurrenceInterval').val(event.recurrence && event.recurrence.interval ? event.recurrence.interval : 1);
                $('#calendarEventRecurrenceEnd').val(event.recurrence && event.recurrence.end_date ? event.recurrence.end_date : '');
                $('#calendarDeleteEventBtn').prop('hidden', false).data('eventId', event.id);
            }
        }
        openModal($('#calendarEventFormModal'));
    }

    function handleEventSave(e) {
        e.preventDefault();
        const form = $(this);
        const data = form.serialize();
        $.ajax({
            url: endpoints.manage,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (response) {
            if (response.status !== 'success') {
                alert(response.message || 'Unable to save event');
                return;
            }
            closeModal($('#calendarEventFormModal'));
            fetchData().then(refreshMonth);
        }).fail(function () {
            alert('Unable to save event.');
        });
    }

    function handleEventDelete() {
        const eventId = $(this).data('eventId');
        if (!eventId) {
            return;
        }
        if (!confirm('Delete this event?')) {
            return;
        }
        $.ajax({
            url: endpoints.manage,
            method: 'POST',
            data: { action: 'delete_event', id: eventId },
            dataType: 'json'
        }).done(function (response) {
            if (response.status !== 'success') {
                alert(response.message || 'Unable to delete event');
                return;
            }
            closeModal($('#calendarEventFormModal'));
            fetchData().then(refreshMonth);
        }).fail(function () {
            alert('Unable to delete event.');
        });
    }

    function openCategoryModal() {
        $('#calendarCategoryForm')[0].reset();
        $('#calendarCategoryId').val('');
        renderCategoryManager();
        openModal($('#calendarCategoryModal'));
    }

    function renderCategoryManager() {
        const list = $('#calendarCategoryManagerList');
        list.empty();
        if (!state.categories.length) {
            list.append('<li class="calendar-empty">No categories created yet.</li>');
            return;
        }
        state.categories.forEach(function (category) {
            const item = $('<li>', { class: 'calendar-category-manager__item' });
            $('<span>', { class: 'calendar-category__marker' }).css('background-color', category.color || '#2563eb').appendTo(item);
            $('<span>', { class: 'calendar-category-manager__name', text: category.name }).appendTo(item);
            const actions = $('<div>', { class: 'calendar-category-manager__actions' });
            $('<button>', { type: 'button', text: 'Edit', class: 'calendar-btn calendar-btn--ghost' })
                .on('click', function () {
                    $('#calendarCategoryId').val(category.id);
                    $('#calendarCategoryName').val(category.name);
                    $('#calendarCategoryColor').val(category.color || '#2563eb');
                }).appendTo(actions);
            $('<button>', { type: 'button', text: 'Delete', class: 'calendar-btn calendar-btn--ghost' })
                .on('click', function () {
                    if (!confirm('Delete this category? Events will keep their color but lose this label.')) {
                        return;
                    }
                    $.ajax({
                        url: endpoints.manage,
                        method: 'POST',
                        data: { action: 'delete_category', id: category.id },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.status !== 'success') {
                            alert(response.message || 'Unable to delete category');
                            return;
                        }
                        fetchData().then(function () {
                            renderCategoryManager();
                            refreshMonth();
                        });
                    }).fail(function () {
                        alert('Unable to delete category.');
                    });
                }).appendTo(actions);
            item.append(actions);
            list.append(item);
        });
    }

    function handleCategorySave(e) {
        e.preventDefault();
        const data = $(this).serialize();
        $.ajax({
            url: endpoints.manage,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (response) {
            if (response.status !== 'success') {
                alert(response.message || 'Unable to save category');
                return;
            }
            $('#calendarCategoryId').val('');
            $('#calendarCategoryName').val('');
            fetchData().then(function () {
                renderCategoryManager();
                refreshMonth();
            });
        }).fail(function () {
            alert('Unable to save category.');
        });
    }

    function buildUpcoming(events) {
        const today = state.today.startOf('day');
        return events.filter(function (event) {
            const start = dayjsLib(event.start);
            return start.isAfter(today) || start.isSame(today, 'day');
        }).sort(function (a, b) {
            return dayjsLib(a.start).diff(dayjsLib(b.start));
        });
    }

    function openModal(modal) {
        modal.attr('aria-hidden', 'false').addClass('open');
    }

    function closeModal(modal) {
        modal.attr('aria-hidden', 'true').removeClass('open');
    }

    function updateMetrics(meta) {
        if (meta) {
            $('#calendarMetricMonth').text(meta.eventsThisMonth || 0);
            $('#calendarMetricCampaigns').text(meta.recurringSeries || 0);
            $('#calendarMetricCategories').text(meta.categories ?? state.categories.length ?? 0);
            $('#calendarMetricUpdated').text(meta.lastUpdated || dayjsLib().format('MMM D, YYYY h:mm A'));
        } else {
            $('#calendarMetricCategories').text(state.categories.length);
        }
    }

    function buildGoogleCalendarLink(event) {
        const format = 'YYYYMMDD[T]HHmmss';
        const start = event.allDay
            ? dayjsLib(event.start).format('YYYYMMDD')
            : dayjsLib(event.start).tz ? dayjsLib(event.start).tz(timezone).format(format) : dayjsLib(event.start).format(format);
        const end = event.allDay
            ? dayjsLib(event.end).add(1, 'day').format('YYYYMMDD')
            : dayjsLib(event.end).tz ? dayjsLib(event.end).tz(timezone).format(format) : dayjsLib(event.end).format(format);
        const text = encodeURIComponent(event.title || 'Event');
        const details = encodeURIComponent(event.description || '');
        return `https://calendar.google.com/calendar/r/eventedit?text=${text}&details=${details}&dates=${start}/${end}&ctz=${encodeURIComponent(timezone)}`;
    }

    function debounce(fn, wait) {
        let timeout;
        return function () {
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(null, args);
            }, wait);
        };
    }

    bootstrap();
})(jQuery);
