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
    const refreshButtonDefaultText = $refreshButton.length ? $refreshButton.find('span').text().trim() : '';
    const dateFormatter = typeof Intl !== 'undefined'
        ? new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        })
        : null;

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

        if (!timestamp) {
            $lastUpdated.text('Unable to refresh insights. Please try again.');
            return;
        }

        const date = new Date(timestamp);
        const formatted = dateFormatter ? dateFormatter.format(date) : date.toLocaleString();
        $lastUpdated.text(`Last updated ${formatted}`);
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

        modules.forEach(function (module) {
            const id = module.id || module.module || module.name || '';
            const name = module.module || module.name || id || '';
            const primary = module.primary || '—';
            const secondary = module.secondary || '';
            const trend = module.trend || '';
            const statusKey = String(module.status || 'ok').toLowerCase();
            const statusClass = statusClassMap[statusKey] || statusClassMap.ok;
            const statusLabel = module.statusLabel || statusLabelFallback[statusKey] || statusLabelFallback.ok;
            const cta = module.cta || `Open ${name}`;

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
                class: 'dashboard-module-cta',
                text: cta
            });

            $footer.append($ctaButton);
            $card.append($footer);

            $grid.append($card);
        });

        $grid.attr('aria-busy', 'false');
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
        $('#dashboardModuleCards').attr('aria-busy', 'true');

        return $.getJSON('modules/dashboard/dashboard_data.php', function(data){
            updateText('#statPages', data.pages);
            updateText('#statPagesBreakdown', [data.pagesPublished, data.pagesDraft], function(values){
                const published = formatNumber(values[0] || 0);
                const drafts = formatNumber(values[1] || 0);
                return `Published: ${published} • Drafts: ${drafts}`;
            });
            updateText('#statMedia', data.media);
            updateText('#statMediaSize', data.mediaSize, function(value){
                return `Library size: ${formatBytes(value)}`;
            });
            updateText('#statUsers', data.users);
            updateText('#statUsersBreakdown', [data.usersAdmins, data.usersEditors], function(values){
                const admins = formatNumber(values[0] || 0);
                const editors = formatNumber(values[1] || 0);
                return `Admins: ${admins} • Editors: ${editors}`;
            });
            updateText('#statViews', data.views);
            updateText('#statViewsAverage', data.analyticsAvgViews, function(value){
                return `Average per page: ${formatNumber(value || 0)}`;
            });

            updateText('#statSeoScore', data.seoScore, formatPercent);
            updateText('#statSeoBreakdown', [data.seoOptimized, data.seoNeedsAttention], function(values){
                const optimized = formatNumber(values[0]);
                const attention = formatNumber(values[1]);
                return `Optimized: ${optimized} • Needs attention: ${attention}`;
            });
            updateText('#statSeoMetadata', data.seoMetadataGaps, function(value){
                return `Metadata gaps: ${formatNumber(value)}`;
            });

            updateText('#statAccessibilityScore', data.accessibilityScore, formatPercent);
            updateText('#statAccessibilityBreakdown', [data.accessibilityCompliant, data.accessibilityNeedsReview], function(values){
                const compliant = formatNumber(values[0]);
                const review = formatNumber(values[1]);
                return `Compliant: ${compliant} • Needs review: ${review}`;
            });
            updateText('#statAccessibilityAlt', data.accessibilityMissingAlt, function(value){
                return `Alt text issues: ${formatNumber(value)}`;
            });

            updateText('#statAlerts', data.openAlerts);
            updateText('#statAlertsBreakdown', [data.alertsSeo, data.alertsAccessibility], function(values){
                const seo = formatNumber(values[0]);
                const accessibility = formatNumber(values[1]);
                return `SEO: ${seo} • Accessibility: ${accessibility}`;
            });

            renderModuleSummaries(data.moduleSummaries || data.modules || []);
        })
            .done(function(){
                updateLastUpdated(Date.now());
            })
            .fail(function(){
                updateLastUpdated(0);
                renderModuleSummaries([], { message: 'Unable to load module data' });
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
