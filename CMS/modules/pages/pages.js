// File: pages.js
$(function(){
        $('#pageTabs').tabs();

        const $pagesCollection = $('#pagesCollection');
        const $searchInput = $('#pagesSearchInput');
        const $filterButtons = $('[data-pages-filter]');
        const $listView = $('#pagesListView');
        const $viewToggleButtons = $('[data-pages-view]');
        const $emptyState = $('#pagesEmptyState');
        const $visibleCount = $('#pagesVisibleCount');
        let activeFilter = 'all';
        $('#cancelEdit').hide();

        function toastSuccess(message){
            if(window.AdminNotifications && typeof window.AdminNotifications.showSuccessToast === 'function'){
                window.AdminNotifications.showSuccessToast(message);
            } else {
                alertModal(message);
            }
        }

        function toastError(message){
            if(window.AdminNotifications && typeof window.AdminNotifications.showErrorToast === 'function'){
                window.AdminNotifications.showErrorToast(message);
            } else {
                alertModal(message);
            }
        }

        function rememberToast(type, message){
            if(window.AdminNotifications && typeof window.AdminNotifications.rememberToast === 'function'){
                window.AdminNotifications.rememberToast(type, message);
            }
        }

        function extractErrorMessage(xhr, fallback){
            if(xhr && xhr.responseJSON && xhr.responseJSON.message){
                return xhr.responseJSON.message;
            }
            if(xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim().length){
                return xhr.responseText;
            }
            return fallback;
        }

        function openPageModal() {
            openModal('pageModal');
        }

        function closePageModal() {
            closeModal('pageModal');
            $('#cancelEdit').hide();
        }

        function getPageCards() {
            if (!$pagesCollection.length) {
                return $();
            }
            return $pagesCollection.find('[data-page-item][data-view="card"]');
        }

        function getPageRows() {
            if (!$listView.length) {
                return $();
            }
            return $listView.find('[data-page-item][data-view="list"]');
        }

        function updateVisibleCount(count) {
            if (!$visibleCount.length) {
                return;
            }
            const label = count === 1 ? 'page' : 'pages';
            $visibleCount.text(`Showing ${count} ${label}`);
        }

        function refreshFilterCounts($cards) {
            const counts = {
                all: 0,
                published: 0,
                drafts: 0,
                restricted: 0
            };

            $cards.each(function(){
                const $card = $(this);
                counts.all++;
                if ($card.data('published') == 1) {
                    counts.published++;
                } else {
                    counts.drafts++;
                }
                const access = (($card.data('access') || 'public') + '').toLowerCase();
                if (access !== 'public') {
                    counts.restricted++;
                }
            });

            Object.keys(counts).forEach(function(key){
                $(`.pages-filter-count[data-count="${key}"]`).text(counts[key]);
            });
        }

        function rowMatchesFilter($card) {
            switch (activeFilter) {
                case 'published':
                    return $card.data('published') == 1;
                case 'drafts':
                    return $card.data('published') != 1;
                case 'restricted':
                    return (($card.data('access') || 'public') + '').toLowerCase() !== 'public';
                case 'all':
                default:
                    return true;
            }
        }

        function applyPageFilters() {
            if (!$pagesCollection.length) {
                return;
            }

            const query = ($searchInput.val() || '').toString().toLowerCase();
            let visible = 0;
            const $cards = getPageCards();
            const $rows = getPageRows();
            const rowIndex = {};

            $rows.each(function(){
                const $row = $(this);
                rowIndex[$row.data('id')] = $row;
            });

            $cards.each(function(){
                const $card = $(this);
                const title = ($card.find('.pages-card__title').text() || '').toLowerCase();
                const slugAttr = ($card.attr('data-slug') || '').toLowerCase();
                const slugData = (($card.data('slug') || '') + '').toLowerCase();
                const slug = slugAttr || slugData;
                const matchesQuery = !query || title.indexOf(query) !== -1 || slug.indexOf(query) !== -1;
                const matchesFilter = rowMatchesFilter($card);
                const $row = rowIndex[$card.data('id')];

                if (matchesQuery && matchesFilter) {
                    $card.removeAttr('hidden');
                    if ($row && $row.length) {
                        $row.removeAttr('hidden');
                    }
                    visible++;
                } else {
                    $card.attr('hidden', 'hidden');
                    if ($row && $row.length) {
                        $row.attr('hidden', 'hidden');
                    }
                }
            });

            if ($emptyState.length) {
                if (visible === 0) {
                    $emptyState.removeAttr('hidden');
                } else {
                    $emptyState.attr('hidden', 'hidden');
                }
            }

            if ($listView.length) {
                const $header = $listView.children('.pages-list-header');
                if ($header.length) {
                    if (visible === 0) {
                        $header.attr('hidden', 'hidden');
                    } else {
                        $header.removeAttr('hidden');
                    }
                }
            }

            updateVisibleCount(visible);
            refreshFilterCounts($cards);
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

        function formatTimestamp(date){
            if (!(date instanceof Date)) {
                return '';
            }
            const pad = (value) => value.toString().padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        }

        function updatePageRow(data){
            if (!data || !data.id) {
                return;
            }

            const $card = $pagesCollection.find(`[data-page-item][data-id="${data.id}"][data-view="card"]`);
            const $row = $listView.find(`[data-page-item][data-id="${data.id}"][data-view="list"]`);

            const publishedFlag = data.published ? 1 : 0;
            const sharedAttributes = {
                'data-title': data.title,
                'data-slug': data.slug,
                'data-content': data.content,
                'data-template': data.template,
                'data-meta_title': data.meta_title,
                'data-meta_description': data.meta_description,
                'data-canonical_url': data.canonical_url,
                'data-og_title': data.og_title,
                'data-og_description': data.og_description,
                'data-og_image': data.og_image,
                'data-access': data.access,
                'data-published': publishedFlag
            };

            const timestamp = formatTimestamp(new Date());
            const accessLabel = ((data.access || 'public') + '').toLowerCase() !== 'public' ? 'Private' : 'Public';

            if ($card.length) {
                $card.attr(sharedAttributes);

                $card.data('title', data.title);
                $card.data('slug', data.slug);
                $card.data('content', data.content);
                $card.data('template', data.template);
                $card.data('meta_title', data.meta_title);
                $card.data('meta_description', data.meta_description);
                $card.data('canonical_url', data.canonical_url);
                $card.data('og_title', data.og_title);
                $card.data('og_description', data.og_description);
                $card.data('og_image', data.og_image);
                $card.data('access', data.access);
                $card.data('published', publishedFlag);

                $card.find('.pages-card__title').text(data.title);
                $card.find('.pages-card__slug').text(`/${data.slug}`);

                const $statusBadge = $card.find('.status-badge');
                $statusBadge.removeClass('status-published status-draft');
                $statusBadge.addClass(publishedFlag ? 'status-published' : 'status-draft');
                $statusBadge.text(publishedFlag ? 'Published' : 'Draft');

                const $viewLink = $card.find('[data-action="view"]').first();
                if ($viewLink.length) {
                    $viewLink.attr('href', `../?page=${encodeURIComponent(data.slug)}`);
                }

                const $toggleBtn = $card.find('.togglePublishBtn');
                if ($toggleBtn.length) {
                    $toggleBtn.text(publishedFlag ? 'Unpublish' : 'Publish');
                }

                const $updatedLabel = $card.find('.pages-card__updated');
                if ($updatedLabel.length && timestamp) {
                    $updatedLabel.html(`<i class="fa-regular fa-clock" aria-hidden="true"></i>Updated ${timestamp}`);
                }
            }

            if ($row.length) {
                $row.attr(sharedAttributes);

                $row.data('title', data.title);
                $row.data('slug', data.slug);
                $row.data('content', data.content);
                $row.data('template', data.template);
                $row.data('meta_title', data.meta_title);
                $row.data('meta_description', data.meta_description);
                $row.data('canonical_url', data.canonical_url);
                $row.data('og_title', data.og_title);
                $row.data('og_description', data.og_description);
                $row.data('og_image', data.og_image);
                $row.data('access', data.access);
                $row.data('published', publishedFlag);

                $row.find('.pages-list-title-text').text(data.title);
                $row.find('.pages-list-slug').text(`/${data.slug}`);

                const $rowStatusBadge = $row.find('.status-badge');
                $rowStatusBadge.removeClass('status-published status-draft');
                $rowStatusBadge.addClass(publishedFlag ? 'status-published' : 'status-draft');
                $rowStatusBadge.text(publishedFlag ? 'Published' : 'Draft');

                const $rowViewLink = $row.find('[data-action="view"]').first();
                if ($rowViewLink.length) {
                    $rowViewLink.attr('href', `../?page=${encodeURIComponent(data.slug)}`);
                }

                const $rowToggleBtn = $row.find('.togglePublishBtn');
                if ($rowToggleBtn.length) {
                    $rowToggleBtn.text(publishedFlag ? 'Unpublish' : 'Publish');
                }

                const $rowUpdated = $row.find('.pages-list-updated');
                if ($rowUpdated.length && timestamp) {
                    $rowUpdated.text(`Updated ${timestamp}`);
                }

                const $rowAccess = $row.find('.pages-list-access');
                if ($rowAccess.length) {
                    $rowAccess.text(accessLabel);
                }
            }
        }

        function findPageItem($el){
            return $el.closest('[data-page-item]');
        }

        function getPageItemsById(id){
            return $(`[data-page-item][data-id="${id}"]`);
        }

        function setActiveView(view){
            if (!$pagesCollection.length || !$viewToggleButtons.length) {
                return;
            }

            const normalizedView = view === 'list' ? 'list' : 'grid';

            if (normalizedView === 'list') {
                $pagesCollection.attr('hidden', 'hidden');
                if ($listView.length) {
                    $listView.removeAttr('hidden');
                }
            } else {
                $pagesCollection.removeAttr('hidden');
                if ($listView.length) {
                    $listView.attr('hidden', 'hidden');
                }
            }

            $viewToggleButtons.removeClass('active').attr('aria-pressed', 'false');
            $viewToggleButtons.filter(`[data-pages-view="${normalizedView}"]`).addClass('active').attr('aria-pressed', 'true');
        }

        $('#pageForm').on('submit', function(e){
            e.preventDefault();

            const $form = $(this);
            const isEditing = $('#pageId').val() !== '';
            const rawSlugValue = $('#slug').val();
            const slugSource = rawSlugValue || $('#title').val() || '';
            let normalizedSlug = slugify(slugSource);
            if (!normalizedSlug) {
                normalizedSlug = 'page';
            }
            $('#slug').val(normalizedSlug);

            const pageData = {
                id: $('#pageId').val(),
                title: $('#title').val(),
                slug: normalizedSlug,
                content: $('#content').val(),
                template: $('#template').val(),
                meta_title: $('#meta_title').val(),
                meta_description: $('#meta_description').val(),
                canonical_url: $('#canonical_url').val(),
                og_title: $('#og_title').val(),
                og_description: $('#og_description').val(),
                og_image: $('#og_image').val(),
                access: $('#access').val(),
                published: $('#published').is(':checked') ? 1 : 0
            };

            const $submitButton = $form.find('button[type="submit"]');
            const originalButtonHtml = $submitButton.html();
            $submitButton.prop('disabled', true).text('Saving...');

            $.post('modules/pages/save_page.php', $form.serialize())
                .done(function(){
                    slugEdited = false;
                    closePageModal();

                    if (isEditing) {
                        updatePageRow(pageData);
                        applyPageFilters();
                        toastSuccess('Page updated successfully.');
                    } else {
                        $('#pageForm')[0].reset();
                        $('#published').prop('checked', false);
                        rememberToast('success', 'Page created successfully.');
                        location.reload();
                    }
                })
                .fail(function(xhr){
                    const message = extractErrorMessage(xhr, 'An unexpected error occurred while saving the page.');
                    toastError(message);
                })
                .always(function(){
                    $submitButton.prop('disabled', false).html(originalButtonHtml);
                });
        });
        $('.deleteBtn').on('click', function(){
            const row = findPageItem($(this));
            if (!row.length) {
                return;
            }
            const pageId = row.data('id');
            confirmModal('Delete this page?').then(ok => {
                if(ok){
                    $.post('modules/pages/delete_page.php', {id: pageId})
                        .done(function(){
                            getPageItemsById(pageId).remove();
                            applyPageFilters();
                            toastSuccess('Page deleted successfully.');
                        })
                        .fail(function(xhr){
                            const message = extractErrorMessage(xhr, 'Unable to delete the page.');
                            toastError(message);
                        });
                }
            });
        });
        $('.editBtn').on('click', function(){
            const row = findPageItem($(this));
            if (!row.length) {
                return;
            }
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

        if ($pagesCollection.length) {
            applyPageFilters();

            $searchInput.on('input', applyPageFilters);

            $filterButtons.on('click', function(){
                const $btn = $(this);
                const newFilter = $btn.data('pagesFilter');
                if (!newFilter) {
                    return;
                }

                activeFilter = newFilter;
                $filterButtons.removeClass('active').attr('aria-pressed', 'false');
                $btn.addClass('active').attr('aria-pressed', 'true');
                applyPageFilters();
            });

            if ($viewToggleButtons.length) {
                $viewToggleButtons.on('click', function(){
                    const $btn = $(this);
                    const view = $btn.data('pagesView');
                    if (!view) {
                        return;
                    }
                    setActiveView(view);
                });

                setActiveView('grid');
            }
        }

        $('.copyBtn').on('click', function(){
            const row = findPageItem($(this));
            if (!row.length) {
                return;
            }
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
            })
                .done(function(){
                    rememberToast('success', 'Page duplicated successfully.');
                    location.reload();
                })
                .fail(function(xhr){
                    const message = extractErrorMessage(xhr, 'Unable to duplicate the page.');
                    toastError(message);
                });
        });

        $('.togglePublishBtn').on('click', function(){
            const row = findPageItem($(this));
            if (!row.length) {
                return;
            }
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
            })
                .done(function(){
                    const message = newStatus ? 'Page published successfully.' : 'Page unpublished successfully.';
                    rememberToast('success', message);
                    location.reload();
                })
                .fail(function(xhr){
                    const message = extractErrorMessage(xhr, 'Unable to update the publish status.');
                    toastError(message);
                });
        });

        $('.pages-card__home.set-home').on('click', function(){
            const row = findPageItem($(this));
            if (!row.length) {
                return;
            }
            $.post('modules/pages/set_home.php', {slug: row.data('slug')})
                .done(function(){
                    rememberToast('success', 'Homepage updated successfully.');
                    location.reload();
                })
                .fail(function(xhr){
                    const message = extractErrorMessage(xhr, 'Unable to update the homepage setting.');
                    toastError(message);
                });
        });
});
