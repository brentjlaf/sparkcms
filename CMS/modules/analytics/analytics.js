// File: analytics.js
$(function(){
    const state = {
        entries: [],
        filter: 'all',
        view: 'grid',
        sort: {
            key: 'views',
            direction: 'desc',
        },
        summary: {
            totalViews: 0,
            averageViews: 0,
            totalPages: 0,
            zeroViews: 0,
        },
        counts: {
            all: 0,
            top: 0,
            growing: 0,
            'no-views': 0,
        }
    };

    const $grid = $('#analyticsGrid');
    const $table = $('#analyticsTableView');
    const $tableBody = $('#analyticsTableBody');
    const $sortButtons = $('[data-analytics-sort]');
    const $empty = $('#analyticsEmptyState');
    const $search = $('#analyticsSearchInput');
    const $filterButtons = $('[data-analytics-filter]');
    const $viewButtons = $('[data-analytics-view]');
    const $counts = $('[data-analytics-count]');
    const $refreshBtn = $('[data-analytics-action="refresh"]');
    const $totalViews = $('#analyticsTotalViews');
    const $averageViews = $('#analyticsAverageViews');
    const $totalPages = $('#analyticsTotalPages');
    const $zeroPages = $('#analyticsZeroPages');
    const $lastUpdated = $('#analyticsLastUpdated');
    const $topList = $('#analyticsTopList');
    const $topEmpty = $('#analyticsTopEmpty');
    const $zeroList = $('#analyticsZeroList');
    const $zeroEmpty = $('#analyticsZeroEmpty');
    const $zeroSummary = $('#analyticsZeroSummary');

    function escapeHtml(str){
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function formatNumber(value){
        const number = Number(value) || 0;
        return number.toLocaleString(undefined, { maximumFractionDigits: 0 });
    }

    function formatAverage(value){
        const number = Number(value) || 0;
        return number.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    }

    function setButtonLoading($button, loading){
        if (!$button.length) {
            return;
        }
        const $text = $button.find('.analytics-btn__text');
        const defaultText = $button.data('defaultText') ?? $text.text();
        if ($button.data('defaultText') === undefined) {
            $button.data('defaultText', defaultText);
        }
        const loadingText = $button.data('loadingText') || 'Working…';
        if (loading) {
            if ($text.length) {
                $text.text(loadingText);
            }
            $button.addClass('is-loading').prop('disabled', true).attr('aria-busy', 'true');
        } else {
            if ($text.length) {
                $text.text($button.data('defaultText'));
            }
            $button.removeClass('is-loading').prop('disabled', false).removeAttr('aria-busy');
        }
    }

    function deriveState(rawEntries){
        const entries = Array.isArray(rawEntries) ? rawEntries.slice() : [];
        const sanitized = entries.map(function(entry){
            const views = Number(entry && entry.views != null ? entry.views : 0);
            return {
                title: entry && entry.title ? String(entry.title) : 'Untitled',
                slug: entry && entry.slug ? String(entry.slug) : '',
                views: views < 0 ? 0 : views,
            };
        });

        sanitized.sort(function(a, b){
            return (b.views || 0) - (a.views || 0);
        });

        const totals = {
            totalViews: 0,
            totalPages: sanitized.length,
            zeroViews: 0,
            averageViews: 0,
        };

        sanitized.forEach(function(entry){
            totals.totalViews += entry.views;
            if (entry.views === 0) {
                totals.zeroViews++;
            }
        });

        totals.averageViews = totals.totalPages > 0 ? totals.totalViews / totals.totalPages : 0;

        const counts = {
            all: sanitized.length,
            top: 0,
            growing: 0,
            'no-views': 0,
        };

        sanitized.forEach(function(entry, index){
            let status = 'growing';
            if (entry.views === 0) {
                status = 'no-views';
            } else if (index < 3 || entry.views >= totals.averageViews) {
                status = 'top';
            }

            counts[status]++;

            entry.status = status;
            entry.rank = index + 1;
            entry.badge = status === 'top' ? 'Top performer' : (status === 'no-views' ? 'Needs promotion' : 'Steady traffic');
            entry.titleLower = entry.title.toLowerCase();
            entry.slugLower = entry.slug.toLowerCase();
        });

        return {
            entries: sanitized,
            totals: totals,
            counts: counts,
        };
    }

    function updateSummary(){
        $totalViews.text(formatNumber(state.summary.totalViews));
        $averageViews.text(formatAverage(state.summary.averageViews));
        $totalPages.text(formatNumber(state.summary.totalPages));
        $zeroPages.text(formatNumber(state.summary.zeroViews));
    }

    function updateFilterCounts(){
        $counts.each(function(){
            const $count = $(this);
            const key = $count.data('analyticsCount');
            if (!key) {
                return;
            }
            const value = state.counts[key] ?? 0;
            $count.text(formatNumber(value));
        });
    }

    function updateLastUpdatedDisplay(value){
        if (!$lastUpdated.length) {
            return;
        }
        let label = 'Data refreshed moments ago';
        if (value instanceof Date) {
            label = 'Data refreshed ' + value.toLocaleString();
            $lastUpdated.attr('data-timestamp', value.toISOString());
        } else if (typeof value === 'string' && value.trim() !== '') {
            label = value;
        }
        $lastUpdated.text(label);
    }

    function renderInsights(){
        if ($topList.length) {
            const topItems = state.entries.slice(0, 3);
            $topList.empty();
            if (topItems.length) {
                topItems.forEach(function(item){
                    $topList.append(
                        '<li>' +
                            '<div>' +
                                '<span class="analytics-insight-item-title">' + escapeHtml(item.title) + '</span>' +
                                '<span class="analytics-insight-item-slug">' + escapeHtml(item.slug) + '</span>' +
                            '</div>' +
                            '<span class="analytics-insight-metric">' + formatNumber(item.views) + ' views</span>' +
                        '</li>'
                    );
                });
                $topList.removeAttr('hidden');
                $topEmpty.attr('hidden', true);
            } else {
                $topList.attr('hidden', true);
                $topEmpty.removeAttr('hidden');
            }
        }

        if ($zeroList.length) {
            const zeroItems = state.entries.filter(function(item){
                return item.views === 0;
            }).slice(0, 3);
            $zeroList.empty();
            if (zeroItems.length) {
                zeroItems.forEach(function(item){
                    $zeroList.append(
                        '<li>' +
                            '<div>' +
                                '<span class="analytics-insight-item-title">' + escapeHtml(item.title) + '</span>' +
                                '<span class="analytics-insight-item-slug">' + escapeHtml(item.slug) + '</span>' +
                            '</div>' +
                            '<span class="analytics-insight-metric">0 views</span>' +
                        '</li>'
                    );
                });
                $zeroList.removeAttr('hidden');
                $zeroEmpty.attr('hidden', true);
                if ($zeroSummary.length) {
                    const total = state.summary.zeroViews;
                    $zeroSummary.text(total === 1
                        ? 'You have 1 page with no recorded views.'
                        : 'You have ' + formatNumber(total) + ' pages with no recorded views.');
                }
            } else {
                $zeroList.attr('hidden', true);
                $zeroEmpty.removeAttr('hidden');
                if ($zeroSummary.length) {
                    $zeroSummary.text('Great job! Every published page has at least one view.');
                }
            }
        }
    }

    function renderGrid(items){
        $grid.empty();
        if (!items.length) {
            return;
        }
        const fragments = items.map(function(item){
            const badgeClass = item.status === 'top'
                ? 'analytics-badge--success'
                : (item.status === 'no-views' ? 'analytics-badge--warning' : 'analytics-badge--neutral');
            const badge = '<span class="analytics-badge ' + badgeClass + '">' + escapeHtml(item.badge) + '</span>';
            const rank = item.rank <= 3 ? '<span class="analytics-rank">#' + item.rank + '</span>' : '';
            return (
                '<article class="analytics-page-card" data-analytics-status="' + item.status + '" role="listitem">' +
                    '<div class="analytics-page-card__header">' +
                        '<div>' +
                            '<h3 class="analytics-page-card__title">' + escapeHtml(item.title) + '</h3>' +
                            '<p class="analytics-page-card__slug">/' + escapeHtml(item.slug) + '</p>' +
                        '</div>' +
                        '<div class="analytics-page-card__metric">' +
                            '<span class="analytics-page-card__views">' + formatNumber(item.views) + '</span>' +
                            '<span class="analytics-page-card__label">views</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="analytics-page-card__footer">' +
                        rank +
                        badge +
                    '</div>' +
                '</article>'
            );
        });
        $grid.html(fragments.join(''));
    }

    function renderTable(items){
        updateSortIndicators();
        if (!$tableBody.length) {
            return;
        }
        const sorted = sortItemsForTable(items);
        $tableBody.empty();
        if (!sorted.length) {
            return;
        }
        const rows = sorted.map(function(item){
            return (
                '<tr>' +
                    '<td class="analytics-table__title">' + escapeHtml(item.title) + '</td>' +
                    '<td class="analytics-table__slug">/' + escapeHtml(item.slug) + '</td>' +
                    '<td class="analytics-table__views">' + formatNumber(item.views) + '</td>' +
                '</tr>'
            );
        });
        $tableBody.html(rows.join(''));
    }

    function applyFilters(){
        const term = ($search.val() || '').toString().trim().toLowerCase();
        return state.entries.filter(function(item){
            const matchesFilter = state.filter === 'all' || item.status === state.filter;
            if (!matchesFilter) {
                return false;
            }
            if (!term) {
                return true;
            }
            return item.titleLower.includes(term) || item.slugLower.includes(term);
        });
    }

    function updateEmptyState(hasResults){
        if (!$empty.length) {
            return;
        }
        if (hasResults) {
            $empty.attr('hidden', true);
        } else {
            $empty.removeAttr('hidden');
        }
    }

    function render(){
        const results = applyFilters();
        const hasResults = results.length > 0;
        renderTable(results);
        if (state.view === 'grid') {
            renderGrid(results);
            $grid.removeAttr('hidden');
            $table.attr('hidden', true);
        } else {
            renderGrid([]);
            $grid.attr('hidden', true);
            $table.removeAttr('hidden');
        }
        updateEmptyState(hasResults);
    }

    function sortItemsForTable(items){
        const sorted = Array.isArray(items) ? items.slice() : [];
        if (sorted.length <= 1) {
            return sorted;
        }
        const key = state.sort.key;
        const direction = state.sort.direction === 'asc' ? 1 : -1;
        sorted.sort(function(a, b){
            let comparison = 0;
            if (key === 'title') {
                comparison = a.titleLower.localeCompare(b.titleLower, undefined, { sensitivity: 'base' });
            } else if (key === 'slug') {
                comparison = a.slugLower.localeCompare(b.slugLower, undefined, { sensitivity: 'base' });
            } else {
                comparison = (a.views || 0) - (b.views || 0);
            }
            if (comparison === 0) {
                comparison = (a.rank || 0) - (b.rank || 0);
            }
            return comparison * direction;
        });
        return sorted;
    }

    function updateSortIndicators(){
        if (!$sortButtons.length) {
            return;
        }
        $sortButtons.each(function(){
            const $button = $(this);
            const key = $button.data('analyticsSort');
            if (!key) {
                return;
            }
            const isActive = key === state.sort.key;
            const isAscending = isActive && state.sort.direction === 'asc';
            const ariaValue = isActive ? (isAscending ? 'ascending' : 'descending') : 'none';
            const baseLabel = $button.data('analyticsSortLabel') || $button.find('.analytics-table__sort-label').text().trim() || 'Column';
            const activeLabel = isActive ? baseLabel + ' column, sorted ' + (isAscending ? 'ascending' : 'descending') : 'Sort by ' + baseLabel + ' column';

            $button.toggleClass('is-active', isActive);
            $button.toggleClass('is-ascending', isAscending);
            $button.toggleClass('is-descending', isActive && !isAscending);
            $button.attr('aria-label', activeLabel);

            const $header = $button.closest('th');
            if ($header.length) {
                $header.attr('aria-sort', ariaValue);
            } else {
                $button.attr('aria-sort', ariaValue);
            }
        });
    }

    function setSort(key){
        if (!key) {
            return;
        }
        const current = state.sort.key;
        let direction = 'asc';
        if (current === key) {
            direction = state.sort.direction === 'asc' ? 'desc' : 'asc';
        } else if (key === 'views') {
            direction = 'desc';
        }
        state.sort = {
            key: key,
            direction: direction,
        };
        render();
    }

    function setFilter(filter){
        if (state.filter === filter) {
            return;
        }
        state.filter = filter;
        $filterButtons.removeClass('active');
        $filterButtons.filter('[data-analytics-filter="' + filter + '"]').addClass('active');
        render();
    }

    function setView(view){
        if (state.view === view) {
            return;
        }
        state.view = view;
        $viewButtons.removeClass('active');
        $viewButtons.filter('[data-analytics-view="' + view + '"]').addClass('active');
        render();
    }

    function debounce(fn, delay){
        let timer = null;
        return function(){
            const context = this;
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function(){
                fn.apply(context, args);
            }, delay);
        };
    }

    function setData(rawEntries){
        const derived = deriveState(rawEntries);
        state.entries = derived.entries;
        state.summary = derived.totals;
        state.counts = derived.counts;
        updateSummary();
        updateFilterCounts();
        renderInsights();
        render();
    }

    function loadFromServer(){
        if (!$refreshBtn.length) {
            return;
        }
        setButtonLoading($refreshBtn, true);
        $.getJSON('modules/analytics/analytics_data.php')
            .done(function(data){
                setData(data || []);
                updateLastUpdatedDisplay(new Date());
            })
            .fail(function(){
                window.alert('Unable to refresh analytics data right now. Please try again later.');
            })
            .always(function(){
                setButtonLoading($refreshBtn, false);
            });
    }

    const initialEntries = window.analyticsInitialEntries || [];
    const initialMeta = window.analyticsInitialMeta || {};
    setData(initialEntries);
    updateLastUpdatedDisplay(initialMeta.lastUpdated);

    $filterButtons.on('click', function(){
        const filter = $(this).data('analyticsFilter');
        if (filter) {
            setFilter(filter);
        }
    });

    $viewButtons.on('click', function(){
        const view = $(this).data('analyticsView');
        if (view) {
            setView(view);
        }
    });

    $search.on('input', debounce(function(){
        render();
    }, 150));

    $sortButtons.on('click', function(){
        const sortKey = $(this).data('analyticsSort');
        if (sortKey) {
            setSort(sortKey);
        }
    });

    if ($refreshBtn.length) {
        $refreshBtn.on('click', function(){
            loadFromServer();
        });
    }

});
