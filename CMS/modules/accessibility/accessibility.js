(function ($) {
    'use strict';

    function loadAccessibilityModule(params) {
        const $container = $('#contentContainer');
        if (!$container.length || typeof $.fn.load !== 'function') {
            return false;
        }

        const query = params ? ('?' + params) : '';
        $container.load('modules/accessibility/view.php' + query, function () {
            $container.find('.content-section').addClass('active');
            $.getScript('modules/accessibility/accessibility.js').fail(function () {
                // no-op if the script could not be reloaded
            });
        });
        return true;
    }

    function getScoreClass(score) {
        if (score >= 90) {
            return 'a11y-score--aaa';
        }
        if (score >= 80) {
            return 'a11y-score--aa';
        }
        if (score >= 60) {
            return 'a11y-score--partial';
        }
        return 'a11y-score--failing';
    }

    function getLevelBadge(level) {
        return 'level-' + String(level || '').toLowerCase();
    }

    function getImpactLabel(impact) {
        switch (impact) {
            case 'critical':
                return 'Critical';
            case 'serious':
                return 'Serious';
            case 'moderate':
                return 'Moderate';
            case 'minor':
                return 'Minor';
            default:
                return 'Review';
        }
    }

    function formatViolationsSummary(violations) {
        if (!violations) {
            return '';
        }
        const segments = [];
        if (violations.critical) {
            segments.push(violations.critical + ' critical');
        }
        if (violations.serious) {
            segments.push(violations.serious + ' serious');
        }
        if (violations.moderate) {
            segments.push(violations.moderate + ' moderate');
        }
        if (violations.minor) {
            segments.push(violations.minor + ' minor');
        }
        if (!segments.length) {
            return 'No outstanding issues';
        }
        return violations.total + ' total (' + segments.join(', ') + ')';
    }

    function renderMetricList(metrics) {
        const items = [];
        if (!metrics) {
            return '';
        }
        const missingAlt = metrics.missingAlt || 0;
        items.push('<li><span class="label">Images</span><span class="value">' + (metrics.images || 0) + '</span><span class="hint">' + (missingAlt > 0 ? missingAlt + ' missing alt text' : 'All images described') + '</span></li>');
        items.push('<li><span class="label">Headings</span><span class="value">H1: ' + ((metrics.headings && metrics.headings.h1) || 0) + ' / H2: ' + ((metrics.headings && metrics.headings.h2) || 0) + '</span><span class="hint">Ensure a single descriptive H1</span></li>');
        items.push('<li><span class="label">Generic links</span><span class="value">' + (metrics.genericLinks || 0) + '</span><span class="hint">' + ((metrics.genericLinks || 0) > 0 ? 'Improve link clarity' : 'All links descriptive') + '</span></li>');
        items.push('<li><span class="label">Landmarks</span><span class="value">' + (metrics.landmarks || 0) + '</span><span class="hint">' + ((metrics.landmarks || 0) > 0 ? 'Landmarks detected' : 'Add semantic landmarks') + '</span></li>');
        return items.join('');
    }

    function filterPages(data, filter, query) {
        return data.filter(function (page) {
            const matchesFilter = (function () {
                switch (filter) {
                    case 'failing':
                        return (page.violations && (page.violations.critical > 0 || page.accessibilityScore < 60 || page.wcagLevel === 'Failing'));
                    case 'partial':
                        return page.wcagLevel === 'Partial';
                    case 'compliant':
                        return page.wcagLevel === 'AA' || page.wcagLevel === 'AAA';
                    default:
                        return true;
                }
            }());

            if (!matchesFilter) {
                return false;
            }

            if (!query) {
                return true;
            }

            const haystacks = [
                page.title || '',
                page.url || '',
                page.path || '',
                (page.pageType || ''),
                (page.statusMessage || ''),
            ];
            if (page.issues && Array.isArray(page.issues.preview)) {
                haystacks.push(page.issues.preview.join(' '));
            }
            return haystacks.join(' ').toLowerCase().indexOf(query) !== -1;
        });
    }

    function updateFilterPills(data, $buttons) {
        const counts = {
            all: data.length,
            failing: 0,
            partial: 0,
            compliant: 0,
        };

        data.forEach(function (page) {
            if (page.wcagLevel === 'Partial') {
                counts.partial += 1;
            }
            if (page.wcagLevel === 'AA' || page.wcagLevel === 'AAA') {
                counts.compliant += 1;
            }
            if ((page.violations && page.violations.critical > 0) || page.wcagLevel === 'Failing' || page.accessibilityScore < 60) {
                counts.failing += 1;
            }
        });

        $buttons.each(function () {
            const $btn = $(this);
            const type = $btn.data('a11y-filter');
            const $count = $btn.find('.a11y-filter-count');
            if ($count.length && Object.prototype.hasOwnProperty.call(counts, type)) {
                $count.text(counts[type]);
            }
        });
    }

    $(function () {
        const data = Array.isArray(window.a11yDashboardData) ? window.a11yDashboardData : [];
        const stats = window.a11yDashboardStats || {};
        const dataMap = {};
        data.forEach(function (page) {
            dataMap[page.slug] = page;
        });

        const $grid = $('#a11yPagesGrid');
        const $tableView = $('#a11yTableView');
        const $tableBody = $('#a11yTableBody');
        const $empty = $('#a11yEmptyState');
        const $filterButtons = $('[data-a11y-filter]');
        const $viewButtons = $('[data-a11y-view]');
        const $searchInput = $('#a11ySearchInput');
        const $modal = $('#a11yPageDetail');
        const $modalClose = $('#a11yDetailClose');
        const $modalMetrics = $('#a11yDetailMetrics');
        const $modalIssues = $('#a11yDetailIssues');
        const $modalScore = $('#a11yDetailScore');
        const $modalLevel = $('#a11yDetailLevel');
        const $modalViolations = $('#a11yDetailViolations');
        const $modalTitle = $('#a11yDetailTitle');
        const $modalUrl = $('#a11yDetailUrl');
        const $modalDescription = $('#a11yDetailDescription');
        const $fullAuditBtn = $modal.find('[data-a11y-action="full-audit"]');
        const $scanAllBtn = $('#scanAllPagesBtn');
        const $downloadReportBtn = $('#downloadWcagReport');

        let currentFilter = 'all';
        let currentView = 'grid';
        let filteredPages = data.slice();
        let activeSlug = null;

        function closeModal() {
            activeSlug = null;
            $modal.attr('hidden', true).removeClass('is-visible');
        }

        function openModal(page) {
            if (!page) {
                return;
            }
            activeSlug = page.slug;
            $modalTitle.text(page.title || 'Accessibility details');
            $modalUrl.text(page.url || '');
            $modalDescription.text(page.summaryLine || page.statusMessage || '');
            $modalScore.text((page.accessibilityScore || 0) + '%');
            $modalScore.removeClass('a11y-score--aaa a11y-score--aa a11y-score--partial a11y-score--failing').addClass(getScoreClass(page.accessibilityScore));
            $modalLevel.text(page.wcagLevel || 'Unknown');
            $modalLevel.removeClass('level-aaa level-aa level-partial level-failing level-').addClass(getLevelBadge(page.wcagLevel));
            $modalViolations.text(formatViolationsSummary(page.violations));
            $modalMetrics.html(renderMetricList(page.metrics));

            if (page.issues && Array.isArray(page.issues.details) && page.issues.details.length) {
                const issueItems = page.issues.details.map(function (issue) {
                    const impact = getImpactLabel(issue.impact);
                    return '<li><span class="issue">' + issue.description + '</span><span class="impact impact-' + issue.impact + '">' + impact + '</span><span class="tip">' + issue.recommendation + '</span></li>';
                });
                $modalIssues.html(issueItems.join(''));
            } else {
                $modalIssues.html('<li class="no-issues">No outstanding issues detected.</li>');
            }

            $modal.attr('hidden', false).addClass('is-visible');
        }

        function createIssueTags(page) {
            if (!page.issues || !Array.isArray(page.issues.preview)) {
                return '';
            }
            const severityClass = (page.violations && page.violations.critical > 0) ? 'critical' : ((page.violations && page.violations.serious > 0) ? 'serious' : '');
            return page.issues.preview.map(function (issue) {
                return '<span class="a11y-issue-tag ' + severityClass + '">' + issue + '</span>';
            }).join('');
        }

        function createCard(page) {
            const $card = $('<article>', {
                class: 'a11y-page-card',
                tabindex: 0,
                role: 'listitem',
                'data-slug': page.slug
            });

            const cardHtml = [
                '<div class="a11y-page-card__header">',
                '<div class="a11y-page-score ' + getScoreClass(page.accessibilityScore) + '">' + (page.accessibilityScore || 0) + '%</div>',
                '<h3 class="a11y-page-title">' + (page.title || 'Untitled') + '</h3>',
                '<p class="a11y-page-url">' + (page.url || '') + '</p>',
                '</div>',
                '<div class="a11y-page-card__metrics">',
                '<div><span class="label">Violations</span><span class="value">' + ((page.violations && page.violations.total) || 0) + '</span></div>',
                '<div><span class="label">Warnings</span><span class="value">' + (page.warnings || 0) + '</span></div>',
                '<div><span class="label">WCAG</span><span class="value ' + getLevelBadge(page.wcagLevel) + '">' + (page.wcagLevel || '—') + '</span></div>',
                '</div>',
                '<div class="a11y-page-card__issues">',
                '<div class="a11y-issue-tags">' + createIssueTags(page) + '</div>',
                '</div>'
            ].join('');

            $card.html(cardHtml);
            return $card;
        }

        function createTableRow(page) {
            const $row = $('<div>', {
                class: 'a11y-table-row',
                role: 'row',
                'data-slug': page.slug
            });

            const rowHtml = [
                '<div class="a11y-table-cell">',
                '<div class="title">' + (page.title || 'Untitled') + '</div>',
                '<div class="subtitle">' + (page.url || '') + '</div>',
                '</div>',
                '<div class="a11y-table-cell score">',
                '<span class="a11y-table-score ' + getScoreClass(page.accessibilityScore) + '">' + (page.accessibilityScore || 0) + '%</span>',
                '</div>',
                '<div class="a11y-table-cell level"><span class="' + getLevelBadge(page.wcagLevel) + '">' + (page.wcagLevel || '—') + '</span></div>',
                '<div class="a11y-table-cell">' + formatViolationsSummary(page.violations) + '</div>',
                '<div class="a11y-table-cell warnings">' + (page.warnings || 0) + '</div>',
                '<div class="a11y-table-cell">' + (page.lastScanned || '') + '</div>',
                '<div class="a11y-table-cell actions"><button type="button" class="a11y-btn a11y-btn--icon" data-a11y-action="open-detail" data-slug="' + page.slug + '"><i class="fas fa-universal-access" aria-hidden="true"></i><span class="sr-only">Open detail</span></button></div>'
            ].join('');

            $row.html(rowHtml);
            return $row;
        }

        function render() {
            if (!$grid.length) {
                return;
            }

            if (!filteredPages.length) {
                $grid.empty().attr('hidden', true);
                $tableView.attr('hidden', true);
                $empty.attr('hidden', false);
                return;
            }

            $empty.attr('hidden', true);

            if (currentView === 'grid') {
                $grid.attr('hidden', false);
                $tableView.attr('hidden', true);
                $grid.empty();
                filteredPages.forEach(function (page) {
                    $grid.append(createCard(page));
                });
            } else {
                $grid.attr('hidden', true).empty();
                $tableView.attr('hidden', false);
                $tableBody.empty();
                filteredPages.forEach(function (page) {
                    $tableBody.append(createTableRow(page));
                });
            }
        }

        function applyFilters() {
            const query = ($searchInput.val() || '').toLowerCase();
            filteredPages = filterPages(data, currentFilter, query);
            render();
        }

        if ($grid.length) {
            updateFilterPills(data, $filterButtons);
            render();
        }

        $filterButtons.on('click', function () {
            const $btn = $(this);
            currentFilter = $btn.data('a11y-filter') || 'all';
            $filterButtons.removeClass('active');
            $btn.addClass('active');
            applyFilters();
        });

        $viewButtons.on('click', function () {
            const $btn = $(this);
            currentView = $btn.data('a11y-view') || 'grid';
            $viewButtons.removeClass('active');
            $btn.addClass('active');
            render();
        });

        $searchInput.on('input', function () {
            applyFilters();
        });

        $grid.on('click', '.a11y-page-card', function () {
            const slug = $(this).data('slug');
            openModal(dataMap[slug]);
        });

        $grid.on('keydown', '.a11y-page-card', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                const slug = $(this).data('slug');
                openModal(dataMap[slug]);
            }
        });

        $tableBody.on('click', '.a11y-table-row', function (event) {
            const $target = $(event.target);
            if ($target.closest('[data-a11y-action="open-detail"]').length) {
                return;
            }
            const slug = $(this).data('slug');
            openModal(dataMap[slug]);
        });

        $tableBody.on('click', '[data-a11y-action="open-detail"]', function (event) {
            event.stopPropagation();
            const slug = $(this).data('slug');
            if (!loadAccessibilityModule('page=' + encodeURIComponent(slug))) {
                if (stats.detailBaseUrl) {
                    window.location.href = stats.detailBaseUrl + encodeURIComponent(slug);
                }
            }
        });

        if ($modal.length) {
            $(document).off('keydown.a11yModal');
            $modal.on('click', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $modalClose.on('click', function () {
                closeModal();
            });

            $(document).on('keydown.a11yModal', function (event) {
                if (event.key === 'Escape' && $modal.hasClass('is-visible')) {
                    closeModal();
                }
            });

            $fullAuditBtn.on('click', function () {
                if (!activeSlug) {
                    return;
                }
                if (!loadAccessibilityModule('page=' + encodeURIComponent(activeSlug))) {
                    if (stats.detailBaseUrl) {
                        window.location.href = stats.detailBaseUrl + encodeURIComponent(activeSlug);
                    }
                }
            });
        }

        if ($scanAllBtn.length) {
            $scanAllBtn.on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) {
                    return;
                }
                $btn.prop('disabled', true).addClass('is-loading');
                const $icon = $btn.find('i');
                const originalIcon = $icon.attr('class');
                $icon.attr('class', 'fas fa-spinner fa-spin');
                $btn.find('span').text('Scanning...');
                setTimeout(function () {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    $icon.attr('class', originalIcon);
                    $btn.find('span').text('Scan All Pages');
                    if (!loadAccessibilityModule('')) {
                        window.location.reload();
                    }
                }, 900);
            });
        }

        if ($downloadReportBtn.length) {
            $downloadReportBtn.on('click', function () {
                window.alert('Generating WCAG compliance report...\n\nThe report includes severity breakdowns, remediation recommendations, and scan history.');
            });
        }

        const $detailPage = $('#a11yDetailPage');
        if ($detailPage.length) {
            const pageSlug = $detailPage.data('page-slug');
            $('#a11yBackToDashboard').on('click', function (event) {
                event.preventDefault();
                if (!loadAccessibilityModule('')) {
                    if (stats.moduleUrl) {
                        window.location.href = stats.moduleUrl;
                    }
                }
            });

            $('[data-a11y-action="rescan-page"]').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) {
                    return;
                }
                const $icon = $btn.find('i');
                const originalClass = $icon.attr('class');
                $btn.prop('disabled', true).addClass('is-loading');
                $icon.attr('class', 'fas fa-spinner fa-spin');
                setTimeout(function () {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    $icon.attr('class', originalClass);
                    if (!loadAccessibilityModule('page=' + encodeURIComponent(pageSlug))) {
                        if (stats.detailBaseUrl) {
                            window.location.href = stats.detailBaseUrl + encodeURIComponent(pageSlug);
                        }
                    }
                }, 900);
            });

        }

        applyFilters();
    });
})(jQuery);
