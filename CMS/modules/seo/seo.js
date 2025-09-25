$(function () {
    const $rows = $('#seoTable tbody tr');
    const $search = $('#seoSearch');
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

    $('[data-seo-filter]').on('click', function () {
        const $card = $(this);
        activeFilter = $card.data('seo-filter');

        $('[data-seo-filter]').removeClass('active');
        $card.addClass('active');
        applyFilters();
    });

    $('[data-seo-filter="all"]').addClass('active');
});
