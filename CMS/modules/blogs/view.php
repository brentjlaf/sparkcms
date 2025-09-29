<!-- File: view.php -->
<div class="content-section" id="blogs">
    <div class="blog-dashboard">
        <header class="a11y-hero blog-hero">
            <div class="a11y-hero-content blog-hero-content">
                <div>
                    <span class="hero-eyebrow blog-hero-eyebrow">Publishing Pipeline</span>
                    <h2 class="a11y-hero-title blog-hero-title">Editorial Dashboard</h2>
                    <p class="a11y-hero-subtitle blog-hero-subtitle">Plan, publish, and measure the health of your content pipeline.</p>
                </div>
                <div class="a11y-hero-actions blog-hero-actions">
                    <button type="button" class="blog-btn blog-btn--ghost" id="categoriesBtn">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Manage Categories</span>
                    </button>
                    <button type="button" class="blog-btn blog-btn--primary" id="newPostBtn">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        <span>New Post</span>
                    </button>
                    <span class="a11y-hero-meta blog-hero-meta">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <span id="blogsLastUpdated">No posts yet</span>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid blog-overview-grid">
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Total Posts</div>
                    <div class="a11y-overview-value blog-overview-value" id="totalPosts">0</div>
                </div>
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Published</div>
                    <div class="a11y-overview-value blog-overview-value" id="publishedPosts">0</div>
                </div>
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Drafts</div>
                    <div class="a11y-overview-value blog-overview-value" id="draftPosts">0</div>
                </div>
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Scheduled</div>
                    <div class="a11y-overview-value blog-overview-value" id="scheduledPosts">0</div>
                </div>
            </div>
        </header>

        <div class="blog-controls">
            <div class="blog-controls-primary">
                <label class="blog-search" for="blogSearchInput">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="blogSearchInput" placeholder="Search posts by title, slug, excerpt, or tag" aria-label="Search posts">
                </label>
                <div class="blog-filter-group" role="group" aria-label="Filter posts by status">
                    <button type="button" class="blog-filter-btn active" data-blog-filter="all" aria-pressed="true">
                        All Posts <span class="blog-filter-count" data-count="all">0</span>
                    </button>
                    <button type="button" class="blog-filter-btn" data-blog-filter="published" aria-pressed="false">
                        Published <span class="blog-filter-count" data-count="published">0</span>
                    </button>
                    <button type="button" class="blog-filter-btn" data-blog-filter="drafts" aria-pressed="false">
                        Drafts <span class="blog-filter-count" data-count="drafts">0</span>
                    </button>
                    <button type="button" class="blog-filter-btn" data-blog-filter="scheduled" aria-pressed="false">
                        Scheduled <span class="blog-filter-count" data-count="scheduled">0</span>
                    </button>
                </div>
            </div>
            <div class="blog-controls-secondary">
                <div class="blog-select-filter">
                    <label for="categoryFilter">Category</label>
                    <select id="categoryFilter">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div class="blog-select-filter">
                    <label for="authorFilter">Author</label>
                    <select id="authorFilter">
                        <option value="">All Authors</option>
                    </select>
                </div>
                <div class="blog-select-filter blog-select-filter--actions">
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

<div class="modal blog-modal" id="postModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="postModalDescription">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closeModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Post settings</span>
                <h2 class="blog-modal__title" id="modalTitle">New Post</h2>
                <p class="blog-modal__description" id="postModalDescription">Craft your article content, assign categories, and schedule publication.</p>
            </header>
            <div class="blog-modal__body">
                <form id="postForm" class="blog-modal__form">
                    <div class="form-group blog-modal__field">
                        <label for="postTitle">Title *</label>
                        <input type="text" id="postTitle" name="title" required>
                    </div>
                    <div class="form-group blog-modal__field">
                        <label for="postSlug">Slug</label>
                        <input type="text" id="postSlug" name="slug" placeholder="auto-generated-from-title">
                    </div>
                    <div class="form-group blog-modal__field">
                        <label for="postExcerpt">Excerpt</label>
                        <textarea id="postExcerpt" name="excerpt" placeholder="Brief description of the post..."></textarea>
                    </div>
                    <div class="form-group blog-modal__field">
                        <label for="postImage">Featured Image</label>
                        <div class="blog-image-input">
                            <input type="text" id="postImage" name="image" placeholder="Select an image from the Media Library" aria-describedby="postImageHint">
                            <button type="button" class="blog-btn blog-btn--subtle" id="chooseFeaturedImage">
                                <i class="fa-solid fa-images" aria-hidden="true"></i>
                                <span>Select from Media Library</span>
                            </button>
                        </div>
                        <p class="blog-modal__hint" id="postImageHint">Choose an image from the Media Library or paste a trusted image URL to highlight this post.</p>
                        <div class="blog-image-preview" id="postImagePreview" aria-live="polite"></div>
                    </div>
                    <div class="form-group blog-modal__field">
                        <label for="postImageAlt">Image alternative text</label>
                        <input type="text" id="postImageAlt" name="imageAlt" placeholder="Describe the featured image for screen readers">
                    </div>
                    <div class="form-group blog-modal__field">
                        <label for="postContent">Content *</label>
                        <div class="editor-container blog-modal__editor">
                            <div id="postContent" class="editor-content" contenteditable="true"></div>
                        </div>
                    </div>
                    <div class="blog-modal__grid">
                        <div class="form-group blog-modal__field">
                            <label for="postCategory">Category *</label>
                            <select id="postCategory" name="category" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div class="form-group blog-modal__field">
                            <label for="postAuthor">Author *</label>
                            <select id="postAuthor" name="author" required>
                                <option value="">Select Author</option>
                            </select>
                        </div>
                    </div>
                    <div class="blog-modal__grid">
                        <div class="form-group blog-modal__field">
                            <label for="postStatus">Status *</label>
                            <select id="postStatus" name="status" required>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                        <div class="form-group blog-modal__field">
                            <label for="publishDate">Publish Date</label>
                            <input type="datetime-local" id="publishDate" name="publishDate">
                        </div>
                    </div>
                    <div class="form-group blog-modal__field">
                        <label for="postTags">Tags</label>
                        <input type="text" id="postTags" name="tags" placeholder="Separate tags with commas">
                    </div>
                    <input type="hidden" id="postId" name="id">
                </form>
            </div>
            <footer class="blog-modal__footer">
                <button type="button" class="blog-modal__button blog-modal__button--secondary" id="cancelBtn">Cancel</button>
                <button type="submit" class="blog-modal__button blog-modal__button--primary" id="saveBtn" form="postForm">Save Post</button>
            </footer>
        </div>
    </div>
</div>

<div class="modal blog-modal" id="categoriesModal" role="dialog" aria-modal="true" aria-labelledby="categoriesModalTitle" aria-describedby="categoriesModalDescription">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closeCategoriesModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Categories</span>
                <h2 class="blog-modal__title" id="categoriesModalTitle">Manage Categories</h2>
                <p class="blog-modal__description" id="categoriesModalDescription">Create, rename, and remove topics to keep your editorial calendar organized.</p>
            </header>
            <div class="blog-modal__body">
                <div class="form-group blog-modal__field">
                    <label for="newCategoryName">Add New Category</label>
                    <div class="blog-modal__inline-input">
                        <input type="text" id="newCategoryName" placeholder="Category name">
                        <button type="button" class="blog-modal__button blog-modal__button--primary" id="addCategoryBtn">Add</button>
                    </div>
                </div>
                <div id="categoriesList" class="blog-category-list" aria-live="polite"></div>
            </div>
            <footer class="blog-modal__footer">
                <button type="button" class="blog-modal__button blog-modal__button--secondary" id="categoriesDoneBtn">Done</button>
            </footer>
        </div>
    </div>
</div>

<div class="modal blog-modal" id="postPreviewModal" role="dialog" aria-modal="true" aria-labelledby="previewTitle">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closePreviewModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Post preview</span>
                <h2 class="blog-modal__title" id="previewTitle"></h2>
                <p class="blog-modal__description">Review your content before publishing or return to make edits.</p>
            </header>
            <div class="blog-modal__body">
                <div id="previewMeta" class="blog-preview-meta"></div>
                <div id="previewImage"></div>
                <div id="previewContent" class="blog-preview-content"></div>
            </div>
            <footer class="blog-modal__footer">
                <button type="button" class="blog-modal__button blog-modal__button--secondary" id="closePreviewBtn">Close</button>
                <button type="button" class="blog-modal__button blog-modal__button--primary" id="editPreviewBtn">Edit</button>
            </footer>
        </div>
    </div>
</div>

<div class="modal blog-modal" id="mediaPickerModal" role="dialog" aria-modal="true" aria-labelledby="mediaPickerTitle">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closeMediaPickerModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Media library</span>
                <h2 class="blog-modal__title" id="mediaPickerTitle">Select a featured image</h2>
                <p class="blog-modal__description">Choose from the media library to quickly set a featured image for your post.</p>
            </header>
            <div class="blog-modal__body blog-media-picker">
                <div class="blog-media-picker__toolbar">
                    <label class="blog-media-picker__search" for="mediaPickerSearch">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="mediaPickerSearch" placeholder="Search media by name or tag" aria-label="Search media">
                    </label>
                </div>
                <div id="mediaPickerGrid" class="blog-media-picker__grid" role="listbox" aria-label="Media library images" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>
