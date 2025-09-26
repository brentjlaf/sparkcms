$(function () {
    const $dashboard = $('.seo-dashboard');
    if ($dashboard.length === 0) {
        return;
    }

    const $searchInput = $('#seoSearchInput');
    const $filterButtons = $dashboard.find('.seo-filter-btn');
    const $viewButtons = $dashboard.find('.seo-view-btn');
    const $grid = $('#seoGrid');
    const $cards = $grid.find('.seo-card');
    const $tableWrapper = $('#seoTableWrapper');
    const $tableRows = $tableWrapper.find('tbody tr');
    const $detailOverlay = $('#seoDetail');
    const $detailClose = $detailOverlay.find('.seo-detail-close');

    const detailElements = {
        title: $detailOverlay.find('[data-detail="title"]'),
        url: $detailOverlay.find('[data-detail="url"]'),
        scoreCircle: $detailOverlay.find('[data-detail="score-circle"]'),
        score: $detailOverlay.find('[data-detail="score"]'),
        scoreLabel: $detailOverlay.find('[data-detail="score-label"]'),
        metaTitle: $detailOverlay.find('[data-detail="meta-title"]'),
        metaTitleLength: $detailOverlay.find('[data-detail="meta-title-length"]'),
        metaDescription: $detailOverlay.find('[data-detail="meta-description"]'),
        metaDescriptionLength: $detailOverlay.find('[data-detail="meta-description-length"]'),
        socialStatus: $detailOverlay.find('[data-detail="social-status"]'),
        lastUpdated: $detailOverlay.find('[data-detail="last-updated"]'),
        issues: $detailOverlay.find('[data-detail="issues"]'),
    };

    let activeFilter = 'all';
    let searchQuery = '';

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

    function renderIssues(list, issues) {
        list.empty();
        if (!issues || issues.length === 0) {
            list.append(
                $('<li/>', { class: 'seo-issue-item' }).append(
                    $('<span/>', { class: 'seo-issue-dot good' }),
                    $('<div/>').text('No outstanding issues. Everything looks great!')
                )
            );
            return;
        }

        issues.forEach((issue) => {
            const severity = issue.severity || 'warning';
            list.append(
                $('<li/>', { class: 'seo-issue-item' }).append(
                    $('<span/>', { class: `seo-issue-dot ${severity}` }),
                    $('<div/>').text(issue.message)
                )
            );
        });
    }

    function updateScoreCircle($circle, score, status) {
        $circle
            .removeClass('score-excellent score-good score-warning score-critical')
            .addClass(`score-${status}`)
            .text(typeof score === 'number' ? score : '—');
    }

    function populateDetail(data) {
        if (!data) {
            return;
        }

        detailElements.title.text(data.title || 'Untitled');
        detailElements.url.text(data.slug ? `/${data.slug}` : '—');
        detailElements.score.text(`${data.score || 0} / 100`);
        detailElements.scoreLabel.text(data.scoreLabel || '');
        updateScoreCircle(detailElements.scoreCircle, data.score || 0, data.scoreStatus || 'warning');

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

        detailElements.lastUpdated.text(data.lastUpdated || 'Unknown');
        renderIssues(detailElements.issues, data.issues || []);
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
        const el = event.currentTarget;
        const data = parsePageData(el);
        if (!data) {
            return;
        }
        openDetail(data);
    }

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

    applyFilters();
});
