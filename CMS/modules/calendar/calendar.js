(function ($) {
    const container = $('#calendar');
    if (!container.length) {
        return;
    }

    const endpoints = {
        data: 'data/calendar_data.json',
        month: 'modules/calendar/calendar_backend.php',
        manage: 'modules/calendar/manage_data.php'
    };

    const timezone = 'America/Los_Angeles';
    let dayjsLib = null;

    const state = {
        today: null,
        currentMonth: null,
        events: [],
        upcoming: [],
        categories: [],
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
            window.location.href = endpoints.manage + '?new_event=1';
        });
        $('#calendarManageCategoriesBtn').on('click', function () {
            window.location.href = endpoints.manage + '#manageCategories';
        });
        $('body').on('click', '[data-calendar-close]', function () {
            closeModal($(this).closest('.calendar-modal'));
        });
        $('.calendar-modal').on('click', function (e) {
            if ($(e.target).is('.calendar-modal')) {
                closeModal($(this));
            }
        });
    }

    function fetchData() {
        const deferred = $.Deferred();
        $.getJSON(endpoints.data)
            .done(function (response) {
                state.categories = Array.isArray(response.categories) ? response.categories : [];
            })
            .fail(function () {
                state.categories = [];
                console.error('Failed to load calendar data file');
            })
            .always(function () {
                populateCategoryOptions();
                ensureCategorySelections();
                renderCategorySidebar();
                updateMetrics();
                deferred.resolve();
            });
        return deferred.promise();
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
                if (!response || response.status !== 'success') {
                    console.error(response && response.message ? response.message : 'Unable to load events');
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
            if (event.sourceId) {
                $('<a>', {
                    class: 'calendar-btn calendar-btn--ghost',
                    text: 'Manage event',
                    href: endpoints.manage + '?edit_event=' + encodeURIComponent(event.sourceId)
                }).appendTo(actions);
            }
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
        selectFilter.find('option:not(:first)').remove();
        state.categories.forEach(function (category) {
            const option = $('<option>', { value: category.name, text: category.name });
            selectFilter.append(option);
        });
    }

    function ensureCategorySelections() {
        if (!state.categories.length) {
            if (state.filters.category) {
                state.filters.category = '';
                $('#calendarCategoryFilter').val('');
            }
            return;
        }
        const validNames = state.categories.map(function (category) { return category.name; });
        if (state.filters.category && validNames.indexOf(state.filters.category) === -1) {
            state.filters.category = '';
            $('#calendarCategoryFilter').val('');
        }
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

        const editBtn = $('#calendarEventDetailEditBtn');
        const deleteBtn = $('#calendarEventDetailDeleteBtn');
        editBtn.off('click');
        deleteBtn.off('click');

        if (event.sourceId) {
            editBtn.prop('hidden', false).on('click', function () {
                window.location.href = endpoints.manage + '?edit_event=' + encodeURIComponent(event.sourceId);
            });
            deleteBtn.prop('hidden', false).on('click', function () {
                if (confirm('Delete this event?')) {
                    window.location.href = endpoints.manage + '?action=delete_event&evt_id=' + encodeURIComponent(event.sourceId);
                }
            });
        } else {
            editBtn.prop('hidden', true);
            deleteBtn.prop('hidden', true);
        }

        openModal($('#calendarEventDetailModal'));
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
