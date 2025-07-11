$(document).ready(function(){
    let posts = [
        {
            id: 1,
            title: "Getting Started with Web Development",
            slug: "getting-started-web-development",
            excerpt: "A comprehensive guide for beginners looking to start their journey in web development.",
            content: "<p>Welcome to the world of web development! This guide will help you understand the basics...</p>",
            category: "Technology",
            author: "John Doe",
            status: "published",
            publishDate: "2024-01-15T10:00:00",
            tags: "web development, beginner, tutorial",
            createdAt: new Date("2024-01-15T10:00:00")
        },
        {
            id: 2,
            title: "Advanced JavaScript Techniques",
            slug: "advanced-javascript-techniques",
            excerpt: "Explore advanced JavaScript concepts and techniques used by professional developers.",
            content: "<p>JavaScript has evolved significantly over the years. In this post, we'll explore...</p>",
            category: "Programming",
            author: "Jane Smith",
            status: "draft",
            publishDate: "",
            tags: "javascript, advanced, programming",
            createdAt: new Date("2024-01-20T14:30:00")
        },
        {
            id: 3,
            title: "The Future of AI in Web Design",
            slug: "future-ai-web-design",
            excerpt: "How artificial intelligence is revolutionizing the way we approach web design and user experience.",
            content: "<p>Artificial Intelligence is changing every industry, and web design is no exception...</p>",
            category: "Design",
            author: "Mike Johnson",
            status: "scheduled",
            publishDate: "2024-02-01T09:00:00",
            tags: "ai, web design, future, ux",
            createdAt: new Date("2024-01-25T16:45:00")
        }
    ];

    let categories = ["Technology", "Programming", "Design", "Marketing", "Business"];
    let authors = [];
    let nextPostId = 4;

    // Load authors from user accounts
    function loadAuthors(){
        $.getJSON('modules/users/list_users.php', function(data){
            authors = data.map(u => u.username);
            populateAuthorOptions();
        });
    }

    function populateAuthorOptions(){
        const authorOptions = authors.map(a=>`<option value="${a}">${a}</option>`).join('');
        $('#authorFilter, #postAuthor').empty().append(`<option value="">All Authors</option>` + authorOptions);
    }

    updateStats();
    loadAuthors();
    populateFilters();
    renderPosts();

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
    $('#statusFilter, #categoryFilter, #authorFilter').change(function(){
        renderPosts();
    });
    $('#searchFilter').on('input', function(){
        renderPosts();
    });
    $('#clearFilters').click(function(){
        $('#statusFilter, #categoryFilter, #authorFilter').val('');
        $('#searchFilter').val('');
        renderPosts();
    });
    $('#postTitle').on('input', function(){
        if(!$('#postId').val()){
            const slug = $(this).val().toLowerCase()
                .replace(/[^a-z0-9]+/g,'-')
                .replace(/^-+|-+$/g,'');
            $('#postSlug').val(slug);
        }
    });
    $('.editor-btn').click(function(){
        const command = $(this).data('command');
        if(command === 'createLink'){
            promptModal('Enter URL:').then(url => {
                if(url){
                    document.execCommand(command,false,url);
                }
            });
        } else {
            document.execCommand(command,false,null);
        }
        $('#postContent').focus();
    });

    function updateStats(){
        const total = posts.length;
        const published = posts.filter(p=>p.status==='published').length;
        const draft = posts.filter(p=>p.status==='draft').length;
        const scheduled = posts.filter(p=>p.status==='scheduled').length;
        $('#totalPosts').text(total);
        $('#publishedPosts').text(published);
        $('#draftPosts').text(draft);
        $('#scheduledPosts').text(scheduled);
    }

    function populateFilters(){
        const categoryOptions = categories.map(c=>`<option value="${c}">${c}</option>`).join('');
        $('#categoryFilter, #postCategory').append(categoryOptions);
        populateAuthorOptions();
    }

    function renderPosts(){
        let filtered = [...posts];
        const status = $('#statusFilter').val();
        const category = $('#categoryFilter').val();
        const author = $('#authorFilter').val();
        const search = $('#searchFilter').val().toLowerCase();

        if(status) filtered = filtered.filter(p=>p.status===status);
        if(category) filtered = filtered.filter(p=>p.category===category);
        if(author) filtered = filtered.filter(p=>p.author===author);
        if(search){
            filtered = filtered.filter(p=>
                p.title.toLowerCase().includes(search) ||
                p.excerpt.toLowerCase().includes(search) ||
                p.tags.toLowerCase().includes(search)
            );
        }
        filtered.sort((a,b)=> new Date(b.createdAt) - new Date(a.createdAt));

        const tbody = $('#postsTableBody');
        tbody.empty();
        filtered.forEach(post=>{
            const row = $(
                `<tr>
                    <td>
                        <div class="post-title">${post.title}</div>
                        <div class="post-excerpt">${post.excerpt}</div>
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
                            <button class="btn btn-sm btn-secondary view-btn" data-id="${post.id}">View</button>
                            <button class="btn btn-sm btn-secondary edit-post-btn" data-id="${post.id}">Edit</button>
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${post.id}">Delete</button>
                        </div>
                    </td>
                </tr>`
            );
            tbody.append(row);
        });
        $('#postsCount').text(`${filtered.length} posts`);
        $('.view-btn').click(function(){
            const id = parseInt($(this).data('id'));
            viewPost(id);
        });
        $('.edit-post-btn').click(function(){
            const id = parseInt($(this).data('id'));
            editPost(id);
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
        } else {
            $('#modalTitle').text('New Post');
            $('#postForm')[0].reset();
            $('#postId').val('');
            $('#postContent').html('');
            $('#publishDate').val('');
        }
        openModal('postModal');
    }

    function viewPost(id){
        const post = posts.find(p=>p.id===id);
        if(!post) return;
        $('#previewTitle').text(post.title);
        const metaHtml = `
            <div class="author-info" style="margin-bottom:8px;">
                <div class="author-avatar">${post.author.split(' ').map(n=>n[0]).join('')}</div>
                <span>${post.author}</span>
            </div>
            <span class="category-tag">${post.category}</span>
            <span class="status-badge status-${post.status}">${post.status}</span>
            <span style="margin-left:5px;">${formatDate(post.publishDate || post.createdAt)}</span>`;
        $('#previewMeta').html(metaHtml);
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
                renderPosts();
                updateStats();
            }
        });
    }

    function savePost(){
        const formData = new FormData($('#postForm')[0]);
        const data = {
            title: formData.get('title'),
            slug: formData.get('slug'),
            excerpt: formData.get('excerpt'),
            content: $('#postContent').html(),
            category: formData.get('category'),
            author: formData.get('author'),
            status: formData.get('status'),
            tags: formData.get('tags'),
            publishDate: formData.get('publishDate')
        };
        const id = $('#postId').val();
        if(id){
            const idx = posts.findIndex(p=>p.id===parseInt(id));
            posts[idx] = {...posts[idx], ...data};
        } else {
            data.id = nextPostId++;
            data.createdAt = new Date();
            posts.push(data);
        }
        closeModal('postModal');
        renderPosts();
        updateStats();
    }

    function openCategoriesModal(){
        renderCategories();
        openModal('categoriesModal');
    }

    function renderCategories(){
        const list = $('#categoriesList');
        list.empty();
        const items = categories.map(cat=>{
            const count = posts.filter(p=>p.category===cat).length;
            return `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #e2e8f0;">
                    <span>${cat} (${count} posts)</span>
                    <button class="btn btn-sm btn-danger delete-category-btn" data-category="${cat}">Delete</button>
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

    function formatDate(str){
        const d = new Date(str);
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
    });

    let autoSaveTimer;
    $('#postTitle, #postContent, #postExcerpt').on('input', function(){
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function(){
            console.log('Auto-saving draft...');
        },2000);
    });
});
