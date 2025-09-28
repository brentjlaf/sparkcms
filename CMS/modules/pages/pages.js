// File: pages.js
$(function(){
        $('#pageTabs').tabs();

        const $pagesTable = $('#pagesTable');
        const $searchInput = $('#pagesSearchInput');
        const $filterButtons = $('[data-pages-filter]');
        const $emptyState = $('#pagesEmptyState');
        const $visibleCount = $('#pagesVisibleCount');
        let activeFilter = 'all';
        $('#cancelEdit').hide();

        function openPageModal() {
            openModal('pageModal');
        }

        function closePageModal() {
            closeModal('pageModal');
            $('#cancelEdit').hide();
        }

        function getPageRows() {
            return $pagesTable.find('tbody tr');
        }

        function updateVisibleCount(count) {
            if (!$visibleCount.length) {
                return;
            }
            const label = count === 1 ? 'page' : 'pages';
            $visibleCount.text(`Showing ${count} ${label}`);
        }

        function refreshFilterCounts($rows) {
            const counts = {
                all: 0,
                published: 0,
                drafts: 0,
                restricted: 0
            };

            $rows.each(function(){
                const $row = $(this);
                counts.all++;
                if ($row.data('published') == 1) {
                    counts.published++;
                } else {
                    counts.drafts++;
                }
                const access = (($row.data('access') || 'public') + '').toLowerCase();
                if (access !== 'public') {
                    counts.restricted++;
                }
            });

            Object.keys(counts).forEach(function(key){
                $(`.pages-filter-count[data-count="${key}"]`).text(counts[key]);
            });
        }

        function rowMatchesFilter($row) {
            switch (activeFilter) {
                case 'published':
                    return $row.data('published') == 1;
                case 'drafts':
                    return $row.data('published') != 1;
                case 'restricted':
                    return (($row.data('access') || 'public') + '').toLowerCase() !== 'public';
                case 'all':
                default:
                    return true;
            }
        }

        function applyPageFilters() {
            if (!$pagesTable.length) {
                return;
            }

            const query = ($searchInput.val() || '').toString().toLowerCase();
            let visible = 0;
            const $rows = getPageRows();

            $rows.each(function(){
                const $row = $(this);
                const title = ($row.find('.pages-title-text').text() || '').toLowerCase();
                const slug = (($row.data('slug') || '') + '').toLowerCase();
                const matchesQuery = !query || title.indexOf(query) !== -1 || slug.indexOf(query) !== -1;
                const matchesFilter = rowMatchesFilter($row);

                if (matchesQuery && matchesFilter) {
                    $row.show();
                    visible++;
                } else {
                    $row.hide();
                }
            });

            if ($emptyState.length) {
                if (visible === 0) {
                    $emptyState.removeAttr('hidden');
                } else {
                    $emptyState.attr('hidden', 'hidden');
                }
            }

            updateVisibleCount(visible);
            refreshFilterCounts($rows);
        }

        let slugEdited = false;
        function slugify(text){
            return text.toString().toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .replace(/-+/g, '-');
        }

        $('#slug').on('input', function(){ slugEdited = true; });
        $('#title').on('input', function(){
            if($('#pageId').val() === '' && !slugEdited){
                $('#slug').val(slugify($(this).val()));
            }
        });

        $('#pageForm').on('submit', function(e){
            e.preventDefault();
            $.post('modules/pages/save_page.php', $(this).serialize(), function(){
                closePageModal();
                slugEdited = false;
                location.reload();
            });
        });
        $('.deleteBtn').on('click', function(){
            const row = $(this).closest('tr');
            confirmModal('Delete this page?').then(ok => {
                if(ok){
                    $.post('modules/pages/delete_page.php', {id: row.data('id')}, function(){
                        row.remove();
                        applyPageFilters();
                    });
                }
            });
        });
        $('.editBtn').on('click', function(){
            const row = $(this).closest('tr');
            $('#formTitle').text('Page Settings');
            $('#pageId').val(row.data('id'));
            $('#title').val(row.data('title'));
            // Use attribute to avoid any jQuery data caching that may return
            // the numeric id instead of the actual slug
            $('#slug').val(row.attr('data-slug'));
            $('#content').val(row.data('content'));
            $('#published').prop('checked', row.data('published') == 1);
            const tmpl = row.data('template') ? row.data('template') : 'page.php';
            $('#template').val(tmpl);
            $('#meta_title').val(row.data('meta_title'));
            $('#meta_description').val(row.data('meta_description'));
            $('#canonical_url').val(row.data('canonical_url'));
            $('#og_title').val(row.data('og_title'));
            $('#og_description').val(row.data('og_description'));
            $('#og_image').val(row.data('og_image'));
            $('#access').val(row.data('access'));
            $('#cancelEdit').show();
            $('#pageTabs').tabs('option', 'active', 0);
            openPageModal();
            slugEdited = true;
        });
        $('#cancelEdit').on('click', function(){
            $('#formTitle').text('Add New Page');
            $('#pageId').val('');
            $('#pageForm')[0].reset();
            $('#published').prop('checked', false);
            $('#canonical_url').val('');
            closePageModal();
            slugEdited = false;
        });
        $('#newPageBtn').on('click', function(){
            $('#formTitle').text('Add New Page');
            $('#pageId').val('');
            $('#pageForm')[0].reset();
            $('#published').prop('checked', false);
            $('#content').val('');
            $('#canonical_url').val('');
            $('#pageTabs').tabs('option', 'active', 0);
            $('#cancelEdit').hide();
            openPageModal();
            slugEdited = false;
        });

        $('#closePageModal').on('click', function(){
            closePageModal();
            slugEdited = false;
        });

        if ($pagesTable.length) {
            applyPageFilters();

            $searchInput.on('input', applyPageFilters);

            $filterButtons.on('click', function(){
                const $btn = $(this);
                const newFilter = $btn.data('pagesFilter');
                if (!newFilter) {
                    return;
                }

                activeFilter = newFilter;
                $filterButtons.removeClass('active');
                $btn.addClass('active');
                applyPageFilters();
            });
        }

        $('.copyBtn').on('click', function(){
            const row = $(this).closest('tr');
            const data = row.data();
            $.post('modules/pages/save_page.php', {
                title: data.title + ' Copy',
                slug: data.slug + '-copy',
                content: data.content,
                published: data.published,
                template: data.template,
                meta_title: data.meta_title,
                meta_description: data.meta_description,
                og_title: data.og_title,
                og_description: data.og_description,
                og_image: data.og_image,
                access: data.access
            }, function(){
                location.reload();
            });
        });

        $('.togglePublishBtn').on('click', function(){
            const row = $(this).closest('tr');
            const data = row.data();
            const newStatus = data.published ? 0 : 1;
            $.post('modules/pages/save_page.php', {
                id: data.id,
                title: data.title,
                slug: data.slug,
                content: data.content,
                published: newStatus,
                template: data.template,
                meta_title: data.meta_title,
                meta_description: data.meta_description,
                og_title: data.og_title,
                og_description: data.og_description,
                og_image: data.og_image,
                access: data.access
            }, function(){
                location.reload();
            });
        });

        $('.home-icon.set-home').on('click', function(){
            const row = $(this).closest('tr');
            $.post('modules/pages/set_home.php', {slug: row.data('slug')}, function(){
                location.reload();
            });
        });
});
