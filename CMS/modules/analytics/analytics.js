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
            totalViews: { current: 0, previous: 0, difference: 0, percent: null },
            averageViews: { current: 0, previous: 0, difference: 0, percent: null },
            totalPages: { current: 0, previous: 0, difference: 0, percent: null },
            zeroViews: { current: 0, previous: 0, difference: 0, percent: null },
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
    const $exportBtn = $('[data-analytics-action="export"]');
    const $totalViews = $('#analyticsTotalViews');
    const $averageViews = $('#analyticsAverageViews');
    const $totalPages = $('#analyticsTotalPages');
    const $zeroPages = $('#analyticsZeroPages');
    const $totalViewsDelta = $('#analyticsTotalViewsDelta');
    const $averageViewsDelta = $('#analyticsAverageViewsDelta');
    const $totalPagesDelta = $('#analyticsTotalPagesDelta');
    const $zeroPagesDelta = $('#analyticsZeroPagesDelta');
    const $lastUpdated = $('#analyticsLastUpdated');
    const $topList = $('#analyticsTopList');
    const $topEmpty = $('#analyticsTopEmpty');
    const $zeroList = $('#analyticsZeroList');
    const $zeroEmpty = $('#analyticsZeroEmpty');
    const $zeroSummary = $('#analyticsZeroSummary');
    const $detail = $('#analyticsDetail');
    const $detailClose = $('#analyticsDetailClose');
    const $detailTitle = $('#analyticsDetailTitle');
    const $detailSlug = $('#analyticsDetailSlug');
    const $detailSummary = $('#analyticsDetailSummary');
    const $detailViews = $('#analyticsDetailViews');
    const $detailDelta = $('#analyticsDetailDelta');
    const $detailBadge = $('#analyticsDetailBadge');
    const $detailTrend = $('#analyticsDetailTrend');
    const $detailMetrics = $('#analyticsDetailMetrics');
    const $detailInsightsSection = $('#analyticsDetailInsightsSection');
    const $detailInsights = $('#analyticsDetailInsights');
    const $detailVisit = $('#analyticsDetailVisit');

    let detailActiveSlug = null;
    let detailReturnFocus = null;

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

    function formatSignedNumber(value){
        const number = Number(value);
        if (!Number.isFinite(number) || number === 0) {
            return '0';
        }
        const absolute = Math.abs(number);
        const formatted = formatNumber(absolute);
        const sign = number > 0 ? '+' : '−';
        return sign + formatted;
    }

    function formatPercentChange(value){
        if (value == null || !Number.isFinite(value)) {
            return '';
        }
        if (value === 0) {
            return '0%';
        }
        const absolute = Math.abs(value);
        const decimals = absolute < 10 ? 1 : 0;
        const sign = value > 0 ? '+' : '−';
        return sign + absolute.toFixed(decimals) + '%';
    }

    function formatShareOfTraffic(share){
        if (!Number.isFinite(share) || share <= 0) {
            return '0%';
        }
        const percent = share * 100;
        if (percent < 1) {
            return 'Less than 1%';
        }
        const decimals = percent < 10 ? 1 : 0;
        return percent.toFixed(decimals) + '%';
    }

    function buildDefaultExportFileName(){
        const now = new Date();
        const pad = function(value){
            return String(value).padStart(2, '0');
        };
        return 'analytics-export-' + now.getFullYear()
            + pad(now.getMonth() + 1)
            + pad(now.getDate())
            + '-' + pad(now.getHours())
            + pad(now.getMinutes())
            + pad(now.getSeconds())
            + '.csv';
    }

    function parseFileNameFromHeader(disposition){
        if (!disposition || typeof disposition !== 'string') {
            return null;
        }
        const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
        if (utfMatch && utfMatch[1]) {
            try {
                return decodeURIComponent(utfMatch[1]);
            } catch (err) {
                console.error('Failed to decode filename from header', err);
            }
        }
        const asciiMatch = disposition.match(/filename="?([^";]+)"?/i);
        if (asciiMatch && asciiMatch[1]) {
            return asciiMatch[1];
        }
        return null;
    }

    function triggerDownloadFromBlob(blob, suggestedName){
        const urlCreator = window.URL || window.webkitURL;
        if (!urlCreator || typeof urlCreator.createObjectURL !== 'function') {
            throw new Error('Browser does not support file downloads.');
        }

        const url = urlCreator.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = suggestedName || 'analytics-export.csv';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(function(){
            urlCreator.revokeObjectURL(url);
        }, 0);
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

    function createSummary(current, previous){
        const safeCurrent = Number.isFinite(current) ? current : 0;
        const safePrevious = Number.isFinite(previous) ? previous : 0;
        const difference = safeCurrent - safePrevious;
        return {
            current: safeCurrent,
            previous: safePrevious,
            difference: difference,
            percent: safePrevious === 0 ? null : (difference / Math.abs(safePrevious)) * 100,
        };
    }

    function deriveState(rawEntries){
        const entries = Array.isArray(rawEntries) ? rawEntries.slice() : [];
        const sanitized = entries.map(function(entry){
            const views = Number(entry && entry.views != null ? entry.views : 0);
            const previousViews = Number(entry && entry.previousViews != null ? entry.previousViews : entry.views);
            return {
                title: entry && entry.title ? String(entry.title) : 'Untitled',
                slug: entry && entry.slug ? String(entry.slug) : '',
                views: views < 0 ? 0 : views,
                previousViews: previousViews < 0 ? 0 : previousViews,
            };
        });

        sanitized.sort(function(a, b){
            return (b.views || 0) - (a.views || 0);
        });

        const totals = {
            totalViews: 0,
            previousViews: 0,
            totalPages: sanitized.length,
            zeroViews: 0,
            previousZeroViews: 0,
        };

        sanitized.forEach(function(entry){
            totals.totalViews += entry.views;
            totals.previousViews += entry.previousViews;
            if (entry.views === 0) {
                totals.zeroViews++;
            }
            if (entry.previousViews === 0) {
                totals.previousZeroViews++;
            }
        });

        const averageViews = totals.totalPages > 0 ? totals.totalViews / totals.totalPages : 0;
        const previousAverageViews = totals.totalPages > 0 ? totals.previousViews / totals.totalPages : 0;

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
            } else if (index < 3 || entry.views >= averageViews) {
                status = 'top';
            }

            counts[status]++;

            entry.status = status;
            entry.rank = index + 1;
            entry.badge = status === 'top' ? 'Top performer' : (status === 'no-views' ? 'Needs promotion' : 'Steady traffic');
            entry.titleLower = entry.title.toLowerCase();
            entry.slugLower = entry.slug.toLowerCase();
            const difference = entry.views - entry.previousViews;
            entry.difference = difference;
            entry.percentChange = entry.previousViews === 0
                ? (entry.views === 0 ? 0 : null)
                : (difference / Math.abs(entry.previousViews)) * 100;
            entry.trend = entry.previousViews === 0 && entry.views > 0
                ? 'new'
                : (difference > 0 ? 'up' : (difference < 0 ? 'down' : 'flat'));
            entry.shareOfViews = totals.totalViews > 0 ? entry.views / totals.totalViews : 0;
            entry.isNewThisPeriod = entry.previousViews === 0 && entry.views > 0;
            entry.hadPreviousTraffic = entry.previousViews > 0;
        });

        return {
            entries: sanitized,
            summary: {
                totalViews: createSummary(totals.totalViews, totals.previousViews),
                averageViews: createSummary(averageViews, previousAverageViews),
                totalPages: createSummary(totals.totalPages, totals.totalPages),
                zeroViews: createSummary(totals.zeroViews, totals.previousZeroViews),
            },
            counts: counts,
        };
    }

    function updateMetric($valueElement, $deltaElement, summary, options){
        if (!summary) {
            return;
        }
        const formatter = options && options.formatter ? options.formatter : formatNumber;
        if ($valueElement && $valueElement.length) {
            const formattedValue = formatter(summary.current);
            $valueElement.text(formattedValue);
            if ($valueElement.attr('data-value') !== undefined) {
                $valueElement.attr('data-value', summary.current);
            }
        }
        updateDelta($deltaElement, summary, options);
    }

    function updateDelta($deltaElement, summary, options){
        if (!$deltaElement || !$deltaElement.length) {
            return;
        }

        const $text = $deltaElement.find('.analytics-overview-delta__text');
        const $sr = $deltaElement.find('.analytics-overview-delta__sr');
        const $icon = $deltaElement.find('.analytics-overview-delta__icon');

        const current = Number(summary.current || 0);
        const previous = Number(summary.previous || 0);
        const difference = Number(summary.difference != null ? summary.difference : current - previous);
        const hasPrevious = summary.previous != null;
        const absoluteChange = Math.abs(difference);
        const formatter = options && options.formatter ? options.formatter : formatNumber;
        const diffFormatter = options && options.differenceFormatter ? options.differenceFormatter : formatter;
        const unit = options && options.unit ? options.unit : 'value';
        const positiveWhenHigher = !(options && options.reverse);

        let percentLabel = 'No change';
        if (hasPrevious && previous !== 0) {
            const percent = (absoluteChange / Math.abs(previous)) * 100;
            const decimals = percent < 10 ? 1 : 0;
            percentLabel = (difference > 0 ? '+' : '-') + percent.toFixed(decimals) + '%';
        } else if (!hasPrevious || previous === 0) {
            if (current === 0) {
                percentLabel = 'No change';
            } else {
                percentLabel = 'New';
            }
        }

        let visibleText;
        if (percentLabel === 'New') {
            visibleText = 'New vs previous period';
        } else if (percentLabel === 'No change') {
            visibleText = 'No change vs previous';
        } else {
            visibleText = percentLabel + ' vs previous period';
        }

        if ($text.length) {
            $text.text(visibleText);
        }

        let srText;
        if (!hasPrevious) {
            srText = 'No previous period data. Current period reports ' + formatter(current) + ' ' + unit + '.';
        } else if (previous === 0 && current !== 0) {
            srText = 'Increased by ' + diffFormatter(absoluteChange) + ' ' + unit + ' compared to zero previously.';
        } else if (previous === 0 && current === 0) {
            srText = 'No change from the previous period.';
        } else if (difference > 0) {
            srText = 'Increased by ' + diffFormatter(absoluteChange) + ' ' + unit + ' compared to ' + formatter(previous) + ' previously.';
        } else if (difference < 0) {
            srText = 'Decreased by ' + diffFormatter(absoluteChange) + ' ' + unit + ' compared to ' + formatter(previous) + ' previously.';
        } else {
            srText = 'No change from the previous period.';
        }

        if ($sr.length) {
            $sr.text(srText);
        }

        let directionClass = 'neutral';
        if (difference > 0) {
            directionClass = positiveWhenHigher ? 'positive' : 'negative';
        } else if (difference < 0) {
            directionClass = positiveWhenHigher ? 'negative' : 'positive';
        }

        $deltaElement
            .removeClass('analytics-overview-delta--positive analytics-overview-delta--negative analytics-overview-delta--neutral')
            .addClass('analytics-overview-delta--' + directionClass);

        if ($icon.length) {
            let iconClass = 'fa-minus';
            if (difference > 0) {
                iconClass = positiveWhenHigher ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
            } else if (difference < 0) {
                iconClass = positiveWhenHigher ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up';
            } else if (percentLabel === 'New') {
                iconClass = positiveWhenHigher ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
            }
            $icon.attr('class', 'fa-solid analytics-overview-delta__icon ' + iconClass);
        }
    }

    function updateSummary(){
        updateMetric($totalViews, $totalViewsDelta, state.summary.totalViews, {
            unit: 'views',
            formatter: formatNumber,
            differenceFormatter: formatNumber,
        });
        updateMetric($averageViews, $averageViewsDelta, state.summary.averageViews, {
            unit: 'views per page',
            formatter: formatAverage,
            differenceFormatter: formatAverage,
        });
        updateMetric($totalPages, $totalPagesDelta, state.summary.totalPages, {
            unit: 'pages',
            formatter: formatNumber,
            differenceFormatter: formatNumber,
        });
        updateMetric($zeroPages, $zeroPagesDelta, state.summary.zeroViews, {
            unit: 'pages with no views',
            formatter: formatNumber,
            differenceFormatter: formatNumber,
            reverse: true,
        });
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

    function updateLastUpdatedDisplay(value, isoString){
        if (!$lastUpdated.length) {
            return;
        }
        let label = 'Data refreshed moments ago';
        if (value instanceof Date) {
            label = 'Data refreshed ' + value.toLocaleString();
            $lastUpdated.attr('data-timestamp', value.toISOString());
        } else if (typeof value === 'string' && value.trim() !== '') {
            label = value;
            if (isoString) {
                $lastUpdated.attr('data-timestamp', isoString);
            }
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
                    const total = state.summary.zeroViews ? state.summary.zeroViews.current : 0;
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
            const slugDisplay = item.slug ? '/' + item.slug : '/';
            const detailLabel = item.slug
                ? item.title + ' (' + slugDisplay + ')'
                : item.title;
            return (
                '<article class="analytics-page-card" data-analytics-status="' + item.status + '" data-analytics-slug="' + escapeHtml(item.slug) + '" role="listitem" tabindex="0" aria-label="View analytics details for ' + escapeHtml(detailLabel) + '">' +
                    '<div class="analytics-page-card__header">' +
                        '<div>' +
                            '<h3 class="analytics-page-card__title">' + escapeHtml(item.title) + '</h3>' +
                            '<p class="analytics-page-card__slug">' + escapeHtml(slugDisplay) + '</p>' +
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
            const slugDisplay = item.slug ? '/' + item.slug : '/';
            const detailLabel = item.slug
                ? item.title + ' (' + slugDisplay + ')'
                : item.title;
            return (
                '<tr class="analytics-table__row" data-analytics-slug="' + escapeHtml(item.slug) + '" tabindex="0" aria-label="View analytics details for ' + escapeHtml(detailLabel) + '">' +
                    '<td class="analytics-table__title">' + escapeHtml(item.title) + '</td>' +
                    '<td class="analytics-table__slug">' + escapeHtml(slugDisplay) + '</td>' +
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

    function getSlugFromElement(element){
        if (!element) {
            return null;
        }
        const node = element.nodeType === 1 ? element : (element[0] || null);
        if (!node) {
            return null;
        }
        const slugAttr = node.getAttribute ? node.getAttribute('data-analytics-slug') : null;
        if (slugAttr == null) {
            return null;
        }
        return slugAttr;
    }

    function findEntryBySlug(slug){
        if (slug == null) {
            return null;
        }
        const key = String(slug);
        for (let index = 0; index < state.entries.length; index++) {
            if (state.entries[index].slug === key) {
                return state.entries[index];
            }
        }
        return null;
    }

    function buildDeltaText(entry){
        if (!entry) {
            return '';
        }
        if (entry.previousViews === 0 && entry.views > 0) {
            return 'New this period (+' + formatNumber(entry.views) + ')';
        }
        if (entry.previousViews === 0 && entry.views === 0) {
            return 'Awaiting first visitors';
        }
        if (entry.difference === 0) {
            return 'No change vs previous period';
        }
        const signed = formatSignedNumber(entry.difference);
        const percentLabel = formatPercentChange(entry.percentChange);
        let text = signed + ' vs previous period';
        if (percentLabel) {
            text += ' (' + percentLabel + ')';
        }
        return text;
    }

    function buildDeltaMetric(entry){
        if (!entry) {
            return 'No change';
        }
        if (entry.previousViews === 0 && entry.views > 0) {
            return '+' + formatNumber(entry.views) + ' new views';
        }
        if (entry.previousViews === 0 && entry.views === 0) {
            return 'No change';
        }
        if (entry.difference === 0) {
            return 'No change';
        }
        const percentLabel = formatPercentChange(entry.percentChange);
        let metric = formatSignedNumber(entry.difference) + ' views';
        if (percentLabel) {
            metric += ' (' + percentLabel + ')';
        }
        return metric;
    }

    function getStatusSummary(entry){
        if (!entry) {
            return '';
        }
        const shareLabel = formatShareOfTraffic(entry.shareOfViews);
        if (entry.status === 'no-views') {
            if (entry.hadPreviousTraffic) {
                return 'Traffic dropped to zero this period after recording ' + formatNumber(entry.previousViews) + ' views last time.';
            }
            return 'This page has not received any visits yet. Promote it to capture the first views.';
        }
        if (entry.status === 'top') {
            if (entry.difference < 0) {
                return 'Still a top performer, but visits slipped compared to the previous period.';
            }
            return 'This page is driving ' + shareLabel + ' of your site traffic.';
        }
        if (entry.difference > 0) {
            return 'Traffic is steadily increasing and now represents ' + shareLabel + ' of total views.';
        }
        if (entry.difference < 0) {
            return 'Traffic softened this period. Refresh or promote the page to maintain momentum.';
        }
        return 'Traffic is stable and contributes ' + shareLabel + ' of total views.';
    }

    function getTrendLabel(entry){
        if (!entry) {
            return '';
        }
        if (entry.trend === 'new') {
            return 'New traffic this period';
        }
        if (entry.trend === 'up') {
            const percentLabel = formatPercentChange(entry.percentChange);
            return 'Traffic trending up' + (percentLabel ? ' (' + percentLabel + ')' : '');
        }
        if (entry.trend === 'down') {
            const percentLabel = formatPercentChange(entry.percentChange);
            return 'Traffic trending down' + (percentLabel ? ' (' + percentLabel + ')' : '');
        }
        if (entry.previousViews === 0 && entry.views === 0) {
            return 'Awaiting first visitors';
        }
        return 'Traffic holding steady';
    }

    function getTrendBadgeClass(entry){
        if (!entry) {
            return 'analytics-detail__badge--neutral';
        }
        if (entry.trend === 'up' || entry.trend === 'new') {
            return 'analytics-detail__badge--positive';
        }
        if (entry.trend === 'down') {
            return 'analytics-detail__badge--negative';
        }
        return 'analytics-detail__badge--neutral';
    }

    function getStatusBadgeClass(entry){
        if (!entry) {
            return 'analytics-detail__badge--neutral';
        }
        if (entry.status === 'top') {
            return 'analytics-detail__badge--success';
        }
        if (entry.status === 'no-views') {
            return 'analytics-detail__badge--warning';
        }
        return 'analytics-detail__badge--neutral';
    }

    function getInsightSuggestions(entry){
        const baseSuggestions = {
            top: [
                'Feature this page in newsletters or on the homepage to extend its reach.',
                'Link to complementary content to keep high-intent visitors exploring longer.'
            ],
            growing: [
                'Monitor engagement over the next few weeks to spot breakout potential.',
                'Test new calls-to-action to turn consistent visitors into conversions.'
            ],
            'no-views': [
                'Add internal links from popular pages to send visitors here.',
                'Share the page on social channels or email to secure first visits.'
            ],
            default: [
                'Keep an eye on this page to understand how readers engage with the topic.'
            ]
        };

        const bucket = baseSuggestions[entry.status] ? baseSuggestions[entry.status].slice() : baseSuggestions.default.slice();

        if (entry.difference < 0 && entry.previousViews > 0) {
            bucket.unshift('Traffic dropped by ' + formatNumber(Math.abs(entry.difference)) + ' views versus last period—refresh the content or promote it again.');
        } else if (entry.difference > 0 && entry.hadPreviousTraffic) {
            bucket.unshift('Traffic grew by ' + formatNumber(entry.difference) + ' views this period. Highlight it in upcoming campaigns to build on the momentum.');
        } else if (entry.isNewThisPeriod) {
            bucket.unshift('The page is attracting its first visitors—share it broadly to keep the momentum going.');
        }

        return bucket.slice(0, 3);
    }

    function buildPageUrl(slug){
        const cleanSlug = (slug || '').toString().replace(/^\/+/, '');
        if (cleanSlug === '') {
            return '/';
        }
        return '/' + cleanSlug;
    }

    function renderDetail(entry){
        if (!$detail.length || !entry) {
            return;
        }
        const slugDisplay = entry.slug ? '/' + entry.slug : '/';
        const deltaClass = entry.previousViews === 0 && entry.views > 0
            ? 'positive'
            : (entry.difference > 0 ? 'positive' : (entry.difference < 0 ? 'negative' : 'neutral'));

        if ($detailTitle.length) {
            $detailTitle.text(entry.title || 'Page analytics details');
        }
        if ($detailSlug.length) {
            $detailSlug.text(slugDisplay);
        }
        if ($detailSummary.length) {
            $detailSummary.text(getStatusSummary(entry));
        }
        if ($detailViews.length) {
            $detailViews.text(formatNumber(entry.views));
        }
        if ($detailDelta.length) {
            $detailDelta
                .attr('class', 'analytics-detail__delta analytics-detail__delta--' + deltaClass)
                .text(buildDeltaText(entry));
        }
        if ($detailBadge.length) {
            $detailBadge
                .attr('class', 'analytics-detail__badge ' + getStatusBadgeClass(entry))
                .text(entry.badge || 'Page insight');
        }
        if ($detailTrend.length) {
            $detailTrend
                .attr('class', 'analytics-detail__badge ' + getTrendBadgeClass(entry))
                .text(getTrendLabel(entry));
        }

        if ($detailMetrics.length) {
            const metrics = [
                { label: 'Previous period', value: formatNumber(entry.previousViews) + ' views' },
                { label: 'Change vs previous', value: buildDeltaMetric(entry) },
                { label: 'Share of site traffic', value: formatShareOfTraffic(entry.shareOfViews) },
                { label: 'Sitewide rank', value: '#' + entry.rank + ' of ' + formatNumber(state.counts.all) }
            ];
            const metricHtml = metrics.map(function(metric){
                return '<li>' +
                    '<span class="analytics-detail__metric-label">' + escapeHtml(metric.label) + '</span>' +
                    '<span class="analytics-detail__metric-value">' + escapeHtml(metric.value) + '</span>' +
                '</li>';
            }).join('');
            $detailMetrics.html(metricHtml);
        }

        if ($detailInsights.length) {
            const suggestions = getInsightSuggestions(entry);
            if (suggestions.length) {
                const insightsHtml = suggestions.map(function(text){
                    return '<li>' + escapeHtml(text) + '</li>';
                }).join('');
                $detailInsights.html(insightsHtml);
                if ($detailInsightsSection.length) {
                    $detailInsightsSection.removeAttr('hidden');
                }
            } else {
                $detailInsights.empty();
                if ($detailInsightsSection.length) {
                    $detailInsightsSection.attr('hidden', true);
                }
            }
        }

        if ($detailVisit.length) {
            const url = buildPageUrl(entry.slug);
            $detailVisit.attr('href', url);
        }
    }

    function closeDetail(options){
        if (!$detail.length) {
            return;
        }
        if (detailActiveSlug == null) {
            $detail.attr('hidden', true).removeClass('is-visible');
            return;
        }
        $detail.attr('hidden', true).removeClass('is-visible');
        const shouldRestoreFocus = !(options && options.skipFocus);
        const trigger = detailReturnFocus;
        detailActiveSlug = null;
        detailReturnFocus = null;
        if (shouldRestoreFocus && trigger && typeof trigger.focus === 'function') {
            try {
                trigger.focus();
            } catch (error) {
                // Ignore focus errors
            }
        }
    }

    function openDetail(entry, trigger){
        if (!$detail.length || !entry) {
            return;
        }
        detailActiveSlug = entry.slug;
        detailReturnFocus = trigger && trigger.nodeType === 1 ? trigger : (trigger && trigger[0] ? trigger[0] : null);
        renderDetail(entry);
        $detail.attr('hidden', false).addClass('is-visible');
        if ($detailClose.length) {
            setTimeout(function(){
                $detailClose.trigger('focus');
            }, 0);
        }
    }

    function openDetailBySlug(slug, trigger){
        const entry = findEntryBySlug(slug);
        if (!entry) {
            return;
        }
        openDetail(entry, trigger);
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
        state.summary = derived.summary;
        state.counts = derived.counts;
        updateSummary();
        updateFilterCounts();
        renderInsights();
        render();
        if (detailActiveSlug != null) {
            const activeEntry = findEntryBySlug(detailActiveSlug);
            if (activeEntry) {
                renderDetail(activeEntry);
            } else {
                closeDetail({ skipFocus: true });
            }
        }
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
    updateLastUpdatedDisplay(initialMeta.lastUpdated, initialMeta.lastUpdatedIso);

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

    if ($exportBtn.length) {
        $exportBtn.on('click', function(){
            const params = {
                filter: state.filter || 'all',
                search: ($search.val() || '').toString().trim(),
            };

            setButtonLoading($exportBtn, true);

            $.ajax({
                url: 'modules/analytics/export.php',
                method: 'GET',
                data: params,
                xhrFields: { responseType: 'blob' }
            })
                .done(function(blob, textStatus, jqXHR){
                    try {
                        const disposition = jqXHR.getResponseHeader('Content-Disposition');
                        const fileName = parseFileNameFromHeader(disposition) || buildDefaultExportFileName();
                        triggerDownloadFromBlob(blob, fileName);
                    } catch (error) {
                        console.error('Unable to trigger analytics export download', error);
                        window.alert('The export was generated but the download could not start. Please try again.');
                    }
                })
                .fail(function(jqXHR){
                    let message = 'Unable to export analytics data right now. Please try again later.';
                    if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) {
                        message = jqXHR.responseJSON.error;
                    }
                    window.alert(message);
                })
                .always(function(){
                    setButtonLoading($exportBtn, false);
                });
        });
    }

    $grid.on('click', '.analytics-page-card', function(){
        const slug = getSlugFromElement(this);
        if (slug !== null) {
            openDetailBySlug(slug, this);
        }
    });

    $grid.on('keydown', '.analytics-page-card', function(event){
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const slug = getSlugFromElement(this);
            if (slug !== null) {
                openDetailBySlug(slug, this);
            }
        }
    });

    $tableBody.on('click', '.analytics-table__row', function(){
        const slug = getSlugFromElement(this);
        if (slug !== null) {
            openDetailBySlug(slug, this);
        }
    });

    $tableBody.on('keydown', '.analytics-table__row', function(event){
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const slug = getSlugFromElement(this);
            if (slug !== null) {
                openDetailBySlug(slug, this);
            }
        }
    });

    if ($detail.length) {
        $detail.on('click', function(event){
            if (event.target === this) {
                closeDetail();
            }
        });
    }

    if ($detailClose.length) {
        $detailClose.on('click', function(){
            closeDetail();
        });
    }

    $(document).on('keydown.analyticsDetail', function(event){
        if (event.key === 'Escape' && detailActiveSlug != null) {
            closeDetail();
        }
    });

});
