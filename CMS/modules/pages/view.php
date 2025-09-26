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
foreach ($pages as $p) {
    if (!empty($p['published'])) {
        $publishedPages++;
    } else {
        $draftPages++;
    }
    $totalViews += $p['views'] ?? 0;
}
?>
<div class="content-section" id="pages">
                    <div class="table-card">
                        <div class="table-header">
                            <div class="table-title">All Pages</div>
                            <div class="table-actions">
                                <button class="btn btn-primary" id="newPageBtn">+ New Page</button>
                            </div>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-icon pages"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-label">Total Pages</div>
                                        <div class="stat-number"><?php echo $totalPages; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-icon pages"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-label">Published</div>
                                        <div class="stat-number"><?php echo $publishedPages; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-icon pages"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-label">Drafts</div>
                                        <div class="stat-number"><?php echo $draftPages; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-icon views"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-label">Total Views</div>
                                        <div class="stat-number"><?php echo $totalViews; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <table class="data-table" id="pagesTable">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Views</th>
                                    <th title="Homepage" aria-label="Homepage"><i class="fa-solid fa-house" aria-hidden="true"></i></th>
                                    <th>Last Modified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($pages as $p): ?>
<tr data-id="<?php echo $p['id']; ?>"
    data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES); ?>"
    data-slug="<?php echo htmlspecialchars($p['slug'], ENT_QUOTES); ?>"
    data-content="<?php echo htmlspecialchars($p['content'], ENT_QUOTES); ?>"
    data-published="<?php echo !empty($p['published']) ? 1 : 0; ?>"
    data-template="<?php echo htmlspecialchars($p['template'] ?? '', ENT_QUOTES); ?>"
    data-meta_title="<?php echo htmlspecialchars($p['meta_title'] ?? '', ENT_QUOTES); ?>"
    data-meta_description="<?php echo htmlspecialchars($p['meta_description'] ?? '', ENT_QUOTES); ?>"
    data-og_title="<?php echo htmlspecialchars($p['og_title'] ?? '', ENT_QUOTES); ?>"
    data-og_description="<?php echo htmlspecialchars($p['og_description'] ?? '', ENT_QUOTES); ?>"
    data-og_image="<?php echo htmlspecialchars($p['og_image'] ?? '', ENT_QUOTES); ?>"
    data-access="<?php echo htmlspecialchars($p['access'] ?? 'public', ENT_QUOTES); ?>">
    <td class="title"><?php echo htmlspecialchars($p['title']); ?></td>
    <td class="status">
        <span class="status-badge <?php echo !empty($p['published']) ? 'status-published' : 'status-draft'; ?>">
            <?php echo !empty($p['published']) ? 'Published' : 'Draft'; ?>
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
    <td>
        <?php $viewUrl = '../?page=' . urlencode($p['slug']); ?>
        <a class="btn btn-secondary" href="<?php echo $viewUrl; ?>" target="_blank">View</a>
        <button class="btn btn-secondary editBtn">Settings</button>
        <button class="btn btn-secondary copyBtn">Copy</button>
        <button class="btn btn-secondary togglePublishBtn">
            <?php echo !empty($p['published']) ? 'Unpublish' : 'Publish'; ?>
        </button>
        <button class="btn btn-danger deleteBtn">Delete</button>
    </td>
</tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="pageModal" class="modal">
                        <div class="modal-content">
                            <button class="close-btn" id="closePageModal" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                            <div class="modal-header">
                                <div class="modal-title" id="formTitle">Add New Page</div>
                            </div>
                            <form id="pageForm">
                                <input type="hidden" name="id" id="pageId">
                                <input type="hidden" name="content" id="content">
                                <div id="pageTabs">
                                    <ul>
                                        <li><a href="#tab-settings">Page Settings</a></li>
                                        <li><a href="#tab-seo">SEO Options</a></li>
                                        <li><a href="#tab-og">Social Open Graph</a></li>
                                    </ul>
                                    <div id="tab-settings">
                                        <div class="form-group">
                                            <label class="form-label">Title</label>
                                            <input type="text" class="form-input" name="title" id="title" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Slug</label>
                                            <input type="text" class="form-input" name="slug" id="slug" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label"><input type="checkbox" name="published" id="published"> Published</label>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Template</label>
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
                                            <label class="form-label">Access</label>
                                            <select class="form-select" name="access" id="access">
                                                <option value="public">Public</option>
                                                <option value="private">Private</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="tab-seo">
                                        <div class="form-group">
                                            <label class="form-label">Meta Title</label>
                                            <input type="text" class="form-input" name="meta_title" id="meta_title">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Meta Description</label>
                                            <textarea class="form-textarea" name="meta_description" id="meta_description" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div id="tab-og">
                                        <div class="form-group">
                                            <label class="form-label">OG Title</label>
                                            <input type="text" class="form-input" name="og_title" id="og_title">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">OG Description</label>
                                            <textarea class="form-textarea" name="og_description" id="og_description" rows="3"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">OG Image URL</label>
                                            <input type="text" class="form-input" name="og_image" id="og_image">
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:10px; margin-top:15px;">
                                    <button type="submit" class="btn btn-primary">Save Page</button>
                                    <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

