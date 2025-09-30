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

    const $workspaceTabs = $('[data-commerce-workspace]');
    const $workspacePanels = $('[data-commerce-panel]');

    function setActiveWorkspace(workspace) {
        $workspaceTabs.each(function(){
            const $tab = $(this);
            const isActive = $tab.data('commerceWorkspace') === workspace;
            $tab.toggleClass('active', isActive);
            $tab.attr('aria-selected', isActive ? 'true' : 'false');
            if (isActive) {
                $tab.attr('tabindex', '0');
            } else {
                $tab.attr('tabindex', '-1');
            }
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

    const $inventoryCells = $('[data-inventory]');
    const threshold = Number(settings.low_inventory_threshold) || 0;
    if ($inventoryCells.length && threshold) {
        $inventoryCells.each(function(){
            const $cell = $(this);
            const quantity = Number($cell.data('inventory'));
            if (Number.isFinite(quantity) && quantity <= threshold) {
                $cell.addClass('is-low');
                $cell.attr('aria-label', `${quantity} in stock, below threshold of ${threshold}`);
            }
        });
    }

    const $catalogRows = $('#commerceCatalogTable tbody tr');
    const $catalogSearch = $('#commerceCatalogSearch');
    const $catalogCategory = $('#commerceCatalogCategory');
    const $catalogStatus = $('#commerceCatalogStatus');
    const $catalogEmpty = $('#commerceCatalogEmpty');

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
    setActiveWorkspace('dashboard');
    applyCatalogFilters();
    applyOrderFilters();
    applyCustomerFilters();
});
