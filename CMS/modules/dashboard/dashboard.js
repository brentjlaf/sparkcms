// File: dashboard.js
$(function(){
    function formatNumber(value) {
        if (typeof value === 'number') {
            return value.toLocaleString();
        }
        const numeric = parseInt(value, 10);
        if (Number.isNaN(numeric)) {
            return '0';
        }
        return numeric.toLocaleString();
    }

    function formatPercent(value) {
        if (typeof value !== 'number') {
            value = parseFloat(value);
        }
        if (!Number.isFinite(value)) {
            value = 0;
        }
        return `${Math.round(value)}%`;
    }

    function formatBytes(bytes) {
        const size = Number(bytes);
        if (!Number.isFinite(size) || size <= 0) {
            return '0 KB';
        }
        const units = ['bytes', 'KB', 'MB', 'GB'];
        let power = Math.floor(Math.log(size) / Math.log(1024));
        power = Math.max(0, Math.min(power, units.length - 1));
        const value = size / (1024 ** power);
        if (power === 0) {
            return `${formatNumber(size)} ${units[power]}`;
        }
        const display = value >= 10 ? Math.round(value) : Math.round(value * 10) / 10;
        return `${formatNumber(display)} ${units[power]}`;
    }

    function updateText(selector, value, formatter) {
        const $el = $(selector);
        if (!$el.length) {
            return;
        }
        const output = typeof formatter === 'function' ? formatter(value) : formatNumber(value);
        $el.text(output);
    }

    const $refreshButton = $('#dashboardRefresh');
    const $lastUpdated = $('#dashboardLastUpdated');
    const $metricsGrid = $('.dashboard-overview-grid');
    const $metricsStatus = $('#dashboardMetricsStatus');
    const refreshButtonDefaultText = $refreshButton.length ? $refreshButton.find('span').text().trim() : '';
    const dateFormatter = typeof Intl !== 'undefined'
        ? new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        })
        : null;

    const metricsMessages = {
        loading: 'Loading dashboard metrics…',
        updated: 'Dashboard metrics updated.',
        error: 'Unable to load dashboard metrics. Please try again.'
    };

    function setRefreshState(isLoading) {
        if (!$refreshButton.length) {
            return;
        }

        const $label = $refreshButton.find('span');
        if (isLoading) {
            $refreshButton.prop('disabled', true).attr('aria-busy', 'true');
            if ($label.length) {
                $label.data('previous', $label.text());
                $label.text('Refreshing…');
            }
        } else {
            $refreshButton.prop('disabled', false).removeAttr('aria-busy');
            if ($label.length) {
                const previous = $label.data('previous') || refreshButtonDefaultText || 'Refresh insights';
                $label.text(previous);
            }
        }
    }

    function updateLastUpdated(timestamp) {
        if (!$lastUpdated.length) {
            return;
        }

        let value = timestamp;
        if (typeof value === 'string' && value !== '') {
            const parsed = Date.parse(value);
            if (!Number.isNaN(parsed)) {
                value = parsed;
            }
        }

        if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0) {
            $lastUpdated.text('Unable to refresh insights. Please try again.');
            return;
        }

        const date = new Date(value);
        const formatted = dateFormatter ? dateFormatter.format(date) : date.toLocaleString();
        $lastUpdated.text(`Last updated ${formatted}`);
    }

    function setMetricsLoading(isLoading, statusText) {
        if (!$metricsGrid.length) {
            return;
        }

        $metricsGrid.attr('aria-busy', isLoading ? 'true' : 'false');

        const $cards = $metricsGrid.find('.dashboard-overview-card');
        if (isLoading) {
            $cards.addClass('is-loading');
            $cards.find('[data-stat]').attr('aria-hidden', 'true');
        } else {
            $cards.removeClass('is-loading');
            $cards.find('[data-stat]').removeAttr('aria-hidden');
        }

        if ($metricsStatus.length) {
            const message = statusText || (isLoading ? metricsMessages.loading : metricsMessages.updated);
            $metricsStatus.text(message);
        }
    }

    const statusClassMap = {
        urgent: 'status-urgent',
        warning: 'status-warning',
        ok: 'status-ok'
    };

    const statusLabelFallback = {
        urgent: 'Action required',
        warning: 'Needs attention',
        ok: 'On track'
    };

    const quickActionIcons = {
        pages: { icon: 'fa-solid fa-file-lines', className: 'pages' },
        media: { icon: 'fa-solid fa-images', className: 'media' },
        users: { icon: 'fa-solid fa-users', className: 'users' },
        analytics: { icon: 'fa-solid fa-chart-line', className: 'analytics' },
        blogs: { icon: 'fa-solid fa-newspaper', className: 'blogs' },
        forms: { icon: 'fa-solid fa-square-poll-horizontal', className: 'forms' },
        menus: { icon: 'fa-solid fa-bars', className: 'menus' },
        logs: { icon: 'fa-solid fa-clock-rotate-left', className: 'logs' },
        search: { icon: 'fa-solid fa-magnifying-glass', className: 'search' },
        settings: { icon: 'fa-solid fa-sliders', className: 'settings' },
        sitemap: { icon: 'fa-solid fa-sitemap', className: 'sitemap' },
        speed: { icon: 'fa-solid fa-gauge-high', className: 'speed' },
        events: { icon: 'fa-solid fa-ticket', className: 'events' },
        calendar: { icon: 'fa-solid fa-calendar-days', className: 'calendar' },
        accessibility: { icon: 'fa-solid fa-universal-access', className: 'accessibility' },
        default: { icon: 'fa-solid fa-circle-info', className: 'settings' }
    };

    function renderModuleSummaries(modules, options) {
        const $grid = $('#dashboardModuleCards');
        if (!$grid.length) {
            return;
        }

        const settings = options || {};
        $grid.empty();

        const message = settings.message || 'No module data available';

        if (!Array.isArray(modules) || modules.length === 0) {
            $grid.append(
                $('<article>', {
                    class: 'dashboard-module-card empty',
                    role: 'listitem',
                    tabindex: 0,
                    'aria-label': message
                }).append(
                    $('<header>', { class: 'dashboard-module-card-header' }).append(
                        $('<div>', { class: 'dashboard-module-card-title' }).append(
                            $('<span>', {
                                class: 'dashboard-module-name',
                                text: 'No insights available'
                            })
                        )
                    )
                ).append(
                    $('<p>', {
                        class: 'dashboard-module-secondary',
                        text: message
                    })
                )
            );
            $grid.attr('aria-busy', 'false');
            return;
        }

        const statusPriority = {
            urgent: 0,
            warning: 1,
            ok: 2
        };

        const sortedModules = Array.isArray(modules)
            ? modules.slice().sort(function (a, b) {
                const statusA = String(a && a.status ? a.status : 'ok').toLowerCase();
                const statusB = String(b && b.status ? b.status : 'ok').toLowerCase();
                const priorityA = Object.prototype.hasOwnProperty.call(statusPriority, statusA)
                    ? statusPriority[statusA]
                    : statusPriority.ok;
                const priorityB = Object.prototype.hasOwnProperty.call(statusPriority, statusB)
                    ? statusPriority[statusB]
                    : statusPriority.ok;

                if (priorityA === priorityB) {
                    const nameA = (a && (a.module || a.name || '')) || '';
                    const nameB = (b && (b.module || b.name || '')) || '';
                    return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
                }

                return priorityA - priorityB;
            })
            : modules;

        function capitalizeLastWord(text) {
            if (typeof text !== 'string') {
                return text;
            }

            const match = text.match(/(\S+)(\s*)$/);
            if (!match) {
                return text;
            }

            const [, lastWord, trailing] = match;
            if (!lastWord) {
                return text;
            }

            const capitalizedWord = lastWord.charAt(0).toUpperCase() + lastWord.slice(1);
            return text.slice(0, match.index) + capitalizedWord + (trailing || '');
        }

        sortedModules.forEach(function (module) {
            const id = module.id || module.module || module.name || '';
            const name = module.module || module.name || id || '';
            const primary = module.primary || '—';
            const secondary = module.secondary || '';
            const trend = module.trend || '';
            const statusKey = String(module.status || 'ok').toLowerCase();
            const statusClass = statusClassMap[statusKey] || statusClassMap.ok;
            const statusLabel = module.statusLabel || statusLabelFallback[statusKey] || statusLabelFallback.ok;
            const cta = capitalizeLastWord(module.cta || `Open ${name}`);

            const $card = $('<article>', {
                class: `dashboard-module-card ${statusClass}`,
                role: 'listitem',
                tabindex: module.id ? 0 : -1,
                'data-module': module.id || '',
                'aria-label': `${name} module – ${statusLabel}`
            });

            const $header = $('<header>', { class: 'dashboard-module-card-header' });
            const $title = $('<div>', { class: 'dashboard-module-card-title' });
            $title.append(
                $('<span>', {
                    class: 'dashboard-module-name',
                    text: name
                })
            );
            $title.append(
                $('<span>', {
                    class: 'dashboard-module-status',
                    text: statusLabel,
                    'aria-live': 'polite'
                })
            );
            $header.append($title);
            $header.append(
                $('<p>', {
                    class: 'dashboard-module-primary',
                    text: primary
                })
            );

            $card.append($header);

            if (secondary) {
                $card.append(
                    $('<p>', {
                        class: 'dashboard-module-secondary',
                        text: secondary
                    })
                );
            }

            const $footer = $('<footer>', { class: 'dashboard-module-card-footer' });
            if (trend) {
                $footer.append(
                    $('<span>', {
                        class: 'dashboard-module-trend',
                        text: trend
                    })
                );
            }

            const $ctaButton = $('<button>', {
                type: 'button',
                class: 'dashboard-module-cta a11y-btn a11y-btn--secondary',
                text: cta
            });

            $footer.append($ctaButton);
            $card.append($footer);

            $grid.append($card);
        });

        $grid.attr('aria-busy', 'false');
    }

    function renderQuickActions(modules) {
        const $quick = $('#dashboardQuickActions');
        if (!$quick.length) {
            return;
        }

        $quick.empty();

        if (!Array.isArray(modules) || modules.length === 0) {
            $quick.append(
                $('<article>', {
                    class: 'dashboard-quick-card empty',
                    role: 'listitem',
                    tabindex: -1,
                    'aria-label': 'No quick actions available'
                }).append(
                    $('<div>', { class: 'dashboard-quick-content' }).append(
                        $('<span>', {
                            class: 'dashboard-quick-label',
                            text: 'All caught up'
                        }),
                        $('<p>', {
                            class: 'dashboard-quick-description',
                            text: 'There are no modules that require attention right now.'
                        })
                    )
                )
            );
            $quick.attr('aria-busy', 'false');
            return;
        }

        const statusPriority = {
            urgent: 0,
            warning: 1,
            ok: 2
        };

        const attentionStatuses = ['urgent', 'warning'];

        const sortedModules = modules.slice().sort(function (a, b) {
            const statusA = String(a && a.status ? a.status : 'ok').toLowerCase();
            const statusB = String(b && b.status ? b.status : 'ok').toLowerCase();
            const priorityA = Object.prototype.hasOwnProperty.call(statusPriority, statusA)
                ? statusPriority[statusA]
                : statusPriority.ok;
            const priorityB = Object.prototype.hasOwnProperty.call(statusPriority, statusB)
                ? statusPriority[statusB]
                : statusPriority.ok;

            if (priorityA === priorityB) {
                const nameA = (a && (a.module || a.name || '')) || '';
                const nameB = (b && (b.module || b.name || '')) || '';
                return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
            }

            return priorityA - priorityB;
        });

        const seen = Object.create(null);
        const ordered = [];

        sortedModules.forEach(function (module) {
            const id = String(module && (module.id || module.module || module.name || '')).toLowerCase();
            if (!id || seen[id]) {
                return;
            }

            seen[id] = true;
            ordered.push(module);
        });

        const priorityModules = ordered.filter(function (module) {
            const status = String(module && module.status ? module.status : 'ok').toLowerCase();
            return attentionStatuses.indexOf(status) !== -1;
        });

        const fallbackModules = ordered.filter(function (module) {
            return priorityModules.indexOf(module) === -1;
        });

        const selection = priorityModules.concat(fallbackModules).slice(0, 3);

        if (selection.length === 0) {
            $quick.append(
                $('<article>', {
                    class: 'dashboard-quick-card empty',
                    role: 'listitem',
                    tabindex: -1,
                    'aria-label': 'No quick actions available'
                }).append(
                    $('<div>', { class: 'dashboard-quick-content' }).append(
                        $('<span>', {
                            class: 'dashboard-quick-label',
                            text: 'All caught up'
                        }),
                        $('<p>', {
                            class: 'dashboard-quick-description',
                            text: 'There are no modules that require attention right now.'
                        })
                    )
                )
            );
            $quick.attr('aria-busy', 'false');
            return;
        }

        selection.forEach(function (module) {
            const id = String(module && (module.id || module.module || module.name || '')).toLowerCase();
            const name = module.module || module.name || module.id || '';
            const statusKey = String(module.status || 'ok').toLowerCase();
            const statusLabel = module.statusLabel || statusLabelFallback[statusKey] || statusLabelFallback.ok;
            const detail = module.trend || module.secondary || module.primary || '';
            const description = detail ? statusLabel + ' — ' + detail : statusLabel;

            const iconConfig = Object.prototype.hasOwnProperty.call(quickActionIcons, id)
                ? quickActionIcons[id]
                : quickActionIcons.default;

            const $card = $('<article>', {
                class: 'dashboard-quick-card',
                role: 'listitem',
                tabindex: 0,
                'data-module': id,
                'aria-label': `${name} – ${description}`
            });

            const $icon = $('<span>', {
                class: `dashboard-quick-icon ${iconConfig.className}`,
                'aria-hidden': 'true'
            }).append(
                $('<i>', { class: iconConfig.icon })
            );

            const $content = $('<div>', { class: 'dashboard-quick-content' });
            $content.append(
                $('<span>', {
                    class: 'dashboard-quick-label',
                    text: name
                })
            );
            $content.append(
                $('<p>', {
                    class: 'dashboard-quick-description',
                    text: description
                })
            );

            const $arrow = $('<span>', {
                class: 'dashboard-quick-arrow',
                'aria-hidden': 'true'
            }).append(
                $('<i>', { class: 'fa-solid fa-arrow-right' })
            );

            $card.append($icon, $content, $arrow);
            $quick.append($card);
        });

        $quick.attr('aria-busy', 'false');
    }

    function navigateToModule(section) {
        if (!section) {
            return;
        }

        const target = String(section).trim();
        if (!target) {
            return;
        }

        const safeTarget = (typeof CSS !== 'undefined' && typeof CSS.escape === 'function')
            ? CSS.escape(target)
            : target.replace(/"/g, '\\"');
        const $navItem = $(`.nav-item[data-section="${safeTarget}"]`);

        if ($navItem.length) {
            $navItem.trigger('click');
            return;
        }

        $(document).trigger('sparkcms:navigate', { section: target });
    }

    function bindModuleNavigation() {
        $('#dashboardQuickActions')
            .on('click', '.dashboard-quick-card', function (event) {
                event.preventDefault();
                navigateToModule($(this).data('module'));
            })
            .on('keydown', '.dashboard-quick-card', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    navigateToModule($(this).data('module'));
                }
            });

        $('#dashboardModuleCards')
            .on('click', '.dashboard-module-card', function (event) {
                if ($(event.target).closest('.dashboard-module-cta').length) {
                    return;
                }

                const moduleId = $(this).data('module');
                if (moduleId) {
                    event.preventDefault();
                    navigateToModule(moduleId);
                }
            })
            .on('keydown', '.dashboard-module-card', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    const moduleId = $(this).data('module');
                    if (moduleId) {
                        event.preventDefault();
                        navigateToModule(moduleId);
                    }
                }
            })
            .on('click', '.dashboard-module-cta', function (event) {
                event.preventDefault();
                const $card = $(this).closest('.dashboard-module-card');
                const moduleId = $card.data('module');
                if (moduleId) {
                    navigateToModule(moduleId);
                }
            });
    }

    function loadStats(){
        setRefreshState(true);
        setMetricsLoading(true);
        $('#dashboardModuleCards').attr('aria-busy', 'true');
        $('#dashboardQuickActions').attr('aria-busy', 'true');

        const request = $.getJSON('modules/dashboard/dashboard_data.php', function(data){
            updateText('#statPages, [data-stat="pages"]', data.pages);
            updateText('#statPagesBreakdown, [data-stat="pages-breakdown"]', [data.pagesPublished, data.pagesDraft], function(values){
                const published = formatNumber(values[0] || 0);
                const drafts = formatNumber(values[1] || 0);
                return `Published: ${published} • Drafts: ${drafts}`;
            });
            updateText('#statMedia, [data-stat="media"]', data.media);
            updateText('#statMediaSize, [data-stat="media-size"]', data.mediaSize, function(value){
                return `Library size: ${formatBytes(value)}`;
            });
            updateText('#statUsers, [data-stat="users"]', data.users);
            updateText('#statUsersBreakdown, [data-stat="users-breakdown"]', [data.usersAdmins, data.usersEditors], function(values){
                const admins = formatNumber(values[0] || 0);
                const editors = formatNumber(values[1] || 0);
                return `Admins: ${admins} • Editors: ${editors}`;
            });
            updateText('#statViews, [data-stat="views"]', data.views);
            updateText('#statViewsAverage, [data-stat="views-average"]', data.analyticsAvgViews, function(value){
                return `Average per page: ${formatNumber(value || 0)}`;
            });

            updateText('#statAccessibilityScore, [data-stat="accessibility-score"]', data.accessibilityScore, formatPercent);
            updateText('#statAccessibilityBreakdown, [data-stat="accessibility-breakdown"]', [data.accessibilityCompliant, data.accessibilityNeedsReview], function(values){
                const compliant = formatNumber(values[0]);
                const review = formatNumber(values[1]);
                return `Compliant: ${compliant} • Needs review: ${review}`;
            });
            updateText('#statAccessibilityAlt, [data-stat="accessibility-alt"]', data.accessibilityMissingAlt, function(value){
                return `Alt text issues: ${formatNumber(value)}`;
            });

            updateText('#statAlerts, [data-stat="alerts"]', data.openAlerts);
            updateText('#statAlertsBreakdown, [data-stat="alerts-breakdown"]', data.alertsAccessibility, function(value){
                return `Accessibility reviews pending: ${formatNumber(value || 0)}`;
            });

            const modules = data.moduleSummaries || data.modules || [];
            renderModuleSummaries(modules);
            renderQuickActions(modules);
        });

        return request
            .done(function(response){
                const generatedAt = response && response.generatedAt ? response.generatedAt : Date.now();
                updateLastUpdated(generatedAt);
                setMetricsLoading(false, metricsMessages.updated);
            })
            .fail(function(){
                updateLastUpdated(null);
                renderModuleSummaries([], { message: 'Unable to load module data' });
                renderQuickActions([]);
                setMetricsLoading(false, metricsMessages.error);
            })
            .always(function(){
                setRefreshState(false);
            });
    }

    if ($refreshButton.length) {
        $refreshButton.on('click', function(){
            loadStats();
        });
    }

    bindModuleNavigation();
    loadStats();
});
