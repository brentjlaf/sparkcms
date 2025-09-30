// File: blogs.js
$(document).ready(function(){
    let posts = [];
    let categories = [];
    let authors = [];
    let nextPostId = 1;
    let isLoadingPosts = false;
    let postsLoadError = false;

    const $postsTableBody = $('#postsTableBody');
    const $postsCountLabel = $('#postsCount');
    const $sortButtons = $('[data-blog-sort]');
    const sortState = { key: null, direction: 'asc' };
    let mediaItems = [];
    let mediaLoaded = false;

    const $searchInput = $('#blogSearchInput');
    const $statusFilterButtons = $('[data-blog-filter]');
    let activeStatusFilter = 'all';

    function renderTableMessage(message, options={}){
        const opts = Object.assign({ isError: false, countLabel: null }, options);
        const className = opts.isError ? 'text-danger' : 'text-muted';
        $postsTableBody.html(`<tr><td colspan="6" class="text-center ${className}">${message}</td></tr>`);
        if(typeof opts.countLabel === 'string'){
            $postsCountLabel.text(opts.countLabel);
        }
    }

    function normalizePostFromServer(rawPost){
        const post = Object.assign({}, rawPost || {});
        const parsedId = parseInt(post.id, 10);
        post.id = Number.isNaN(parsedId) ? post.id : parsedId;
        post.title = (post.title || '').trim() || 'Untitled Post';
        post.slug = (post.slug || '').trim();
        post.excerpt = (post.excerpt || '').trim();
        post.content = post.content || '';
        post.category = (post.category || '').trim() || 'Uncategorized';
        post.author = (post.author || '').trim() || 'Unknown Author';
        const status = (post.status || '').trim().toLowerCase();
        post.status = status || 'draft';
        if(Array.isArray(post.tags)){
            post.tags = post.tags.join(', ');
        }else{
            post.tags = (post.tags || '').toString();
        }
        post.publishDate = (post.publishDate || '').trim();
        post.image = (post.image || '').trim();
        post.imageAlt = (post.imageAlt || '').trim();
        if(!post.createdAt){
            post.createdAt = post.publishDate || new Date().toISOString();
        }
        return post;
    }

    function refreshCategoriesFromPosts(){
        const unique = new Set(categories);
        posts.forEach(post => {
            if(post.category){
                unique.add(post.category);
            }
        });
        categories = Array.from(unique).sort((a,b)=>a.localeCompare(b));
    }

    function loadPostsFromServer(){
        if(isLoadingPosts){
            return;
        }
        isLoadingPosts = true;
        postsLoadError = false;
        renderTableMessage('Loading posts…', { countLabel: 'Loading posts…' });
        $.getJSON('modules/blogs/list_posts.php')
            .done(function(data){
                if(Array.isArray(data)){
                    postsLoadError = false;
                    posts = data.map(normalizePostFromServer);
                    const maxId = posts.reduce((max, post) => {
                        const id = parseInt(post.id, 10);
                        return Number.isNaN(id) ? max : Math.max(max, id);
                    }, 0);
                    nextPostId = maxId + 1;
                    refreshCategoriesFromPosts();
                    updateStats();
                    populateFilters();
                    renderPosts();
                    renderCategories();
                }else{
                    postsLoadError = false;
                    posts = [];
                    refreshCategoriesFromPosts();
                    updateStats();
                    populateFilters();
                    renderTableMessage('No blog posts found.', { countLabel: 'Showing 0 posts' });
                    renderCategories();
                }
            })
            .fail(function(){
                postsLoadError = true;
                posts = [];
                refreshCategoriesFromPosts();
                updateStats();
                populateFilters();
                renderTableMessage('Unable to load blog posts. Please try again later.', { isError: true, countLabel: 'Showing 0 posts' });
                renderCategories();
            })
            .always(function(){
                isLoadingPosts = false;
            });
    }

    function getCmsBasePath(){
        if(window.__cmsBasePath !== undefined){
            return window.__cmsBasePath;
        }
        const path = window.location.pathname || '';
        const cmsMarker = '/CMS/';
        let base = '';
        const markerIndex = path.indexOf(cmsMarker);
        if(markerIndex !== -1){
            base = path.substring(0, markerIndex);
        } else {
            const fallbackIndex = path.indexOf('/CMS');
            base = fallbackIndex !== -1 ? path.substring(0, fallbackIndex) : '';
        }
        window.__cmsBasePath = base;
        return base;
    }

    function isAbsoluteResource(path){
        return /^https?:\/\//i.test(path || '') || (typeof path === 'string' && (path.startsWith('//') || path.startsWith('/')));
    }

    function resolveCmsPath(relativePath){
        const value = String(relativePath || '');
        if(!value){
            return '';
        }
        if(isAbsoluteResource(value)){
            return value;
        }
        const base = getCmsBasePath().replace(/\/$/, '');
        const cleaned = value.replace(/^\.\/+/, '').replace(/^\/+/, '');
        if(!cleaned){
            return '';
        }
        if(!base){
            return `/${cleaned}`;
        }
        return `${base}/${cleaned}`;
    }

    function normalizeImageValue(value){
        const raw = (value || '').trim();
        if(!raw){
            return '';
        }
        if(/^https?:\/\//i.test(raw)){
            try{
                const parsed = new URL(raw, window.location.origin);
                if(parsed.origin === window.location.origin){
                    return normalizeImageValue(parsed.pathname);
                }
            }catch(err){
                return raw;
            }
            return raw;
        }
        if(raw.startsWith('//') || raw.startsWith('data:')){
            return raw;
        }
        if(raw.startsWith('/')){
            return raw.replace(/\/+/g, '/');
        }
        const base = getCmsBasePath().replace(/\/$/, '');
        const cleaned = raw.replace(/^\.\/+/, '').replace(/^\/+/, '');
        if(!cleaned){
            return '';
        }
        const baseName = base.replace(/^\//, '');
        if(baseName && cleaned.startsWith(`${baseName}/`)){
            return `/${cleaned}`;
        }
        if(base){
            return `${base}/${cleaned}`;
        }
        return `/${cleaned}`;
    }

    function renderMediaPicker(items){
        const grid = $('#mediaPickerGrid');
        if(!items.length){
            grid.html('<p class="blog-media-picker__status">No images found in the media library.</p>');
            return;
        }
        const html = items.map(item => {
            const filePath = resolveCmsPath(item.file);
            const thumbPath = resolveCmsPath(item.thumbnail || item.file);
            const safeFile = escapeAttribute(filePath);
            const safeThumb = escapeAttribute(thumbPath);
            const safeName = escapeAttribute(item.name || item.file || 'Media item');
            return `
                <button type="button" class="blog-media-picker__item" data-file="${safeFile}" role="option">
                    <span class="blog-media-picker__thumb"><img src="${safeThumb}" alt="${safeName}"></span>
                    <span class="blog-media-picker__name">${safeName}</span>
                </button>
            `;
        }).join('');
        grid.html(html);
    }

    function loadMediaLibrary(){
        const grid = $('#mediaPickerGrid');
        grid.html('<p class="blog-media-picker__status">Loading images…</p>');
        return $.getJSON('modules/media/list_media.php', { sort: 'name', order: 'asc' })
            .done(function(data){
                mediaItems = Array.isArray(data.media)
                    ? data.media.filter(item => (item.type || '') === 'images' && item.file)
                    : [];
                mediaLoaded = true;
                if(!mediaItems.length){
                    grid.html('<p class="blog-media-picker__status">No images found in the media library.</p>');
                    return;
                }
                renderMediaPicker(mediaItems);
            })
            .fail(function(){
                grid.html('<p class="blog-media-picker__status blog-media-picker__status--error">Unable to load the media library. Please try again.</p>');
            });
    }

    function openMediaPicker(){
        $('#mediaPickerSearch').val('');
        openModal('mediaPickerModal');
        if(mediaLoaded && mediaItems.length){
            renderMediaPicker(mediaItems);
            return;
        }
        loadMediaLibrary();
    }

    function escapeAttribute(value){
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function getPostInitial(post){
        const source = (post.title || post.slug || '?').trim();
        return source ? source.charAt(0).toUpperCase() : '?';
    }

    function updateImagePreview(url, altText){
        const preview = $('#postImagePreview');
        if(url){
            const safeAlt = escapeAttribute(altText || 'Featured blog image');
            preview
                .html(`<img src="${url}" alt="${safeAlt}">`)
                .addClass('has-image');
        } else {
            preview.empty().removeClass('has-image');
        }
    }

    function getPreviewAltText(){
        const explicit = $('#postImageAlt').val();
        if(explicit && explicit.trim()){
            return explicit.trim();
        }
        const title = $('#postTitle').val();
        if(title && title.trim()){
            return title.trim();
        }
        return 'Featured blog image';
    }

    function setImagePreviewFromPost(post){
        updateImagePreview(post.image || '', post.imageAlt || post.title || '');
    }

    window.__selectImageFromPicker = function(url){
        if(!url){
            return;
        }
        const normalized = normalizeImageValue(url);
        $('#postImage').val(normalized).trigger('change');
        closeModal('mediaPickerModal');
    };

    // Load authors from user accounts
    function loadAuthors(){
        $.getJSON('modules/users/list_users.php', function(data){
            authors = data.map(u => u.username);
            populateAuthorOptions();
        });
    }

    function populateAuthorOptions(){
        const authorOptions = authors.map(a=>`<option value="${a}">${a}</option>`).join('');
        const currentFilterAuthor = $('#authorFilter').val() || '';
        const currentPostAuthor = $('#postAuthor').val() || '';
        $('#authorFilter').html(`<option value="">All Authors</option>${authorOptions}`);
        $('#postAuthor').html(`<option value="">Select Author</option>${authorOptions}`);
        if(currentFilterAuthor && authors.includes(currentFilterAuthor)){
            $('#authorFilter').val(currentFilterAuthor);
        }
        if(currentPostAuthor && authors.includes(currentPostAuthor)){
            $('#postAuthor').val(currentPostAuthor);
        }
    }

    setActiveStatusFilter('all');
    setSort('date', 'desc', { silent: true });
    loadAuthors();
    loadPostsFromServer();

    $('#newPostBtn').click(function(){
        openPostModal();
    });
    $('#categoriesBtn').click(function(){
        openCategoriesModal();
    });
    $('#closeModal, #cancelBtn').click(function(){
        closeModal('postModal');
    });
    $('#closeCategoriesModal').click(function(){
        closeModal('categoriesModal');
    });
    $('#categoriesDoneBtn').click(function(){
        closeModal('categoriesModal');
    });
    $('#closePreviewModal, #closePreviewBtn').click(function(){
        closeModal('postPreviewModal');
    });
    $('#editPreviewBtn').click(function(){
        const id = parseInt($(this).data('id'));
        closeModal('postPreviewModal');
        openPostModal(id);
    });
    $('#postForm').submit(function(e){
        e.preventDefault();
        savePost();
    });
    $('#addCategoryBtn').click(function(){
        addCategory();
    });
    $('#newCategoryName').keypress(function(e){
        if(e.which===13){
            addCategory();
        }
    });
    $('#categoryFilter, #authorFilter').change(function(){
        renderPosts();
    });
    $searchInput.on('input', function(){
        renderPosts();
    });
    $sortButtons.on('click', function(){
        const $btn = $(this);
        const key = $btn.data('blogSort');
        if(!key){
            return;
        }
        let direction = getDefaultSortDirection($btn);
        if(sortState.key === key){
            direction = sortState.direction === 'asc' ? 'desc' : 'asc';
        }
        setSort(key, direction);
    });
    $statusFilterButtons.on('click', function(){
        const filter = $(this).data('blog-filter');
        if(!filter || filter === activeStatusFilter){
            return;
        }
        setActiveStatusFilter(filter);
        renderPosts();
    });
    $('#clearFilters').click(function(){
        $('#categoryFilter, #authorFilter').val('');
        $searchInput.val('');
        setActiveStatusFilter('all');
        renderPosts();
    });
    $('#postTitle').on('input', function(){
        const value = $(this).val();
        if(!$('#postId').val()){
            const slug = value.toLowerCase()
                .replace(/[^a-z0-9]+/g,'-')
                .replace(/^-+|-+$/g,'');
            $('#postSlug').val(slug);
        }
        const imageUrl = $('#postImage').val().trim();
        if(imageUrl && !$('#postImageAlt').val().trim()){
            updateImagePreview(imageUrl, value.trim());
        }
    });

    $('#postImage').on('input', function(){
        const url = $(this).val().trim();
        const alt = getPreviewAltText();
        updateImagePreview(url, alt);
    });

    $('#postImage').on('change', function(){
        const normalized = normalizeImageValue($(this).val());
        $(this).val(normalized);
        const alt = getPreviewAltText();
        updateImagePreview(normalized, alt);
    });

    $('#chooseFeaturedImage').on('click', function(e){
        e.preventDefault();
        openMediaPicker();
    });

    $('#mediaPickerGrid').on('click', '.blog-media-picker__item', function(){
        const file = $(this).data('file');
        if(!file){
            return;
        }
        $('#postImage').val(file).trigger('change');
        closeModal('mediaPickerModal');
    });

    $('#mediaPickerSearch').on('input', function(){
        const term = $(this).val().toLowerCase().trim();
        if(!mediaItems.length){
            return;
        }
        if(!term){
            renderMediaPicker(mediaItems);
            return;
        }
        const filtered = mediaItems.filter(item => {
            const name = (item.name || '').toLowerCase();
            let tags = '';
            if(Array.isArray(item.tags)){
                tags = item.tags.join(' ').toLowerCase();
            } else if(typeof item.tags === 'string'){
                tags = item.tags.toLowerCase();
            }
            const file = (item.file || '').toLowerCase();
            return name.includes(term) || tags.includes(term) || file.includes(term);
        });
        renderMediaPicker(filtered);
    });

    $('#closeMediaPickerModal').click(function(){
        closeModal('mediaPickerModal');
    });

    $('#postImageAlt').on('input change', function(){
        const url = $('#postImage').val().trim();
        if(url){
            const alt = getPreviewAltText();
            updateImagePreview(url, alt);
        }
    });


    function updateStats(){
        const total = posts.length;
        const published = posts.filter(p=>p.status==='published').length;
        const drafts = posts.filter(p=>p.status==='draft').length;
        const scheduled = posts.filter(p=>p.status==='scheduled').length;
        $('#totalPosts').text(total);
        $('#publishedPosts').text(published);
        $('#draftPosts').text(drafts);
        $('#scheduledPosts').text(scheduled);
        updateStatusFilterCounts({
            all: total,
            published,
            drafts,
            scheduled
        });
    }

    function populateFilters(){
        const categoryOptions = categories.map(c=>`<option value="${c}">${c}</option>`).join('');
        const currentFilterCategory = $('#categoryFilter').val() || '';
        const currentPostCategory = $('#postCategory').val() || '';
        $('#categoryFilter').html(`<option value="">All Categories</option>${categoryOptions}`);
        $('#postCategory').html(`<option value="">Select Category</option>${categoryOptions}`);
        if(currentFilterCategory && categories.includes(currentFilterCategory)){
            $('#categoryFilter').val(currentFilterCategory);
        }
        if(currentPostCategory && categories.includes(currentPostCategory)){
            $('#postCategory').val(currentPostCategory);
        }
        populateAuthorOptions();
    }

    function setActiveStatusFilter(filter){
        activeStatusFilter = filter;
        $statusFilterButtons.removeClass('active').attr('aria-pressed', 'false');
        const $target = $statusFilterButtons.filter(`[data-blog-filter="${filter}"]`);
        if($target.length){
            $target.addClass('active').attr('aria-pressed', 'true');
        }
    }

    function updateStatusFilterCounts(counts){
        Object.entries(counts).forEach(([key, value])=>{
            $(`.blog-filter-count[data-count="${key}"]`).text(value);
        });
    }

    function compareStrings(a, b){
        return (a || '').toString().localeCompare((b || '').toString(), undefined, { sensitivity: 'base', numeric: true });
    }

    function parseDateValue(value){
        if(!value){
            return 0;
        }
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? 0 : date.getTime();
    }

    const statusRank = {
        draft: 1,
        scheduled: 2,
        published: 3
    };

    const sortComparators = {
        title(a, b){
            const primary = compareStrings(a.title, b.title);
            if(primary !== 0){
                return primary;
            }
            return compareStrings(a.slug, b.slug);
        },
        author(a, b){
            const primary = compareStrings(a.author, b.author);
            if(primary !== 0){
                return primary;
            }
            return compareStrings(a.title, b.title);
        },
        category(a, b){
            const primary = compareStrings(a.category, b.category);
            if(primary !== 0){
                return primary;
            }
            return compareStrings(a.title, b.title);
        },
        status(a, b){
            const rankA = statusRank[(a.status || '').toLowerCase()] || 0;
            const rankB = statusRank[(b.status || '').toLowerCase()] || 0;
            if(rankA !== rankB){
                return rankA - rankB;
            }
            return compareStrings(a.title, b.title);
        },
        date(a, b){
            const diff = parseDateValue(a.publishDate || a.createdAt) - parseDateValue(b.publishDate || b.createdAt);
            if(diff !== 0){
                return diff;
            }
            return compareStrings(a.title, b.title);
        }
    };

    function getSortComparator(key){
        if(key && typeof sortComparators[key] === 'function'){
            return sortComparators[key];
        }
        return sortComparators.date;
    }

    function updateSortIndicators(){
        if(!$sortButtons.length){
            return;
        }
        $sortButtons.each(function(){
            const $btn = $(this);
            const key = $btn.data('blogSort');
            const isActive = sortState.key === key;
            let ariaValue = 'none';

            $btn.removeClass('is-active sort-asc sort-desc');
            if(isActive){
                ariaValue = sortState.direction === 'asc' ? 'ascending' : 'descending';
                $btn.addClass('is-active');
                $btn.addClass(sortState.direction === 'asc' ? 'sort-asc' : 'sort-desc');
            }

            const $th = $btn.closest('th');
            if($th.length){
                $th.attr('aria-sort', ariaValue);
            }
        });
    }

    function getDefaultSortDirection($btn){
        const attr = ($btn.data('defaultDirection') || '').toString().toLowerCase();
        return attr === 'desc' ? 'desc' : 'asc';
    }

    function setSort(key, direction, options={}){
        if(!key){
            return;
        }
        const normalizedDirection = direction === 'desc' ? 'desc' : 'asc';
        const hasChanged = sortState.key !== key || sortState.direction !== normalizedDirection;

        sortState.key = key;
        sortState.direction = normalizedDirection;
        updateSortIndicators();

        if(options.silent){
            return;
        }
        if(hasChanged || options.forceRender){
            renderPosts();
        }
    }

    function renderPosts(){
        updateSortIndicators();
        if(postsLoadError){
            renderTableMessage('Unable to load blog posts. Please try again later.', { isError: true, countLabel: 'Showing 0 posts' });
            return;
        }
        if(isLoadingPosts && !posts.length){
            renderTableMessage('Loading posts…', { countLabel: 'Loading posts…' });
            return;
        }

        let filtered = [...posts];
        const category = $('#categoryFilter').val();
        const author = $('#authorFilter').val();
        const search = ($searchInput.val() || '').toString().toLowerCase().trim();

        switch(activeStatusFilter){
            case 'published':
                filtered = filtered.filter(p=>p.status==='published');
                break;
            case 'drafts':
                filtered = filtered.filter(p=>p.status==='draft');
                break;
            case 'scheduled':
                filtered = filtered.filter(p=>p.status==='scheduled');
                break;
            default:
                break;
        }

        if(category) filtered = filtered.filter(p=>p.category===category);
        if(author) filtered = filtered.filter(p=>p.author===author);
        if(search){
            filtered = filtered.filter(p=>{
                const title = (p.title || '').toLowerCase();
                const slug = (p.slug || '').toLowerCase();
                const excerpt = (p.excerpt || '').toLowerCase();
                const tags = (p.tags || '').toLowerCase();
                return title.includes(search) || slug.includes(search) || excerpt.includes(search) || tags.includes(search);
            });
        }
        const sortKey = sortState.key || 'date';
        const comparator = getSortComparator(sortKey);
        const directionMultiplier = sortState.direction === 'desc' ? -1 : 1;
        filtered.sort((a, b) => {
            const result = comparator(a, b);
            if(result !== 0){
                return result * directionMultiplier;
            }
            return compareStrings(a.title, b.title) * directionMultiplier;
        });

        const countLabel = filtered.length === 1 ? 'post' : 'posts';
        $postsCountLabel.text(`Showing ${filtered.length} ${countLabel}`);

        if(!filtered.length){
            const message = posts.length
                ? 'No posts match the current filters.'
                : 'No blog posts available yet.';
            $postsTableBody.html(`<tr><td colspan="6" class="text-center text-muted">${message}</td></tr>`);
            updateLastUpdated();
            return;
        }

        const tbody = $postsTableBody;
        tbody.empty();
        filtered.forEach(post=>{
            const safeTitleAttr = escapeAttribute(post.title);
            const safeSlugAttr = escapeAttribute(post.slug);
            const safeAuthorAttr = escapeAttribute(post.author);
            const safeCategoryAttr = escapeAttribute(post.category);
            const safeStatusAttr = escapeAttribute(post.status);
            const safeDateAttr = escapeAttribute(post.publishDate || post.createdAt || '');
            const safeAlt = escapeAttribute(post.imageAlt || `Featured image for ${post.title || 'blog post'}`);
            const thumbnail = post.image
                ? `<div class="blog-table-thumb"><img src="${post.image}" alt="${safeAlt}"></div>`
                : `<div class="blog-table-thumb blog-table-thumb--empty" aria-hidden="true"><span>${getPostInitial(post)}</span></div>`;
            const row = $(`
                <tr
                    data-blog-post
                    data-id="${post.id}"
                    data-title="${safeTitleAttr}"
                    data-slug="${safeSlugAttr}"
                    data-author="${safeAuthorAttr}"
                    data-category="${safeCategoryAttr}"
                    data-status="${safeStatusAttr}"
                    data-date="${safeDateAttr}"
                >
                    <td>
                        <div class="blog-post-cell">
                            ${thumbnail}
                            <div>
                                <div class="post-title">${post.title}</div>
                                <div class="post-excerpt">${post.excerpt}</div>
                            </div>
                        </div>
                        ${post.image ? '' : '<span class="sr-only">No featured image</span>'}
                    </td>
                    <td>
                        <div class="author-info">
                            <div class="author-avatar">${post.author.split(' ').map(n=>n[0]).join('')}</div>
                            <span>${post.author}</span>
                        </div>
                    </td>
                    <td><span class="category-tag">${post.category}</span></td>
                    <td><span class="status-badge status-${post.status}">${post.status}</span></td>
                    <td>${formatDate(post.publishDate || post.createdAt)}</td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-sm btn-secondary view-btn" data-id="${post.id}"><i class="fa-solid fa-eye btn-icon" aria-hidden="true"></i><span class="btn-label">View</span></button>
                            <button class="btn btn-sm btn-secondary edit-post-btn" data-id="${post.id}"><i class="fa-solid fa-pen-to-square btn-icon" aria-hidden="true"></i><span class="btn-label">Edit</span></button>
                            ${post.status !== 'published' ? `<button class="btn btn-sm btn-primary publish-btn" data-id="${post.id}"><i class="fa-solid fa-paper-plane btn-icon" aria-hidden="true"></i><span class="btn-label">Publish</span></button>` : ''}
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${post.id}"><i class="fa-solid fa-trash btn-icon" aria-hidden="true"></i><span class="btn-label">Delete</span></button>
                        </div>
                    </td>
                </tr>`
            );
            tbody.append(row);
        });
        updateLastUpdated();
        $('.view-btn').click(function(){
            const id = parseInt($(this).data('id'));
            viewPost(id);
        });
        $('.edit-post-btn').click(function(){
            const id = parseInt($(this).data('id'));
            editPost(id);
        });
        $('.publish-btn').click(function(){
            const id = parseInt($(this).data('id'));
            publishPost(id);
        });
        $('.delete-btn').click(function(){
            const id = parseInt($(this).data('id'));
            deletePost(id);
        });
    }

    function openPostModal(id=null){
        if(id){
            const post = posts.find(p=>p.id===id);
            $('#modalTitle').text('Edit Post');
            $('#postId').val(post.id);
            $('#postTitle').val(post.title);
            $('#postSlug').val(post.slug);
            $('#postExcerpt').val(post.excerpt);
            $('#postImage').val(post.image || '');
            $('#postImageAlt').val(post.imageAlt || '');
            $('#postContent').html(post.content);
            $('#postCategory').val(post.category);
            $('#postAuthor').val(post.author);
            $('#postStatus').val(post.status);
            $('#postTags').val(post.tags);
            if(post.publishDate){
                $('#publishDate').val(new Date(post.publishDate).toISOString().slice(0,16));
            } else {
                $('#publishDate').val('');
            }
            setImagePreviewFromPost(post);
        } else {
            $('#modalTitle').text('New Post');
            $('#postForm')[0].reset();
            $('#postId').val('');
            $('#postContent').html('');
            $('#publishDate').val('');
            setImagePreviewFromPost({});
        }
        openModal('postModal');
    }

    function viewPost(id){
        const post = posts.find(p=>p.id===id);
        if(!post) return;
        $('#previewTitle').text(post.title);
        const metaHtml = `
            <div class="blog-preview-meta__row">
                <div class="author-info blog-preview-author">
                    <div class="author-avatar">${post.author.split(' ').map(n=>n[0]).join('')}</div>
                    <div class="blog-preview-author__details">
                        <span class="blog-preview-author__name">${post.author}</span>
                        <span class="blog-preview-date">${formatDate(post.publishDate || post.createdAt)}</span>
                    </div>
                </div>
                <div class="blog-preview-labels">
                    <span class="category-tag">${post.category}</span>
                    <span class="status-badge status-${post.status}">${post.status}</span>
                </div>
            </div>`;
        $('#previewMeta').html(metaHtml);
        if(post.image){
            const previewAlt = escapeAttribute(post.imageAlt || `Featured image for ${post.title || 'blog post'}`);
            $('#previewImage').html(`<figure class="blog-preview-image"><img src="${post.image}" alt="${previewAlt}"></figure>`);
        } else {
            $('#previewImage').empty();
        }
        $('#previewContent').html(post.content);
        $('#editPreviewBtn').data('id', post.id);
        openModal('postPreviewModal');
    }

    function editPost(id){
        openPostModal(id);
    }

    function deletePost(id){
        confirmModal('Are you sure you want to delete this post?').then(ok => {
            if(ok){
                posts = posts.filter(p=>p.id!==id);
                refreshCategoriesFromPosts();
                populateFilters();
                renderPosts();
                updateStats();
                renderCategories();
            }
        });
    }

    function savePost(){
        const formData = new FormData($('#postForm')[0]);
        const imageUrl = normalizeImageValue(formData.get('image'));
        const imageAlt = (formData.get('imageAlt') || '').trim();
        const data = {
            title: formData.get('title'),
            slug: formData.get('slug'),
            excerpt: formData.get('excerpt'),
            content: $('#postContent').html(),
            category: formData.get('category'),
            author: formData.get('author'),
            status: formData.get('status'),
            tags: formData.get('tags'),
            publishDate: formData.get('publishDate'),
            image: imageUrl,
            imageAlt
        };
        if(data.status === 'published' && !data.publishDate){
            data.publishDate = new Date().toISOString();
        }
        const id = $('#postId').val();
        if(id){
            const idx = posts.findIndex(p=>p.id===parseInt(id));
            posts[idx] = {...posts[idx], ...data};
        } else {
            data.id = nextPostId++;
            data.createdAt = new Date().toISOString();
            posts.push(data);
        }
        refreshCategoriesFromPosts();
        populateFilters();
        closeModal('postModal');
        renderPosts();
        updateStats();
        renderCategories();
    }

    function publishPost(id){
        const post = posts.find(p=>p.id===id);
        if(!post) return;
        confirmModal('Publish this post now?').then(ok => {
            if(!ok) return;
            post.status = 'published';
            const now = new Date();
            const nowIso = now.toISOString();
            if(!post.publishDate){
                post.publishDate = nowIso;
            } else {
                const currentDate = new Date(post.publishDate);
                if(Number.isNaN(currentDate.getTime()) || currentDate > now){
                    post.publishDate = nowIso;
                }
            }
            renderPosts();
            updateStats();
        });
    }

    function openCategoriesModal(){
        renderCategories();
        openModal('categoriesModal');
    }

    function renderCategories(){
        const list = $('#categoriesList');
        list.empty();
        if (!categories.length) {
            list.html('<p class="blog-category-empty">No categories yet. Create your first topic above.</p>');
            return;
        }

        const items = categories.map(cat=>{
            const count = posts.filter(p=>p.category===cat).length;
            const countLabel = count === 1 ? 'post' : 'posts';
            return `
                <div class="blog-category-item">
                    <div class="blog-category-item__info">
                        <span class="blog-category-item__name">${cat}</span>
                        <span class="blog-category-item__count">${count} ${countLabel}</span>
                    </div>
                    <button type="button" class="blog-modal__button blog-modal__button--danger delete-category-btn" data-category="${cat}">Delete</button>
                </div>`;
        }).join('');
        list.html(items);
        $('.delete-category-btn').click(function(){
            const cat = $(this).data('category');
            deleteCategory(cat);
        });
    }

    function addCategory(){
        const name = $('#newCategoryName').val().trim();
        if(name && !categories.includes(name)){
            categories.push(name);
            $('#newCategoryName').val('');
            renderCategories();
            populateFilters();
        }
    }

    function deleteCategory(name){
        confirmModal(`Are you sure you want to delete the category "${name}"?`).then(ok => {
            if(ok){
                categories = categories.filter(c=>c!==name);
                posts.forEach(p=>{ if(p.category===name) p.category='Uncategorized'; });
                renderCategories();
                populateFilters();
                renderPosts();
            }
        });
    }

    function updateLastUpdated(){
        let latest = null;
        posts.forEach(post => {
            const baseValue = post.publishDate || post.createdAt;
            const date = new Date(baseValue);
            if (!Number.isNaN(date.getTime())) {
                if (!latest || date > latest) {
                    latest = date;
                }
            }
        });

        if (latest) {
            $('#blogsLastUpdated').text(`Updated ${formatDate(latest)}`);
        } else {
            $('#blogsLastUpdated').text('No posts yet');
        }
    }

    function formatDate(value){
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) {
            return '—';
        }
        return d.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    $(window).click(function(e){
        if(e.target.id==='postModal') closeModal('postModal');
        if(e.target.id==='categoriesModal') closeModal('categoriesModal');
        if(e.target.id==='postPreviewModal') closeModal('postPreviewModal');
        if(e.target.id==='mediaPickerModal') closeModal('mediaPickerModal');
    });

    let autoSaveTimer;
    $('#postTitle, #postExcerpt').on('input', function(){
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function(){
            console.log('Auto-saving draft...');
        },2000);
    });
});
