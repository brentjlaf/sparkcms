$(function () {
    const $dashboard = $('.seo-dashboard');
    if ($dashboard.length === 0) {
        return;
    }

    const $searchInput = $('#seoSearchInput');
    const $filterButtons = $dashboard.find('.seo-filter-btn');
    const $viewButtons = $dashboard.find('.seo-view-btn');
    const $sortSelect = $('#seoSortSelect');
    const $sortStatus = $('#seoSortStatus');
    const $grid = $('#seoGrid');
    let $cards = $grid.find('.seo-card');
    const $tableWrapper = $('#seoTableWrapper');
    const $tableBody = $tableWrapper.find('tbody');
    let $tableRows = $tableBody.find('tr');
    const $detailOverlay = $('#seoDetail');
    const $detailClose = $detailOverlay.find('.seo-detail-close');

    const detailElements = {
        hero: $detailOverlay.find('[data-detail="hero"]'),
        title: $detailOverlay.find('[data-detail="title"]'),
        url: $detailOverlay.find('[data-detail="url"]'),
        summary: $detailOverlay.find('[data-detail="summary"]'),
        scoreValue: $detailOverlay.find('[data-detail="score-value"]'),
        scoreDelta: $detailOverlay.find('[data-detail="score-delta"]'),
        scoreStatusLabel: $detailOverlay.find('[data-detail="score-status-label"]'),
        scoreLabel: $detailOverlay.find('[data-detail="score-label"]'),
        lastUpdated: $detailOverlay.find('[data-detail="last-updated"]'),
        criticalCount: $detailOverlay.find('[data-detail="critical-count"]'),
        warningCount: $detailOverlay.find('[data-detail="warning-count"]'),
        metaTitle: $detailOverlay.find('[data-detail="meta-title"]'),
        metaTitleLength: $detailOverlay.find('[data-detail="meta-title-length"]'),
        metaDescription: $detailOverlay.find('[data-detail="meta-description"]'),
        metaDescriptionLength: $detailOverlay.find('[data-detail="meta-description-length"]'),
        socialStatus: $detailOverlay.find('[data-detail="social-status"]'),
        wordCount: $detailOverlay.find('[data-detail="word-count"]'),
        headingStatus: $detailOverlay.find('[data-detail="heading-status"]'),
        imageAltStatus: $detailOverlay.find('[data-detail="image-alt-status"]'),
        internalLinkStatus: $detailOverlay.find('[data-detail="internal-link-status"]'),
        issues: $detailOverlay.find('[data-detail="issues"]'),
        issueCount: $detailOverlay.find('[data-detail="issue-count"]'),
    };

    function normalizeScoreValue(value, fallback) {
        const number = Number(value);
        if (Number.isFinite(number)) {
            return Math.max(0, Math.min(100, Math.round(number)));
        }
        return fallback;
    }

    function getScoreDeltaMeta(current, previous) {
        const currentScore = normalizeScoreValue(current, 0);
        const previousScore = normalizeScoreValue(previous, currentScore);
        const delta = currentScore - previousScore;
        const abs = Math.abs(delta);
        let className = 'score-delta--even';
        let srText = 'No change since last scan.';
        if (delta > 0) {
            className = 'score-delta--up';
            srText = 'Improved by ' + abs + ' ' + (abs === 1 ? 'point' : 'points') + ' since last scan.';
        } else if (delta < 0) {
            className = 'score-delta--down';
            srText = 'Regressed by ' + abs + ' ' + (abs === 1 ? 'point' : 'points') + ' since last scan.';
        }
        const display = delta === 0 ? '0' : (delta > 0 ? '+' : '−') + abs;
        return { display: display, className: className, srText: srText, value: currentScore };
    }

    function renderScoreDelta(current, previous) {
        const meta = getScoreDeltaMeta(current, previous);
        return {
            markup: '<span class="score-delta ' + meta.className + '"><span aria-hidden="true">' + meta.display + '</span><span class="sr-only">' + meta.srText + '</span></span>',
            current: meta.value
        };
    }

    const sortDefinitions = {
        'score-desc': {
            label: 'Highest score',
            compare: (a, b) => {
                if (b.score !== a.score) {
                    return b.score - a.score;
                }
                if (b.updated !== a.updated) {
                    return b.updated - a.updated;
                }
                return a.title.localeCompare(b.title);
            },
        },
        'score-asc': {
            label: 'Lowest score',
            compare: (a, b) => {
                if (a.score !== b.score) {
                    return a.score - b.score;
                }
                if (a.updated !== b.updated) {
                    return a.updated - b.updated;
                }
                return a.title.localeCompare(b.title);
            },
        },
        'updated-desc': {
            label: 'Most recently updated',
            compare: (a, b) => {
                if (b.updated !== a.updated) {
                    return b.updated - a.updated;
                }
                if (b.score !== a.score) {
                    return b.score - a.score;
                }
                return a.title.localeCompare(b.title);
            },
        },
        'title-asc': {
            label: 'Title (A → Z)',
            compare: (a, b) => {
                const result = a.title.localeCompare(b.title);
                if (result !== 0) {
                    return result;
                }
                if (b.score !== a.score) {
                    return b.score - a.score;
                }
                return b.updated - a.updated;
            },
        },
    };

    let activeFilter = 'all';
    let activeSort = $sortSelect.length ? $sortSelect.val() : 'score-desc';
    let searchQuery = '';

    if (!sortDefinitions[activeSort]) {
        activeSort = 'score-desc';
        if ($sortSelect.length) {
            $sortSelect.val(activeSort);
        }
    }

    function getTableBody() {
        return $tableBody.length ? $tableBody : $tableWrapper.find('tbody');
    }

    function refreshCollections() {
        $cards = $grid.find('.seo-card');
        const $body = getTableBody();
        $tableRows = $body.find('tr');
    }

    function getSortData(element) {
        const $el = $(element);
        const rawScore = Number($el.data('score'));
        const rawUpdated = Number($el.data('updated'));
        const rawTitle = ($el.data('title') || '').toString().toLowerCase();

        return {
            score: Number.isNaN(rawScore) ? 0 : rawScore,
            updated: Number.isNaN(rawUpdated) ? 0 : rawUpdated,
            title: rawTitle,
        };
    }

    function updateSortStatus(label) {
        if (!$sortStatus.length) {
            return;
        }
        $sortStatus.text(`Sorted by ${label}`);
    }

    function sortItems(criteria) {
        const sortKey = sortDefinitions[criteria] ? criteria : 'score-desc';
        const definition = sortDefinitions[sortKey];
        activeSort = sortKey;

        if ($sortSelect.length && $sortSelect.val() !== sortKey) {
            $sortSelect.val(sortKey);
        }

        refreshCollections();

        const compare = definition.compare;
        const cardElements = $cards.get();
        cardElements.sort((a, b) => compare(getSortData(a), getSortData(b)));
        cardElements.forEach((card) => {
            if ($grid.length) {
                $grid.append(card);
            }
        });

        const $body = getTableBody();
        const rowElements = $tableRows.get();
        rowElements.sort((a, b) => compare(getSortData(a), getSortData(b)));
        rowElements.forEach((row) => {
            if ($body.length) {
                $body.append(row);
            }
        });

        refreshCollections();
        updateSortStatus(definition.label);
        applyFilters();
    }

    function parsePageData(el) {
        const raw = el.getAttribute('data-page');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function matchesFilter(status) {
        if (activeFilter === 'all') {
            return true;
        }
        if (activeFilter === 'good') {
            return status === 'good' || status === 'excellent';
        }
        return status === activeFilter;
    }

    function matchesSearch(text) {
        if (searchQuery === '') {
            return true;
        }
        return text.indexOf(searchQuery) !== -1;
    }

    function applyFilters() {
        refreshCollections();
        const items = [].concat($cards.get(), $tableRows.get());
        items.forEach((el) => {
            const $el = $(el);
            const status = ($el.data('status') || '').toString();
            const searchable = ($el.data('search') || '').toString();
            const visible = matchesFilter(status) && matchesSearch(searchable);
            $el.toggle(visible);
        });
    }

    $searchInput.on('input', function () {
        searchQuery = $(this).val().toLowerCase();
        applyFilters();
    });

    $filterButtons.on('click', function () {
        const $button = $(this);
        activeFilter = $button.data('filter');
        $filterButtons.removeClass('active');
        $button.addClass('active');
        applyFilters();
    });

    $sortSelect.on('change', function () {
        sortItems($(this).val());
    });

    $viewButtons.on('click', function () {
        const $button = $(this);
        const view = $button.data('view');
        $viewButtons.removeClass('active');
        $button.addClass('active');

        if (view === 'table') {
            $grid.css('display', 'none');
            $tableWrapper.addClass('active');
        } else {
            $tableWrapper.removeClass('active');
            $grid.css('display', 'grid');
        }
    });

    function renderIssues(container, issues) {
        container.empty();

        if (!issues || issues.length === 0) {
            container.append(
                $('<div/>', { class: 'seo-detail-empty' }).append(
                    $('<i/>', { class: 'fas fa-check-circle', 'aria-hidden': 'true' }),
                    $('<p/>').text('Great work! Keep monitoring this page for future improvements.')
                )
            );
            return { total: 0, critical: 0, warning: 0 };
        }

        let criticalCount = 0;
        let warningCount = 0;

        issues.forEach((issue) => {
            const severityRaw = issue.severity || 'warning';
            const severity = severityRaw === 'critical' ? 'critical' : (severityRaw === 'warning' ? 'warning' : 'good');
            if (severity === 'critical') {
                criticalCount++;
            } else if (severity === 'warning') {
                warningCount++;
            }

            const description = severity === 'critical'
                ? 'Resolve this immediately to avoid search visibility losses.'
                : 'Review and fix to strengthen overall optimization.';
            const label = severity === 'critical' ? 'Critical' : (severity === 'warning' ? 'Warning' : 'Info');

            container.append(
                $('<article/>', { class: `seo-detail-issue severity-${severity}` }).append(
                    $('<div/>', { class: 'seo-detail-issue-title' }).text(issue.message),
                    $('<p/>', { class: 'seo-detail-issue-text' }).text(description),
                    $('<span/>', { class: 'seo-detail-issue-tag' }).text(label)
                )
            );
        });

        return { total: issues.length, critical: criticalCount, warning: warningCount };
    }

    function populateDetail(data) {
        if (!data) {
            return;
        }

        const status = typeof data.scoreStatus === 'string' ? data.scoreStatus : 'warning';
        detailElements.hero
            .removeClass('status-excellent status-good status-warning status-critical')
            .addClass(`status-${status}`);

        detailElements.title.text(data.title || 'Untitled');
        detailElements.url.text(data.slug ? `/${data.slug}` : '—');
        const summaryText = data.summary || 'Review the detected items to improve overall SEO quality.';
        detailElements.summary.text(summaryText);
        const scoreDelta = renderScoreDelta(data.score, data.previousScore);
        detailElements.scoreValue.text(scoreDelta.current);
        detailElements.scoreDelta.html(scoreDelta.markup);
        detailElements.scoreStatusLabel.text(data.scoreStatusLabel || 'SEO Score');
        detailElements.scoreLabel.text(data.scoreLabel || '—');

        if (data.metaTitle) {
            detailElements.metaTitle.text(data.metaTitle);
            detailElements.metaTitleLength.text(`Length: ${data.metaTitleLength || 0} characters`);
        } else {
            detailElements.metaTitle.text('No meta title provided.');
            detailElements.metaTitleLength.text('Add a concise title between 30 and 60 characters.');
        }

        if (data.metaDescription) {
            detailElements.metaDescription.text(data.metaDescription);
            detailElements.metaDescriptionLength.text(`Length: ${data.metaDescriptionLength || 0} characters`);
        } else {
            detailElements.metaDescription.text('No meta description provided.');
            detailElements.metaDescriptionLength.text('Add a compelling description between 50 and 160 characters.');
        }

        detailElements.socialStatus.text(
            data.hasSocial
                ? 'Social preview is complete with OG title, description, and image.'
                : 'Social preview is incomplete. Provide OG title, description, and image for better sharing.'
        );

        const lastUpdated = data.lastUpdated || 'Unknown';
        detailElements.lastUpdated.text(lastUpdated);
        const criticalRaw = Number(data.criticalCount);
        const warningRaw = Number(data.warningCount);
        const critical = Number.isFinite(criticalRaw) ? criticalRaw : 0;
        const warning = Number.isFinite(warningRaw) ? warningRaw : 0;
        detailElements.criticalCount.text(`${critical} ${critical === 1 ? 'issue' : 'issues'}`);
        detailElements.warningCount.text(`${warning} ${warning === 1 ? 'warning' : 'warnings'}`);
        const wordCount = typeof data.wordCount === 'number' ? data.wordCount : 0;
        if (wordCount > 0) {
            const readingTime = Math.max(1, Math.round(wordCount / 200));
            detailElements.wordCount.text(`${wordCount} words · ~${readingTime} min read`);
        } else {
            detailElements.wordCount.text('No content detected');
        }

        const h1Count = typeof data.h1Count === 'number' ? data.h1Count : 0;
        if (h1Count === 0) {
            detailElements.headingStatus.text('No H1 heading found');
        } else if (h1Count === 1) {
            detailElements.headingStatus.text('Single H1 heading in place');
        } else {
            detailElements.headingStatus.text(`${h1Count} H1 headings detected`);
        }

        const missingAlt = typeof data.missingAltCount === 'number' ? data.missingAltCount : 0;
        if (missingAlt === 0) {
            detailElements.imageAltStatus.text('All images include descriptive alt text');
        } else {
            detailElements.imageAltStatus.text(`${missingAlt} image${missingAlt === 1 ? '' : 's'} missing alt text`);
        }

        const internalLinks = typeof data.internalLinkCount === 'number' ? data.internalLinkCount : 0;
        if (internalLinks === 0) {
            detailElements.internalLinkStatus.text('No internal links detected');
        } else {
            detailElements.internalLinkStatus.text(`${internalLinks} internal link${internalLinks === 1 ? '' : 's'} found`);
        }

        const issuesMeta = renderIssues(detailElements.issues, data.issues || []);
        const totalIssues = typeof issuesMeta.total === 'number' ? issuesMeta.total : 0;
        const criticalIssues = critical > 0
            ? critical
            : (typeof issuesMeta.critical === 'number' ? issuesMeta.critical : 0);
        const badgeClass = totalIssues === 0
            ? 'status-good'
            : (criticalIssues > 0 ? 'status-critical' : 'status-warning');
        detailElements.issueCount
            .removeClass('status-good status-warning status-critical')
            .addClass(badgeClass)
            .text(`${totalIssues} ${totalIssues === 1 ? 'issue' : 'issues'}`);
    }

    function openDetail(data) {
        populateDetail(data);
        $detailOverlay.addClass('active').attr('aria-hidden', 'false');
        $('body').addClass('modal-open');
    }

    function closeDetail() {
        $detailOverlay.removeClass('active').attr('aria-hidden', 'true');
        $('body').removeClass('modal-open');
    }

    function handleItemClick(event) {
        if ($(event.target).closest('.seo-open-detail-page').length) {
            return;
        }

        const el = event.currentTarget;
        const data = parsePageData(el);
        if (!data) {
            return;
        }
        openDetail(data);
    }

    $dashboard.on('click', '.seo-open-detail-page', function (event) {
        event.stopPropagation();
    });

    $cards.on('click', handleItemClick);
    $tableRows.on('click', handleItemClick);

    $detailClose.on('click', closeDetail);
    $detailOverlay.on('click', function (event) {
        if (event.target === this) {
            closeDetail();
        }
    });

    $(document).on('keyup', function (event) {
        if (event.key === 'Escape' && $detailOverlay.hasClass('active')) {
            closeDetail();
        }
    });

    sortItems(activeSort);
});
