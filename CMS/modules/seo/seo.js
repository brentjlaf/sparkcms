(function ($) {
    'use strict';

    var STORAGE_FILTER_KEY = 'seo:filter';
    var STORAGE_VIEW_KEY = 'seo:view';
    var STORAGE_SORT_KEY = 'seo:sort';
    var STORAGE_SORT_DIR_KEY = 'seo:sortDir';

    function loadSeoModule(params) {
        var $container = $('#contentContainer');
        if (!$container.length || typeof $.fn.load !== 'function') {
            return false;
        }

        var query = params ? ('?' + params) : '';
        $container.load('modules/seo/view.php' + query, function () {
            $container.find('.content-section').addClass('active');
            $.getScript('modules/seo/seo.js').fail(function () {
                // ignore errors reloading script
            });
        });
        return true;
    }

    function clampScore(value) {
        var num = Number(value);
        if (!Number.isFinite(num)) {
            return 0;
        }
        if (num < 0) {
            return 0;
        }
        if (num > 100) {
            return 100;
        }
        return Math.round(num);
    }

    function formatDate(date) {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        var options = {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        };
        try {
            return date.toLocaleString(undefined, options);
        } catch (error) {
            return date.toISOString();
        }
    }

    function getScoreClass(score) {
        if (score >= 90) {
            return 'a11y-score--aaa seo-score--optimized';
        }
        if (score >= 75) {
            return 'a11y-score--aa seo-score--on-track';
        }
        if (score >= 50) {
            return 'a11y-score--partial seo-score--needs-work';
        }
        return 'a11y-score--failing seo-score--critical';
    }

    function getBadgeCopy(level) {
        switch (String(level || '').toLowerCase()) {
            case 'optimized':
                return 'Optimized';
            case 'on track':
            case 'on-track':
                return 'On Track';
            case 'needs work':
            case 'needs-work':
                return 'Needs Work';
            default:
                return 'Critical';
        }
    }

    function getOptimizationClass(level) {
        return 'seo-badge--' + String(level || 'critical').toLowerCase().replace(/\s+/g, '-');
    }

    function normalizeNumber(value) {
        var number = Number(value);
        if (Number.isFinite(number)) {
            return number;
        }
        number = parseFloat(value);
        return Number.isNaN(number) ? 0 : number;
    }

    function summarizeIssues(issues) {
        if (!Array.isArray(issues) || !issues.length) {
            return 'No outstanding issues';
        }
        var counts = { critical: 0, high: 0, medium: 0, low: 0 };
        issues.forEach(function (issue) {
            var key = String(issue.impact || '').toLowerCase();
            if (Object.prototype.hasOwnProperty.call(counts, key)) {
                counts[key] += 1;
            }
        });
        var segments = [];
        if (counts.critical) {
            segments.push(counts.critical + ' critical');
        }
        if (counts.high) {
            segments.push(counts.high + ' high');
        }
        if (counts.medium) {
            segments.push(counts.medium + ' medium');
        }
        if (counts.low) {
            segments.push(counts.low + ' low');
        }
        return segments.join(', ') || 'Low impact opportunities';
    }

    function persistState(key, value) {
        try {
            window.sessionStorage.setItem(key, value);
        } catch (error) {
            // ignore storage errors
        }
    }

    function retrieveState(key, fallback) {
        try {
            var value = window.sessionStorage.getItem(key);
            return value === null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function sortPages(pages, key, direction) {
        var dir = direction === 'asc' ? 1 : -1;
        return pages.slice().sort(function (a, b) {
            var aValue;
            var bValue;
            switch (key) {
                case 'title':
                    aValue = (a.title || '').toString().toLowerCase();
                    bValue = (b.title || '').toString().toLowerCase();
                    if (aValue < bValue) { return -1 * dir; }
                    if (aValue > bValue) { return 1 * dir; }
                    return 0;
                case 'issues':
                    aValue = Array.isArray(a.issues && a.issues.details) ? a.issues.details.length : 0;
                    bValue = Array.isArray(b.issues && b.issues.details) ? b.issues.details.length : 0;
                    return (aValue - bValue) * dir;
                case 'wordCount':
                    aValue = normalizeNumber(a.metrics && a.metrics.wordCount);
                    bValue = normalizeNumber(b.metrics && b.metrics.wordCount);
                    return (aValue - bValue) * dir;
                case 'score':
                default:
                    aValue = normalizeNumber(a.seoScore);
                    bValue = normalizeNumber(b.seoScore);
                    return (aValue - bValue) * dir;
            }
        });
    }

    function filterPages(pages, filter, query) {
        var loweredQuery = (query || '').toLowerCase();
        return pages.filter(function (page) {
            var matchesFilter = true;
            switch (filter) {
                case 'critical':
                    matchesFilter = (page.optimizationLevel === 'Critical' || normalizeNumber(page.seoScore) < 50);
                    break;
                case 'opportunity':
                    matchesFilter = page.optimizationLevel === 'Needs Work';
                    break;
                case 'onTrack':
                    matchesFilter = page.optimizationLevel === 'On Track';
                    break;
                case 'optimized':
                    matchesFilter = page.optimizationLevel === 'Optimized';
                    break;
                default:
                    matchesFilter = true;
            }
            if (!matchesFilter) {
                return false;
            }
            if (!loweredQuery) {
                return true;
            }
            var haystacks = [
                page.title || '',
                page.url || '',
                page.path || '',
                page.statusMessage || '',
                page.summaryLine || ''
            ];
            if (page.issues && Array.isArray(page.issues.preview)) {
                haystacks.push(page.issues.preview.join(' '));
            }
            return haystacks.join(' ').toLowerCase().indexOf(loweredQuery) !== -1;
        });
    }

    function updateStatsSummary(stats, pages) {
        if (!stats) {
            return;
        }
        var total = pages.length;
        var sum = 0;
        var critical = 0;
        var optimized = 0;
        pages.forEach(function (page) {
            var score = clampScore(page.seoScore);
            sum += score;
            if (page.optimizationLevel === 'Optimized') {
                optimized++;
            }
            critical += normalizeNumber(page.issues && page.issues.counts && (page.issues.counts.critical || 0));
            critical += normalizeNumber(page.issues && page.issues.counts && (page.issues.counts.high || 0));
        });
        var avg = total > 0 ? Math.round(sum / total) : 0;
        stats.totalPages = total;
        stats.avgScore = avg;
        stats.optimizedPages = optimized;
        stats.criticalIssues = critical;
    }

    function attachSeverityFilters($context, prefix) {
        prefix = prefix || 'seo';
        var selectorBtn = '[data-' + prefix + '-severity]';
        var $buttons = $context.find(selectorBtn);
        if (!$buttons.length) {
            return;
        }
        var $issueCards = $context.find('.' + prefix + '-issue-card');
        var $count = $context.find('#' + prefix + 'IssueCount');
        var $status = $context.find('#' + prefix + 'IssueFilterStatus');
        var $empty = $context.find('#' + prefix + 'NoIssuesMessage');
        var active = 'all';

        function setActive(target) {
            active = target;
            var visible = 0;
            $issueCards.each(function () {
                var $card = $(this);
                var impact = String($card.data('impact') || '').toLowerCase();
                var matches = target === 'all' || impact === target;
                if (matches) {
                    $card.removeAttr('hidden');
                    visible++;
                } else {
                    $card.attr('hidden', 'hidden');
                }
            });
            if ($count.length) {
                $count.text(visible + ' ' + (visible === 1 ? 'issue' : 'issues'));
            }
            if ($status.length) {
                $status.text('Showing ' + (target === 'all' ? 'all severities' : target + ' issues') + '.');
            }
            if ($empty.length) {
                if (visible === 0) {
                    $empty.removeAttr('hidden');
                } else {
                    $empty.attr('hidden', 'hidden');
                }
            }
            $buttons.each(function () {
                var $btn = $(this);
                var matches = $btn.data(prefix + 'Severity') === target;
                $btn.toggleClass('active', matches);
                $btn.attr('aria-pressed', matches ? 'true' : 'false');
            });
        }

        $buttons.on('click', function () {
            var value = $(this).data(prefix + 'Severity');
            setActive(String(value));
        });

        setActive(active);
    }

    function renderGrid($grid, pages) {
        var fragments = [];
        pages.forEach(function (page) {
            var score = clampScore(page.seoScore);
            var badge = getBadgeCopy(page.optimizationLevel);
            fragments.push(
                '<article class="a11y-page-card seo-page-card" role="listitem" tabindex="0" data-slug="' + (page.slug || '') + '">' +
                '<div class="a11y-page-card__header">' +
                '<div class="a11y-page-card__title">' +
                '<h3 class="a11y-page-title">' + $('<div>').text(page.title || 'Untitled').html() + '</h3>' +
                '<p class="a11y-page-url">' + $('<div>').text(page.url || '').html() + '</p>' +
                '</div>' +
                '<div class="score-indicator score-indicator--card">' +
                '<div class="a11y-page-score ' + getScoreClass(score) + '"><span class="score-indicator__number">' + score + '%</span></div>' +
                '</div>' +
                '</div>' +
                '<div class="a11y-page-card__metrics seo-page-card__metrics">' +
                '<div><span class="label">Optimization</span><span class="value ' + getOptimizationClass(page.optimizationLevel) + '">' + badge + '</span></div>' +
                '<div><span class="label">Issues</span><span class="value">' + (page.issues && page.issues.details ? page.issues.details.length : 0) + '</span></div>' +
                '<div><span class="label">Words</span><span class="value">' + (page.metrics ? (page.metrics.wordCount || 0) : 0) + '</span></div>' +
                '</div>' +
                '<div class="seo-page-card__summary">' + $('<div>').text(page.summaryLine || '').html() + '</div>' +
                '<div class="seo-page-card__actions">' +
                '<button type="button" class="a11y-btn a11y-btn--ghost" data-seo-action="open-detail" data-slug="' + (page.slug || '') + '">View details</button>' +
                '</div>' +
                '</article>'
            );
        });
        $grid.html(fragments.join(''));
    }

    function renderTable($tableBody, pages) {
        var rows = [];
        pages.forEach(function (page) {
            var score = clampScore(page.seoScore);
            rows.push(
                '<div class="a11y-table-row seo-table-row" role="row" data-slug="' + (page.slug || '') + '">' +
                '<div class="a11y-table-cell" role="cell">' +
                '<div class="title">' + $('<div>').text(page.title || 'Untitled').html() + '</div>' +
                '<div class="subtitle">' + $('<div>').text(page.url || '').html() + '</div>' +
                '</div>' +
                '<div class="a11y-table-cell score" role="cell">' + score + '%</div>' +
                '<div class="a11y-table-cell" role="cell">' + getBadgeCopy(page.optimizationLevel) + '</div>' +
                '<div class="a11y-table-cell" role="cell">' + (page.issues && page.issues.details ? page.issues.details.length : 0) + '</div>' +
                '<div class="a11y-table-cell" role="cell">' + (page.metrics ? (page.metrics.wordCount || 0) : 0) + '</div>' +
                '<div class="a11y-table-cell" role="cell">' + $('<div>').text(page.lastScanned || '').html() + '</div>' +
                '<div class="a11y-table-cell actions" role="cell"><button type="button" class="a11y-btn a11y-btn--ghost" data-seo-action="open-detail" data-slug="' + (page.slug || '') + '">Inspect</button></div>' +
                '</div>'
            );
        });
        $tableBody.html(rows.join(''));
    }

    function openModal($modal, page, stats) {
        if (!$modal.length || !page) {
            return;
        }
        var $overlay = $modal;
        var $title = $modal.find('#seoDetailTitle');
        var $url = $modal.find('#seoDetailUrl');
        var $description = $modal.find('#seoDetailDescription');
        var $score = $modal.find('#seoDetailScore');
        var $level = $modal.find('#seoDetailLevel');
        var $issues = $modal.find('#seoDetailIssues');
        var $metricList = $modal.find('#seoDetailMetrics');
        var $issuesList = $modal.find('#seoDetailIssuesList');

        $title.text(page.title || 'Page SEO Details');
        $url.text(page.url || '');
        $description.text(page.summaryLine || '');

        var score = clampScore(page.seoScore);
        $score.text(score + '%').attr('class', 'seo-detail-score score-indicator score-indicator--badge ' + getScoreClass(score));
        $level.text(getBadgeCopy(page.optimizationLevel));
        $issues.text(summarizeIssues(page.issues && page.issues.details));

        var metrics = [];
        metrics.push('<li><span class="label">Word count</span><span class="value">' + (page.metrics ? (page.metrics.wordCount || 0) : 0) + '</span></li>');
        metrics.push('<li><span class="label">Internal links</span><span class="value">' + (page.metrics ? (page.metrics.internalLinks || 0) : 0) + '</span></li>');
        metrics.push('<li><span class="label">External links</span><span class="value">' + (page.metrics ? (page.metrics.externalLinks || 0) : 0) + '</span></li>');
        metrics.push('<li><span class="label">Missing alt</span><span class="value">' + (page.metrics ? (page.metrics.missingAlt || 0) : 0) + '</span></li>');
        metrics.push('<li><span class="label">Canonical</span><span class="value">' + (page.metadata && page.metadata.canonical ? 'Declared' : 'None') + '</span></li>');
        metrics.push('<li><span class="label">Structured data</span><span class="value">' + (page.metadata ? (page.metadata.structuredDataCount || 0) : 0) + '</span></li>');
        $metricList.html(metrics.join(''));

        var issuesMarkup = [];
        if (page.issues && Array.isArray(page.issues.details) && page.issues.details.length) {
            page.issues.details.forEach(function (issue) {
                var impact = String(issue.impact || '').toLowerCase();
                var impactClass = impact ? 'impact-' + impact : '';
                var impactLabel = impact ? impact.charAt(0).toUpperCase() + impact.slice(1) : '';
                var description = $('<div>').text(issue.description || '').html();
                var recommendation = $('<div>').text(issue.recommendation || '').html();
                var parts = ['<li'];
                if (impactClass) {
                    parts.push(' class="' + impactClass + '"');
                }
                parts.push('>');
                if (impactLabel) {
                    parts.push('<span class="impact ' + impactClass + '">' + impactLabel + '</span>');
                }
                parts.push('<span class="issue">' + description + '</span>');
                if (recommendation) {
                    parts.push('<span class="tip">' + recommendation + '</span>');
                }
                parts.push('</li>');
                issuesMarkup.push(parts.join(''));
            });
        } else {
            issuesMarkup.push('<li class="no-issues">No outstanding issues detected.</li>');
        }
        $issuesList.html(issuesMarkup.join(''));

        $overlay.removeAttr('hidden');
        $overlay.addClass('is-visible');
        $overlay.attr('aria-hidden', 'false');
        $overlay.data('active-slug', page.slug || '');
        $overlay.data('return-focus', document.activeElement);

        setTimeout(function () {
            $overlay.find('#seoDetailClose').trigger('focus');
        }, 10);

        $(document).off('keydown.seoModal').on('keydown.seoModal', function (event) {
            if (event.key === 'Escape') {
                closeModal($modal, stats);
            }
        });
    }

    function closeModal($modal, stats) {
        if (!$modal.length) {
            return;
        }
        $modal.removeClass('is-visible');
        $modal.attr('aria-hidden', 'true');
        $modal.attr('hidden', 'hidden');
        $(document).off('keydown.seoModal');
        var returnFocus = $modal.data('return-focus');
        if (returnFocus && typeof returnFocus.focus === 'function') {
            returnFocus.focus();
        }
        $modal.removeData('return-focus');
        $modal.removeData('active-slug');
    }

    function simulateScan(pages, stats) {
        var now = new Date();
        pages.forEach(function (page) {
            var currentScore = clampScore(page.seoScore);
            var adjustment = 0;
            if (currentScore < 95) {
                adjustment = Math.min(100, currentScore + 1);
            } else {
                adjustment = currentScore;
            }
            page.previousScore = currentScore;
            page.seoScore = adjustment;
            page.lastScanned = formatDate(now);
            var issueCount = page.issues && Array.isArray(page.issues.details) ? page.issues.details.length : 0;
            if (issueCount > 0) {
                page.summaryLine = 'SEO score at ' + page.seoScore + '% with ' + issueCount + ' issue' + (issueCount === 1 ? '' : 's') + ' remaining.';
            } else {
                page.summaryLine = 'SEO score at ' + page.seoScore + '% with no outstanding issues.';
            }
        });
        stats.lastScan = formatDate(now);
        updateStatsSummary(stats, pages);
    }

    function setupDashboard(pages, stats) {
        var $dashboard = $('.seo-dashboard');
        if (!$dashboard.length) {
            return;
        }

        var $grid = $('#seoPagesGrid');
        var $table = $('#seoTableBody');
        var $tableView = $('#seoTableView');
        var $gridView = $('#seoPagesGrid');
        var $empty = $('#seoEmptyState');
        var $filterButtons = $('.seo-filter-btn');
        var $sortSelect = $('#seoSortSelect');
        var $sortDirection = $('#seoSortDirection');
        var $search = $('#seoSearchInput');
        var $viewButtons = $('.seo-view-btn');
        var $scanAllBtn = $('#seoScanAllBtn');
        var $modal = $('#seoPageDetail');

        var dataMap = {};
        pages.forEach(function (page) {
            dataMap[page.slug || ''] = page;
        });

        var state = {
            filter: retrieveState(STORAGE_FILTER_KEY, 'all'),
            sortKey: retrieveState(STORAGE_SORT_KEY, 'score'),
            sortDir: retrieveState(STORAGE_SORT_DIR_KEY, 'desc'),
            view: retrieveState(STORAGE_VIEW_KEY, 'grid'),
            query: ''
        };

        function render() {
            var filtered = filterPages(pages, state.filter, state.query);
            var sorted = sortPages(filtered, state.sortKey, state.sortDir);

            var hasResults = sorted.length > 0;
            if (!hasResults) {
                $empty.removeAttr('hidden');
                $gridView.attr('hidden', 'hidden');
                $tableView.attr('hidden', 'hidden');
                if (state.view === 'table') {
                    $table.empty();
                } else {
                    $grid.empty();
                }
                return;
            }

            if (state.view === 'table') {
                renderTable($table, sorted);
                $tableView.removeAttr('hidden');
                $gridView.attr('hidden', 'hidden');
            } else {
                renderGrid($grid, sorted);
                $gridView.removeAttr('hidden');
                $tableView.attr('hidden', 'hidden');
            }

            if (hasResults) {
                $empty.attr('hidden', 'hidden');
            } else {
                $empty.removeAttr('hidden');
            }
        }

        render();

        $filterButtons.each(function () {
            var $btn = $(this);
            var value = String($btn.data('seoFilter'));
            if (value === state.filter) {
                $btn.addClass('active').attr('aria-pressed', 'true');
            } else {
                $btn.removeClass('active').attr('aria-pressed', 'false');
            }
        });

        $viewButtons.each(function () {
            var $btn = $(this);
            var value = String($btn.data('seoView'));
            var active = value === state.view;
            $btn.toggleClass('active', active);
            $btn.attr('aria-pressed', active ? 'true' : 'false');
        });

        $sortSelect.val(state.sortKey);
        $sortDirection.attr('data-direction', state.sortDir);
        $('#seoSortDirectionLabel').text(state.sortDir === 'asc' ? 'Low to high' : 'High to low');

        $filterButtons.on('click', function () {
            var filter = String($(this).data('seoFilter'));
            state.filter = filter;
            persistState(STORAGE_FILTER_KEY, filter);
            $filterButtons.removeClass('active').attr('aria-pressed', 'false');
            $(this).addClass('active').attr('aria-pressed', 'true');
            render();
        });

        $sortSelect.on('change', function () {
            var value = $(this).val();
            state.sortKey = String(value);
            persistState(STORAGE_SORT_KEY, state.sortKey);
            render();
        });

        $sortDirection.on('click', function () {
            var dir = $(this).attr('data-direction') === 'asc' ? 'desc' : 'asc';
            state.sortDir = dir;
            persistState(STORAGE_SORT_DIR_KEY, dir);
            $(this).attr('data-direction', dir);
            $('#seoSortDirectionLabel').text(dir === 'asc' ? 'Low to high' : 'High to low');
            render();
        });

        $viewButtons.on('click', function () {
            var view = String($(this).data('seoView'));
            state.view = view;
            persistState(STORAGE_VIEW_KEY, view);
            $viewButtons.removeClass('active').attr('aria-pressed', 'false');
            $(this).addClass('active').attr('aria-pressed', 'true');
            render();
        });

        var searchTimeout = null;
        $search.on('input', function () {
            clearTimeout(searchTimeout);
            var value = $(this).val();
            searchTimeout = setTimeout(function () {
                state.query = String(value || '').trim();
                render();
            }, 120);
        });

        $grid.on('click', '[data-seo-action="open-detail"]', function () {
            var slug = $(this).data('slug');
            openModal($modal, dataMap[slug], stats);
        });

        $grid.on('keydown', '.seo-page-card', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                var slug = $(this).data('slug');
                openModal($modal, dataMap[slug], stats);
            }
        });

        $table.on('click', '[data-seo-action="open-detail"]', function (event) {
            event.stopPropagation();
            var slug = $(this).data('slug');
            openModal($modal, dataMap[slug], stats);
        });

        $table.on('click', '.seo-table-row', function (event) {
            var $target = $(event.target);
            if ($target.closest('[data-seo-action="open-detail"]').length) {
                return;
            }
            var slug = $(this).data('slug');
            openModal($modal, dataMap[slug], stats);
        });

        if ($modal.length) {
            $modal.on('click', function (event) {
                if (event.target === this) {
                    closeModal($modal, stats);
                }
            });
            $('#seoDetailClose').on('click', function () {
                closeModal($modal, stats);
            });
            $(document).on('keydown.seoModal', function (event) {
                if (event.key === 'Escape') {
                    closeModal($modal, stats);
                }
            });
            $modal.find('[data-seo-action="full-audit"]').on('click', function () {
                var slug = $modal.data('active-slug');
                window.alert('Launching full SEO audit for ' + (slug || 'selected page') + '.\n\nCrawl will refresh metadata, structured data, and internal linking diagnostics.');
            });
        }

        if ($scanAllBtn.length) {
            $scanAllBtn.on('click', function () {
                var $btn = $(this);
                if ($btn.prop('disabled')) {
                    return;
                }
                var originalText = $btn.find('span').text();
                var $icon = $btn.find('i');
                var originalIcon = $icon.attr('class');
                $btn.prop('disabled', true).addClass('is-loading');
                $btn.find('span').text('Scanning...');
                $icon.attr('class', 'fas fa-spinner fa-spin');
                setTimeout(function () {
                    simulateScan(pages, stats);
                    $btn.prop('disabled', false).removeClass('is-loading');
                    $btn.find('span').text(originalText);
                    $icon.attr('class', originalIcon);
                    render();
                    if (stats && stats.lastScan) {
                        $dashboard.attr('data-last-scan', stats.lastScan);
                        $dashboard.find('.seo-hero-meta').html('<i class="fas fa-clock" aria-hidden="true"></i> Last scan: ' + $('<div>').text(stats.lastScan).html());
                        $('#seoStatTotalPages').text(stats.totalPages || 0);
                        $('#seoStatAvgScore').text((stats.avgScore || 0) + '%');
                        $('#seoStatCritical').text(stats.criticalIssues || 0);
                        $('#seoStatOptimized').text(stats.optimizedPages || 0);
                    }
                }, 800);
            });
        }
    }

    function setupDetailPage(stats) {
        var $detail = $('#seoDetailPage');
        if (!$detail.length) {
            return;
        }
        var slug = $detail.data('page-slug');
        $('#seoBackToDashboard').on('click', function (event) {
            event.preventDefault();
            if (!loadSeoModule('')) {
                if (stats && stats.moduleUrl) {
                    window.location.href = stats.moduleUrl;
                }
            }
        });

        $('[data-seo-action="rescan-page"]').on('click', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }
            var $icon = $btn.find('i');
            var original = $icon.attr('class');
            $btn.prop('disabled', true).addClass('is-loading');
            $icon.attr('class', 'fas fa-spinner fa-spin');
            setTimeout(function () {
                $btn.prop('disabled', false).removeClass('is-loading');
                $icon.attr('class', original);
                if (!loadSeoModule('page=' + encodeURIComponent(slug))) {
                    if (stats && stats.detailBaseUrl) {
                        window.location.href = stats.detailBaseUrl + encodeURIComponent(slug);
                    }
                }
            }, 800);
        });

        attachSeverityFilters($detail, 'seo');
    }

    function bootstrap() {
        var pages = Array.isArray(window.__SEO_MODULE_DATA__) ? window.__SEO_MODULE_DATA__ : [];
        var stats = window.__SEO_MODULE_STATS__ || {};
        setupDashboard(pages, stats);
        setupDetailPage(stats);
        attachSeverityFilters($('#seoPageDetail'), 'seo');
    }

    $(function () {
        bootstrap();
    });

    window.loadSeoModule = loadSeoModule;
})(jQuery);
