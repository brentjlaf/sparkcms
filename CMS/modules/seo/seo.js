(function ($) {
    'use strict';

    function getModuleData() {
        if (window.__SEO_MODULE_DATA__ && typeof window.__SEO_MODULE_DATA__ === 'object') {
            return window.__SEO_MODULE_DATA__;
        }
        return { pages: [], stats: {} };
    }

    function normalizeScore(value, fallback) {
        var number = Number(value);
        if (Number.isFinite && Number.isFinite(number)) {
            return Math.max(0, Math.min(100, Math.round(number)));
        }
        number = parseFloat(value);
        if (!isNaN(number)) {
            return Math.max(0, Math.min(100, Math.round(number)));
        }
        return fallback;
    }

    function compareStrings(a, b) {
        return String(a || '').localeCompare(String(b || ''), undefined, { sensitivity: 'base' });
    }

    function getScoreDeltaMeta(current, previous) {
        var currentScore = normalizeScore(current, 0);
        var previousScore = typeof previous === 'undefined' || previous === null ? currentScore : normalizeScore(previous, currentScore);
        var delta = currentScore - previousScore;
        var absolute = Math.abs(delta);
        var className = 'score-delta--even';
        var srText = 'No change since last scan.';
        if (delta > 0) {
            className = 'score-delta--up';
            srText = 'Improved by ' + absolute + ' ' + (absolute === 1 ? 'point' : 'points') + ' since last scan.';
        } else if (delta < 0) {
            className = 'score-delta--down';
            srText = 'Regressed by ' + absolute + ' ' + (absolute === 1 ? 'point' : 'points') + ' since last scan.';
        }
        var display = delta === 0 ? '0' : (delta > 0 ? '+' : '−') + absolute;
        return {
            className: className,
            display: display,
            srText: srText
        };
    }

    function renderScoreDelta(current, previous) {
        var meta = getScoreDeltaMeta(current, previous);
        return '<span class="score-delta ' + meta.className + '"><span aria-hidden="true">' + meta.display + '</span><span class="sr-only">' + meta.srText + '</span></span>';
    }

    function getLevelBadge(level) {
        var normalized = String(level || '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
        return 'level-' + (normalized || '');
    }

    function getScoreQualityClass(score) {
        if (score >= 90) {
            return 'seo-score--excellent';
        }
        if (score >= 75) {
            return 'seo-score--good';
        }
        if (score >= 60) {
            return 'seo-score--fair';
        }
        return 'seo-score--poor';
    }

    function formatSeoViolations(violations) {
        if (!violations) {
            return 'No outstanding SEO issues';
        }
        var total = Number(violations.total || 0);
        if (!total) {
            return 'No outstanding SEO issues';
        }
        var parts = [];
        if (violations.critical) {
            parts.push(violations.critical + ' critical');
        }
        if (violations.serious) {
            parts.push(violations.serious + ' serious');
        }
        if (violations.moderate) {
            parts.push(violations.moderate + ' moderate');
        }
        if (violations.minor) {
            parts.push(violations.minor + ' minor');
        }
        return total + ' total' + (parts.length ? ' (' + parts.join(', ') + ')' : '');
    }

    function renderMetricList(metrics) {
        if (!metrics) {
            return '';
        }
        function boolLabel(value, positive, negative) {
            return value ? positive : negative;
        }
        var links = metrics.links || {};
        var items = [];
        items.push('<li><span class="label">Title length</span><span class="value">' + (metrics.titleLength || 0) + ' characters</span><span class="hint">Keep between 50–60 characters</span></li>');
        items.push('<li><span class="label">Meta description</span><span class="value">' + (metrics.metaDescriptionLength || 0) + ' characters</span><span class="hint">Aim for 120–160 characters</span></li>');
        items.push('<li><span class="label">Canonical URL</span><span class="value">' + boolLabel(metrics.hasCanonical, 'Present', 'Missing') + '</span><span class="hint">Prevent duplicate content</span></li>');
        items.push('<li><span class="label">Word count</span><span class="value">' + (metrics.wordCount || 0) + '</span><span class="hint">Support in-depth coverage</span></li>');
        items.push('<li><span class="label">H1 headings</span><span class="value">' + (metrics.h1Count || 0) + '</span><span class="hint">Use a single descriptive H1</span></li>');
        items.push('<li><span class="label">Structured data</span><span class="value">' + boolLabel(metrics.hasStructuredData, 'Detected', 'Not detected') + '</span><span class="hint">Enhance rich results</span></li>');
        items.push('<li><span class="label">Images</span><span class="value">' + (metrics.images || 0) + '</span><span class="hint">' + ((metrics.missingAlt || 0) > 0 ? metrics.missingAlt + ' missing alt text' : 'All images described') + '</span></li>');
        items.push('<li><span class="label">Open Graph</span><span class="value">' + boolLabel(metrics.hasOpenGraph, 'Configured', 'Missing') + '</span><span class="hint">Optimise social sharing</span></li>');
        items.push('<li><span class="label">Internal links</span><span class="value">' + (links.internal || 0) + '</span><span class="hint">Strengthen crawl paths</span></li>');
        items.push('<li><span class="label">External links</span><span class="value">' + (links.external || 0) + '</span><span class="hint">Provide authoritative references</span></li>');
        items.push('<li><span class="label">Robots directives</span><span class="value">' + boolLabel(metrics.isNoindex, 'Noindex', 'Indexable') + '</span><span class="hint">Ensure important pages are indexable</span></li>');
        return items.join('');
    }

    function normalizeImpact(impact) {
        var value = String(impact || '').toLowerCase();
        if (['critical', 'serious', 'moderate', 'minor'].indexOf(value) !== -1) {
            return value;
        }
        return 'review';
    }

    function sortPages(pages, sortKey) {
        var sorted = pages.slice();
        var sorters = {
            'score-desc': function (a, b) {
                var diff = normalizeScore(b.seoScore, 0) - normalizeScore(a.seoScore, 0);
                return diff !== 0 ? diff : compareStrings(a.title, b.title);
            },
            'score-asc': function (a, b) {
                var diff = normalizeScore(a.seoScore, 0) - normalizeScore(b.seoScore, 0);
                return diff !== 0 ? diff : compareStrings(a.title, b.title);
            },
            'issues-desc': function (a, b) {
                var diff = (b.violations && b.violations.total || 0) - (a.violations && a.violations.total || 0);
                return diff !== 0 ? diff : compareStrings(a.title, b.title);
            },
            'title-asc': function (a, b) {
                return compareStrings(a.title, b.title) || compareStrings(a.url, b.url);
            }
        };

        var sorter = sorters[sortKey] || sorters['score-desc'];
        sorted.sort(sorter);
        return sorted;
    }

    function matchesFilter(page, filter) {
        switch (filter) {
            case 'critical':
                return page.optimizationLevel === 'Critical';
            case 'needs-work':
                return page.optimizationLevel === 'Needs Improvement';
            case 'optimized':
                return page.optimizationLevel === 'Optimised';
            default:
                return true;
        }
    }

    function matchesQuery(page, query) {
        if (!query) {
            return true;
        }
        var haystacks = [
            page.title || '',
            page.url || '',
            page.statusMessage || '',
            page.summaryLine || ''
        ];
        if (page.issues && page.issues.preview) {
            haystacks.push(page.issues.preview.join(' '));
        }
        return haystacks.join(' ').toLowerCase().indexOf(query) !== -1;
    }

    function reorderElements($container, selector, order) {
        var orderMap = {};
        order.forEach(function (slug, index) {
            orderMap[String(slug || '')] = index;
        });
        var elements = $container.find(selector).get();
        elements.sort(function (a, b) {
            var aSlug = $(a).data('page-slug');
            var bSlug = $(b).data('page-slug');
            var aIndex = Object.prototype.hasOwnProperty.call(orderMap, aSlug) ? orderMap[aSlug] : Number.MAX_SAFE_INTEGER;
            var bIndex = Object.prototype.hasOwnProperty.call(orderMap, bSlug) ? orderMap[bSlug] : Number.MAX_SAFE_INTEGER;
            if (aIndex === bIndex) {
                return 0;
            }
            return aIndex < bIndex ? -1 : 1;
        });
        elements.forEach(function (element) {
            $container.append(element);
        });
    }

    function updateVisibility($elements, visibleSlugs) {
        $elements.each(function () {
            var $el = $(this);
            var slug = String($el.data('page-slug') || '');
            if (visibleSlugs.has(slug)) {
                $el.removeAttr('hidden').attr('aria-hidden', 'false').show();
            } else {
                $el.attr('hidden', 'hidden').attr('aria-hidden', 'true').hide();
            }
        });
    }

    function updateStats($root, stats) {
        if (!stats) {
            return;
        }
        $root.find('#seoStatTotalPages').text(stats.totalPages || 0);
        $root.find('#seoStatAvgScore').text((stats.avgScore || 0) + '%');
        $root.find('#seoStatCritical').text(stats.criticalIssues || 0);
        $root.find('#seoStatOptimized').text(stats.optimizedPages || 0);
        if (stats.filterCounts) {
            Object.keys(stats.filterCounts).forEach(function (key) {
                $root.find('.a11y-filter-count[data-count="' + key + '"]').text(stats.filterCounts[key]);
            });
        }
    }

    function initDashboard($root, pages, pagesMap, stats) {
        stats = stats || {};
        updateStats($root, stats);

        var currentFilter = 'all';
        var currentSort = 'score-desc';
        var currentView = 'grid';
        var searchQuery = '';
        var detailBaseUrl = stats.detailBaseUrl || '';

        var $grid = $root.find('.seo-page-grid');
        var $gridCards = $grid.find('.seo-page-card');
        var $table = $root.find('.seo-page-table');
        var $tableBody = $table.find('tbody');
        var $tableRows = $tableBody.find('tr');
        var $modal = $('#seoPageDetail');
        var $modalClose = $('#seoDetailClose');
        var $modalTitle = $('#seoDetailTitle');
        var $modalUrl = $('#seoDetailUrl');
        var $modalDescription = $('#seoDetailDescription');
        var $modalScore = $('#seoDetailScore');
        var $modalLevel = $('#seoDetailLevel');
        var $modalSignals = $('#seoDetailSignals');
        var $modalMetrics = $('#seoDetailMetrics');
        var $modalIssues = $('#seoDetailIssues');
        var $fullAuditBtn = $modal.find('[data-seo-action="full-audit"]');
        var activeSlug = null;
        var lastFocusedElement = null;

        $grid.attr('aria-hidden', 'false');
        $table.attr('aria-hidden', 'true');
        if ($modal.length) {
            $modal.attr('aria-hidden', 'true');
        }

        function closeModal() {
            if (!$modal.length) {
                return;
            }
            $modal.attr('hidden', 'hidden').attr('aria-hidden', 'true').removeClass('is-visible');
            activeSlug = null;
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
            lastFocusedElement = null;
        }

        function openModal(page) {
            if (!page) {
                return;
            }
            if (!$modal.length) {
                if (detailBaseUrl) {
                    window.location.href = detailBaseUrl + encodeURIComponent(page.slug || '');
                }
                return;
            }

            activeSlug = page.slug || '';
            lastFocusedElement = document.activeElement;

            var score = normalizeScore(page.seoScore, 0);
            var deltaHtml = renderScoreDelta(score, page.previousScore);
            var scoreClass = getScoreQualityClass(score);
            var levelClass = getLevelBadge(page.optimizationLevel);

            $modalTitle.text(page.title || 'SEO details');
            $modalUrl.text(page.url || '');
            $modalDescription.text(page.summaryLine || page.statusMessage || '');
            $modalScore
                .removeClass('seo-score--excellent seo-score--good seo-score--fair seo-score--poor')
                .addClass(scoreClass)
                .html('<span class="score-indicator__number">' + score + '%</span>' + deltaHtml);
            $modalLevel.attr('class', 'a11y-detail-level ' + levelClass).text(page.optimizationLevel || 'Unknown');
            $modalSignals.text(formatSeoViolations(page.violations));
            $modalMetrics.html(renderMetricList(page.metrics));

            $modalIssues.empty();
            if (page.issues && Array.isArray(page.issues.details) && page.issues.details.length) {
                page.issues.details.forEach(function (issue) {
                    var impact = normalizeImpact(issue.impact);
                    var label = impact.charAt(0).toUpperCase() + impact.slice(1);
                    var $item = $('<li>');
                    $('<span>', { 'class': 'issue', text: issue.description || '' }).appendTo($item);
                    $('<span>', { 'class': 'impact impact-' + impact, text: label }).appendTo($item);
                    $('<span>', { 'class': 'tip', text: issue.recommendation || '' }).appendTo($item);
                    $modalIssues.append($item);
                });
            } else {
                $modalIssues.append($('<li>', { 'class': 'no-issues', text: 'No outstanding SEO issues detected.' }));
            }

            $modal.attr('hidden', false).attr('aria-hidden', 'false').addClass('is-visible');
            if ($modalClose.length) {
                $modalClose.trigger('focus');
            }

            if (window.sessionStorage) {
                try {
                    window.sessionStorage.setItem('seo:lastViewedSlug', activeSlug);
                } catch (error) {
                    // ignore storage errors
                }
            }
        }

        function openModalBySlug(slug) {
            var page = pagesMap[slug];
            if (page) {
                openModal(page);
            }
        }

        function applyFilters() {
            var normalizedQuery = searchQuery.trim().toLowerCase();
            var filtered = pages.filter(function (page) {
                return matchesFilter(page, currentFilter) && matchesQuery(page, normalizedQuery);
            });

            var sorted = sortPages(filtered, currentSort);
            var order = sorted.map(function (page) { return page.slug || ''; });
            var visibleSet = new Set(order);

            if (order.length === 0) {
                $root.addClass('seo-dashboard--empty');
            } else {
                $root.removeClass('seo-dashboard--empty');
            }

            if (order.length) {
                reorderElements($grid, '.seo-page-card', order);
                reorderElements($tableBody, 'tr', order);
            }

            $gridCards = $grid.find('.seo-page-card');
            $tableRows = $tableBody.find('tr');

            updateVisibility($gridCards, visibleSet);
            updateVisibility($tableRows, visibleSet);
        }

        applyFilters();

        $root.find('.a11y-filter-btn').on('click', function () {
            var $btn = $(this);
            var filter = String($btn.data('seo-filter') || 'all');
            currentFilter = filter;
            $root.find('.a11y-filter-btn').removeClass('active').attr('aria-pressed', 'false');
            $btn.addClass('active').attr('aria-pressed', 'true');
            applyFilters();
        });

        $root.find('.a11y-sort-btn').on('click', function () {
            var $btn = $(this);
            var sortKey = String($btn.data('seo-sort') || 'score-desc');
            currentSort = sortKey;
            $root.find('.a11y-sort-btn').removeClass('active').attr('aria-pressed', 'false');
            $btn.addClass('active').attr('aria-pressed', 'true');
            applyFilters();
        });

        $root.find('#seoSearchInput').on('input', function () {
            searchQuery = String($(this).val() || '');
            applyFilters();
        });

        $root.find('.seo-view-btn').on('click', function () {
            var $btn = $(this);
            var view = String($btn.data('seo-view') || 'grid');
            if (view === currentView) {
                return;
            }
            currentView = view;
            $root.find('.seo-view-btn').removeClass('active').attr('aria-pressed', 'false');
            $btn.addClass('active').attr('aria-pressed', 'true');
            if (currentView === 'table') {
                $grid.attr('hidden', 'hidden').attr('aria-hidden', 'true');
                $table.removeAttr('hidden').attr('aria-hidden', 'false');
            } else {
                $table.attr('hidden', 'hidden').attr('aria-hidden', 'true');
                $grid.removeAttr('hidden').attr('aria-hidden', 'false');
            }
        });

        $root.find('[data-seo-action="scan-all"]').on('click', function () {
            alert('Scanning all pages for SEO signals...\n\nThis would trigger a batch analysis of titles, descriptions, headings, structured data, and link health.');
        });

        $grid.on('click', '.seo-page-card', function (event) {
            var $target = $(event.target);
            if ($target.closest('[data-seo-action]').length) {
                return;
            }
            var slug = String($(this).data('page-slug') || '');
            openModalBySlug(slug);
        });

        $grid.on('keydown', '.seo-page-card', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                var slug = String($(this).data('page-slug') || '');
                openModalBySlug(slug);
            }
        });

        $tableBody.on('click', 'tr', function () {
            var slug = String($(this).data('page-slug') || '');
            openModalBySlug(slug);
        });

        $tableBody.on('keydown', 'tr', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                var slug = String($(this).data('page-slug') || '');
                openModalBySlug(slug);
            }
        });

        if ($modal.length) {
            $modal.on('click', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $modalClose.on('click', function () {
                closeModal();
            });

            $(document).off('keydown.seoModal').on('keydown.seoModal', function (event) {
                if (event.key === 'Escape' && $modal.hasClass('is-visible')) {
                    closeModal();
                }
            });

            $fullAuditBtn.on('click', function () {
                if (!activeSlug || !detailBaseUrl) {
                    return;
                }
                window.location.href = detailBaseUrl + encodeURIComponent(activeSlug);
            });
        }
    }

    function initDetail($root) {
        var $issueCards = $root.find('[data-impact]');
        var $buttons = $root.find('[data-seo-severity]');
        var $status = $('#seoIssueFilterStatus');

        function updateIssueVisibility(filter) {
            var visibleCount = 0;
            $issueCards.each(function () {
                var $card = $(this);
                var impact = String($card.data('impact') || '');
                var shouldShow = (filter === 'all' || impact === filter);
                if (shouldShow) {
                    $card.removeAttr('hidden').show();
                    visibleCount++;
                } else {
                    $card.attr('hidden', 'hidden').hide();
                }
            });
            if (visibleCount === 0) {
                $('#seoNoIssuesMessage').removeAttr('hidden').show();
            } else {
                $('#seoNoIssuesMessage').attr('hidden', 'hidden').hide();
            }
            if ($status.length) {
                $status.text(visibleCount + ' issues visible for severity filter “' + filter + '”.');
            }
        }

        $buttons.on('click', function () {
            var $btn = $(this);
            var filter = String($btn.data('seo-severity') || 'all');
            $buttons.removeClass('active').attr('aria-pressed', 'false');
            $btn.addClass('active').attr('aria-pressed', 'true');
            updateIssueVisibility(filter);
        });

        $root.find('[data-seo-action="rescan-page"]').on('click', function () {
            alert('Re-scanning this page...\n\nA fresh crawl would analyse metadata, headings, internal links, and structured data to refresh the SEO score.');
        });

        updateIssueVisibility('all');
    }

    $(function () {
        var moduleData = getModuleData();
        var pages = Array.isArray(moduleData.pages) ? moduleData.pages : [];
        var stats = moduleData.stats || {};
        var pagesMap = {};
        pages.forEach(function (page) {
            pagesMap[String(page.slug || '')] = page;
        });

        var $dashboard = $('.seo-dashboard');
        if ($dashboard.length) {
            initDashboard($dashboard, pages, pagesMap, stats);
        }

        var $detail = $('.seo-detail-page');
        if ($detail.length) {
            initDetail($detail);
        }
    });
}(jQuery));
