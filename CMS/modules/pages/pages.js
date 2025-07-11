$(function(){
        $('#pageTabs').tabs();

        function openPageModal() {
            openModal('pageModal');
        }

        function closePageModal() {
            closeModal('pageModal');
            $('#cancelEdit').hide();
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
                location.reload();
            });
        });
        $('.deleteBtn').on('click', function(){
            const row = $(this).closest('tr');
            confirmModal('Delete this page?').then(ok => {
                if(ok){
                    $.post('modules/pages/delete_page.php', {id: row.data('id')}, function(){
                        row.remove();
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
            closePageModal();
            slugEdited = false;
        });
        $('#newPageBtn').on('click', function(){
            $('#formTitle').text('Add New Page');
            $('#pageId').val('');
            $('#pageForm')[0].reset();
            $('#published').prop('checked', false);
            $('#content').val('');
            $('#pageTabs').tabs('option', 'active', 0);
            openPageModal();
            slugEdited = false;
        });

        $('#closePageModal').on('click', function(){
            closePageModal();
            slugEdited = false;
        });

        // Live filter pages by search query
        $('.search-input').on('input', function(){
            const query = $(this).val().toLowerCase();
            $('#pagesTable tbody tr').each(function(){
                const title = $(this).find('.title').text().toLowerCase();
                const slug = $(this).data('slug').toString().toLowerCase();
                if(title.indexOf(query) !== -1 || slug.indexOf(query) !== -1){
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

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
