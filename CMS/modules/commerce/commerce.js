// File: commerce.js
$(function(){
    const dataScript = document.getElementById('commerceDataset');
    let dataset = {};
    if (dataScript) {
        try {
            dataset = JSON.parse(dataScript.textContent || '{}');
        } catch (error) {
            console.error('Unable to parse commerce dataset', error);
            dataset = {};
        }
    }

    const summary = dataset.summary || {};
    const reports = dataset.reports || {};
    const settings = dataset.settings || {};
    const currency = (dataset.currency || 'USD').toUpperCase();

    const currencySymbols = {
        USD: '$',
        EUR: '€',
        GBP: '£',
        AUD: 'A$',
        CAD: 'C$'
    };

    const currencySymbol = currencySymbols[currency] || '';

    function formatCurrency(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return currencySymbol + '0.00';
        }
        const amount = number.toFixed(2);
        return currencySymbol ? currencySymbol + amount : amount;
    }

    function formatPercent(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return '0%';
        }
        return `${parseFloat(number.toFixed(1))}%`;
    }

    function slugify(value) {
        return value
            .toString()
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'category';
    }

    function escapeHtml(value) {
        return (value == null ? '' : String(value))
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getBadgeClass(status) {
        const normalized = (status || '').toString().toLowerCase();
        switch (normalized) {
            case 'active':
            case 'fulfilled':
            case 'vip':
                return 'status-badge status-good';
            case 'processing':
            case 'ready for pickup':
            case 'support':
            case 'new':
            case 'loyal':
                return 'status-badge status-warning';
            case 'refund requested':
            case 'pending payment':
            case 'dormant':
            case 'attention':
            case 'backorder':
            case 'restock':
                return 'status-badge status-critical';
            case 'preorder':
                return 'status-badge status-info';
            default:
                return 'status-badge';
        }
    }

    function showToast(type, message) {
        if (window.AdminNotifications && typeof window.AdminNotifications[type] === 'function') {
            window.AdminNotifications[type](message);
            return;
        }
        if (window.AdminNotifications) {
            const fallbackType = type === 'showSuccessToast' ? 'showSuccessToast' : 'showErrorToast';
            if (typeof window.AdminNotifications[fallbackType] === 'function') {
                window.AdminNotifications[fallbackType](message);
                return;
            }
        }
        if (typeof window.alertModal === 'function') {
            alertModal(message);
            return;
        }
        window.alert(message);
    }

    function notifySuccess(message) {
        if (window.AdminNotifications && typeof window.AdminNotifications.showSuccessToast === 'function') {
            window.AdminNotifications.showSuccessToast(message);
        } else {
            showToast('showSuccessToast', message);
        }
    }

    function notifyInfo(message) {
        if (window.AdminNotifications && typeof window.AdminNotifications.showInfoToast === 'function') {
            window.AdminNotifications.showInfoToast(message);
        } else {
            showToast('showInfoToast', message);
        }
    }

    const $body = $('body');
    const modalState = {
        active: null,
        lastFocus: null
    };

    function findModalFocusTarget($modal) {
        if (!$modal || !$modal.length) {
            return null;
        }
        const $preferred = $modal.find('[data-commerce-initial-focus]').filter(':visible').first();
        if ($preferred.length) {
            return $preferred;
        }
        return $modal
            .find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
            .filter(':visible')
            .first();
    }

    function openCommerceModal(id) {
        const $modal = $(`#${id}`);
        if (!$modal.length) {
            return;
        }
        modalState.lastFocus = document.activeElement;
        $modal.addClass('active');
        $modal.attr('aria-hidden', 'false');
        $body.addClass('commerce-modal-open');
        modalState.active = id;
        const $focusTarget = findModalFocusTarget($modal);
        if ($focusTarget && $focusTarget.length) {
            setTimeout(function(){
                $focusTarget.trigger('focus');
            }, 50);
        }
    }

    function closeCommerceModal(id) {
        const $modal = $(`#${id}`);
        if (!$modal.length) {
            return;
        }
        $modal.removeClass('active');
        $modal.attr('aria-hidden', 'true');
        if (!$('.commerce-modal.active').length) {
            $body.removeClass('commerce-modal-open');
        }
        if (modalState.active === id) {
            modalState.active = null;
        }
        if (id === 'commerceProductModal') {
            resetProductForm();
        }
        if (id === 'commerceCategoryModal') {
            resetCategoryForm();
            renderCategorySelect();
        }
        const { lastFocus } = modalState;
        if (lastFocus && typeof lastFocus.focus === 'function') {
            setTimeout(function(){
                lastFocus.focus();
            }, 50);
        }
        modalState.lastFocus = null;
    }

    const $workspaceTabs = $('[data-commerce-workspace]');
    const $workspacePanels = $('[data-commerce-panel]');

    function setActiveWorkspace(workspace) {
        $workspaceTabs.each(function(){
            const $tab = $(this);
            const isActive = $tab.data('commerceWorkspace') === workspace;
            $tab.toggleClass('active', isActive);
            $tab.attr('aria-selected', isActive ? 'true' : 'false');
            $tab.attr('tabindex', isActive ? '0' : '-1');
        });

        $workspacePanels.each(function(){
            const $panel = $(this);
            const isActive = $panel.data('commercePanel') === workspace;
            if (isActive) {
                $panel.removeAttr('hidden');
                $panel.attr('aria-hidden', 'false');
            } else {
                $panel.attr('hidden', 'hidden');
                $panel.attr('aria-hidden', 'true');
            }
        });
    }

    $workspaceTabs.on('click keydown', function(event){
        if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) {
            return;
        }
        event.preventDefault();
        const workspace = $(this).data('commerceWorkspace');
        setActiveWorkspace(workspace);
    });

    const threshold = Number(settings.low_inventory_threshold) || 0;
    function updateInventoryHighlights() {
        if (!threshold) {
            return;
        }
        const $inventoryCells = $('[data-inventory]');
        if (!$inventoryCells.length) {
            return;
        }
        $inventoryCells.each(function(){
            const $cell = $(this);
            const quantity = Number($cell.data('inventory'));
            if (Number.isFinite(quantity) && quantity <= threshold) {
                $cell.addClass('is-low');
                $cell.attr('aria-label', `${quantity} in stock, below threshold of ${threshold}`);
            } else {
                $cell.removeClass('is-low');
                $cell.removeAttr('aria-label');
            }
        });
    }

    const $metricCards = $('[data-metric-value]');
    $metricCards.each(function(){
        const $card = $(this);
        const label = ($card.data('metricValue') || '').toString().toLowerCase();
        if (label === 'total revenue' && summary.total_revenue) {
            $card.text(formatCurrency(summary.total_revenue));
        }
        if (label === 'orders' && summary.orders) {
            $card.text(Number(summary.orders).toLocaleString());
        }
    });

    let categories = Array.isArray(dataset.categories) ? dataset.categories.slice() : [];
    let catalog = Array.isArray(dataset.catalog) ? dataset.catalog.slice() : [];

    const $catalogTableBody = $('#commerceCatalogTable tbody');
    let $catalogRows = $('#commerceCatalogTable tbody tr');
    const $catalogSearch = $('#commerceCatalogSearch');
    const $catalogCategory = $('#commerceCatalogCategory');
    const $catalogStatus = $('#commerceCatalogStatus');
    const $catalogEmpty = $('#commerceCatalogEmpty');

    function createProductRow(product) {
        const sku = (product.sku || '').toString();
        const name = (product.name || 'Untitled product').toString();
        const category = (product.category || 'Uncategorised').toString();
        const categorySlug = slugify(category);
        const price = formatCurrency(product.price || 0);
        const inventory = Number.isFinite(Number(product.inventory)) ? Number(product.inventory) : 0;
        const status = (product.status || 'Unknown').toString();
        const statusSlug = status.toLowerCase();
        const visibility = (product.visibility || 'Unknown').toString();
        const visibilitySlug = visibility.toLowerCase();
        const updated = (product.updated || '').toString();

        return `
            <tr data-commerce-item data-sku="${escapeHtml(sku)}" data-category="${escapeHtml(categorySlug)}" data-status="${escapeHtml(statusSlug)}" data-visibility="${escapeHtml(visibilitySlug)}" data-updated="${escapeHtml(updated)}">
                <td>
                    <div class="commerce-table-primary">
                        <strong>${escapeHtml(name)}</strong>
                        <span class="commerce-table-meta">SKU: ${escapeHtml(sku)}</span>
                    </div>
                </td>
                <td>${escapeHtml(category)}</td>
                <td>${escapeHtml(price)}</td>
                <td><span class="commerce-inventory" data-inventory="${inventory}">${inventory}</span></td>
                <td><span class="${getBadgeClass(status)}">${escapeHtml(status)}</span></td>
                <td>${escapeHtml(visibility)}</td>
                <td>${escapeHtml(updated)}</td>
                <td>
                    <button type="button" class="commerce-inline-action" data-action="edit-product" data-sku="${escapeHtml(sku)}">
                        <span>Edit</span>
                    </button>
                </td>
            </tr>
        `;
    }

    function applyCatalogFilters() {
        const query = ($catalogSearch.val() || '').toString().toLowerCase();
        const category = ($catalogCategory.val() || 'all').toString();
        const status = ($catalogStatus.val() || 'all').toString();
        let visible = 0;

        $catalogRows.each(function(){
            const $row = $(this);
            const name = ($row.find('strong').text() || '').toLowerCase();
            const sku = ($row.data('sku') || '').toString().toLowerCase();
            const rowCategory = ($row.data('category') || '').toString();
            const rowStatus = ($row.data('status') || '').toString();

            const matchesQuery = !query || name.indexOf(query) !== -1 || sku.indexOf(query) !== -1;
            const matchesCategory = category === 'all' || rowCategory === category;
            const matchesStatus = status === 'all' || rowStatus === status;

            if (matchesQuery && matchesCategory && matchesStatus) {
                $row.removeAttr('hidden');
                visible += 1;
            } else {
                $row.attr('hidden', 'hidden');
            }
        });

        if (visible === 0) {
            $catalogEmpty.removeAttr('hidden');
        } else {
            $catalogEmpty.attr('hidden', 'hidden');
        }
    }

    function renderCatalogRows() {
        if (!$catalogTableBody.length) {
            return;
        }
        const rows = catalog.map(createProductRow).join('');
        $catalogTableBody.html(rows);
        $catalogRows = $('#commerceCatalogTable tbody tr');
        updateInventoryHighlights();
        applyCatalogFilters();
    }

    $catalogSearch.on('input', applyCatalogFilters);
    $catalogCategory.on('change', applyCatalogFilters);
    $catalogStatus.on('change', applyCatalogFilters);

    const $orderRows = $('#commerceOrderTable tbody tr');
    const $orderStatus = $('#commerceOrderStatus');
    const $orderSearch = $('#commerceOrderSearch');
    const $orderEmpty = $('#commerceOrderEmpty');

    function applyOrderFilters() {
        const status = ($orderStatus.val() || 'all').toString();
        const query = ($orderSearch.val() || '').toString().toLowerCase();
        let visible = 0;

        $orderRows.each(function(){
            const $row = $(this);
            const rowStatus = ($row.data('status') || '').toString();
            const orderId = ($row.data('orderId') || '').toString();
            const customer = ($row.data('customer') || '').toString();
            const channel = ($row.data('channel') || '').toString();

            const matchesStatus = status === 'all' || rowStatus === status;
            const matchesQuery = !query || orderId.indexOf(query) !== -1 || customer.indexOf(query) !== -1 || channel.indexOf(query) !== -1;

            if (matchesStatus && matchesQuery) {
                $row.removeAttr('hidden');
                visible += 1;
            } else {
                $row.attr('hidden', 'hidden');
            }
        });

        if (visible === 0) {
            $orderEmpty.removeAttr('hidden');
        } else {
            $orderEmpty.attr('hidden', 'hidden');
        }
    }

    $orderStatus.on('change', applyOrderFilters);
    $orderSearch.on('input', applyOrderFilters);

    const $customerRows = $('#commerceCustomerTable tbody tr');
    const $customerSegment = $('#commerceCustomerSegment');
    const $customerStatus = $('#commerceCustomerStatus');
    const $customerEmpty = $('#commerceCustomerEmpty');

    function applyCustomerFilters() {
        const segment = ($customerSegment.val() || 'all').toString();
        const status = ($customerStatus.val() || 'all').toString();
        let visible = 0;

        $customerRows.each(function(){
            const $row = $(this);
            const rowSegment = ($row.data('segment') || '').toString();
            const rowStatus = ($row.data('status') || '').toString();

            const matchesSegment = segment === 'all' || rowSegment === segment;
            const matchesStatus = status === 'all' || rowStatus === status;

            if (matchesSegment && matchesStatus) {
                $row.removeAttr('hidden');
                visible += 1;
            } else {
                $row.attr('hidden', 'hidden');
            }
        });

        if (visible === 0) {
            $customerEmpty.removeAttr('hidden');
        } else {
            $customerEmpty.attr('hidden', 'hidden');
        }
    }

    $customerSegment.on('change', applyCustomerFilters);
    $customerStatus.on('change', applyCustomerFilters);

    const $customerOrderButtons = $('[data-commerce-customer-orders]');

    function focusOrdersForCustomer(customerName) {
        setActiveWorkspace('orders');

        if ($orderStatus.length) {
            $orderStatus.val('all');
        }

        if ($orderSearch.length) {
            $orderSearch.val(customerName || '');
        }

        applyOrderFilters();

        if ($orderSearch.length) {
            $orderSearch.trigger('focus');
        }
    }

    $customerOrderButtons.on('click keydown', function(event){
        if (event.type === 'keydown' && !['Enter', ' ', 'Spacebar'].includes(event.key)) {
            return;
        }

        event.preventDefault();

        const customerName = ($(this).data('customerName') || '').toString();
        focusOrdersForCustomer(customerName);
    });

    function sortCategories(list) {
        return list.slice().sort(function(a, b){
            return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
        });
    }

    const $categoryForm = $('#commerceCategoryForm');
    const $categorySelect = $('#commerceCategorySelect');
    const $categoryIdInput = $('#commerceCategoryId');
    const $categoryNameInput = $('#commerceCategoryName');
    const $categoryReset = $('#commerceCategoryReset');
    const $categorySubmit = $('#commerceCategorySubmit');
    const $categoryList = $('#commerceCategoryList');
    const $categorySummary = $('#commerceCategorySummary');

    function renderCategorySelect() {
        if (!$categorySelect.length) {
            return;
        }
        const selected = $categoryIdInput.val();
        const options = ['<option value="">Create new category</option>'];
        sortCategories(categories).forEach(function(category){
            options.push(`<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`);
        });
        $categorySelect.html(options.join(''));
        if (selected && categories.some(function(category){ return category.id === selected; })) {
            $categorySelect.val(selected);
        } else {
            $categorySelect.val('');
        }
    }

    function renderCategoryList() {
        const items = sortCategories(categories);
        const chips = items.length
            ? items.map(function(category){
                return `<li class="commerce-chip" data-category-id="${escapeHtml(category.id)}">${escapeHtml(category.name)}</li>`;
            }).join('')
            : '<li class="commerce-chip commerce-chip--empty">No categories yet</li>';
        if ($categoryList.length) {
            $categoryList.html(chips);
        }
        if ($categorySummary.length) {
            $categorySummary.html(chips);
        }
    }

    function renderCategoryFilter() {
        if (!$catalogCategory.length) {
            return;
        }
        const current = ($catalogCategory.val() || 'all').toString();
        const options = ['<option value="all">All categories</option>'];
        sortCategories(categories).forEach(function(category){
            options.push(`<option value="${escapeHtml(category.slug)}">${escapeHtml(category.name)}</option>`);
        });
        $catalogCategory.html(options.join(''));
        if (current !== 'all' && categories.some(function(category){ return category.slug === current; })) {
            $catalogCategory.val(current);
        } else {
            $catalogCategory.val('all');
        }
    }

    const $productCategoryOptions = $('#commerceProductCategoryOptions');
    function renderProductCategoryOptions() {
        if (!$productCategoryOptions.length) {
            return;
        }
        const options = sortCategories(categories).map(function(category){
            return `<option value="${escapeHtml(category.name)}"></option>`;
        });
        $productCategoryOptions.html(options.join(''));
    }

    function resetCategoryForm() {
        if ($categoryForm.length) {
            $categoryForm[0].reset();
        }
        $categoryIdInput.val('');
        if ($categorySubmit.length) {
            $categorySubmit.text('Save category');
        }
        if ($categorySelect.length) {
            $categorySelect.val('');
        }
        $categoryNameInput.val('');
    }

    $categorySelect.on('change', function(){
        const id = ($(this).val() || '').toString();
        if (!id) {
            resetCategoryForm();
            renderCategorySelect();
            return;
        }
        const category = categories.find(function(item){
            return item.id === id;
        });
        if (!category) {
            resetCategoryForm();
            renderCategorySelect();
            return;
        }
        $categoryIdInput.val(category.id);
        $categoryNameInput.val(category.name).trigger('focus');
        if ($categorySubmit.length) {
            $categorySubmit.text('Update category');
        }
    });

    $categoryReset.on('click', function(){
        resetCategoryForm();
        renderCategorySelect();
    });

    $categoryForm.on('submit', function(event){
        event.preventDefault();
        if (!$categoryForm.length) {
            return;
        }
        const payload = {
            id: $categoryIdInput.val(),
            name: ($categoryNameInput.val() || '').toString().trim()
        };

        if (!payload.name) {
            showToast('showErrorToast', 'Category name is required.');
            $categoryNameInput.trigger('focus');
            return;
        }

        $.ajax({
            method: 'POST',
            url: 'modules/commerce/save_category.php',
            data: payload,
            dataType: 'json'
        }).done(function(response){
            if (!response || !response.success) {
                const message = response && response.message ? response.message : 'Unable to save category.';
                showToast('showErrorToast', message);
                return;
            }
            if (Array.isArray(response.categories)) {
                categories = response.categories;
            }
            if (Array.isArray(response.catalog)) {
                catalog = response.catalog;
            }
            renderCategorySelect();
            renderCategoryList();
            renderCategoryFilter();
            renderProductCategoryOptions();
            renderCatalogRows();
            resetCategoryForm();
            notifySuccess(response.message || 'Category saved.');
        }).fail(function(xhr){
            let message = 'Unable to save category.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showToast('showErrorToast', message);
        });
    });

    const $productForm = $('#commerceProductForm');
    const $productModalTitle = $('#commerceProductModalTitle');
    const $productOriginalSku = $('#commerceProductOriginalSku');
    const $productSku = $('#commerceProductSku');
    const $productName = $('#commerceProductName');
    const $productCategoryInput = $('#commerceProductCategory');
    const $productPrice = $('#commerceProductPrice');
    const $productInventory = $('#commerceProductInventory');
    const $productStatus = $('#commerceProductStatus');
    const $productVisibility = $('#commerceProductVisibility');
    const $productUpdated = $('#commerceProductUpdated');
    const $productSubmit = $('#commerceProductSubmit');
    const $productReset = $('#commerceProductReset');

    function getTodayDate() {
        const now = new Date();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${now.getFullYear()}-${month}-${day}`;
    }

    function resetProductForm() {
        if ($productForm.length) {
            $productForm[0].reset();
        }
        $productOriginalSku.val('');
        if ($productSubmit.length) {
            $productSubmit.text('Add product');
        }
        if ($productUpdated.length) {
            $productUpdated.val(getTodayDate());
        }
        if ($productModalTitle.length) {
            $productModalTitle.text('Add new product');
        }
    }

    function loadProductForEdit(product) {
        if (!product || !$productForm.length) {
            return;
        }
        $productOriginalSku.val(product.sku || '');
        $productSku.val(product.sku || '');
        $productName.val(product.name || '');
        $productCategoryInput.val(product.category || '');
        if (product.price !== undefined && product.price !== null && product.price !== '') {
            $productPrice.val(Number(product.price));
        } else {
            $productPrice.val('');
        }
        if (product.inventory !== undefined && product.inventory !== null && product.inventory !== '') {
            $productInventory.val(parseInt(product.inventory, 10));
        } else {
            $productInventory.val('');
        }
        if (product.status) {
            $productStatus.val(product.status);
        }
        if (product.visibility) {
            $productVisibility.val(product.visibility);
        }
        $productUpdated.val(product.updated || getTodayDate());
        if ($productSubmit.length) {
            $productSubmit.text('Update product');
        }
        if ($productModalTitle.length) {
            $productModalTitle.text('Edit product');
        }
        $productName.trigger('focus');
    }

    $productReset.on('click', function(){
        closeCommerceModal('commerceProductModal');
    });

    $productForm.on('submit', function(event){
        event.preventDefault();
        if (!$productForm.length) {
            return;
        }
        const payload = {
            original_sku: ($productOriginalSku.val() || '').toString(),
            sku: ($productSku.val() || '').toString().trim(),
            name: ($productName.val() || '').toString().trim(),
            category: ($productCategoryInput.val() || '').toString().trim(),
            price: ($productPrice.val() || '').toString(),
            inventory: ($productInventory.val() || '').toString(),
            status: ($productStatus.val() || '').toString(),
            visibility: ($productVisibility.val() || '').toString(),
            updated: ($productUpdated.val() || '').toString()
        };

        if (!payload.sku) {
            showToast('showErrorToast', 'SKU is required.');
            $productSku.trigger('focus');
            return;
        }
        if (!payload.name) {
            showToast('showErrorToast', 'Product name is required.');
            $productName.trigger('focus');
            return;
        }
        if (!payload.category) {
            showToast('showErrorToast', 'Product category is required.');
            $productCategoryInput.trigger('focus');
            return;
        }

        $.ajax({
            method: 'POST',
            url: 'modules/commerce/save_product.php',
            data: payload,
            dataType: 'json'
        }).done(function(response){
            if (!response || !response.success) {
                const message = response && response.message ? response.message : 'Unable to save product.';
                showToast('showErrorToast', message);
                return;
            }
            if (Array.isArray(response.catalog)) {
                catalog = response.catalog;
            }
            if (Array.isArray(response.categories)) {
                categories = response.categories;
            }
            renderCategorySelect();
            renderCategoryList();
            renderCategoryFilter();
            renderProductCategoryOptions();
            renderCatalogRows();
            closeCommerceModal('commerceProductModal');
            notifySuccess(response.message || 'Product saved.');
        }).fail(function(xhr){
            let message = 'Unable to save product.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showToast('showErrorToast', message);
        });
    });

    $('#commerceCatalogTable').on('click', '[data-action="edit-product"]', function(){
        const sku = ($(this).data('sku') || '').toString();
        if (!sku) {
            return;
        }
        const product = catalog.find(function(item){
            return (item.sku || '').toString() === sku;
        });
        if (!product) {
            showToast('showErrorToast', 'Unable to find the selected product.');
            return;
        }
        setActiveWorkspace('catalog');
        openCommerceModal('commerceProductModal');
        loadProductForEdit(product);
    });

    $('[data-commerce-open-modal]').on('click', function(){
        const target = ($(this).data('commerceOpenModal') || '').toString();
        if (!target) {
            return;
        }
        if (target === 'commerceProductModal') {
            resetProductForm();
        }
        if (target === 'commerceCategoryModal') {
            resetCategoryForm();
            renderCategorySelect();
        }
        openCommerceModal(target);
    });

    $('[data-commerce-close-modal]').on('click', function(){
        const target = ($(this).data('commerceCloseModal') || '').toString();
        if (!target) {
            return;
        }
        closeCommerceModal(target);
    });

    $('.commerce-modal').on('click', function(event){
        if (event.target === this) {
            const id = $(this).attr('id');
            if (id) {
                closeCommerceModal(id);
            }
        }
    });

    $(document).on('keydown', function(event){
        if (event.key === 'Escape' && modalState.active) {
            closeCommerceModal(modalState.active);
        }
    });

    const $alerts = $('#commerceAlerts .commerce-alert-item');
    $('[data-commerce-action="resolve-alerts"]').on('click', function(){
        if (!$alerts.length) {
            notifyInfo('There are no alerts to resolve.');
            return;
        }
        $alerts.addClass('is-resolved');
        $('#commerceAlerts').attr('data-all-resolved', 'true');
        notifySuccess('All operational alerts are now tracked as resolved.');
    });

    $('#commerceRefresh').on('click', function(){
        const now = new Date();
        const timestamp = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        $('#commerceLastUpdated').text(`Updated ${timestamp}`);
        notifySuccess('Commerce metrics refreshed using the latest dataset.');
    });

    $('#commerceExport').on('click', function(){
        const reportSummary = `Revenue: ${formatCurrency(summary.total_revenue || 0)} | Orders: ${(summary.orders || 0).toLocaleString()} | Conversion: ${formatPercent(reports.conversion_rate || 0)}`;
        notifyInfo(`Summary exported: ${reportSummary}`);
    });

    $('[data-commerce-toggle]').on('change', function(){
        const $input = $(this);
        const key = ($input.data('commerceToggle') || '').toString();
        const state = $input.is(':checked') ? 'enabled' : 'disabled';
        notifySuccess(`${key.replace(/_/g, ' ')} ${state}.`);
    });

    $('[data-benchmark]').each(function(){
        const $item = $(this);
        const benchmark = ($item.data('benchmark') || '').toString();
        const target = Number($item.data('target'));
        let actual = 0;

        if (benchmark === 'conversion') {
            actual = Number(reports.conversion_rate || 0);
        } else if (benchmark === 'refunds') {
            actual = Number(summary.refund_rate || 0);
        } else if (benchmark === 'repeat') {
            actual = Number(summary.repeat_purchase_rate || 0);
        }

        if (!Number.isFinite(target) || !Number.isFinite(actual)) {
            return;
        }

        if (benchmark === 'refunds') {
            if (actual <= target) {
                $item.addClass('is-on-track');
            } else {
                $item.addClass('is-at-risk');
            }
        } else if (actual >= target) {
            $item.addClass('is-on-track');
        } else {
            $item.addClass('is-at-risk');
        }
    });

    // Initialise state on load
    renderCategorySelect();
    renderCategoryList();
    renderCategoryFilter();
    renderProductCategoryOptions();
    resetProductForm();
    renderCatalogRows();
    applyOrderFilters();
    applyCustomerFilters();
    setActiveWorkspace('dashboard');
});
