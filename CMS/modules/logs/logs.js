// File: logs.js
$(function(){
    const dashboard = $('.logs-dashboard');
    if (!dashboard.length) {
        return;
    }

    const timeline = $('#logsTimeline');
    const matchCountEl = $('#logsMatchCount');
    const searchInput = $('#logsSearch');
    const filterContainer = $('#logsFilters');
    const refreshBtn = $('#logsRefreshBtn');
    const endpoint = dashboard.data('endpoint');

    const statsEls = {
        total: $('#logsTotalCount'),
        last7: $('#logsLast7Days'),
        users: $('#logsUserCount'),
        pages: $('#logsPageCount'),
        topActionLabel: $('#logsTopActionLabel'),
        topActionCount: $('#logsTopActionCount'),
        lastActivity: $('#logsLastActivity'),
        past24h: $('#logsPast24h')
    };

    let currentAction = 'all';
    let allLogs = [];

    function escapeHtml(str) {
        return $('<div>').text(str).html();
    }

    function getActionLabel(log) {
        const raw = log && typeof log.action !== 'undefined' ? String(log.action) : '';
        const label = raw.trim();
        return label !== '' ? label : 'Updated content';
    }

    function slugifyAction(label) {
        return label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'unknown';
    }

    function formatAbsolute(timestamp) {
        if (!timestamp) {
            return 'No recent activity';
        }
        const date = new Date(timestamp * 1000);
        return date.toLocaleString();
    }

    function heroTime(timestamp) {
        if (!timestamp) {
            return 'No activity yet';
        }
        const now = Date.now();
        const diff = now - timestamp * 1000;
        if (diff < 0) {
            return 'Scheduled update';
        }
        const seconds = Math.floor(diff / 1000);
        if (seconds < 60) {
            return 'Just now';
        }
        if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            return minutes + ' min' + (minutes === 1 ? '' : 's') + ' ago';
        }
        if (seconds < 86400) {
            const hours = Math.floor(seconds / 3600);
            return hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
        }
        if (seconds < 604800) {
            const days = Math.floor(seconds / 86400);
            return days + ' day' + (days === 1 ? '' : 's') + ' ago';
        }
        return formatAbsolute(timestamp);
    }

    function relativeTime(timestamp) {
        if (!timestamp) {
            return 'Unknown time';
        }
        const now = Date.now();
        const diff = now - timestamp * 1000;
        if (diff < 0) {
            return 'Scheduled update';
        }
        const seconds = Math.floor(diff / 1000);
        if (seconds < 60) {
            return 'Just now';
        }
        if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            return minutes + ' min' + (minutes === 1 ? '' : 's');
        }
        if (seconds < 86400) {
            const hours = Math.floor(seconds / 3600);
            return hours + ' hr' + (hours === 1 ? '' : 's');
        }
        if (seconds < 604800) {
            const days = Math.floor(seconds / 86400);
            return days + ' day' + (days === 1 ? '' : 's');
        }
        return formatAbsolute(timestamp);
    }

    function normalizeLogs(logs) {
        if (typeof logs === 'string') {
            try {
                logs = JSON.parse(logs);
            } catch (err) {
                logs = [];
            }
        }
        if (!Array.isArray(logs)) {
            return [];
        }
        return logs.map(function(item){
            const label = getActionLabel(item);
            const slug = item && item.action_slug ? String(item.action_slug) : slugifyAction(label);
            return {
                time: parseInt(item.time, 10) || 0,
                user: item && item.user ? String(item.user) : '',
                page_title: item && item.page_title ? String(item.page_title) : 'Unknown',
                action: label,
                action_slug: slug
            };
        }).sort(function(a, b){
            return b.time - a.time;
        });
    }

    function summarizeActions(logs) {
        const summary = {};
        logs.forEach(function(log){
            const label = getActionLabel(log);
            const slug = log.action_slug || slugifyAction(label);
            if (!summary[slug]) {
                summary[slug] = { slug: slug, label: label, count: 0 };
            }
            summary[slug].count += 1;
        });
        return Object.values(summary).sort(function(a, b){
            return b.count - a.count;
        });
    }

    function renderFilters(logs) {
        const actions = summarizeActions(logs);
        if (currentAction !== 'all' && !actions.some(function(action){ return action.slug === currentAction; })) {
            currentAction = 'all';
        }

        filterContainer.empty();

        const allBtn = $('<button type="button" class="logs-filter-btn"></button>')
            .attr('data-filter', 'all')
            .toggleClass('active', currentAction === 'all')
            .append($('<span>').text('All activity'))
            .append($('<span class="logs-filter-count" id="logsAllCount"></span>').text(logs.length));

        filterContainer.append(allBtn);

        actions.slice(0, 4).forEach(function(action){
            const btn = $('<button type="button" class="logs-filter-btn"></button>')
                .attr('data-filter', action.slug)
                .toggleClass('active', currentAction === action.slug)
                .append($('<span>').text(action.label))
                .append($('<span class="logs-filter-count"></span>').attr('data-filter-count', action.slug).text(action.count));
            filterContainer.append(btn);
        });
    }

    function buildCard(log) {
        const label = getActionLabel(log);
        const slug = log.action_slug || slugifyAction(label);
        const timestamp = log.time || 0;
        const exact = timestamp ? new Date(timestamp * 1000).toISOString() : '';
        const absolute = formatAbsolute(timestamp);
        const relative = relativeTime(timestamp);
        const context = log.context || 'page';
        const pageTitle = log.page_title || (context === 'system' ? 'System activity' : 'Unknown');
        const user = log.user && log.user !== '' ? log.user : 'System';
        const details = Array.isArray(log.details) ? log.details : (log.details ? [log.details] : []);
        const detailsHtml = details.length ? '<ul class="logs-activity-details">' + details.map(function(detail){
            return '<li>' + escapeHtml(detail) + '</li>';
        }).join('') + '</ul>' : '';
        const searchText = (user + ' ' + pageTitle + ' ' + label + ' ' + details.join(' ')).toLowerCase();

        return (
            '<article class="logs-activity-card" data-search="' + escapeHtml(searchText) + '" data-action="' + escapeHtml(slug) + '">' +
                '<header class="logs-activity-card__header">' +
                    '<span class="logs-activity-badge">' + escapeHtml(label) + '</span>' +
                    '<time datetime="' + exact + '" class="logs-activity-time" title="' + escapeHtml(absolute) + '">' +
                        escapeHtml(relative) +
                    '</time>' +
                '</header>' +
                '<h4 class="logs-activity-page">' + escapeHtml(pageTitle) + '</h4>' +
                '<p class="logs-activity-description">' +
                    '<span class="logs-activity-user">' + escapeHtml(user) + '</span>' +
                    '<span class="logs-activity-divider" aria-hidden="true">•</span>' +
                    '<span class="logs-activity-action">' + escapeHtml(label) + '</span>' +
                '</p>' +
                detailsHtml +
            '</article>'
        );
    }

    function updateTimeline(logs) {
        if (!logs.length) {
            timeline.html('<div class="logs-empty"><i class="fas fa-clipboard-list" aria-hidden="true"></i><p>No activity recorded yet.</p><p class="logs-empty-hint">Updates will appear here as your team edits content.</p></div>');
            return;
        }
        const html = logs.map(buildCard).join('');
        timeline.html(html);
    }

    function updateMatchCount(count) {
        if (!matchCountEl.length) {
            return;
        }
        if (count === 0) {
            matchCountEl.text('No entries to display');
        } else if (count === 1) {
            matchCountEl.text('1 entry');
        } else {
            matchCountEl.text(count + ' entries');
        }
    }

    function updateStats(logs) {
        const total = logs.length;
        const now = Date.now();
        const past24h = logs.filter(function(log){
            return log.time && (now - log.time * 1000) <= 86400000;
        }).length;
        const last7 = logs.filter(function(log){
            return log.time && (now - log.time * 1000) <= 604800000;
        }).length;

        const uniqueUsers = new Set();
        const uniquePages = new Set();
        logs.forEach(function(log){
            if (log.user) {
                uniqueUsers.add(log.user.toLowerCase());
            }
            if (log.page_title) {
                uniquePages.add(log.page_title.toLowerCase());
            }
        });

        const heroTimestamp = logs.length ? logs[0].time : 0;
        const heroLabel = heroTime(heroTimestamp);
        const heroTitle = heroTimestamp ? formatAbsolute(heroTimestamp) : 'No recent activity';

        if (statsEls.total.length) {
            statsEls.total.text(total);
        }
        if (statsEls.last7.length) {
            statsEls.last7.text(last7);
        }
        if (statsEls.users.length) {
            statsEls.users.text(uniqueUsers.size);
        }
        if (statsEls.pages.length) {
            statsEls.pages.text(uniquePages.size);
        }
        if (statsEls.past24h.length) {
            statsEls.past24h.text(past24h);
        }
        if (statsEls.lastActivity.length) {
            statsEls.lastActivity.text(heroLabel).attr('title', heroTitle);
        }
        const allCountEl = $('#logsAllCount');
        if (allCountEl.length) {
            allCountEl.text(total);
        }

        const actions = summarizeActions(logs);
        if (statsEls.topActionLabel.length && statsEls.topActionCount.length) {
            if (actions.length) {
                statsEls.topActionLabel.text(actions[0].label);
                statsEls.topActionCount.text(actions[0].count + (actions[0].count === 1 ? ' entry' : ' entries'));
            } else {
                statsEls.topActionLabel.text('—');
                statsEls.topActionCount.text('No recorded actions yet');
            }
        }
    }

    function applyFilters() {
        const query = searchInput.val() ? searchInput.val().toLowerCase().trim() : '';
        const filtered = allLogs.filter(function(log){
            const slug = log.action_slug || slugifyAction(getActionLabel(log));
            const matchesAction = currentAction === 'all' || slug === currentAction;
            if (!matchesAction) {
                return false;
            }
            if (!query) {
                return true;
            }
            const haystack = (log.user + ' ' + log.page_title + ' ' + getActionLabel(log)).toLowerCase();
            return haystack.indexOf(query) !== -1;
        });

        updateTimeline(filtered);
        updateMatchCount(filtered.length);
    }

    function setLogs(logs) {
        allLogs = normalizeLogs(logs);
        renderFilters(allLogs);
        updateStats(allLogs);
        applyFilters();
    }

    filterContainer.on('click', 'button', function(){
        const filter = $(this).data('filter') || 'all';
        if (currentAction === filter) {
            return;
        }
        currentAction = filter;
        filterContainer.find('button').removeClass('active');
        $(this).addClass('active');
        applyFilters();
    });

    searchInput.on('input', function(){
        applyFilters();
    });

    if (refreshBtn.length) {
        refreshBtn.on('click', function(){
            if (!endpoint) {
                return;
            }
            refreshBtn.prop('disabled', true).addClass('is-loading');
            $.getJSON(endpoint).done(function(data){
                setLogs(data || []);
            }).fail(function(){
                const alert = $('<div class="logs-inline-alert" role="status">Unable to refresh activity right now.</div>');
                timeline.prepend(alert);
                setTimeout(function(){
                    alert.fadeOut(250, function(){
                        $(this).remove();
                    });
                }, 4000);
            }).always(function(){
                refreshBtn.prop('disabled', false).removeClass('is-loading');
            });
        });
    }

    setLogs(dashboard.data('logs'));
});
