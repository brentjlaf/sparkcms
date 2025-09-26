$(function () {
    const $summaryRows = $('#accessibilityTable tbody tr.a11y-summary-row');
    const $search = $('#a11ySearch');
    const $filters = $('[data-a11y-filter]');
    const $scanBtn = $('#scanAllPagesBtn');
    let activeFilter = 'all';

    function hideDetail($button, $detailRow) {
        $button.attr('aria-expanded', 'false').removeClass('is-active');
        $button.closest('.a11y-summary-row').attr('data-expanded', 'false');
        $detailRow.hide().attr('hidden', true);
    }

    function showDetail($button, $detailRow) {
        $button.attr('aria-expanded', 'true').addClass('is-active');
        $button.closest('.a11y-summary-row').attr('data-expanded', 'true');
        $detailRow.show().attr('hidden', false);
    }

    function applyFilters() {
        const query = ($search.val() || '').toLowerCase();

        $summaryRows.each(function () {
            const $row = $(this);
            const $detailRow = $row.next('.a11y-detail-row');
            const combinedText = ($row.text() + ' ' + $detailRow.text()).toLowerCase();
            const status = ($row.data('status') || '').toString();
            const matchesText = query === '' || combinedText.indexOf(query) !== -1;
            const matchesFilter = activeFilter === 'all' || status.split(' ').includes(activeFilter);
            const $detailButton = $row.find('.a11y-detail-btn');

            if (matchesText && matchesFilter) {
                $row.show();
                if ($row.attr('data-expanded') === 'true') {
                    $detailRow.show().attr('hidden', false);
                } else {
                    $detailRow.hide().attr('hidden', true);
                }
            } else {
                $row.hide();
                hideDetail($detailButton, $detailRow);
            }
        });
    }

    function setActiveFilter(filter) {
        activeFilter = filter;
        $filters.removeClass('active');
        $filters
            .filter(function () {
                return $(this).data('a11y-filter') === filter;
            })
            .addClass('active');
        applyFilters();
    }

    $search.on('input', applyFilters);

    $filters.on('click', function () {
        const filter = $(this).data('a11y-filter');
        setActiveFilter(filter);
    });

    $summaryRows.find('.a11y-detail-btn').on('click', function () {
        const $button = $(this);
        const detailId = $button.attr('aria-controls');
        const $detailRow = $('#' + detailId);
        const isExpanded = $button.attr('aria-expanded') === 'true';

        $summaryRows.find('.a11y-detail-btn[aria-expanded="true"]').each(function () {
            const $openButton = $(this);
            if ($openButton.is($button)) {
                return;
            }
            const openDetailId = $openButton.attr('aria-controls');
            hideDetail($openButton, $('#' + openDetailId));
        });

        if (isExpanded) {
            hideDetail($button, $detailRow);
        } else {
            showDetail($button, $detailRow);
        }
    });

    if ($scanBtn.length) {
        $scanBtn.on('click', function () {
            const $btn = $(this);

            if ($btn.prop('disabled')) {
                return;
            }

            $btn.prop('disabled', true).addClass('is-loading');
            const $icon = $btn.find('.fa-solid');
            $icon.removeClass('fa-arrows-rotate').addClass('fa-spinner fa-spin');
            $btn.find('.a11y-scan-label').text('Scanning...');

            setTimeout(function () {
                window.location.reload();
            }, 800);
        });
    }

    setActiveFilter('all');
});
