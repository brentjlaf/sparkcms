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
        </div>

        <section class="a11y-detail-card table-card pages-table-card">
            <table class="pages-list-view" id="pagesListView">
                <thead>
                    <tr>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="title" data-default-direction="asc">
                                <span>Page</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="status" data-default-direction="desc">
                                <span>Status</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="template" data-default-direction="asc">
                                <span>Template</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="views" data-default-direction="desc">
                                <span>Views</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="updated" data-default-direction="desc">
                                <span>Last updated</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="access" data-default-direction="asc">
                                <span>Access</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" class="pages-list-actions-heading">Actions</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($pages as $p): ?>
<?php
    $id = isset($p['id']) ? (int) $p['id'] : 0;
    $title = isset($p['title']) && $p['title'] !== '' ? (string) $p['title'] : 'Untitled Page';
    $slug = isset($p['slug']) ? (string) $p['slug'] : '';
    $content = isset($p['content']) ? (string) $p['content'] : '';
    $templateName = isset($p['template']) && $p['template'] !== '' ? $p['template'] : 'page.php';
    $metaTitle = $p['meta_title'] ?? '';
    $metaDescription = $p['meta_description'] ?? '';
    $canonicalUrl = $p['canonical_url'] ?? '';
    $ogTitle = $p['og_title'] ?? '';
    $ogDescription = $p['og_description'] ?? '';
    $ogImage = $p['og_image'] ?? '';
    $accessRaw = $p['access'] ?? 'public';

    $isPublished = !empty($p['published']);
    $accessValue = strtolower((string) $accessRaw);
    $isRestricted = $accessValue !== 'public';
    $views = (int) ($p['views'] ?? 0);
    $viewsDisplay = number_format($views);
    $lastModified = isset($p['last_modified']) ? (int) $p['last_modified'] : 0;
    $modifiedDisplay = $lastModified > 0 ? date('M j, Y g:i A', $lastModified) : 'No edits yet';
    $viewUrl = '../?page=' . urlencode($slug);
    $accessLabel = $isRestricted ? 'Private' : 'Public';
?>
                    <tr class="pages-list-row"
                        data-id="<?php echo $id; ?>"
                        data-title="<?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-slug="<?php echo htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-content="<?php echo htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-published="<?php echo $isPublished ? 1 : 0; ?>"
                        data-template="<?php echo htmlspecialchars($templateName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-meta_title="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-meta_description="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-canonical_url="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-og_title="<?php echo htmlspecialchars($ogTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-og_description="<?php echo htmlspecialchars($ogDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-og_image="<?php echo htmlspecialchars($ogImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-access="<?php echo htmlspecialchars($accessRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-views="<?php echo $views; ?>"
                        data-last_modified="<?php echo $lastModified; ?>"
                        data-page-item="1"
                        data-view="list">
                        <td class="pages-list-cell pages-list-cell--title" data-label="Page">
                            <div class="pages-list-title">
                                <span class="pages-list-title-text"><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                <span class="pages-list-slug"><?php echo '/' . htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
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
                        </td>
                        <td class="pages-list-cell pages-list-cell--status" data-label="Status">
                            <span class="status-badge <?php echo $isPublished ? 'status-published' : 'status-draft'; ?>">
                                <?php echo $isPublished ? 'Published' : 'Draft'; ?>
                            </span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--template" data-label="Template">
                            <span class="pages-list-template"><?php echo htmlspecialchars($templateName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--views" data-label="Views">
                            <span class="pages-list-views"><?php echo $viewsDisplay; ?></span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--updated" data-label="Last updated">
                            <span class="pages-list-updated">
                                <?php if ($lastModified > 0): ?>
                                    Updated <?php echo htmlspecialchars($modifiedDisplay); ?>
                                <?php else: ?>
                                    No edits yet
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--access" data-label="Access">
                            <span class="pages-list-access"><?php echo htmlspecialchars($accessLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--actions" data-label="Actions">
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
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
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
