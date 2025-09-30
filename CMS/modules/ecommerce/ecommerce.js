(function ($) {
    'use strict';

    function formatDate(value) {
        if (!value) {
            return '';
        }
        var parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return value;
        }
        return parsed.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function setPageTitle(viewLabel) {
        if (!viewLabel) {
            return;
        }
        var $pageTitle = $('#pageTitle');
        if ($pageTitle.length) {
            $pageTitle.text('E-commerce · ' + viewLabel);
        }
    }

    function renderBreadcrumb($container, activeView, labels) {
        if (!$container || !$container.length) {
            return;
        }
        $container.empty();

        var $rootItem = $('<li/>', { 'class': 'module-breadcrumbs-item' });
        if (activeView === 'dashboard') {
            $('<span/>', {
                text: 'E-commerce',
                'aria-current': 'page'
            }).appendTo($rootItem);
        } else {
            $('<a/>', {
                'class': 'ecommerce-breadcrumb',
                'data-view': 'dashboard',
                href: '#',
                text: 'E-commerce'
            }).appendTo($rootItem);
            $('<span/>', {
                'class': 'module-breadcrumbs-separator',
                'aria-hidden': 'true',
                text: '/'
            }).appendTo($rootItem);
        }
        $rootItem.appendTo($container);

        if (activeView !== 'dashboard') {
            var $detailItem = $('<li/>', { 'class': 'module-breadcrumbs-item' });
            $('<span/>', {
                text: labels[activeView] || activeView,
                'aria-current': 'page'
            }).appendTo($detailItem);
            $detailItem.appendTo($container);
        }
    }

    function initOrderDetail($module, currencyFormatter) {
        var $detail = $module.find('#orderDetail');
        var $content = $module.find('#orderContent');
        var $placeholder = $module.find('#orderPlaceholder');
        var $itemsBody = $module.find('#orderDetailItems');
        var $timeline = $module.find('#orderDetailTimeline');

        function render(order) {
            if (!order) {
                $content.attr('hidden', true);
                $placeholder.removeAttr('hidden');
                $module.find('#orderDetailId').text('Order');
                $module.find('#orderDetailStatus').text('Status');
                $itemsBody.empty();
                $timeline.empty();
                return;
            }

            $placeholder.attr('hidden', true);
            $content.removeAttr('hidden');

            $detail.attr('data-order-id', order.id || '');
            $module.find('#orderDetailId').text(order.id || 'Order');
            $module.find('#orderDetailStatus').text(order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'Status');

            var customerLines = [];
            if (order.customer) {
                customerLines.push(order.customer);
            }
            if (order.shipping_address) {
                var addr = order.shipping_address;
                if (addr.email) {
                    customerLines.push(addr.email);
                }
                var addressParts = [addr.line1, addr.city, addr.state, addr.postal_code, addr.country].filter(Boolean);
                if (addressParts.length) {
                    customerLines.push(addressParts.join(', '));
                }
            }
            $module.find('#orderDetailCustomer').html(customerLines.join('<br>'));

            $itemsBody.empty();
            if (Array.isArray(order.items)) {
                order.items.forEach(function (item) {
                    var subtotal = (item.quantity || 0) * (item.price || 0);
                    var $row = $('<tr/>');
                    $('<td/>', { text: item.name || '' }).appendTo($row);
                    $('<td/>', { text: item.sku || '' }).appendTo($row);
                    $('<td/>', { text: item.quantity || 0 }).appendTo($row);
                    $('<td/>', { text: currencyFormatter(item.price || 0) }).appendTo($row);
                    $('<td/>', { text: currencyFormatter(subtotal) }).appendTo($row);
                    $row.appendTo($itemsBody);
                });
            }

            $module.find('#orderDetailTotal').text(currencyFormatter(order.total || 0));
            $module.find('#orderDetailTax').text(currencyFormatter(order.tax || 0));
            $module.find('#orderDetailShipping').text(currencyFormatter(order.shipping_total || 0));
            $module.find('#orderDetailTransaction').text(order.transaction_reference || '—');

            $timeline.empty();
            if (Array.isArray(order.shipping_events) && order.shipping_events.length) {
                order.shipping_events.forEach(function (event) {
                    var $item = $('<li/>');
                    $('<span/>', { 'class': 'timeline-label', text: event.label || '' }).appendTo($item);
                    $('<span/>', { 'class': 'timeline-time', text: formatDate(event.timestamp) }).appendTo($item);
                    $item.appendTo($timeline);
                });
            }
        }

        return {
            render: render
        };
    }

    function initOrders($module, labels, currencyFormatter) {
        var $table = $module.find('#ordersTable tbody');
        var $statusFilter = $module.find('#orderStatusFilter');
        var $paymentFilter = $module.find('#orderPaymentFilter');
        var $dateStart = $module.find('#orderDateStart');
        var $dateEnd = $module.find('#orderDateEnd');
        var $emptyRow = $module.find('#ordersTable tbody .ecommerce-empty-state').closest('tr');
        var detail = initOrderDetail($module, currencyFormatter);
        var activeOrderId = null;

        function parseOrder($row) {
            var data = $row.data('orderParsed');
            if (!data) {
                var raw = $row.attr('data-order');
                if (!raw) {
                    return null;
                }
                try {
                    data = JSON.parse(raw);
                    $row.data('orderParsed', data);
                } catch (err) {
                    console.error('Unable to parse order payload', err);
                    return null;
                }
            }
            return data;
        }

        function applyFilters() {
            var statusValue = ($statusFilter.val() || '').toLowerCase();
            var paymentValue = ($paymentFilter.val() || '').toLowerCase();
            var startValue = $dateStart.val() ? new Date($dateStart.val()) : null;
            var endValue = $dateEnd.val() ? new Date($dateEnd.val()) : null;
            if (endValue) {
                endValue.setHours(23, 59, 59, 999);
            }

            var visibleRows = 0;
            $table.find('tr').each(function () {
                var $row = $(this);
                if ($row.is($emptyRow)) {
                    return;
                }
                var order = parseOrder($row);
                if (!order) {
                    $row.hide();
                    return;
                }
                var matches = true;
                if (statusValue && (order.status || '').toLowerCase() !== statusValue) {
                    matches = false;
                }
                if (matches && paymentValue && (order.payment_method || '').toLowerCase() !== paymentValue) {
                    matches = false;
                }
                if (matches && startValue) {
                    var orderDate = order.submitted_at ? new Date(order.submitted_at) : null;
                    if (!orderDate || orderDate < startValue) {
                        matches = false;
                    }
                }
                if (matches && endValue) {
                    var endDate = order.submitted_at ? new Date(order.submitted_at) : null;
                    if (!endDate || endDate > endValue) {
                        matches = false;
                    }
                }

                if (matches) {
                    $row.show();
                    visibleRows += 1;
                } else {
                    $row.hide();
                    if (activeOrderId && order.id === activeOrderId) {
                        detail.render(null);
                        activeOrderId = null;
                    }
                }
            });

            if ($emptyRow.length) {
                if (visibleRows === 0) {
                    $emptyRow.show();
                } else {
                    $emptyRow.hide();
                }
            }
        }

        $statusFilter.on('change', applyFilters);
        $paymentFilter.on('change', applyFilters);
        $dateStart.on('change', applyFilters);
        $dateEnd.on('change', applyFilters);

        $table.on('click', 'tr', function (event) {
            var $row = $(this);
            if ($row.is($emptyRow)) {
                return;
            }
            if ($(event.target).is('input[type="checkbox"]')) {
                return;
            }
            var order = parseOrder($row);
            if (!order) {
                return;
            }
            $table.find('tr').removeClass('is-selected');
            $row.addClass('is-selected');
            activeOrderId = order.id;
            detail.render(order);
        });

        applyFilters();
    }

    function initProductDrawer($module) {
        var $drawer = $module.find('#productDrawer');
        var $form = $module.find('#productForm');
        var $name = $form.find('#productName');
        var $sku = $form.find('#productSku');
        var $regenerate = $form.find('#regenerateSku');
        var $descriptionEditor = $form.find('#productLongDescription');
        var $descriptionInput = $form.find('#productLongDescriptionInput');
        var $scheduleField = $form.find('.schedule-field');
        var $imageInput = $form.find('#productImage');
        var $imageInfo = $form.find('#productImageInfo');
        var tags = [];
        var $tagInput = $form.find('#productTags');
        var autocompleteData = [];

        try {
            var attr = $tagInput.attr('data-autocomplete');
            if (attr) {
                autocompleteData = JSON.parse(attr);
            }
        } catch (err) {
            autocompleteData = [];
        }

        function openDrawer() {
            $drawer.attr('aria-hidden', 'false');
            $drawer.addClass('is-open');
            $form.trigger('reset');
            tags = [];
            renderTags();
            $descriptionEditor.empty();
            $descriptionInput.val('');
            $imageInfo.attr('hidden', true).text('');
        }

        function closeDrawer() {
            $drawer.attr('aria-hidden', 'true');
            $drawer.removeClass('is-open');
        }

        function generateSku() {
            var nameValue = $name.val() || '';
            var base = nameValue.trim().toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            if (!base) {
                base = 'SKU';
            }
            var suffix = Math.floor(Math.random() * 9000 + 1000);
            $sku.val(base + '-' + suffix);
        }

        function renderTags() {
            var $wrapper = $form.find('.ecommerce-tag-list');
            if (!$wrapper.length) {
                $wrapper = $('<div/>', { 'class': 'ecommerce-tag-list' }).insertAfter($tagInput);
            }
            $wrapper.empty();
            if (!tags.length) {
                $wrapper.attr('hidden', true);
                return;
            }
            $wrapper.removeAttr('hidden');
            tags.forEach(function (tag, index) {
                var $tag = $('<span/>', { 'class': 'ecommerce-tag', text: tag });
                $('<button/>', {
                    type: 'button',
                    'class': 'ecommerce-tag-remove',
                    'aria-label': 'Remove ' + tag,
                    html: '&times;'
                }).data('index', index).appendTo($tag);
                $tag.appendTo($wrapper);
            });
        }

        function addTag(value) {
            var trimmed = (value || '').trim();
            if (!trimmed) {
                return;
            }
            if (tags.indexOf(trimmed) !== -1) {
                return;
            }
            tags.push(trimmed);
            renderTags();
        }

        $module.on('click', '[data-action="open-product-drawer"]', function () {
            openDrawer();
        });
        $module.on('click', '[data-action="close-product-drawer"]', function () {
            closeDrawer();
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            $descriptionInput.val($descriptionEditor.html().trim());
            $tagInput.val(tags.join(', '));
            // Placeholder save handler
            $form.trigger('ecommerce:product:submit');
            closeDrawer();
        });

        $name.on('blur', function () {
            if ($sku.val()) {
                return;
            }
            generateSku();
        });

        $regenerate.on('click', function (event) {
            event.preventDefault();
            generateSku();
        });

        $form.on('click', '.ecommerce-wysiwyg-toolbar button', function (event) {
            event.preventDefault();
            var command = $(this).data('format');
            if (command) {
                document.execCommand(command, false, null);
                $descriptionEditor.trigger('input');
            }
        });

        $descriptionEditor.on('input', function () {
            $descriptionInput.val($descriptionEditor.html().trim());
        });

        $form.find('input[name="status"]').on('change', function () {
            if (this.value === 'scheduled') {
                $scheduleField.removeAttr('hidden');
            } else {
                $scheduleField.attr('hidden', true);
                $form.find('#productSchedule').val('');
            }
        });

        $imageInput.on('change', function () {
            var file = this.files && this.files[0];
            if (!file) {
                $imageInfo.attr('hidden', true).text('');
                return;
            }
            $imageInfo.text(file.name + ' • ' + Math.round(file.size / 1024) + ' KB');
            $imageInfo.removeAttr('hidden');
        });

        $form.find('#productImageDropzone').on('dragover', function (event) {
            event.preventDefault();
            event.originalEvent.dataTransfer.dropEffect = 'copy';
            $(this).addClass('is-dragging');
        }).on('dragleave drop', function (event) {
            event.preventDefault();
            $(this).removeClass('is-dragging');
            if (event.type === 'drop') {
                var files = event.originalEvent.dataTransfer.files;
                if (files && files.length) {
                    $imageInput[0].files = files;
                    $imageInput.trigger('change');
                }
            }
        });

        $tagInput.on('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                addTag($tagInput.val());
                $tagInput.val('');
            } else if (event.key === 'Backspace' && !$tagInput.val() && tags.length) {
                tags.pop();
                renderTags();
            }
        });

        $form.on('click', '.ecommerce-tag-remove', function () {
            var index = $(this).data('index');
            tags.splice(index, 1);
            renderTags();
        });

        $tagInput.on('input', function () {
            var value = $tagInput.val().toLowerCase();
            var suggestion = autocompleteData.find(function (item) {
                return item.toLowerCase().indexOf(value) === 0;
            });
            if (suggestion) {
                $tagInput.attr('aria-description', 'Suggested tag ' + suggestion);
            } else {
                $tagInput.removeAttr('aria-description');
            }
        });

        return {
            open: openDrawer,
            close: closeDrawer
        };
    }

    function initNotificationTabs($module) {
        var $tabs = $module.find('.notification-tab');
        var $editors = $module.find('[data-template-editor]');

        $tabs.on('click', function () {
            var $tab = $(this);
            var template = $tab.data('template');
            $tabs.attr('aria-selected', 'false');
            $tab.attr('aria-selected', 'true');
            $editors.each(function () {
                var $editor = $(this);
                if ($editor.data('template-editor') === template) {
                    $editor.removeAttr('hidden');
                } else {
                    $editor.attr('hidden', true);
                }
            });
        });
    }

    function initSubNav($module, labels) {
        var $buttons = $module.find('.ecommerce-subnav-button');
        var $panels = $module.find('.ecommerce-panel');
        var $breadcrumbs = $module.find('.module-breadcrumbs-list');

        function setActive(view) {
            $module.attr('data-active-view', view);
            $buttons.each(function () {
                var $btn = $(this);
                if ($btn.data('view') === view) {
                    $btn.attr('aria-current', 'page');
                    $btn.addClass('is-active');
                } else {
                    $btn.removeAttr('aria-current');
                    $btn.removeClass('is-active');
                }
            });
            $panels.each(function () {
                var $panel = $(this);
                if ($panel.data('panel') === view) {
                    $panel.removeAttr('hidden');
                } else {
                    $panel.attr('hidden', true);
                }
            });
            renderBreadcrumb($breadcrumbs, view, labels);
            setPageTitle(labels[view] || view);

            $(document).trigger('sparkcms:ecommerce:viewChange', {
                view: view
            });
        }

        $buttons.on('click', function () {
            setActive($(this).data('view'));
        });

        $module.on('click', '.ecommerce-breadcrumb', function (event) {
            event.preventDefault();
            setActive($(this).data('view'));
        });

        return setActive;
    }

    function initQuickActions($module, setActiveView) {
        $module.on('click', '[data-action="add-product"]', function () {
            setActiveView('products');
            $module.find('[data-action="open-product-drawer"]').first().trigger('click');
        });
        $module.on('click', '[data-action="view-orders"]', function () {
            setActiveView('orders');
        });
    }

    $(function () {
        var $module = $('#ecommerce');
        if (!$module.length) {
            return;
        }

        var labels = {
            dashboard: 'Dashboard',
            products: 'Products',
            orders: 'Orders',
            customers: 'Customers',
            reports: 'Reports',
            settings: 'Settings'
        };

        var currencySymbol = $module.data('currency') || '$';
        function currencyFormatter(value) {
            if (isNaN(value)) {
                return currencySymbol + '0.00';
            }
            var amount = Number(value);
            var fractionDigits = Math.floor(amount) === amount ? 0 : 2;
            return currencySymbol + amount.toFixed(fractionDigits);
        }

        var setActive = initSubNav($module, labels);
        initQuickActions($module, setActive);
        initOrders($module, labels, currencyFormatter);
        var drawer = initProductDrawer($module);
        initNotificationTabs($module);

        var initialView = $module.data('active-view') || 'dashboard';
        setActive(initialView);

        $(document).on('sparkcms:ecommerce:navigate', function (event, payload) {
            if (!payload || !payload.view) {
                return;
            }
            setActive(payload.view);
            if (payload.view === 'products') {
                drawer.open();
            }
        });
    });
}(jQuery));
