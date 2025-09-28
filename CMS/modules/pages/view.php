<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = read_json_file($settingsFile);
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
                    <h2 class="a11y-hero-title pages-hero-title">Pages</h2>
                    <p class="a11y-hero-subtitle pages-hero-subtitle">Keep your site structure organised and publish updates with confidence.</p>
                </div>
                <div class="a11y-hero-actions pages-hero-actions">
                    <button type="button" class="pages-btn pages-btn--primary" id="newPageBtn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New Page</span>
                    </button>
                    <a class="pages-btn pages-btn--ghost" href="../" target="_blank" rel="noopener">
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
                <button type="button" class="pages-filter-btn active" data-pages-filter="all">All Pages <span class="pages-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="published">Published <span class="pages-filter-count" data-count="published"><?php echo $filterCounts['published']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="drafts">Drafts <span class="pages-filter-count" data-count="drafts"><?php echo $filterCounts['drafts']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="restricted">Private <span class="pages-filter-count" data-count="restricted"><?php echo $filterCounts['restricted']; ?></span></button>
            </div>
        </div>

        <div class="pages-table-card">
            <div class="pages-table-header">
                <div>
                    <h3 class="pages-table-title">Page inventory</h3>
                    <p class="pages-table-subtitle">Manage publishing status, homepage selection, and metadata across all content.</p>
                </div>
                <div class="pages-table-meta" id="pagesVisibleCount">Showing <?php echo $totalPages . ' ' . $pagesWord; ?></div>
            </div>
            <div class="pages-table-wrapper">
                <table class="data-table pages-table" id="pagesTable">
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Status</th>
                            <th scope="col">Views</th>
                            <th scope="col" title="Homepage" aria-label="Homepage"><i class="fa-solid fa-house" aria-hidden="true"></i></th>
                            <th scope="col">Last Modified</th>
                            <th scope="col" class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($pages as $p): ?>
<?php $isPublished = !empty($p['published']); ?>
<tr class="pages-row" data-id="<?php echo $p['id']; ?>"
    data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES); ?>"
    data-slug="<?php echo htmlspecialchars($p['slug'], ENT_QUOTES); ?>"
    data-content="<?php echo htmlspecialchars($p['content'], ENT_QUOTES); ?>"
    data-published="<?php echo $isPublished ? 1 : 0; ?>"
    data-template="<?php echo htmlspecialchars($p['template'] ?? '', ENT_QUOTES); ?>"
    data-meta_title="<?php echo htmlspecialchars($p['meta_title'] ?? '', ENT_QUOTES); ?>"
    data-meta_description="<?php echo htmlspecialchars($p['meta_description'] ?? '', ENT_QUOTES); ?>"
    data-og_title="<?php echo htmlspecialchars($p['og_title'] ?? '', ENT_QUOTES); ?>"
    data-og_description="<?php echo htmlspecialchars($p['og_description'] ?? '', ENT_QUOTES); ?>"
    data-og_image="<?php echo htmlspecialchars($p['og_image'] ?? '', ENT_QUOTES); ?>"
    data-access="<?php echo htmlspecialchars($p['access'] ?? 'public', ENT_QUOTES); ?>">
    <td class="title">
        <div class="pages-title">
            <span class="pages-title-text"><?php echo htmlspecialchars($p['title']); ?></span>
            <span class="pages-slug"><?php echo '/' . htmlspecialchars($p['slug']); ?></span>
        </div>
    </td>
    <td class="status">
        <span class="status-badge <?php echo $isPublished ? 'status-published' : 'status-draft'; ?>">
            <?php echo $isPublished ? 'Published' : 'Draft'; ?>
        </span>
    </td>
    <td class="views"><?php echo $p['views'] ?? 0; ?></td>
    <td class="home">
        <?php if ($homepage === $p['slug']): ?>
            <span class="home-icon is-home" title="Homepage"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
        <?php else: ?>
            <span class="home-icon set-home" title="Set as homepage"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
        <?php endif; ?>
    </td>
    <td class="modified"><?php echo isset($p['last_modified']) ? date('Y-m-d H:i', $p['last_modified']) : ''; ?></td>
    <td class="pages-actions">
        <?php $viewUrl = '../?page=' . urlencode($p['slug']); ?>
        <a class="pages-action-link" href="<?php echo $viewUrl; ?>" target="_blank">View</a>
        <button type="button" class="pages-action-link editBtn">Settings</button>
        <button type="button" class="pages-action-link copyBtn">Copy</button>
        <button type="button" class="pages-action-link togglePublishBtn">
            <?php echo $isPublished ? 'Unpublish' : 'Publish'; ?>
        </button>
        <button type="button" class="pages-action-link pages-action-link--danger deleteBtn">Delete</button>
    </td>
</tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

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
