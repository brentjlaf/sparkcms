$(function () {
    const $rows = $('#accessibilityTable tbody tr');
    const $search = $('#a11ySearch');
    let activeFilter = 'all';
    const $filters = $('[data-a11y-filter]');

    function applyFilters() {
        const query = $search.val().toLowerCase();

        $rows.each(function () {
            const $row = $(this);
            const matchesText = query === '' || $row.text().toLowerCase().indexOf(query) !== -1;
            const status = ($row.data('status') || '').toString();
            const matchesFilter =
                activeFilter === 'all' || status.split(' ').includes(activeFilter);

            if (matchesText && matchesFilter) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }

    function setActiveFilter(filter) {
        activeFilter = filter;
        $filters.removeClass('active');
        $filters.filter(function () {
            return $(this).data('a11y-filter') === filter;
        }).addClass('active');
        applyFilters();
    }

    $search.on('input', applyFilters);

    $filters.on('click', function () {
        const filter = $(this).data('a11y-filter');
        setActiveFilter(filter);
    });

    setActiveFilter('all');
});
