<!-- File: view.php -->
<div class="content-section" id="blogs">
    <div class="blog-dashboard">
        <header class="blog-hero">
            <div class="blog-hero-content">
                <div>
                    <h2 class="blog-hero-title">Editorial Dashboard</h2>
                    <p class="blog-hero-subtitle">Plan, publish, and measure the health of your content pipeline.</p>
                </div>
                <div class="blog-hero-actions">
                    <button type="button" class="blog-btn blog-btn--ghost" id="categoriesBtn">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Manage Categories</span>
                    </button>
                    <button type="button" class="blog-btn blog-btn--primary" id="newPostBtn">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        <span>New Post</span>
                    </button>
                </div>
            </div>
            <div class="blog-hero-meta">
                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                <span id="blogsLastUpdated">No posts yet</span>
            </div>
            <div class="blog-overview-grid">
                <article class="blog-overview-card">
                    <span class="blog-overview-label">Total Posts</span>
                    <span class="blog-overview-value" id="totalPosts">0</span>
                </article>
                <article class="blog-overview-card">
                    <span class="blog-overview-label">Published</span>
                    <span class="blog-overview-value" id="publishedPosts">0</span>
                </article>
                <article class="blog-overview-card">
                    <span class="blog-overview-label">Drafts</span>
                    <span class="blog-overview-value" id="draftPosts">0</span>
                </article>
                <article class="blog-overview-card">
                    <span class="blog-overview-label">Scheduled</span>
                    <span class="blog-overview-value" id="scheduledPosts">0</span>
                </article>
            </div>
        </header>

        <div class="blog-controls">
            <label class="blog-search" for="searchFilter">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="searchFilter" placeholder="Search posts by title, excerpt, or tag" aria-label="Search posts">
            </label>
            <div class="blog-filter-row">
                <div class="blog-filter">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>
                <div class="blog-filter">
                    <label for="categoryFilter">Category</label>
                    <select id="categoryFilter">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div class="blog-filter">
                    <label for="authorFilter">Author</label>
                    <select id="authorFilter">
                        <option value="">All Authors</option>
                    </select>
                </div>
                <div class="blog-filter blog-filter--actions">
                    <label>&nbsp;</label>
                    <button type="button" class="blog-btn blog-btn--subtle" id="clearFilters">
                        <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>
                        <span>Reset</span>
                    </button>
                </div>
            </div>
        </div>

        <section class="table-card blog-table-card">
            <header class="blog-table-header">
                <div>
                    <h3>Content pipeline</h3>
                    <p>Track statuses, schedules, and author activity at a glance.</p>
                </div>
                <span class="blog-posts-count" id="postsCount">0 posts</span>
            </header>
            <div class="blog-table-wrapper">
                <table class="data-table blog-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="postsTableBody"></tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div class="modal" id="postModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">New Post</h2>
            <button class="modal-close" id="closeModal" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-body">
            <form id="postForm">
                <div class="form-group">
                    <label for="postTitle">Title *</label>
                    <input type="text" id="postTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label for="postSlug">Slug</label>
                    <input type="text" id="postSlug" name="slug" placeholder="auto-generated-from-title">
                </div>
                <div class="form-group">
                    <label for="postExcerpt">Excerpt</label>
                    <textarea id="postExcerpt" name="excerpt" placeholder="Brief description of the post..."></textarea>
                </div>
                <div class="form-group">
                    <label for="postContent">Content *</label>
                    <div class="editor-container">
                        <div id="postContent" class="editor-content" contenteditable="true"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="postCategory">Category *</label>
                        <select id="postCategory" name="category" required>
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="postAuthor">Author *</label>
                        <select id="postAuthor" name="author" required>
                            <option value="">Select Author</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="postStatus">Status *</label>
                        <select id="postStatus" name="status" required>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="scheduled">Scheduled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="publishDate">Publish Date</label>
                        <input type="datetime-local" id="publishDate" name="publishDate">
                    </div>
                </div>
                <div class="form-group">
                    <label for="postTags">Tags</label>
                    <input type="text" id="postTags" name="tags" placeholder="Separate tags with commas">
                </div>
                <input type="hidden" id="postId" name="id">
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveBtn" form="postForm">Save Post</button>
        </div>
    </div>
</div>

<div class="modal" id="categoriesModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Manage Categories</h2>
            <button class="modal-close" id="closeCategoriesModal" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="newCategoryName">Add New Category</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="newCategoryName" placeholder="Category name" style="flex: 1;">
                    <button class="btn btn-primary" id="addCategoryBtn">Add</button>
                </div>
            </div>
            <div id="categoriesList"></div>
        </div>
    </div>
</div>

<div class="modal" id="postPreviewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="previewTitle"></h2>
            <button class="modal-close" id="closePreviewModal" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-body">
            <div id="previewMeta" style="margin-bottom:10px;"></div>
            <div id="previewContent"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="closePreviewBtn">Close</button>
            <button type="button" class="btn btn-primary" id="editPreviewBtn">Edit</button>
        </div>
    </div>
</div>
