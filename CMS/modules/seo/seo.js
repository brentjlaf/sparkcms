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
                $el.removeAttr('hidden').show();
            } else {
                $el.attr('hidden', 'hidden').hide();
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
        updateStats($root, stats);

        var currentFilter = 'all';
        var currentSort = 'score-desc';
        var currentView = 'grid';
        var searchQuery = '';

        var $grid = $root.find('.seo-page-grid');
        var $gridCards = $grid.find('.seo-page-card');
        var $table = $root.find('.seo-page-table');
        var $tableBody = $table.find('tbody');
        var $tableRows = $tableBody.find('tr');

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
            currentView = view;
            $root.find('.seo-view-btn').removeClass('active').attr('aria-pressed', 'false');
            $btn.addClass('active').attr('aria-pressed', 'true');
            if (currentView === 'table') {
                $grid.attr('hidden', 'hidden');
                $table.removeAttr('hidden');
            } else {
                $table.attr('hidden', 'hidden');
                $grid.removeAttr('hidden');
            }
        });

        $root.find('[data-seo-action="scan-all"]').on('click', function () {
            alert('Scanning all pages for SEO signals...\n\nThis would trigger a batch analysis of titles, descriptions, headings, structured data, and link health.');
        });

        $root.on('click', '.seo-card-link', function (event) {
            var slug = String($(this).closest('.seo-page-card').data('page-slug') || '');
            if (!slug || !pagesMap[slug]) {
                return;
            }
            // allow default navigation but announce action for context
            if (window.sessionStorage) {
                sessionStorage.setItem('seo:lastViewedSlug', slug);
            }
        });
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
