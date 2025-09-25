$(function () {
    const $rows = $('#accessibilityTable tbody tr');
    const $search = $('#a11ySearch');
    let activeFilter = 'all';

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

    $search.on('input', applyFilters);

    $('[data-a11y-filter]').on('click', function () {
        const $card = $(this);
        activeFilter = $card.data('a11y-filter');

        $('[data-a11y-filter]').removeClass('active');
        $card.addClass('active');
        applyFilters();
    });

    $('[data-a11y-filter="all"]').addClass('active');
});
