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

  const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];

  const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  const datePickers = [];
  let activeDatePicker = null;

  function parseLocalDateTime(value) {
    if (!value) {
      return null;
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return date;
  }

  function formatLocalDateTime(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
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

  function createDefaultDate() {
    const now = new Date();
    now.setSeconds(0, 0);
    return now;
  }

  function ensureMinuteOption(select, minute) {
    if (!select) {
      return minute;
    }
    const options = Array.from(select.options).map((option) => Number(option.value));
    if (options.length === 0) {
      return minute;
    }
    if (options.includes(minute)) {
      return minute;
    }
    return options.reduce((closest, current) => {
      const diffCurrent = Math.abs(current - minute);
      const diffClosest = Math.abs(closest - minute);
      if (diffCurrent < diffClosest) {
        return current;
      }
      if (diffCurrent === diffClosest && current < closest) {
        return current;
      }
      return closest;
    }, options[0]);
  }

  function commitDatePickerValue(picker, localValue) {
    if (!picker) {
      return;
    }
    const value = localValue || '';
    picker.value = value;
    if (picker.hiddenInput) {
      picker.hiddenInput.value = value;
    }
    picker.selectedDate = value ? parseLocalDateTime(value) : null;
    picker.tempDate = null;
    if (picker.displayInput) {
      if (value) {
        const friendly = formatReadableDate(value);
        picker.displayInput.value = friendly || value;
      } else {
        picker.displayInput.value = '';
        picker.displayInput.placeholder = picker.placeholder || '';
      }
      picker.displayInput.setAttribute('aria-expanded', 'false');
    }
    picker.element.classList.toggle('is-empty', !value);
    if (value) {
      picker.element.classList.remove('is-invalid');
    }
  }

  function renderDatePicker(picker) {
    if (!picker || !picker.panel) {
      return;
    }
    if (!picker.viewDate) {
      const base = picker.tempDate || picker.selectedDate || createDefaultDate();
      picker.viewDate = new Date(base.getFullYear(), base.getMonth(), 1);
    }
    const viewYear = picker.viewDate.getFullYear();
    const viewMonth = picker.viewDate.getMonth();
    if (picker.monthLabel) {
      picker.monthLabel.textContent = `${MONTH_NAMES[viewMonth]} ${viewYear}`;
    }

    if (picker.weekdaysContainer && !picker.weekdaysContainer.childElementCount) {
      const fragment = document.createDocumentFragment();
      DAY_NAMES.forEach((name) => {
        const span = document.createElement('span');
        span.textContent = name;
        fragment.appendChild(span);
      });
      picker.weekdaysContainer.appendChild(fragment);
    }

    if (picker.daysContainer) {
      picker.daysContainer.innerHTML = '';
      const fragment = document.createDocumentFragment();
      const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
      const startIndex = firstDayOfMonth.getDay();
      const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
      const totalCells = 42;
      const selected = picker.tempDate || picker.selectedDate;
      const selectedYear = selected ? selected.getFullYear() : null;
      const selectedMonth = selected ? selected.getMonth() : null;
      const selectedDay = selected ? selected.getDate() : null;
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      for (let index = 0; index < totalCells; index += 1) {
        const dayNumber = index - startIndex + 1;
        const cellDate = new Date(viewYear, viewMonth, dayNumber);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'calendar-picker-day';
        if (dayNumber < 1 || dayNumber > daysInMonth) {
          button.textContent = String(cellDate.getDate());
          button.disabled = true;
          button.classList.add('is-outside');
        } else {
          button.textContent = String(dayNumber);
          button.setAttribute('data-calendar-picker-day', String(dayNumber));
          if (
            selectedYear === viewYear &&
            selectedMonth === viewMonth &&
            selectedDay === dayNumber
          ) {
            button.classList.add('is-selected');
          }
          const normalizedCell = new Date(cellDate);
          normalizedCell.setHours(0, 0, 0, 0);
          if (normalizedCell.getTime() === today.getTime()) {
            button.classList.add('is-today');
          }
        }
        fragment.appendChild(button);
      }
      picker.daysContainer.appendChild(fragment);
    }
  }

  function openDatePicker(picker) {
    if (!picker || !picker.panel) {
      return;
    }
    datePickers.forEach((other) => {
      if (other !== picker) {
        closeDatePicker(other);
      }
    });
    const base = picker.selectedDate ? new Date(picker.selectedDate.getTime()) : createDefaultDate();
    picker.tempDate = new Date(base.getTime());
    picker.viewDate = new Date(base.getFullYear(), base.getMonth(), 1);
    if (picker.hourSelect) {
      picker.hourSelect.value = String(base.getHours());
    }
    if (picker.minuteSelect) {
      const minute = ensureMinuteOption(picker.minuteSelect, base.getMinutes());
      picker.minuteSelect.value = String(minute);
      picker.tempDate.setMinutes(minute);
    }
    picker.tempDate.setSeconds(0, 0);
    picker.panel.hidden = false;
    picker.element.classList.add('is-open');
    if (picker.displayInput) {
      picker.displayInput.setAttribute('aria-expanded', 'true');
    }
    activeDatePicker = picker;
    renderDatePicker(picker);
  }

  function closeDatePicker(picker) {
    if (!picker || !picker.panel || picker.panel.hidden) {
      return;
    }
    picker.panel.hidden = true;
    picker.element.classList.remove('is-open');
    picker.tempDate = null;
    if (picker.displayInput) {
      picker.displayInput.setAttribute('aria-expanded', 'false');
    }
    if (activeDatePicker === picker) {
      activeDatePicker = null;
    }
  }

  function changeDatePickerMonth(picker, offset) {
    if (!picker) {
      return;
    }
    if (!picker.viewDate) {
      picker.viewDate = new Date();
      picker.viewDate.setDate(1);
    }
    picker.viewDate.setMonth(picker.viewDate.getMonth() + offset);
    renderDatePicker(picker);
  }

  function clearAllDatePickers() {
    datePickers.forEach((picker) => {
      commitDatePickerValue(picker, '');
      picker.viewDate = null;
      picker.element.classList.remove('is-invalid');
    });
  }

  function findDatePicker(fieldName) {
    return datePickers.find((picker) => picker.fieldName === fieldName);
  }

  function setDatePickerValue(fieldName, localValue) {
    const picker = findDatePicker(fieldName);
    if (picker) {
      commitDatePickerValue(picker, localValue);
    } else if (eventForm) {
      const fallback = eventForm.querySelector(`input[name="${fieldName}"]`);
      if (fallback) {
        fallback.value = localValue || '';
      }
    }
  }

  function setDatePickerIsoValue(fieldName, isoValue) {
    const localValue = toDateTimeLocal(isoValue);
    setDatePickerValue(fieldName, localValue);
  }

  function validateDatePickers() {
    let valid = true;
    let firstInvalid = null;
    datePickers.forEach((picker) => {
      if (picker.required && !picker.value) {
        picker.element.classList.add('is-invalid');
        if (!firstInvalid) {
          firstInvalid = picker;
        }
        valid = false;
      }
    });
    return { valid, firstInvalid };
  }

  function initDatePickers() {
    if (!eventForm) {
      return;
    }
    const pickerElements = Array.from(eventForm.querySelectorAll('[data-calendar-picker]'));
    pickerElements.forEach((element) => {
      if (datePickers.some((existing) => existing.element === element)) {
        return;
      }
      const hiddenInput = element.querySelector('[data-calendar-picker-value]');
      const displayInput = element.querySelector('[data-calendar-picker-display]');
      const panel = element.querySelector('[data-calendar-picker-panel]');
      if (!hiddenInput || !displayInput || !panel) {
        return;
      }
      const picker = {
        element,
        hiddenInput,
        displayInput,
        panel,
        hourSelect: panel.querySelector('[data-calendar-picker-hour]'),
        minuteSelect: panel.querySelector('[data-calendar-picker-minute]'),
        monthLabel: panel.querySelector('[data-calendar-picker-month]'),
        weekdaysContainer: panel.querySelector('[data-calendar-picker-weekdays]'),
        daysContainer: panel.querySelector('[data-calendar-picker-days]'),
        applyButton: panel.querySelector('[data-calendar-picker-apply]'),
        clearButton: panel.querySelector('[data-calendar-picker-clear]'),
        prevButton: panel.querySelector('[data-calendar-picker-prev]'),
        nextButton: panel.querySelector('[data-calendar-picker-next]'),
        placeholder: element.getAttribute('data-calendar-picker-placeholder') || displayInput.placeholder || '',
        required: element.hasAttribute('data-calendar-picker-required'),
        fieldName: hiddenInput.getAttribute('name') || '',
        selectedDate: null,
        tempDate: null,
        viewDate: null,
        value: hiddenInput.value || '',
      };

      displayInput.setAttribute('aria-expanded', 'false');
      displayInput.setAttribute('aria-readonly', 'true');
      displayInput.setAttribute('role', 'combobox');
      displayInput.setAttribute('autocomplete', 'off');
      displayInput.setAttribute('spellcheck', 'false');

      panel.hidden = true;

      if (picker.hourSelect && !picker.hourSelect.childElementCount) {
        for (let hour = 0; hour < 24; hour += 1) {
          const option = document.createElement('option');
          option.value = String(hour);
          option.textContent = String(hour).padStart(2, '0');
          picker.hourSelect.appendChild(option);
        }
      }

      if (picker.minuteSelect && !picker.minuteSelect.childElementCount) {
        for (let minute = 0; minute < 60; minute += 5) {
          const option = document.createElement('option');
          option.value = String(minute);
          option.textContent = String(minute).padStart(2, '0');
          picker.minuteSelect.appendChild(option);
        }
      }

      displayInput.addEventListener('click', (event) => {
        event.preventDefault();
        picker.element.classList.remove('is-invalid');
        openDatePicker(picker);
      });

      displayInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          picker.element.classList.remove('is-invalid');
          openDatePicker(picker);
          return;
        }
        if (event.key === 'ArrowDown') {
          event.preventDefault();
          picker.element.classList.remove('is-invalid');
          openDatePicker(picker);
          return;
        }
        if (event.key === 'Escape') {
          closeDatePicker(picker);
        }
      });

      if (picker.prevButton) {
        picker.prevButton.addEventListener('click', (event) => {
          event.preventDefault();
          changeDatePickerMonth(picker, -1);
        });
      }

      if (picker.nextButton) {
        picker.nextButton.addEventListener('click', (event) => {
          event.preventDefault();
          changeDatePickerMonth(picker, 1);
        });
      }

      if (picker.daysContainer) {
        picker.daysContainer.addEventListener('click', (event) => {
          const target = event.target;
          if (!(target instanceof HTMLElement)) {
            return;
          }
          const button = target.closest('button[data-calendar-picker-day]');
          if (!button || button.disabled) {
            return;
          }
          const dayValue = Number(button.getAttribute('data-calendar-picker-day'));
          if (!Number.isFinite(dayValue)) {
            return;
          }
          const hour = picker.hourSelect ? Number(picker.hourSelect.value || 0) : 0;
          const minute = picker.minuteSelect ? Number(picker.minuteSelect.value || 0) : 0;
          if (!picker.viewDate) {
            picker.viewDate = new Date();
            picker.viewDate.setDate(1);
          }
          picker.tempDate = new Date(
            picker.viewDate.getFullYear(),
            picker.viewDate.getMonth(),
            dayValue,
            hour,
            minute,
            0,
            0
          );
          renderDatePicker(picker);
        });
      }

      if (picker.hourSelect) {
        picker.hourSelect.addEventListener('change', () => {
          const hour = Number(picker.hourSelect.value || 0);
          const base = picker.tempDate || picker.selectedDate || createDefaultDate();
          picker.tempDate = new Date(base.getTime());
          picker.tempDate.setHours(hour);
          picker.tempDate.setSeconds(0, 0);
          picker.viewDate = new Date(picker.tempDate.getFullYear(), picker.tempDate.getMonth(), 1);
          renderDatePicker(picker);
        });
      }

      if (picker.minuteSelect) {
        picker.minuteSelect.addEventListener('change', () => {
          const minute = Number(picker.minuteSelect.value || 0);
          const base = picker.tempDate || picker.selectedDate || createDefaultDate();
          picker.tempDate = new Date(base.getTime());
          picker.tempDate.setMinutes(minute);
          picker.tempDate.setSeconds(0, 0);
          picker.viewDate = new Date(picker.tempDate.getFullYear(), picker.tempDate.getMonth(), 1);
          renderDatePicker(picker);
        });
      }

      if (picker.applyButton) {
        picker.applyButton.addEventListener('click', (event) => {
          event.preventDefault();
          const commitDate = picker.tempDate
            ? new Date(picker.tempDate.getTime())
            : picker.selectedDate
            ? new Date(picker.selectedDate.getTime())
            : null;
          if (commitDate) {
            commitDate.setSeconds(0, 0);
            commitDatePickerValue(picker, formatLocalDateTime(commitDate));
          } else {
            commitDatePickerValue(picker, '');
          }
          closeDatePicker(picker);
          if (picker.displayInput) {
            picker.displayInput.focus();
          }
        });
      }

      if (picker.clearButton) {
        picker.clearButton.addEventListener('click', (event) => {
          event.preventDefault();
          commitDatePickerValue(picker, '');
          picker.viewDate = null;
          closeDatePicker(picker);
          if (picker.displayInput) {
            picker.displayInput.focus();
          }
        });
      }

      commitDatePickerValue(picker, picker.value);
      datePickers.push(picker);
    });
  }

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
    clearAllDatePickers();
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
    setDatePickerIsoValue('start_date', event.start_date);
    setDatePickerIsoValue('end_date', event.end_date);
    const recurrenceSelect = eventForm.querySelector('select[name="recurring_interval"]');
    if (recurrenceSelect) {
      recurrenceSelect.value = event.recurring_interval || 'none';
    }
    setDatePickerIsoValue('recurring_end_date', event.recurring_end_date);
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

  initDatePickers();

  document.addEventListener('mousedown', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    datePickers.forEach((picker) => {
      if (!picker.panel || picker.panel.hidden) {
        return;
      }
      if (picker.element.contains(target)) {
        return;
      }
      closeDatePicker(picker);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && activeDatePicker) {
      const current = activeDatePicker;
      closeDatePicker(current);
      if (current && current.displayInput) {
        current.displayInput.focus();
      }
    }
  });

  window.addEventListener('resize', () => {
    if (activeDatePicker) {
      closeDatePicker(activeDatePicker);
    }
  });

  if (eventForm) {
    eventForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const submitButton = eventForm.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
      }
      const validation = validateDatePickers();
      if (!validation.valid) {
        if (submitButton) {
          submitButton.disabled = false;
        }
        setMessage('Please choose a start date and time.', 'error');
        if (validation.firstInvalid && validation.firstInvalid.displayInput) {
          validation.firstInvalid.displayInput.focus();
        }
        return;
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
