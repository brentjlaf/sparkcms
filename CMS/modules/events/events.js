/* File: modules/events/events.js */
(function () {
    const root = document.querySelector('.events-dashboard');
    if (!root) {
        return;
    }

    const endpoint = root.dataset.eventsEndpoint || '';
    const initialPayload = (() => {
        try {
            return JSON.parse(root.dataset.eventsInitial || '{}');
        } catch (error) {
            return {};
        }
    })();

    const selectors = {
        stats: {
            events: root.querySelector('[data-events-stat="events"]'),
            tickets: root.querySelector('[data-events-stat="tickets"]'),
            revenue: root.querySelector('[data-events-stat="revenue"]'),
        },
        upcoming: root.querySelector('[data-events-upcoming]'),
        tableBody: root.querySelector('[data-events-table]'),
        filters: {
            status: root.querySelector('[data-events-filter="status"]'),
            search: root.querySelector('[data-events-filter="search"]'),
        },
        orders: {
            body: root.querySelector('[data-events-orders]'),
            filterEvent: root.querySelector('[data-events-orders-filter="event"]'),
            filterStatus: root.querySelector('[data-events-orders-filter="status"]'),
            exportBtn: root.querySelector('[data-events-export]'),
        },
        reports: {
            tableBody: root.querySelector('[data-events-reports-table]'),
        },
        modal: document.querySelector('[data-events-modal="event"]'),
        confirmModal: document.querySelector('[data-events-modal="confirm"]'),
        categoriesModal: document.querySelector('[data-events-modal="categories"]'),
        categoriesForm: document.querySelector('[data-events-form="category"]'),
        categoriesList: document.querySelector('[data-events-categories-list]'),
        categoriesFormTitle: document.querySelector('[data-events-category-form-title]'),
        categoriesSubmit: document.querySelector('[data-events-category-submit]'),
        categoriesReset: document.querySelector('[data-events-category-reset]'),
        toast: document.querySelector('[data-events-toast]'),
    };

    const state = {
        events: new Map(),
        eventRows: [],
        orders: [],
        salesSummary: [],
        categories: [],
        filters: {
            status: '',
            search: '',
        },
        ordersFilter: {
            event: '',
            status: '',
        },
        confirm: null,
        categoryEditing: null,
    };

    if (Array.isArray(initialPayload.events)) {
        initialPayload.events.forEach((event) => {
            if (event && event.id) {
                state.events.set(String(event.id), event);
            }
        });
    }
    if (Array.isArray(initialPayload.categories)) {
        state.categories = sortCategories(initialPayload.categories);
    }
    if (initialPayload.sales && typeof initialPayload.sales === 'object') {
        state.salesSummary = Object.entries(initialPayload.sales).map(([eventId, metrics]) => ({
            event_id: eventId,
            title: state.events.get(String(eventId))?.title || 'Event',
            tickets_sold: metrics.tickets_sold ?? 0,
            revenue: metrics.revenue ?? 0,
            status: state.events.get(String(eventId))?.status || 'draft',
        }));
    }
    if (Array.isArray(initialPayload.orders)) {
        state.orders = initialPayload.orders.map((order) => {
            const event = state.events.get(String(order.event_id || ''));
            const tickets = Array.isArray(order.tickets)
                ? order.tickets.reduce((sum, ticket) => sum + (parseInt(ticket.quantity, 10) || 0), 0)
                : 0;
            return {
                id: order.id,
                event: event ? event.title : '',
                event_id: order.event_id,
                buyer_name: order.buyer_name,
                tickets,
                amount: order.amount,
                status: String(order.status || 'paid').toLowerCase(),
                ordered_at: order.ordered_at,
            };
        });
    }

    function formatDate(value) {
        if (!value) {
            return 'Date TBD';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return 'Date TBD';
        }
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function sortCategories(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list
            .slice()
            .filter((item) => item && item.id && item.name)
            .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }));
    }

    function getCategoryOptionsContainer() {
        return selectors.modal?.querySelector('[data-events-category-options]') || null;
    }

    function getSelectedCategoryIds() {
        const container = getCategoryOptionsContainer();
        if (!container) {
            return [];
        }
        return Array.from(container.querySelectorAll('input[name="categories[]"]:checked')).map((input) => input.value);
    }

    function renderCategoryOptions(selectedIds = []) {
        const container = getCategoryOptionsContainer();
        if (!container) {
            return;
        }
        const selectedSet = new Set(Array.isArray(selectedIds) ? selectedIds.map((id) => String(id)) : []);
        container.innerHTML = '';
        if (!Array.isArray(state.categories) || state.categories.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'events-category-empty';
            empty.textContent = 'No categories yet. Manage categories to create one.';
            container.appendChild(empty);
            return;
        }
        state.categories.forEach((category) => {
            const label = document.createElement('label');
            label.className = 'events-category-item';
            label.innerHTML = `
                <input type="checkbox" name="categories[]" value="${category.id}">
                <span>${category.name}</span>
            `;
            const input = label.querySelector('input');
            if (input && selectedSet.has(String(category.id))) {
                input.checked = true;
            }
            container.appendChild(label);
        });
    }

    function renderCategoryList() {
        const list = selectors.categoriesList;
        if (!list) {
            return;
        }
        list.innerHTML = '';
        if (!Array.isArray(state.categories) || state.categories.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'events-empty';
            cell.textContent = 'No categories yet. Create one above.';
            row.appendChild(cell);
            list.appendChild(row);
            return;
        }
        state.categories.forEach((category) => {
            const row = document.createElement('tr');
            const updatedLabel = category.updated_at ? formatDate(category.updated_at) : '—';
            row.innerHTML = `
                <td>${category.name}</td>
                <td>${category.slug || ''}</td>
                <td>${updatedLabel}</td>
                <td class="events-table-actions">
                    <button type="button" class="events-action" data-events-category-edit data-id="${category.id}">
                        <i class="fa-solid fa-pen"></i><span class="sr-only">Edit</span>
                    </button>
                    <button type="button" class="events-action danger" data-events-category-delete data-id="${category.id}">
                        <i class="fa-solid fa-trash"></i><span class="sr-only">Delete</span>
                    </button>
                </td>
            `;
            list.appendChild(row);
        });
    }

    function updateCategoryFormMode() {
        if (selectors.categoriesFormTitle) {
            selectors.categoriesFormTitle.textContent = state.categoryEditing ? 'Edit category' : 'Create category';
        }
        if (selectors.categoriesSubmit) {
            selectors.categoriesSubmit.textContent = state.categoryEditing ? 'Update category' : 'Save category';
        }
    }

    function resetCategoryForm() {
        if (selectors.categoriesForm) {
            selectors.categoriesForm.reset();
        }
        state.categoryEditing = null;
        updateCategoryFormMode();
    }

    function fillCategoryForm(category) {
        if (!selectors.categoriesForm) {
            return;
        }
        selectors.categoriesForm.querySelector('[name="id"]').value = category?.id || '';
        selectors.categoriesForm.querySelector('[name="name"]').value = category?.name || '';
        selectors.categoriesForm.querySelector('[name="slug"]').value = category?.slug || '';
        state.categoryEditing = category?.id || null;
        updateCategoryFormMode();
    }

    function openCategoriesModal(categoryId = null) {
        if (!selectors.categoriesModal) {
            return;
        }
        renderCategoryList();
        resetCategoryForm();
        if (categoryId) {
            const category = state.categories.find((item) => String(item.id) === String(categoryId));
            if (category) {
                fillCategoryForm(category);
            }
        }
        openModal(selectors.categoriesModal);
    }

    function closeCategoryModal() {
        if (!selectors.categoriesModal) {
            return;
        }
        resetCategoryForm();
        closeModal(selectors.categoriesModal);
    }

    function showToast(message, type = 'success') {
        if (!selectors.toast) {
            return;
        }
        selectors.toast.dataset.type = type;
        selectors.toast.querySelector('[data-events-toast-message]').textContent = message;
        selectors.toast.hidden = false;
        selectors.toast.classList.add('is-visible');
        setTimeout(() => {
            selectors.toast.classList.remove('is-visible');
            selectors.toast.hidden = true;
        }, 2400);
    }

    function buildQuery(params) {
        const query = new URLSearchParams();
        Object.entries(params || {}).forEach(([key, value]) => {
            if (value !== undefined && value !== null && String(value) !== '') {
                query.append(key, value);
            }
        });
        return query.toString();
    }

    function fetchJSON(action, options = {}) {
        const method = options.method || 'GET';
        const headers = options.headers || {};
        let url = `${endpoint}?action=${encodeURIComponent(action)}`;
        const fetchOptions = { method, headers: { ...headers } };
        if (method === 'GET' && options.params) {
            const query = buildQuery(options.params);
            if (query) {
                url += `&${query}`;
            }
        }
        if (method !== 'GET' && options.body) {
            fetchOptions.body = JSON.stringify(options.body);
            fetchOptions.headers['Content-Type'] = 'application/json';
        }
        return fetch(url, fetchOptions).then((response) => {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        });
    }

    function renderStats(stats) {
        if (selectors.stats.events) {
            selectors.stats.events.textContent = stats.total_events ?? state.eventRows.length;
        }
        if (selectors.stats.tickets) {
            selectors.stats.tickets.textContent = stats.total_tickets_sold ?? 0;
        }
        if (selectors.stats.revenue) {
            const revenue = stats.total_revenue ?? 0;
            selectors.stats.revenue.textContent = formatCurrency(revenue);
        }
    }

    function renderUpcoming(list) {
        if (!selectors.upcoming) {
            return;
        }
        selectors.upcoming.innerHTML = '';
        if (!Array.isArray(list) || list.length === 0) {
            const li = document.createElement('li');
            li.className = 'events-empty';
            li.textContent = 'No upcoming events scheduled. Create one to get started.';
            selectors.upcoming.appendChild(li);
            return;
        }
        list.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'events-upcoming-item';
            li.dataset.eventId = item.id;
            li.innerHTML = `
                <div class="events-upcoming-primary">
                    <span class="events-upcoming-title">${item.title || 'Untitled event'}</span>
                    <span class="events-upcoming-date">${formatDate(item.start)}</span>
                </div>
                <div class="events-upcoming-meta">
                    <span class="events-upcoming-stat" data-label="Tickets sold">${item.tickets_sold ?? 0}</span>
                    <span class="events-upcoming-stat" data-label="Revenue">${formatCurrency(item.revenue ?? 0)}</span>
                </div>
            `;
            selectors.upcoming.appendChild(li);
        });
    }

    function createStatusBadge(status) {
        const span = document.createElement('span');
        const value = String(status || 'draft');
        span.className = `events-status events-status--${value}`;
        span.textContent = value.charAt(0).toUpperCase() + value.slice(1);
        return span;
    }

    function createEventRow(row) {
        const tr = document.createElement('tr');
        tr.dataset.eventId = row.id;
        const startLabel = formatDate(row.start);
        const endLabel = row.end ? formatDate(row.end) : '';
        tr.innerHTML = `
            <td>
                <div class="events-table-title">${row.title}</div>
                <div class="events-table-sub">${row.location || ''}</div>
            </td>
            <td>
                <div>${startLabel}</div>
                ${endLabel ? `<div class="events-table-sub">Ends ${endLabel}</div>` : ''}
            </td>
            <td>${row.location || 'TBA'}</td>
            <td>${row.tickets_sold ?? 0} / ${row.capacity ?? 0}</td>
            <td>${formatCurrency(row.revenue ?? 0)}</td>
            <td data-status></td>
            <td class="events-table-actions">
                <button type="button" class="events-action" data-events-action="edit" data-id="${row.id}">
                    <i class="fa-solid fa-pen"></i><span class="sr-only">Edit</span>
                </button>
                <button type="button" class="events-action" data-events-action="sales" data-id="${row.id}">
                    <i class="fa-solid fa-chart-column"></i><span class="sr-only">View sales</span>
                </button>
                <button type="button" class="events-action" data-events-action="end" data-id="${row.id}">
                    <i class="fa-solid fa-flag-checkered"></i><span class="sr-only">End event</span>
                </button>
                <button type="button" class="events-action danger" data-events-action="delete" data-id="${row.id}">
                    <i class="fa-solid fa-trash"></i><span class="sr-only">Delete</span>
                </button>
            </td>
        `;
        const badgeCell = tr.querySelector('[data-status]');
        badgeCell.appendChild(createStatusBadge(row.status));
        return tr;
    }

    function applyEventFilters(rows) {
        return rows.filter((row) => {
            const matchesStatus = !state.filters.status || row.status === state.filters.status;
            const term = state.filters.search.trim().toLowerCase();
            const matchesSearch = !term || `${row.title} ${row.location}`.toLowerCase().includes(term);
            return matchesStatus && matchesSearch;
        });
    }

    function renderEventsTable() {
        if (!selectors.tableBody) {
            return;
        }
        selectors.tableBody.innerHTML = '';
        const filtered = applyEventFilters(state.eventRows);
        if (filtered.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'events-empty';
            cell.textContent = 'No events match the current filters.';
            row.appendChild(cell);
            selectors.tableBody.appendChild(row);
            return;
        }
        filtered.forEach((row) => {
            selectors.tableBody.appendChild(createEventRow(row));
        });
    }

    function populateEventSelect(select) {
        if (!select) {
            return;
        }
        const current = select.value;
        select.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'All events';
        select.appendChild(defaultOption);
        Array.from(state.events.values())
            .sort((a, b) => {
                const aTime = a.start ? new Date(a.start).getTime() : 0;
                const bTime = b.start ? new Date(b.start).getTime() : 0;
                return aTime - bTime;
            })
            .forEach((event) => {
                const option = document.createElement('option');
                option.value = event.id;
                option.textContent = event.title || 'Untitled event';
                select.appendChild(option);
            });
        if (current && select.querySelector(`option[value="${current}"]`)) {
            select.value = current;
        }
    }

    function renderOrdersTable() {
        if (!selectors.orders.body) {
            return;
        }
        selectors.orders.body.innerHTML = '';
        if (!Array.isArray(state.orders) || state.orders.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.className = 'events-empty';
            cell.textContent = 'No orders found for the selected filters.';
            row.appendChild(cell);
            selectors.orders.body.appendChild(row);
            return;
        }
        state.orders.forEach((order) => {
            const tr = document.createElement('tr');
            const totalTickets = order.tickets ?? 0;
            tr.innerHTML = `
                <td>${order.id}</td>
                <td>${order.buyer_name || ''}</td>
                <td>${totalTickets}</td>
                <td>${formatCurrency(order.amount ?? 0)}</td>
                <td><span class="events-status events-status--${order.status}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></td>
                <td>${formatDate(order.ordered_at)}</td>
            `;
            selectors.orders.body.appendChild(tr);
        });
    }

    function renderReportsTable() {
        const table = selectors.reports.tableBody;
        if (!table) {
            return;
        }
        table.innerHTML = '';
        if (!Array.isArray(state.salesSummary) || state.salesSummary.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'events-empty';
            cell.textContent = 'No report data available yet.';
            row.appendChild(cell);
            table.appendChild(row);
            return;
        }
        state.salesSummary.forEach((report) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="events-table-title">${report.title || 'Untitled event'}</div>
                </td>
                <td>${report.tickets_sold ?? 0}</td>
                <td>${formatCurrency(report.revenue ?? 0)}</td>
                <td data-status></td>
            `;
            const statusCell = row.querySelector('[data-status]');
            if (statusCell) {
                statusCell.appendChild(createStatusBadge(report.status));
            }
            table.appendChild(row);
        });
    }

    function openModal(modal) {
        if (!modal) {
            return;
        }
        modal.classList.add('is-open');
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            const editor = form.querySelector('[data-events-editor]');
            if (editor) {
                editor.innerHTML = '';
            }
            const tickets = form.querySelector('[data-events-tickets]');
            if (tickets) {
                tickets.innerHTML = '<div class="events-ticket-empty">No ticket types yet. Add one to begin selling.</div>';
            }
        }
    }

    function bindModalDismissals() {
        document.querySelectorAll('[data-events-close]').forEach((button) => {
            button.addEventListener('click', () => {
                const backdrop = button.closest('.events-modal-backdrop');
                if (!backdrop) {
                    return;
                }
                if (backdrop === selectors.categoriesModal) {
                    closeCategoryModal();
                } else {
                    closeModal(backdrop);
                }
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal(selectors.modal);
                closeModal(selectors.confirmModal);
                closeCategoryModal();
            }
        });
    }

    function fillEventForm(eventData) {
        const modal = selectors.modal;
        if (!modal) {
            return;
        }
        const form = modal.querySelector('[data-events-form="event"]');
        form.reset();
        form.querySelector('[name="id"]').value = eventData?.id || '';
        form.querySelector('[name="title"]').value = eventData?.title || '';
        form.querySelector('[name="location"]').value = eventData?.location || '';
        form.querySelector('[name="start"]').value = eventData?.start ? eventData.start.substring(0, 16) : '';
        form.querySelector('[name="end"]').value = eventData?.end ? eventData.end.substring(0, 16) : '';
        form.querySelector(`[name="status"][value="${eventData?.status || 'draft'}"]`).checked = true;
        const editor = form.querySelector('[data-events-editor]');
        const target = form.querySelector('[data-events-editor-target]');
        if (editor && target) {
            editor.innerHTML = eventData?.description || '';
            target.value = eventData?.description || '';
        }
        renderCategoryOptions(Array.isArray(eventData?.categories) ? eventData.categories : []);
        const ticketContainer = form.querySelector('[data-events-tickets]');
        ticketContainer.innerHTML = '';
        const tickets = Array.isArray(eventData?.tickets) ? eventData.tickets : [];
        if (tickets.length === 0) {
            ticketContainer.innerHTML = '<div class="events-ticket-empty">No ticket types yet. Add one to begin selling.</div>';
        } else {
            tickets.forEach((ticket) => addTicketRow(ticketContainer, ticket));
        }
    }

    function addTicketRow(container, ticket = {}) {
        if (!container) {
            return;
        }
        const row = document.createElement('div');
        row.className = 'events-ticket-row';
        row.innerHTML = `
            <input type="hidden" data-ticket-field="id" value="${ticket.id || ''}">
            <label>
                <span>Name</span>
                <input type="text" data-ticket-field="name" value="${ticket.name || ''}" required>
            </label>
            <label>
                <span>Price</span>
                <input type="number" min="0" step="0.01" data-ticket-field="price" value="${ticket.price ?? 0}" required>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" min="0" step="1" data-ticket-field="quantity" value="${ticket.quantity ?? 0}" required>
            </label>
            <label class="events-ticket-toggle">
                <input type="checkbox" data-ticket-field="enabled" ${ticket.enabled === false ? '' : 'checked'}>
                <span>Enabled</span>
            </label>
            <button type="button" class="events-action danger" data-ticket-remove>
                <i class="fa-solid fa-times"></i><span class="sr-only">Remove ticket</span>
            </button>
        `;
        const empty = container.querySelector('.events-ticket-empty');
        if (empty) {
            empty.remove();
        }
        container.appendChild(row);
    }

    function gatherTickets(container) {
        const rows = Array.from(container.querySelectorAll('.events-ticket-row'));
        return rows.map((row) => ({
            id: row.querySelector('[data-ticket-field="id"]').value || undefined,
            name: row.querySelector('[data-ticket-field="name"]').value.trim(),
            price: parseFloat(row.querySelector('[data-ticket-field="price"]').value || '0'),
            quantity: parseInt(row.querySelector('[data-ticket-field="quantity"]').value || '0', 10),
            enabled: row.querySelector('[data-ticket-field="enabled"]').checked,
        })).filter((ticket) => ticket.name !== '');
    }

    function serializeForm(form) {
        const formData = new FormData(form);
        const payload = {};
        formData.forEach((value, key) => {
            if (key.endsWith('[]')) {
                const base = key.slice(0, -2);
                if (!Array.isArray(payload[base])) {
                    payload[base] = [];
                }
                payload[base].push(value);
            } else {
                payload[key] = value;
            }
        });
        return payload;
    }

    function setupEditor(form) {
        const toolbar = form.querySelector('.events-editor-toolbar');
        const editor = form.querySelector('[data-events-editor]');
        const target = form.querySelector('[data-events-editor-target]');
        if (!toolbar || !editor || !target) {
            return;
        }
        toolbar.addEventListener('click', (event) => {
            const command = event.target.closest('[data-editor-command]')?.dataset.editorCommand;
            if (!command) {
                return;
            }
            event.preventDefault();
            document.execCommand(command, false, null);
            target.value = editor.innerHTML;
        });
        editor.addEventListener('input', () => {
            target.value = editor.innerHTML;
        });
    }

    function handleEventForm() {
        const modal = selectors.modal;
        if (!modal) {
            return;
        }
        const form = modal.querySelector('[data-events-form="event"]');
        setupEditor(form);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const payload = serializeForm(form);
            payload.tickets = gatherTickets(form.querySelector('[data-events-tickets]'));
            return fetchJSON('save_event', { method: 'POST', body: payload })
                .then((response) => {
                    if (response?.event?.id) {
                        state.events.set(response.event.id, response.event);
                    }
                    closeModal(selectors.modal);
                    showToast('Event saved successfully.');
                    refreshAll();
                })
                .catch(() => {
                    showToast('Unable to save event.', 'error');
                });
        });
        modal.addEventListener('click', (event) => {
            if (event.target.matches('[data-ticket-remove]')) {
                const row = event.target.closest('.events-ticket-row');
                if (row) {
                    row.remove();
                }
                const container = modal.querySelector('[data-events-tickets]');
                if (container && container.children.length === 0) {
                    container.innerHTML = '<div class="events-ticket-empty">No ticket types yet. Add one to begin selling.</div>';
                }
            }
        });
        const addTicketBtn = modal.querySelector('[data-events-ticket-add]');
        addTicketBtn.addEventListener('click', () => {
            addTicketRow(modal.querySelector('[data-events-tickets]'));
        });
    }

    function openEventModal(eventId) {
        const modal = selectors.modal;
        if (!modal) {
            return;
        }
        const title = modal.querySelector('.events-modal-title');
        title.textContent = eventId ? 'Edit event' : 'Create event';
        if (eventId) {
            fetchJSON('get_event', { params: { id: eventId } })
                .then((response) => {
                    fillEventForm(response.event || {});
                    openModal(modal.closest('.events-modal-backdrop'));
                })
                .catch(() => {
                    showToast('Unable to load event.', 'error');
                });
        } else {
            fillEventForm({});
            openModal(modal.closest('.events-modal-backdrop'));
        }
    }

    function openConfirm(message, onConfirm) {
        const modal = selectors.confirmModal;
        if (!modal) {
            return;
        }
        modal.querySelector('[data-events-confirm-message]').textContent = message;
        state.confirm = onConfirm;
        openModal(modal);
    }

    function attachConfirmHandler() {
        if (!selectors.confirmModal) {
            return;
        }
        selectors.confirmModal.querySelector('[data-events-confirm]').addEventListener('click', () => {
            if (typeof state.confirm === 'function') {
                state.confirm();
            }
            state.confirm = null;
            closeModal(selectors.confirmModal);
        });
    }

    function refreshOrders() {
        return fetchJSON('list_orders', { params: state.ordersFilter })
            .then((response) => {
                state.orders = Array.isArray(response.orders) ? response.orders : [];
                renderOrdersTable();
            })
            .catch(() => {
                showToast('Unable to load orders.', 'error');
            });
    }

    function refreshEvents() {
        return fetchJSON('list_events')
            .then((response) => {
                state.eventRows = Array.isArray(response.events) ? response.events : [];
                response.events.forEach((row) => {
                    const existing = state.events.get(row.id);
                    if (existing) {
                        existing.status = row.status;
                        existing.categories = Array.isArray(row.categories) ? row.categories : [];
                    }
                });
                renderEventsTable();
                populateEventSelect(selectors.orders.filterEvent);
            })
            .catch(() => {
                showToast('Unable to load events.', 'error');
            });
    }

    function refreshOverview() {
        return fetchJSON('overview')
            .then((response) => {
                renderStats({
                    total_events: response.stats?.total_events,
                    total_tickets_sold: response.stats?.total_tickets_sold,
                    total_revenue: response.stats?.total_revenue,
                });
                const upcoming = (response.upcoming || []).map((item) => ({
                    ...item,
                    revenue: item.revenue,
                }));
                renderUpcoming(upcoming);
            })
            .catch(() => {
                showToast('Unable to refresh overview.', 'error');
            });
    }

    function refreshReportsSummary() {
        return fetchJSON('reports_summary')
            .then((response) => {
                state.salesSummary = Array.isArray(response.reports) ? response.reports : [];
                renderReportsTable();
            })
            .catch(() => {
                showToast('Unable to load reports data.', 'error');
            });
    }

    function refreshCategories() {
        return fetchJSON('list_categories')
            .then((response) => {
                if (Array.isArray(response.categories)) {
                    state.categories = sortCategories(response.categories);
                    renderCategoryList();
                    renderCategoryOptions(getSelectedCategoryIds());
                }
            })
            .catch(() => {
                showToast('Unable to load categories.', 'error');
            });
    }

    function refreshAll() {
        refreshEvents();
        refreshOrders();
        refreshOverview();
        refreshReportsSummary();
        refreshCategories();
    }

    function attachEventListeners() {
        if (selectors.filters.status) {
            selectors.filters.status.addEventListener('change', (event) => {
                state.filters.status = event.target.value;
                renderEventsTable();
            });
        }
        if (selectors.filters.search) {
            selectors.filters.search.addEventListener('input', (event) => {
                state.filters.search = event.target.value;
                renderEventsTable();
            });
        }
        root.addEventListener('click', (event) => {
            const action = event.target.closest('[data-events-action]');
            if (!action) {
                return;
            }
            const id = action.dataset.id;
            switch (action.dataset.eventsAction) {
                case 'edit':
                    openEventModal(id);
                    break;
                case 'sales':
                    document.getElementById('eventsOrdersTitle')?.scrollIntoView({ behavior: 'smooth' });
                    if (selectors.orders.filterEvent) {
                        selectors.orders.filterEvent.value = id;
                        state.ordersFilter.event = id;
                        refreshOrders();
                    }
                    break;
                case 'end':
                    openConfirm('End this event? It will move to the ended state.', () => {
                        fetchJSON('end_event', { method: 'POST', body: { id } })
                            .then(() => {
                                showToast('Event ended.');
                                refreshAll();
                            })
                            .catch(() => {
                                showToast('Unable to end event.', 'error');
                            });
                    });
                    break;
                case 'delete':
                    openConfirm('Delete this event? This cannot be undone.', () => {
                        fetchJSON('delete_event', { method: 'POST', body: { id } })
                            .then(() => {
                                state.events.delete(id);
                                showToast('Event deleted.');
                                refreshAll();
                            })
                            .catch(() => {
                                showToast('Unable to delete event.', 'error');
                            });
                    });
                    break;
                default:
                    break;
            }
        });
        if (selectors.orders.filterEvent) {
            selectors.orders.filterEvent.addEventListener('change', (event) => {
                state.ordersFilter.event = event.target.value;
                refreshOrders();
            });
        }
        if (selectors.orders.filterStatus) {
            selectors.orders.filterStatus.addEventListener('change', (event) => {
                state.ordersFilter.status = event.target.value;
                refreshOrders();
            });
        }
        if (selectors.orders.exportBtn) {
            selectors.orders.exportBtn.addEventListener('click', () => {
                window.open(`${endpoint}?action=export_orders`, '_blank');
            });
        }
        const reportButtons = root.querySelectorAll('[data-events-report-download]');
        reportButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const type = button.dataset.eventsReportDownload;
                const rows = [['Event', 'Tickets Sold', 'Revenue', 'Status']];
                state.salesSummary.forEach((item) => {
                    rows.push([
                        item.title,
                        item.tickets_sold,
                        item.revenue,
                        item.status,
                    ]);
                });
                const csv = rows.map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `events-${type}-report.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            });
        });
    }

    function handleCategoryForm() {
        if (selectors.categoriesForm) {
            selectors.categoriesForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const formData = new FormData(selectors.categoriesForm);
                const payload = {
                    id: formData.get('id') || undefined,
                    name: String(formData.get('name') || '').trim(),
                    slug: String(formData.get('slug') || '').trim(),
                };
                if (!payload.name) {
                    showToast('Category name is required.', 'error');
                    return;
                }
                if (!payload.id) {
                    delete payload.id;
                }
                if (!payload.slug) {
                    delete payload.slug;
                }
                const isUpdate = Boolean(state.categoryEditing);
                fetchJSON('save_category', { method: 'POST', body: payload })
                    .then((response) => {
                        if (Array.isArray(response.categories)) {
                            state.categories = sortCategories(response.categories);
                        }
                        renderCategoryList();
                        renderCategoryOptions(getSelectedCategoryIds());
                        showToast(isUpdate ? 'Category updated.' : 'Category created.');
                        resetCategoryForm();
                    })
                    .catch(() => {
                        showToast('Unable to save category.', 'error');
                    });
            });
        }
        if (selectors.categoriesReset) {
            selectors.categoriesReset.addEventListener('click', (event) => {
                event.preventDefault();
                resetCategoryForm();
            });
        }
        if (selectors.categoriesModal) {
            selectors.categoriesModal.addEventListener('click', (event) => {
                const editBtn = event.target.closest('[data-events-category-edit]');
                if (editBtn) {
                    const category = state.categories.find((item) => String(item.id) === String(editBtn.dataset.id));
                    if (category) {
                        fillCategoryForm(category);
                        selectors.categoriesForm?.querySelector('[name="name"]').focus();
                    }
                    return;
                }
                const deleteBtn = event.target.closest('[data-events-category-delete]');
                if (deleteBtn) {
                    const id = deleteBtn.dataset.id;
                    openConfirm('Delete this category? It will be removed from any events.', () => {
                        fetchJSON('delete_category', { method: 'POST', body: { id } })
                            .then((response) => {
                                if (Array.isArray(response.categories)) {
                                    state.categories = sortCategories(response.categories);
                                } else {
                                    state.categories = [];
                                }
                                state.events.forEach((event) => {
                                    if (Array.isArray(event.categories)) {
                                        event.categories = event.categories.filter((categoryId) => String(categoryId) !== String(id));
                                    }
                                });
                                renderCategoryList();
                                renderCategoryOptions(getSelectedCategoryIds());
                                showToast('Category deleted.');
                                resetCategoryForm();
                            })
                            .catch(() => {
                                showToast('Unable to delete category.', 'error');
                            });
                    });
                }
            });
        }
    }

    function init() {
        bindModalDismissals();
        attachConfirmHandler();
        handleEventForm();
        handleCategoryForm();
        attachEventListeners();
        populateEventSelect(selectors.orders.filterEvent);
        renderEventsTable();
        renderOrdersTable();
        renderReportsTable();
        renderCategoryOptions();
        renderCategoryList();
        refreshAll();
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-events-open]');
        if (!trigger) {
            return;
        }
        const type = trigger.dataset.eventsOpen;
        if (type === 'event') {
            openEventModal(null);
        } else if (type === 'categories') {
            openCategoriesModal();
        }
    });

    init();
})();
