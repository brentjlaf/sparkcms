// File: modules/calendar/calendar.js
(function () {
  const root = document.getElementById('calendarModule');
  if (!root) {
    return;
  }

  const state = {
    events: [],
    categories: [],
    metrics: null,
  };

  const initial = window.sparkCalendarInitial || {};
  if (Array.isArray(initial.events)) {
    state.events = initial.events.slice();
  }
  if (Array.isArray(initial.categories)) {
    state.categories = initial.categories.slice();
  }
  if (initial.metrics && typeof initial.metrics === 'object') {
    state.metrics = initial.metrics;
  }

  const messageBox = root.querySelector('[data-calendar-message]');
  const eventsTableBody = root.querySelector('[data-calendar-events]');
  const categoriesTableBody = root.querySelector('[data-calendar-categories]');
  const eventForm = root.querySelector('[data-calendar-form="event"]');
  const categoryForm = root.querySelector('[data-calendar-form="category"]');
  const categorySelect = root.querySelector('#calendarEventCategory');
  const eventModal = root.querySelector('[data-calendar-modal="event"]');
  const categoriesModal = root.querySelector('[data-calendar-modal="categories"]');
  const confirmModal = root.querySelector('[data-calendar-modal="confirm"]');
  const confirmTitle = confirmModal
    ? confirmModal.querySelector('[data-calendar-confirm-title]')
    : null;
  const confirmMessage = confirmModal
    ? confirmModal.querySelector('[data-calendar-confirm-message]')
    : null;
  const confirmAcceptButton = confirmModal
    ? confirmModal.querySelector('[data-calendar-confirm-accept]')
    : null;
  const eventModalTitle = root.querySelector('#calendarEventModalTitle');
  const heroNextEvent = root.querySelector('[data-calendar-next-event]');
  const heroStatElements = {
    total: root.querySelector('[data-calendar-stat="total"]'),
    upcoming: root.querySelector('[data-calendar-stat="upcoming"]'),
    recurring: root.querySelector('[data-calendar-stat="recurring"]'),
    categories: root.querySelector('[data-calendar-stat="categories"]'),
  };

  const recurrenceLabels = {
    none: 'None',
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    yearly: 'Yearly',
  };

  let confirmConfig = null;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatDisplayDate(value) {
    if (!value) {
      return '<span class="calendar-muted">&mdash;</span>';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return escapeHtml(value);
    }
    return escapeHtml(
      date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
      })
    );
  }

  function formatReadableDate(value) {
    if (!value) {
      return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }
    return date.toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  }

  function toDateTimeLocal(value) {
    if (!value) {
      return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }
    const pad = (num) => String(num).padStart(2, '0');
    return (
      date.getFullYear() +
      '-' +
      pad(date.getMonth() + 1) +
      '-' +
      pad(date.getDate()) +
      'T' +
      pad(date.getHours()) +
      ':' +
      pad(date.getMinutes())
    );
  }

  const dateTimePickers = new Map();
  let activeDateTimePicker = null;

  const calendarWeekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const hourOptions = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'));
  const minuteOptions = Array.from({ length: 60 }, (_, index) => String(index).padStart(2, '0'));
  const dateTimeDisplayFormatter = new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });

  function parseDateTimeLocal(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return null;
    }
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
    if (!match) {
      return null;
    }
    const year = Number(match[1]);
    const month = Number(match[2]) - 1;
    const day = Number(match[3]);
    const hour = Number(match[4]);
    const minute = Number(match[5]);
    const date = new Date(year, month, day, hour, minute, 0, 0);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return date;
  }

  function formatDateTimeLocal(date) {
    const pad = (num) => String(num).padStart(2, '0');
    return (
      date.getFullYear() +
      '-' +
      pad(date.getMonth() + 1) +
      '-' +
      pad(date.getDate()) +
      'T' +
      pad(date.getHours()) +
      ':' +
      pad(date.getMinutes())
    );
  }

  function normalizeMinute(minute) {
    if (!Number.isFinite(minute)) {
      return 0;
    }
    if (minute < 0) {
      return 0;
    }
    if (minute > 59) {
      return 59;
    }
    return Math.round(minute);
  }

  function closeActiveDateTimePicker(except) {
    if (activeDateTimePicker && activeDateTimePicker !== except) {
      activeDateTimePicker.close();
    }
  }

  function createDateTimePicker(element) {
    const hiddenInput = element.querySelector('[data-calendar-datetime-input]');
    const toggle = element.querySelector('[data-calendar-datetime-toggle]');
    const valueLabel = element.querySelector('[data-calendar-datetime-value]');
    const helper = element.querySelector('[data-calendar-datetime-helper]');
    const placeholder = valueLabel ? valueLabel.textContent : 'Select date & time';
    const isRequired = element.hasAttribute('data-calendar-datetime-required');
    const defaultHour = 9;
    const defaultMinute = 0;

    if (!hiddenInput || !toggle || !valueLabel) {
      return null;
    }

    const panel = document.createElement('div');
    panel.className = 'calendar-datetime-panel';
    panel.setAttribute('data-calendar-datetime-panel', '');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'false');
    if (toggle.id) {
      panel.setAttribute('aria-labelledby', toggle.id);
    }
    panel.setAttribute('hidden', '');

    const weekdayMarkup = calendarWeekdays.map((day) => `<span>${day}</span>`).join('');
    const hourMarkup = hourOptions
      .map((hour) => `<option value="${hour}">${hour}</option>`)
      .join('');
    const minuteMarkup = minuteOptions
      .map((minute) => `<option value="${minute}">${minute}</option>`)
      .join('');

    panel.innerHTML = `
      <div class="calendar-datetime-panel-header">
        <button type="button" class="calendar-datetime-nav" data-calendar-datetime-prev aria-label="Previous month">&#x2039;</button>
        <div class="calendar-datetime-month" data-calendar-datetime-month></div>
        <button type="button" class="calendar-datetime-nav" data-calendar-datetime-next aria-label="Next month">&#x203A;</button>
      </div>
      <div class="calendar-datetime-weekdays">${weekdayMarkup}</div>
      <div class="calendar-datetime-grid" data-calendar-datetime-grid></div>
      <div class="calendar-datetime-time">
        <div class="calendar-datetime-time-heading">Time</div>
        <div class="calendar-datetime-time-controls">
          <select data-calendar-datetime-hour aria-label="Hour">${hourMarkup}</select>
          <select data-calendar-datetime-minute aria-label="Minute">${minuteMarkup}</select>
        </div>
      </div>
      <div class="calendar-datetime-panel-footer">
        <button type="button" class="calendar-datetime-btn calendar-datetime-btn--ghost" data-calendar-datetime-today>Today</button>
        <div class="calendar-datetime-footer-actions">
          <button type="button" class="calendar-datetime-btn calendar-datetime-btn--ghost" data-calendar-datetime-clear>Clear</button>
          <button type="button" class="calendar-datetime-btn calendar-datetime-btn--primary" data-calendar-datetime-apply>Done</button>
        </div>
      </div>
    `;

    element.insertBefore(panel, helper || null);

    let selectedDate = null;
    let currentMonth = new Date();

    const monthLabel = panel.querySelector('[data-calendar-datetime-month]');
    const grid = panel.querySelector('[data-calendar-datetime-grid]');
    const prevButton = panel.querySelector('[data-calendar-datetime-prev]');
    const nextButton = panel.querySelector('[data-calendar-datetime-next]');
    const hourSelect = panel.querySelector('[data-calendar-datetime-hour]');
    const minuteSelect = panel.querySelector('[data-calendar-datetime-minute]');
    const todayButton = panel.querySelector('[data-calendar-datetime-today]');
    const clearButton = panel.querySelector('[data-calendar-datetime-clear]');
    const applyButton = panel.querySelector('[data-calendar-datetime-apply]');

    function clearError() {
      element.classList.remove('is-invalid');
      if (helper) {
        helper.textContent = '';
      }
    }

    function showError(message) {
      element.classList.add('is-invalid');
      if (helper) {
        helper.textContent = message;
      }
    }

    function updateDisplay() {
      if (!valueLabel) {
        return;
      }
      if (selectedDate) {
        valueLabel.textContent = dateTimeDisplayFormatter.format(selectedDate);
      } else {
        valueLabel.textContent = placeholder;
      }
      element.classList.toggle('has-value', Boolean(selectedDate));
    }

    function updateHiddenValue() {
      if (!hiddenInput) {
        return;
      }
      if (selectedDate) {
        const normalized = new Date(
          selectedDate.getFullYear(),
          selectedDate.getMonth(),
          selectedDate.getDate(),
          selectedDate.getHours(),
          selectedDate.getMinutes(),
          0,
          0
        );
        hiddenInput.value = formatDateTimeLocal(normalized);
        clearError();
      } else {
        hiddenInput.value = '';
      }
      updateDisplay();
    }

    function updateTimeSelects() {
      if (!hourSelect || !minuteSelect) {
        return;
      }
      const hourValue = selectedDate ? selectedDate.getHours() : defaultHour;
      const minuteValue = selectedDate ? selectedDate.getMinutes() : defaultMinute;
      hourSelect.value = String(hourValue).padStart(2, '0');
      const normalizedMinute = normalizeMinute(minuteValue);
      minuteSelect.value = String(normalizedMinute).padStart(2, '0');
      if (selectedDate) {
        selectedDate.setHours(hourValue, normalizedMinute, 0, 0);
      }
    }

    function renderCalendar() {
      if (!grid || !monthLabel) {
        return;
      }
      const displayMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
      monthLabel.textContent = displayMonth.toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
      });

      const startDate = new Date(displayMonth.getFullYear(), displayMonth.getMonth(), 1 - displayMonth.getDay());
      const today = new Date();
      const selectedTime = selectedDate ? selectedDate.getTime() : null;
      grid.innerHTML = '';

      for (let index = 0; index < 42; index += 1) {
        const cellDate = new Date(
          startDate.getFullYear(),
          startDate.getMonth(),
          startDate.getDate() + index,
          0,
          0,
          0,
          0
        );
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'calendar-datetime-cell';
        button.textContent = String(cellDate.getDate());

        if (cellDate.getMonth() !== displayMonth.getMonth()) {
          button.classList.add('is-outside');
        }

        if (
          today.getFullYear() === cellDate.getFullYear() &&
          today.getMonth() === cellDate.getMonth() &&
          today.getDate() === cellDate.getDate()
        ) {
          button.classList.add('is-today');
        }

        if (selectedTime) {
          const selectedDateOnly = new Date(selectedTime);
          if (
            selectedDateOnly.getFullYear() === cellDate.getFullYear() &&
            selectedDateOnly.getMonth() === cellDate.getMonth() &&
            selectedDateOnly.getDate() === cellDate.getDate()
          ) {
            button.classList.add('is-selected');
          }
        }

        button.addEventListener('click', () => {
          const hourValue = Number(hourSelect.value || defaultHour);
          const minuteValue = Number(minuteSelect.value || defaultMinute);
          selectedDate = new Date(
            cellDate.getFullYear(),
            cellDate.getMonth(),
            cellDate.getDate(),
            hourValue,
            minuteValue,
            0,
            0
          );
          currentMonth = new Date(cellDate.getFullYear(), cellDate.getMonth(), 1);
          updateHiddenValue();
          renderCalendar();
        });

        grid.appendChild(button);
      }
    }

    function openPanel() {
      closeActiveDateTimePicker(api);
      panel.removeAttribute('hidden');
      element.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      activeDateTimePicker = api;
    }

    function closePanel() {
      panel.setAttribute('hidden', '');
      element.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      if (activeDateTimePicker === api) {
        activeDateTimePicker = null;
      }
    }

    function handleTimeChange() {
      const hourValue = Number(hourSelect.value || defaultHour);
      const minuteValue = Number(minuteSelect.value || defaultMinute);
      const alignedMinute = normalizeMinute(minuteValue);
      minuteSelect.value = String(alignedMinute).padStart(2, '0');
      if (selectedDate) {
        selectedDate.setHours(hourValue, alignedMinute, 0, 0);
      } else {
        const reference = currentMonth ? new Date(currentMonth) : new Date();
        selectedDate = new Date(
          reference.getFullYear(),
          reference.getMonth(),
          reference.getDate(),
          hourValue,
          alignedMinute,
          0,
          0
        );
      }
      currentMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
      updateHiddenValue();
      renderCalendar();
    }

    if (prevButton) {
      prevButton.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
        renderCalendar();
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
        renderCalendar();
      });
    }

    if (todayButton) {
      todayButton.addEventListener('click', () => {
        const now = new Date();
        const alignedMinute = normalizeMinute(now.getMinutes());
        hourSelect.value = String(now.getHours()).padStart(2, '0');
        minuteSelect.value = String(alignedMinute).padStart(2, '0');
        selectedDate = new Date(
          now.getFullYear(),
          now.getMonth(),
          now.getDate(),
          now.getHours(),
          alignedMinute,
          0,
          0
        );
        currentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        updateHiddenValue();
        renderCalendar();
      });
    }

    if (clearButton) {
      clearButton.addEventListener('click', () => {
        selectedDate = null;
        currentMonth = new Date();
        updateTimeSelects();
        updateHiddenValue();
        renderCalendar();
        clearError();
      });
    }

    if (applyButton) {
      applyButton.addEventListener('click', () => {
        closePanel();
      });
    }

    hourSelect.addEventListener('change', handleTimeChange);
    minuteSelect.addEventListener('change', handleTimeChange);

    toggle.addEventListener('click', () => {
      clearError();
      if (panel.hasAttribute('hidden')) {
        openPanel();
      } else {
        closePanel();
      }
    });

    function setValue(rawValue) {
      const parsed = parseDateTimeLocal(rawValue);
      if (parsed) {
        const alignedMinute = normalizeMinute(parsed.getMinutes());
        parsed.setSeconds(0, 0);
        parsed.setMinutes(alignedMinute);
        selectedDate = parsed;
        currentMonth = new Date(parsed.getFullYear(), parsed.getMonth(), 1);
      } else {
        selectedDate = null;
        currentMonth = new Date();
      }
      updateTimeSelects();
      updateHiddenValue();
      renderCalendar();
      clearError();
    }

    function getValue() {
      return hiddenInput.value || '';
    }

    function validate() {
      if (!isRequired) {
        clearError();
        return true;
      }
      if (hiddenInput.value) {
        clearError();
        return true;
      }
      showError('Please select a date and time.');
      return false;
    }

    function focus() {
      toggle.focus();
    }

    const api = {
      required: isRequired,
      open: openPanel,
      close: closePanel,
      contains: (node) => element.contains(node),
      setValue,
      getValue,
      validate,
      clearError,
      focus,
    };

    setValue(hiddenInput.value || '');

    return api;
  }

  function initializeDateTimePickers() {
    if (!eventForm) {
      return;
    }
    const pickerElements = eventForm.querySelectorAll('[data-calendar-datetime]');
    pickerElements.forEach((element) => {
      const hiddenInput = element.querySelector('[data-calendar-datetime-input]');
      if (!hiddenInput) {
        return;
      }
      const name = hiddenInput.getAttribute('name');
      const picker = createDateTimePicker(element);
      if (name && picker) {
        dateTimePickers.set(name, picker);
      }
    });
  }

  document.addEventListener('click', (event) => {
    if (!activeDateTimePicker) {
      return;
    }
    const target = event.target;
    if (!(target instanceof Node)) {
      return;
    }
    if (!activeDateTimePicker.contains(target)) {
      activeDateTimePicker.close();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && activeDateTimePicker) {
      activeDateTimePicker.close();
    }
  });

  function computeMetrics(events, categories) {
    const now = Date.now();
    let upcomingCount = 0;
    let recurringCount = 0;
    let nextEvent = null;

    events.forEach((event) => {
      const startValue = event && event.start_date ? Date.parse(event.start_date) : Number.NaN;
      if (Number.isNaN(startValue)) {
        return;
      }

      if (event && event.recurring_interval && event.recurring_interval !== 'none') {
        recurringCount += 1;
      }

      if (startValue >= now) {
        upcomingCount += 1;
        const eventId = Number.isFinite(Number(event.id)) ? Number(event.id) : 0;
        if (
          !nextEvent ||
          startValue < nextEvent.timestamp ||
          (startValue === nextEvent.timestamp && eventId < nextEvent.id)
        ) {
          nextEvent = {
            id: eventId,
            title: event && event.title ? String(event.title) : '',
            start_date: new Date(startValue).toISOString(),
            timestamp: startValue,
          };
        }
      }
    });

    return {
      total_events: events.length,
      upcoming_count: upcomingCount,
      recurring_count: recurringCount,
      category_count: categories.length,
      next_event: nextEvent,
    };
  }

  function normalizeMetrics(metrics) {
    const fallback = computeMetrics(state.events, state.categories);
    if (!metrics || typeof metrics !== 'object') {
      return fallback;
    }
    return {
      total_events:
        typeof metrics.total_events === 'number' ? metrics.total_events : fallback.total_events,
      upcoming_count:
        typeof metrics.upcoming_count === 'number' ? metrics.upcoming_count : fallback.upcoming_count,
      recurring_count:
        typeof metrics.recurring_count === 'number' ? metrics.recurring_count : fallback.recurring_count,
      category_count:
        typeof metrics.category_count === 'number' ? metrics.category_count : fallback.category_count,
      next_event:
        metrics.next_event && typeof metrics.next_event === 'object'
          ? metrics.next_event
          : fallback.next_event,
    };
  }

  function renderHeroStats() {
    if (!heroNextEvent && !heroStatElements.total) {
      return;
    }

    state.metrics = normalizeMetrics(state.metrics);
    const metrics = state.metrics;

    if (heroStatElements.total) {
      heroStatElements.total.textContent = String(metrics.total_events || 0);
    }
    if (heroStatElements.upcoming) {
      heroStatElements.upcoming.textContent = String(metrics.upcoming_count || 0);
    }
    if (heroStatElements.recurring) {
      heroStatElements.recurring.textContent = String(metrics.recurring_count || 0);
    }
    if (heroStatElements.categories) {
      heroStatElements.categories.textContent = String(metrics.category_count || 0);
    }

    if (heroNextEvent) {
      if (metrics.next_event) {
        const nextTitle = metrics.next_event.title || 'Untitled event';
        const formattedDate = formatReadableDate(metrics.next_event.start_date);
        heroNextEvent.textContent = formattedDate
          ? `Next event: ${nextTitle} • ${formattedDate}`
          : `Next event: ${nextTitle}`;
      } else {
        heroNextEvent.textContent = 'Next event: none scheduled';
      }
    }
  }

  function setMessage(message, type) {
    if (!messageBox) {
      return;
    }
    if (!message) {
      messageBox.classList.remove('is-visible');
      messageBox.textContent = '';
      return;
    }
    messageBox.dataset.type = type || 'info';
    messageBox.textContent = message;
    messageBox.classList.add('is-visible');
  }

  if (initial.message) {
    setMessage(initial.message, 'info');
  }

  if (!state.metrics) {
    state.metrics = computeMetrics(state.events, state.categories);
  }

  function getCategoryMeta(name) {
    if (!name) {
      return null;
    }
    return state.categories.find((category) => category.name === name) || null;
  }

  function renderCategoryOptions() {
    if (!categorySelect) {
      return;
    }
    const options = ['<option value="">(None)</option>'];
    state.categories.forEach((category) => {
      options.push(
        '<option value="' +
          escapeHtml(category.name) +
          '">' +
          escapeHtml(category.name) +
          '</option>'
      );
    });
    categorySelect.innerHTML = options.join('');
  }

  function renderEvents() {
    if (!eventsTableBody) {
      return;
    }

    if (!Array.isArray(state.events) || state.events.length === 0) {
      eventsTableBody.innerHTML =
        '<tr><td colspan="7" class="calendar-empty">No events found.</td></tr>';
      return;
    }

    const rows = state.events
      .slice()
      .sort((a, b) => {
        const aTime = Date.parse(a.start_date || '') || 0;
        const bTime = Date.parse(b.start_date || '') || 0;
        if (aTime === bTime) {
          return (a.id || 0) - (b.id || 0);
        }
        return aTime - bTime;
      })
      .map((event) => {
        const recurrence = recurrenceLabels[event.recurring_interval] || 'None';
        const categoryMeta = getCategoryMeta(event.category);
        const categoryLabel = event.category
          ? '<span class="calendar-badge" style="--calendar-badge-color:' +
            escapeHtml((categoryMeta && categoryMeta.color) || '#4338ca') +
            '">' +
            escapeHtml(event.category) +
            '</span>'
          : '<span class="calendar-muted">None</span>';
        return (
          '<tr data-event-id="' +
          String(event.id || '') +
          '">' +
          '<td>' + escapeHtml(event.id || '') + '</td>' +
          '<td>' + escapeHtml(event.title || '') + '</td>' +
          '<td>' + formatDisplayDate(event.start_date) + '</td>' +
          '<td>' + formatDisplayDate(event.end_date) + '</td>' +
          '<td>' + categoryLabel + '</td>' +
          '<td class="calendar-recurring">' + escapeHtml(recurrence) + '</td>' +
          '<td>' +
          '<div class="calendar-table-actions">' +
          '<button type="button" class="calendar-edit-btn" data-calendar-action="edit" data-event-id="' +
          String(event.id || '') +
          '">Edit</button>' +
          '<button type="button" class="calendar-delete-btn" data-calendar-action="delete" data-event-id="' +
          String(event.id || '') +
          '">Delete</button>' +
          '</div>' +
          '</td>' +
          '</tr>'
        );
      })
      .join('');

    eventsTableBody.innerHTML = rows;
  }

  function renderCategories() {
    if (!categoriesTableBody) {
      return;
    }

    if (!Array.isArray(state.categories) || state.categories.length === 0) {
      categoriesTableBody.innerHTML =
        '<tr><td colspan="4" class="calendar-empty">No categories yet.</td></tr>';
    } else {
      categoriesTableBody.innerHTML = state.categories
        .slice()
        .sort((a, b) => {
          const nameA = (a.name || '').toLowerCase();
          const nameB = (b.name || '').toLowerCase();
          if (nameA < nameB) return -1;
          if (nameA > nameB) return 1;
          return (a.id || 0) - (b.id || 0);
        })
        .map((category) => {
          const color = category.color || '#ffffff';
          return (
            '<tr>' +
            '<td>' + escapeHtml(category.id || '') + '</td>' +
            '<td>' + escapeHtml(category.name || '') + '</td>' +
            '<td>' +
            '<span class="calendar-category-color" style="--calendar-category-color:' +
            escapeHtml(color) +
            ';">' +
            escapeHtml(color) +
            '</span>' +
            '</td>' +
            '<td>' +
            '<button type="button" class="calendar-delete-btn" data-calendar-action="delete-category" data-category-id="' +
            String(category.id || '') +
            '">Delete</button>' +
            '</td>' +
            '</tr>'
          );
        })
        .join('');
    }

    renderCategoryOptions();
  }

  function openModal(modal, titleText) {
    if (!modal) {
      return;
    }
    if (titleText && modal === eventModal && eventModalTitle) {
      eventModalTitle.textContent = titleText;
    }
    modal.classList.add('show');
    document.body.classList.add('calendar-modal-open');
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }
    modal.classList.remove('show');
    if (modal === confirmModal) {
      confirmConfig = null;
      if (confirmAcceptButton) {
        confirmAcceptButton.disabled = false;
        confirmAcceptButton.removeAttribute('data-loading');
        confirmAcceptButton.classList.remove('calendar-confirm-btn--danger');
      }
    }
    if (!root.querySelector('.calendar-modal-backdrop.show')) {
      document.body.classList.remove('calendar-modal-open');
    }
  }

  function openConfirmModal(config) {
    if (!confirmModal || !confirmAcceptButton) {
      return;
    }
    confirmConfig = config || {};
    if (confirmTitle) {
      confirmTitle.textContent = confirmConfig.title || 'Confirm action';
    }
    if (confirmMessage) {
      confirmMessage.textContent = confirmConfig.message || '';
    }
    confirmAcceptButton.textContent = confirmConfig.confirmLabel || 'Confirm';
    confirmAcceptButton.classList.remove('calendar-confirm-btn--danger');
    if (confirmConfig.confirmTone === 'danger') {
      confirmAcceptButton.classList.add('calendar-confirm-btn--danger');
    }
    confirmAcceptButton.disabled = false;
    confirmAcceptButton.removeAttribute('data-loading');
    openModal(confirmModal);
    confirmAcceptButton.focus();
  }

  function closeConfirmModal() {
    if (!confirmModal) {
      return;
    }
    closeModal(confirmModal);
  }

  function resetEventForm() {
    if (!eventForm) {
      return;
    }
    eventForm.reset();
    dateTimePickers.forEach((picker) => {
      picker.setValue('');
    });
    const hiddenId = eventForm.querySelector('input[name="evt_id"]');
    if (hiddenId) {
      hiddenId.value = '';
    }
  }

  function populateEventForm(eventId) {
    if (!eventForm) {
      return;
    }
    resetEventForm();
    if (!eventId) {
      return;
    }
    const event = state.events.find((item) => String(item.id) === String(eventId));
    if (!event) {
      return;
    }
    const hiddenId = eventForm.querySelector('input[name="evt_id"]');
    if (hiddenId) {
      hiddenId.value = event.id;
    }
    const titleInput = eventForm.querySelector('input[name="title"]');
    if (titleInput) {
      titleInput.value = event.title || '';
    }
    if (categorySelect) {
      categorySelect.value = event.category || '';
    }
    const startPicker = dateTimePickers.get('start_date');
    if (startPicker) {
      startPicker.setValue(toDateTimeLocal(event.start_date));
    } else {
      const fallbackStart = eventForm.querySelector('input[name="start_date"]');
      if (fallbackStart) {
        fallbackStart.value = toDateTimeLocal(event.start_date);
      }
    }
    const endPicker = dateTimePickers.get('end_date');
    if (endPicker) {
      endPicker.setValue(toDateTimeLocal(event.end_date));
    } else {
      const fallbackEnd = eventForm.querySelector('input[name="end_date"]');
      if (fallbackEnd) {
        fallbackEnd.value = toDateTimeLocal(event.end_date);
      }
    }
    const recurrenceSelect = eventForm.querySelector('select[name="recurring_interval"]');
    if (recurrenceSelect) {
      recurrenceSelect.value = event.recurring_interval || 'none';
    }
    const recurrenceEndPicker = dateTimePickers.get('recurring_end_date');
    if (recurrenceEndPicker) {
      recurrenceEndPicker.setValue(toDateTimeLocal(event.recurring_end_date));
    } else {
      const recurrenceEndInput = eventForm.querySelector('input[name="recurring_end_date"]');
      if (recurrenceEndInput) {
        recurrenceEndInput.value = toDateTimeLocal(event.recurring_end_date);
      }
    }
    const descriptionField = eventForm.querySelector('textarea[name="description"]');
    if (descriptionField) {
      descriptionField.value = event.description || '';
    }
  }

  function handleOpenEventModal(eventId) {
    populateEventForm(eventId);
    if (eventModalTitle) {
      eventModalTitle.textContent = eventId ? `Edit Event #${eventId}` : 'Add New Event';
    }
    openModal(eventModal);
  }

  function handleDeleteEvent(eventId) {
    if (!eventId) {
      return;
    }
    const event = state.events.find((item) => String(item.id) === String(eventId));
    const eventTitle = event && event.title ? ` "${event.title}"` : '';
    openConfirmModal({
      title: 'Delete Event',
      message: `Are you sure you want to delete event #${eventId}${eventTitle}? This action cannot be undone.`,
      confirmLabel: 'Delete Event',
      confirmTone: 'danger',
      onConfirm: () => {
        const formData = new FormData();
        formData.append('evt_id', eventId);
        return fetch('modules/calendar/api.php?action=delete_event', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        })
          .then((response) => response.json())
          .then((payload) => {
            if (!payload || !payload.success) {
              throw new Error((payload && payload.message) || 'Unable to delete event.');
            }
            state.events = Array.isArray(payload.events) ? payload.events : [];
            state.metrics = payload.metrics || state.metrics;
            setMessage(payload.message || 'Event deleted.', 'success');
            renderEvents();
            renderHeroStats();
          })
          .catch((error) => {
            setMessage(error.message || 'Unable to delete event.', 'error');
            throw error;
          });
      },
    });
  }

  function handleDeleteCategory(categoryId) {
    if (!categoryId) {
      return;
    }
    const category = state.categories.find((item) => String(item.id) === String(categoryId));
    const categoryName = category && category.name ? ` "${category.name}"` : '';
    openConfirmModal({
      title: 'Delete Category',
      message: `Delete category #${categoryId}${categoryName}? Events assigned to this category will remain but without a category.`,
      confirmLabel: 'Delete Category',
      confirmTone: 'danger',
      onConfirm: () => {
        const formData = new FormData();
        formData.append('cat_id', categoryId);
        return fetch('modules/calendar/api.php?action=delete_category', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        })
          .then((response) => response.json())
          .then((payload) => {
            if (!payload || !payload.success) {
              throw new Error((payload && payload.message) || 'Unable to delete category.');
            }
            state.categories = Array.isArray(payload.categories) ? payload.categories : [];
            state.events = Array.isArray(payload.events) ? payload.events : state.events;
            state.metrics = payload.metrics || state.metrics;
            setMessage(payload.message || 'Category deleted.', 'success');
            renderCategories();
            renderEvents();
            renderHeroStats();
          })
          .catch((error) => {
            setMessage(error.message || 'Unable to delete category.', 'error');
            throw error;
          });
      },
    });
  }

  initializeDateTimePickers();

  if (eventForm) {
    eventForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const submitButton = eventForm.querySelector('button[type="submit"]');
      const invalidPickers = [];
      dateTimePickers.forEach((picker) => {
        if (!picker.validate()) {
          invalidPickers.push(picker);
        }
      });
      if (invalidPickers.length > 0) {
        if (submitButton) {
          submitButton.disabled = false;
        }
        setMessage('Please complete the required date and time fields.', 'error');
        const firstInvalid = invalidPickers[0];
        if (firstInvalid && typeof firstInvalid.focus === 'function') {
          firstInvalid.focus();
        }
        return;
      }
      if (submitButton) {
        submitButton.disabled = true;
      }
      const formData = new FormData(eventForm);
      fetch('modules/calendar/api.php?action=save_event', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      })
        .then((response) => response.json())
        .then((payload) => {
          if (!payload || !payload.success) {
            throw new Error((payload && payload.message) || 'Unable to save event.');
          }
          state.events = Array.isArray(payload.events) ? payload.events : [];
          if (Array.isArray(payload.categories)) {
            state.categories = payload.categories;
          }
          state.metrics = payload.metrics || state.metrics;
          renderEvents();
          renderCategories();
          renderHeroStats();
          closeModal(eventModal);
          setMessage(payload.message || 'Event saved.', 'success');
          resetEventForm();
        })
        .catch((error) => {
          setMessage(error.message || 'Unable to save event.', 'error');
        })
        .finally(() => {
          if (submitButton) {
            submitButton.disabled = false;
          }
        });
    });
  }

  if (categoryForm) {
    categoryForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const submitButton = categoryForm.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
      }
      const formData = new FormData(categoryForm);
      fetch('modules/calendar/api.php?action=add_category', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      })
        .then((response) => response.json())
        .then((payload) => {
          if (!payload || !payload.success) {
            throw new Error((payload && payload.message) || 'Unable to add category.');
          }
          state.categories = Array.isArray(payload.categories) ? payload.categories : [];
          state.metrics = payload.metrics || state.metrics;
          setMessage(payload.message || 'Category added.', 'success');
          renderCategories();
          renderHeroStats();
          categoryForm.reset();
          const colorField = categoryForm.querySelector('input[name="cat_color"]');
          if (colorField) {
            colorField.value = '#ffffff';
          }
        })
        .catch((error) => {
          setMessage(error.message || 'Unable to add category.', 'error');
        })
        .finally(() => {
          if (submitButton) {
            submitButton.disabled = false;
          }
        });
    });
  }

  if (confirmAcceptButton) {
    confirmAcceptButton.addEventListener('click', () => {
      if (!confirmConfig || typeof confirmConfig.onConfirm !== 'function') {
        closeConfirmModal();
        return;
      }
      let result;
      try {
        result = confirmConfig.onConfirm();
      } catch (error) {
        closeConfirmModal();
        return;
      }
      if (result && typeof result.then === 'function') {
        confirmAcceptButton.disabled = true;
        confirmAcceptButton.setAttribute('data-loading', 'true');
        result
          .then(() => {
            closeConfirmModal();
          })
          .catch(() => {
            confirmAcceptButton.disabled = false;
            confirmAcceptButton.removeAttribute('data-loading');
          });
      } else {
        closeConfirmModal();
      }
    });
  }

  root.addEventListener('click', (event) => {
    const rawTarget = event.target;
    if (!(rawTarget instanceof HTMLElement)) {
      return;
    }

    const target = rawTarget.closest('[data-calendar-open], [data-calendar-close], [data-calendar-action]');

    if (!target) {
      return;
    }

    if (target.matches('[data-calendar-open="event"]')) {
      resetEventForm();
      handleOpenEventModal('');
      return;
    }

    if (target.matches('[data-calendar-open="categories"]')) {
      openModal(categoriesModal);
      return;
    }

    if (target.matches('[data-calendar-close]')) {
      const modal = target.closest('.calendar-modal-backdrop');
      closeModal(modal);
      return;
    }

    if (target.matches('[data-calendar-action="edit"]')) {
      const eventId = target.getAttribute('data-event-id');
      handleOpenEventModal(eventId);
      return;
    }

    if (target.matches('[data-calendar-action="delete"]')) {
      const eventId = target.getAttribute('data-event-id');
      handleDeleteEvent(eventId);
      return;
    }

    if (target.matches('[data-calendar-action="delete-category"]')) {
      const categoryId = target.getAttribute('data-category-id');
      handleDeleteCategory(categoryId);
    }
  });

  root.addEventListener('mousedown', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    if (target.matches('.calendar-modal-backdrop')) {
      closeModal(target);
    }
  });

  renderCategories();
  renderEvents();
  renderHeroStats();
})();
