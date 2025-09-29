<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$settings = get_site_settings();
$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');
$templates = [];
if ($templateDir) {
    foreach (glob($templateDir . '/*.php') as $t) {
        $name = basename($t);
        if ($name[0] !== '_') $templates[] = $name;
    }
}
$homepage = $settings['homepage'] ?? 'home';

// Page stats
$totalPages = count($pages);
$publishedPages = 0;
$draftPages = 0;
$totalViews = 0;
$restrictedPages = 0;
$lastUpdatedTimestamp = 0;
foreach ($pages as $p) {
    if (!empty($p['published'])) {
        $publishedPages++;
    } else {
        $draftPages++;
    }

    if (($p['access'] ?? 'public') !== 'public') {
        $restrictedPages++;
    }

    if (!empty($p['last_modified'])) {
        $lastUpdatedTimestamp = max($lastUpdatedTimestamp, (int)$p['last_modified']);
    }

    $totalViews += $p['views'] ?? 0;
}

$lastUpdatedDisplay = $lastUpdatedTimestamp > 0 ? date('M j, Y g:i A', $lastUpdatedTimestamp) : 'No edits yet';
$filterCounts = [
    'all' => $totalPages,
    'published' => $publishedPages,
    'drafts' => $draftPages,
    'restricted' => $restrictedPages,
];
$pagesWord = $totalPages === 1 ? 'page' : 'pages';
?>
<div class="content-section" id="pages">
    <div class="pages-dashboard a11y-dashboard" data-last-updated="<?php echo htmlspecialchars($lastUpdatedDisplay, ENT_QUOTES); ?>">
        <header class="a11y-hero pages-hero">
            <div class="a11y-hero-content pages-hero-content">
                <div>
                    <span class="hero-eyebrow pages-hero-eyebrow">Content Inventory</span>
                    <h2 class="a11y-hero-title pages-hero-title">Pages</h2>
                    <p class="a11y-hero-subtitle pages-hero-subtitle">Keep your site structure organised and publish updates with confidence.</p>
                </div>
                <div class="a11y-hero-actions pages-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="newPageBtn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New Page</span>
                    </button>
                    <a class="a11y-btn a11y-btn--ghost" href="../" target="_blank" rel="noopener">
                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                        <span>View Site</span>
                    </a>
                    <span class="a11y-hero-meta pages-hero-meta">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        Last edit: <?php echo htmlspecialchars($lastUpdatedDisplay); ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid pages-overview-grid">
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Total Pages</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $totalPages; ?></div>
                </div>
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Published</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $publishedPages; ?></div>
                </div>
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Drafts</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $draftPages; ?></div>
                </div>
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Total Views</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $totalViews; ?></div>
                </div>
            </div>
        </header>

        <div class="pages-controls">
            <label class="pages-search" for="pagesSearchInput">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <input type="search" id="pagesSearchInput" placeholder="Search pages by title or slug" aria-label="Search pages">
            </label>
            <div class="pages-filter-group" role="group" aria-label="Filter pages by status">
                <button type="button" class="pages-filter-btn active" data-pages-filter="all" aria-pressed="true">All Pages <span class="pages-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="published" aria-pressed="false">Published <span class="pages-filter-count" data-count="published"><?php echo $filterCounts['published']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="drafts" aria-pressed="false">Drafts <span class="pages-filter-count" data-count="drafts"><?php echo $filterCounts['drafts']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="restricted" aria-pressed="false">Private <span class="pages-filter-count" data-count="restricted"><?php echo $filterCounts['restricted']; ?></span></button>
            </div>
            <div class="a11y-view-toggle pages-view-toggle" role="group" aria-label="Toggle page layout">
                <button type="button" class="a11y-view-btn active" data-pages-view="grid" aria-pressed="true" aria-label="Card view">
                    <i class="fa-solid fa-grip" aria-hidden="true"></i>
                </button>
                <button type="button" class="a11y-view-btn" data-pages-view="list" aria-pressed="false" aria-label="List view">
                    <i class="fa-solid fa-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <section class="a11y-detail-card table-card pages-table-card" aria-labelledby="pagesInventoryTitle" aria-describedby="pagesInventoryDescription">
            <header class="table-header pages-table-header">
                <div class="table-header-text">
                    <h3 class="table-title" id="pagesInventoryTitle">Page inventory</h3>
                    <p class="table-description" id="pagesInventoryDescription">Manage publishing status, homepage selection, and metadata across all content.</p>
                </div>
                <span class="table-meta pages-table-meta" id="pagesVisibleCount" aria-live="polite">Showing <?php echo $totalPages . ' ' . $pagesWord; ?></span>
            </header>
            <div class="pages-card-grid" id="pagesCollection" role="list" aria-describedby="pagesInventoryDescription">
<?php foreach ($pages as $p): ?>
<?php
    $isPublished = !empty($p['published']);
    $accessValue = strtolower((string) ($p['access'] ?? 'public'));
    $isRestricted = $accessValue !== 'public';
    $views = (int) ($p['views'] ?? 0);
    $viewsDisplay = number_format($views);
    $lastModified = isset($p['last_modified']) ? (int) $p['last_modified'] : 0;
    $modifiedDisplay = $lastModified > 0 ? date('M j, Y g:i A', $lastModified) : 'No edits yet';
    $viewUrl = '../?page=' . urlencode($p['slug']);
?>
                <article class="pages-card" role="listitem"
                    data-id="<?php echo $p['id']; ?>"
                    data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES); ?>"
                    data-slug="<?php echo htmlspecialchars($p['slug'], ENT_QUOTES); ?>"
                    data-content="<?php echo htmlspecialchars($p['content'], ENT_QUOTES); ?>"
                    data-published="<?php echo $isPublished ? 1 : 0; ?>"
                    data-template="<?php echo htmlspecialchars($p['template'] ?? '', ENT_QUOTES); ?>"
                    data-meta_title="<?php echo htmlspecialchars($p['meta_title'] ?? '', ENT_QUOTES); ?>"
                    data-meta_description="<?php echo htmlspecialchars($p['meta_description'] ?? '', ENT_QUOTES); ?>"
                    data-canonical_url="<?php echo htmlspecialchars($p['canonical_url'] ?? '', ENT_QUOTES); ?>"
                    data-og_title="<?php echo htmlspecialchars($p['og_title'] ?? '', ENT_QUOTES); ?>"
                    data-og_description="<?php echo htmlspecialchars($p['og_description'] ?? '', ENT_QUOTES); ?>"
                    data-og_image="<?php echo htmlspecialchars($p['og_image'] ?? '', ENT_QUOTES); ?>"
                    data-access="<?php echo htmlspecialchars($p['access'] ?? 'public', ENT_QUOTES); ?>"
                    data-page-item="1"
                    data-view="card">
                    <div class="pages-card__header">
                        <div class="pages-card__titles">
                            <span class="pages-card__title"><?php echo htmlspecialchars($p['title']); ?></span>
                            <span class="pages-card__slug"><?php echo '/' . htmlspecialchars($p['slug']); ?></span>
                        </div>
                        <span class="status-badge <?php echo $isPublished ? 'status-published' : 'status-draft'; ?>">
                            <?php echo $isPublished ? 'Published' : 'Draft'; ?>
                        </span>
                    </div>
                    <div class="pages-card__meta">
                        <span class="pages-card__stat" data-pages-stat="views">
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                            <span class="pages-card__stat-value"><?php echo $viewsDisplay; ?></span> views
                        </span>
                        <span class="pages-card__updated">
                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                            <?php if ($lastModified > 0): ?>Updated <?php echo htmlspecialchars($modifiedDisplay); ?><?php else: ?>No edits yet<?php endif; ?>
                        </span>
                    </div>
                    <div class="pages-card__footer">
                        <div class="pages-card__badges">
                            <?php if ($homepage === $p['slug']): ?>
                                <span class="pages-card__badge pages-card__badge--home">
                                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                                    Homepage
                                </span>
                            <?php else: ?>
                                <button type="button" class="a11y-btn a11y-btn--icon pages-card__home set-home" title="Set as homepage" aria-label="Set as homepage">
                                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($isRestricted): ?>
                                <span class="pages-card__badge pages-card__badge--restricted">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    Private
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="pages-card__actions">
                            <a class="a11y-btn a11y-btn--ghost pages-card__action" data-action="view" href="<?php echo $viewUrl; ?>" target="_blank" rel="noopener">
                                View
                            </a>
                            <button type="button" class="a11y-btn a11y-btn--secondary pages-card__action editBtn">Settings</button>
                            <button type="button" class="a11y-btn a11y-btn--ghost pages-card__action copyBtn">Copy</button>
                            <button type="button" class="a11y-btn a11y-btn--secondary pages-card__action togglePublishBtn">
                                <?php echo $isPublished ? 'Unpublish' : 'Publish'; ?>
                            </button>
                            <button type="button" class="a11y-btn a11y-btn--danger pages-card__action deleteBtn">Delete</button>
                        </div>
                    </div>
                </article>
<?php endforeach; ?>
            </div>
            <div class="pages-list-view" id="pagesListView" role="table" aria-describedby="pagesInventoryDescription" hidden>
                <div class="pages-list-header" role="row">
                    <span role="columnheader">Page</span>
                    <span role="columnheader">Status</span>
                    <span role="columnheader">Views</span>
                    <span role="columnheader">Last updated</span>
                    <span role="columnheader">Access</span>
                    <span role="columnheader" class="pages-list-actions-heading">Actions</span>
                </div>
                <div class="pages-list-body" role="rowgroup">
<?php foreach ($pages as $p): ?>
<?php
    $isPublished = !empty($p['published']);
    $accessValue = strtolower((string) ($p['access'] ?? 'public'));
    $isRestricted = $accessValue !== 'public';
    $views = (int) ($p['views'] ?? 0);
    $viewsDisplay = number_format($views);
    $lastModified = isset($p['last_modified']) ? (int) $p['last_modified'] : 0;
    $modifiedDisplay = $lastModified > 0 ? date('M j, Y g:i A', $lastModified) : 'No edits yet';
    $viewUrl = '../?page=' . urlencode($p['slug']);
    $accessLabel = $isRestricted ? 'Private' : 'Public';
?>
                    <div class="pages-list-row"
                        role="row"
                        data-id="<?php echo $p['id']; ?>"
                        data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES); ?>"
                        data-slug="<?php echo htmlspecialchars($p['slug'], ENT_QUOTES); ?>"
                        data-content="<?php echo htmlspecialchars($p['content'], ENT_QUOTES); ?>"
                        data-published="<?php echo $isPublished ? 1 : 0; ?>"
                        data-template="<?php echo htmlspecialchars($p['template'] ?? '', ENT_QUOTES); ?>"
                        data-meta_title="<?php echo htmlspecialchars($p['meta_title'] ?? '', ENT_QUOTES); ?>"
                        data-meta_description="<?php echo htmlspecialchars($p['meta_description'] ?? '', ENT_QUOTES); ?>"
                        data-canonical_url="<?php echo htmlspecialchars($p['canonical_url'] ?? '', ENT_QUOTES); ?>"
                        data-og_title="<?php echo htmlspecialchars($p['og_title'] ?? '', ENT_QUOTES); ?>"
                        data-og_description="<?php echo htmlspecialchars($p['og_description'] ?? '', ENT_QUOTES); ?>"
                        data-og_image="<?php echo htmlspecialchars($p['og_image'] ?? '', ENT_QUOTES); ?>"
                        data-access="<?php echo htmlspecialchars($p['access'] ?? 'public', ENT_QUOTES); ?>"
                        data-page-item="1"
                        data-view="list">
                        <div class="pages-list-cell pages-list-cell--title" role="cell">
                            <div class="pages-list-title">
                                <span class="pages-list-title-text"><?php echo htmlspecialchars($p['title']); ?></span>
                                <span class="pages-list-slug"><?php echo '/' . htmlspecialchars($p['slug']); ?></span>
                            </div>
                            <div class="pages-list-badges">
                                <?php if ($homepage === $p['slug']): ?>
                                    <span class="pages-card__badge pages-card__badge--home">
                                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                                        Homepage
                                    </span>
                                <?php else: ?>
                                    <button type="button" class="a11y-btn a11y-btn--icon pages-card__home set-home" title="Set as homepage" aria-label="Set as homepage">
                                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($isRestricted): ?>
                                    <span class="pages-card__badge pages-card__badge--restricted">
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                        Private
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pages-list-cell pages-list-cell--status" role="cell">
                            <span class="status-badge <?php echo $isPublished ? 'status-published' : 'status-draft'; ?>">
                                <?php echo $isPublished ? 'Published' : 'Draft'; ?>
                            </span>
                        </div>
                        <div class="pages-list-cell pages-list-cell--views" role="cell">
                            <span class="pages-list-views"><?php echo $viewsDisplay; ?></span>
                        </div>
                        <div class="pages-list-cell pages-list-cell--updated" role="cell">
                            <span class="pages-list-updated">
                                <?php if ($lastModified > 0): ?>
                                    Updated <?php echo htmlspecialchars($modifiedDisplay); ?>
                                <?php else: ?>
                                    No edits yet
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="pages-list-cell pages-list-cell--access" role="cell">
                            <span class="pages-list-access"><?php echo htmlspecialchars($accessLabel); ?></span>
                        </div>
                        <div class="pages-list-cell pages-list-cell--actions" role="cell">
                            <div class="pages-list-actions">
                                <a class="a11y-btn a11y-btn--ghost pages-card__action" data-action="view" href="<?php echo $viewUrl; ?>" target="_blank" rel="noopener">
                                    View
                                </a>
                                <button type="button" class="a11y-btn a11y-btn--secondary pages-card__action editBtn">Settings</button>
                                <button type="button" class="a11y-btn a11y-btn--ghost pages-card__action copyBtn">Copy</button>
                                <button type="button" class="a11y-btn a11y-btn--secondary pages-card__action togglePublishBtn">
                                    <?php echo $isPublished ? 'Unpublish' : 'Publish'; ?>
                                </button>
                                <button type="button" class="a11y-btn a11y-btn--danger pages-card__action deleteBtn">Delete</button>
                            </div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </section>

        <div class="pages-empty-state" id="pagesEmptyState" hidden>
            <i class="fa-solid fa-file-circle-question" aria-hidden="true"></i>
            <h3>No pages match your filters</h3>
            <p>Try a different search term or choose another status filter.</p>
        </div>
    </div>

    <div id="pageModal" class="modal page-settings-modal" role="dialog" aria-modal="true" aria-labelledby="formTitle" aria-describedby="pageModalDescription">
        <div class="modal-content">
            <div class="page-modal-surface">
                <button type="button" class="page-modal-close" id="closePageModal" aria-label="Close page settings">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <header class="page-modal-header">
                    <span class="page-modal-subtitle">Page settings</span>
                    <h2 class="page-modal-title" id="formTitle">Add New Page</h2>
                    <p class="page-modal-description" id="pageModalDescription">Configure publishing, templates, and metadata before publishing your page.</p>
                </header>
                <form id="pageForm" class="page-modal-form">
                    <input type="hidden" name="id" id="pageId">
                    <input type="hidden" name="content" id="content">
                    <div class="page-modal-body">
                        <div id="pageTabs" class="page-modal-tabs">
                            <ul>
                                <li><a href="#tab-settings">Page Settings</a></li>
                                <li><a href="#tab-seo">SEO Options</a></li>
                                <li><a href="#tab-og">Social Open Graph</a></li>
                            </ul>
                            <div id="tab-settings" class="page-modal-panel">
                                <div class="page-modal-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="title">Title</label>
                                        <input type="text" class="form-input" name="title" id="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="slug">Slug</label>
                                        <input type="text" class="form-input" name="slug" id="slug" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="template">Template</label>
                                        <select class="form-select" name="template" id="template">
                                            <option value="page.php">page.php</option>
                                            <?php foreach ($templates as $t): ?>
                                            <?php if ($t !== 'page.php'): ?>
                                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="access">Access</label>
                                        <select class="form-select" name="access" id="access">
                                            <option value="public">Public</option>
                                            <option value="private">Private</option>
                                        </select>
                                    </div>
                                    <div class="form-group page-modal-checkbox">
                                        <label class="form-label" for="published">Publishing</label>
                                        <label class="page-modal-toggle">
                                            <input type="checkbox" name="published" id="published">
                                            <span>Published</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div id="tab-seo" class="page-modal-panel">
                                <div class="form-group">
                                    <label class="form-label" for="meta_title">Meta Title</label>
                                    <input type="text" class="form-input" name="meta_title" id="meta_title">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="meta_description">Meta Description</label>
                                    <textarea class="form-textarea" name="meta_description" id="meta_description" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="canonical_url">Canonical URL</label>
                                    <input type="url" class="form-input" name="canonical_url" id="canonical_url" placeholder="https://example.com/your-page">
                                </div>
                            </div>
                            <div id="tab-og" class="page-modal-panel">
                                <div class="form-group">
                                    <label class="form-label" for="og_title">OG Title</label>
                                    <input type="text" class="form-input" name="og_title" id="og_title">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="og_description">OG Description</label>
                                    <textarea class="form-textarea" name="og_description" id="og_description" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="og_image">OG Image URL</label>
                                    <input type="text" class="form-input" name="og_image" id="og_image">
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="page-modal-footer">
                        <button type="button" class="page-modal-button page-modal-button--secondary" id="cancelEdit">Cancel</button>
                        <button type="submit" class="page-modal-button page-modal-button--primary">Save Page</button>
                    </footer>
                </form>
            </div>
        </div>
    </div>

</div>
