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
    const $activityList = $('#dashboardActivityTimeline');
    const $activityEmpty = $('#dashboardActivityEmpty');
    const refreshButtonDefaultText = $refreshButton.length ? $refreshButton.find('span').text().trim() : '';
    const dateFormatter = typeof Intl !== 'undefined'
        ? new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        })
        : null;
    const dayFormatter = typeof Intl !== 'undefined'
        ? new Intl.DateTimeFormat(undefined, {
            weekday: 'long',
            month: 'short',
            day: 'numeric'
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

    function renderModuleSummaries(modules) {
        const $table = $('#moduleSummaryTable tbody');
        if (!$table.length) {
            return;
        }
        $table.empty();

        if (!Array.isArray(modules) || modules.length === 0) {
            $table.append(
                $('<tr>').append(
                    $('<td>', {
                        text: 'No module data available',
                        colspan: 3
                    })
                )
            );
            return;
        }

        modules.forEach(function (module) {
            const name = module.module || module.name || module.id || '';
            const primary = module.primary || '—';
            const secondary = module.secondary || '';
            const $row = $('<tr>');
            if (module.id) {
                $row
                    .attr('data-module', module.id)
                    .attr('tabindex', '0')
                    .attr('role', 'button')
                    .addClass('dashboard-module-link');
            }
            $('<td>').text(name).appendTo($row);
            $('<td>').text(primary).appendTo($row);
            $('<td>').text(secondary).appendTo($row);
            $table.append($row);
        });
    }

    function renderRecentActivity(activity) {
        if (!$activityList.length) {
            return;
        }

        $activityList.empty();

        const hasItems = Array.isArray(activity) && activity.length > 0;

        if (!hasItems) {
            if ($activityEmpty.length) {
                $activityEmpty.removeAttr('hidden');
            }
            $activityList.attr('hidden', 'hidden');
            return;
        }

        if ($activityEmpty.length) {
            $activityEmpty.attr('hidden', 'hidden');
        }
        $activityList.removeAttr('hidden');

        const today = new Date();
        const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const groups = [];
        const groupIndex = {};

        activity.forEach(function (entry) {
            if (!entry || typeof entry !== 'object') {
                return;
            }

            const timeSeconds = Number(entry.time);
            const hasValidTime = Number.isFinite(timeSeconds) && timeSeconds > 0;
            const date = hasValidTime ? new Date(timeSeconds * 1000) : null;
            let label = 'Earlier activity';
            let key = 'unknown';
            let order = -Infinity;

            if (date) {
                const startOfDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                key = startOfDay.getTime();
                order = date.getTime();
                const diff = Math.round((todayStart.getTime() - startOfDay.getTime()) / 86400000);
                if (diff === 0) {
                    label = 'Today';
                } else if (diff === 1) {
                    label = 'Yesterday';
                } else if (dayFormatter) {
                    label = dayFormatter.format(date);
                } else {
                    label = startOfDay.toDateString();
                }
            }

            let group = groupIndex[key];
            if (!group) {
                group = { key, label, items: [], order };
                groupIndex[key] = group;
                groups.push(group);
            }

            if (order > group.order) {
                group.order = order;
            }

            group.items.push({ entry, date });
        });

        groups.sort(function (a, b) {
            return (b.order || -Infinity) - (a.order || -Infinity);
        });

        groups.forEach(function (group) {
            const $groupItem = $('<li>', { class: 'dashboard-activity-group' });
            $('<h4>', {
                class: 'dashboard-activity-group-title',
                text: group.label
            }).appendTo($groupItem);

            const $groupList = $('<ol>', { class: 'dashboard-activity-items' });

            group.items.sort(function (a, b) {
                return (b.entry && b.entry.time ? Number(b.entry.time) : 0) - (a.entry && a.entry.time ? Number(a.entry.time) : 0);
            });

            group.items.forEach(function (item) {
                const entry = item.entry;
                const actor = entry.actor || 'System';
                const action = entry.action || 'Updated content';
                const title = entry.title || '';
                const module = entry.module || '';
                const moduleLabel = entry.moduleLabel || (entry.context ? String(entry.context) : '');
                const context = entry.context || '';
                const targetId = entry.targetId;
                const timeIso = entry.timeIso || (item.date ? item.date.toISOString() : '');
                const formattedTime = item.date
                    ? (dateFormatter ? dateFormatter.format(item.date) : item.date.toLocaleString())
                    : 'Recently';

                const $item = $('<li>', { class: 'dashboard-activity-item' });
                const linkAttrs = {
                    class: 'dashboard-activity-link',
                    href: module ? '#' + module : '#'
                };

                if (module) {
                    linkAttrs['data-module'] = module;
                }
                if (context) {
                    linkAttrs['data-context'] = context;
                }
                if (targetId !== undefined && targetId !== null && targetId !== '') {
                    linkAttrs['data-target-id'] = targetId;
                }

                const $link = $('<a>', linkAttrs);

                const $time = $('<time>', {
                    class: 'dashboard-activity-time',
                    datetime: timeIso,
                    text: formattedTime
                });

                const $summary = $('<span>', { class: 'dashboard-activity-summary' })
                    .append($('<span>', { class: 'dashboard-activity-actor', text: actor }))
                    .append(' ')
                    .append(action);

                if (title) {
                    $summary.append(' ');
                    $summary.append($('<span>', { class: 'dashboard-activity-target', text: title }));
                }

                $link.append($time, $summary);

                if (moduleLabel) {
                    $link.append($('<span>', {
                        class: 'dashboard-activity-module',
                        text: moduleLabel
                    }));
                }

                $item.append($link);
                $groupList.append($item);
            });

            $groupItem.append($groupList);
            $activityList.append($groupItem);
        });
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

        $('#moduleSummaryTable').on('click', 'tbody tr[data-module]', function (event) {
            event.preventDefault();
            navigateToModule($(this).data('module'));
        });

        $('#moduleSummaryTable').on('keydown', 'tbody tr[data-module]', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navigateToModule($(this).data('module'));
            }
        });

        $('#dashboardActivityTimeline').on('click', '.dashboard-activity-link', function (event) {
            const module = $(this).data('module');
            if (module) {
                event.preventDefault();
                navigateToModule(module);
                return;
            }
            event.preventDefault();
        });
    }

    function loadStats(){
        setRefreshState(true);

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
            renderRecentActivity(data.recentActivity || []);
        })
            .done(function(){
                updateLastUpdated(Date.now());
            })
            .fail(function(){
                updateLastUpdated(0);
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
