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
          ? '<span class="calendar-badge" style="color:' +
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
            '<span class="calendar-category-color"><span style="background:' +
            escapeHtml(color) +
            ';"></span>' +
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
    if (!root.querySelector('.calendar-modal-backdrop.show')) {
      document.body.classList.remove('calendar-modal-open');
    }
  }

  function resetEventForm() {
    if (!eventForm) {
      return;
    }
    eventForm.reset();
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
    const startInput = eventForm.querySelector('input[name="start_date"]');
    if (startInput) {
      startInput.value = toDateTimeLocal(event.start_date);
    }
    const endInput = eventForm.querySelector('input[name="end_date"]');
    if (endInput) {
      endInput.value = toDateTimeLocal(event.end_date);
    }
    const recurrenceSelect = eventForm.querySelector('select[name="recurring_interval"]');
    if (recurrenceSelect) {
      recurrenceSelect.value = event.recurring_interval || 'none';
    }
    const recurrenceEndInput = eventForm.querySelector('input[name="recurring_end_date"]');
    if (recurrenceEndInput) {
      recurrenceEndInput.value = toDateTimeLocal(event.recurring_end_date);
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
    const confirmed = window.confirm(`Delete event #${eventId}?`);
    if (!confirmed) {
      return;
    }
    const formData = new FormData();
    formData.append('evt_id', eventId);
    fetch('modules/calendar/api.php?action=delete_event', {
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
      });
  }

  function handleDeleteCategory(categoryId) {
    if (!categoryId) {
      return;
    }
    const confirmed = window.confirm('Delete this category?');
    if (!confirmed) {
      return;
    }
    const formData = new FormData();
    formData.append('cat_id', categoryId);
    fetch('modules/calendar/api.php?action=delete_category', {
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
      });
  }

  if (eventForm) {
    eventForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const submitButton = eventForm.querySelector('button[type="submit"]');
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
