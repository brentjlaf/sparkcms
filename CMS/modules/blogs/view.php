<!-- File: view.php -->
<div class="content-section" id="blogs">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Blog Management</div>
            <div class="table-actions">
                <button class="btn btn-secondary" id="categoriesBtn">Categories</button>
                <button class="btn btn-primary" id="newPostBtn">+ New Post</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon posts"><i class="fa-solid fa-newspaper" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Posts</div>
                        <div class="stat-number" id="totalPosts">0</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon posts"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Published</div>
                        <div class="stat-number" id="publishedPosts">0</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon posts"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Drafts</div>
                        <div class="stat-number" id="draftPosts">0</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon posts"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Scheduled</div>
                        <div class="stat-number" id="scheduledPosts">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="filters">
            <h3 class="filters-title">Filters</h3>
            <div class="filters-row">
            <div class="filter-group">
                <label>Status</label>
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Category</label>
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Author</label>
                <select id="authorFilter">
                    <option value="">All Authors</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" id="searchFilter" placeholder="Search posts...">
            </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-secondary" id="clearFilters">Clear</button>
                </div>
            </div>
        </div>

        <div class="table-header" style="margin-top:20px;">
            <div class="table-title">Posts</div>
            <span class="posts-count" id="postsCount">0 posts</span>
        </div>
        <table class="data-table">
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
