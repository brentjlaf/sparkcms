<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = read_json_file($postsFile);
$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$lower = strtolower($q);
$results = [];
if ($lower !== '') {
    foreach ($pages as $p) {
        if (stripos($p['title'], $lower) !== false || stripos($p['slug'], $lower) !== false || stripos($p['content'], $lower) !== false) {
            $p['type'] = 'Page';
            $results[] = $p;
        }
    }
    foreach ($posts as $b) {
        if (stripos($b['title'], $lower) !== false || stripos($b['slug'], $lower) !== false || stripos($b['excerpt'], $lower) !== false || stripos($b['content'], $lower) !== false || stripos($b['tags'], $lower) !== false) {
            $b['type'] = 'Post';
            $results[] = $b;
        }
    }
    foreach ($media as $m) {
        $tags = isset($m['tags']) && is_array($m['tags']) ? implode(',', $m['tags']) : '';
        if (stripos($m['name'], $lower) !== false || stripos($m['file'], $lower) !== false || stripos($tags, $lower) !== false) {
            $m['type'] = 'Media';
            $m['title'] = $m['name'];
            $m['slug'] = $m['file'];
            $results[] = $m;
        }
    }
}
$resultCount = count($results);
$resultSummary = $resultCount === 1
    ? 'Showing 1 result'
    : 'Showing ' . number_format($resultCount) . ' results';
$querySuffix = $q !== ''
    ? ' for &ldquo;' . htmlspecialchars($q) . '&rdquo;'
    : '';
$typeCounts = [
    'Page' => 0,
    'Post' => 0,
    'Media' => 0,
];
foreach ($results as $entry) {
    $type = $entry['type'] ?? '';
    if (isset($typeCounts[$type])) {
        $typeCounts[$type]++;
    }
}
?>
<div class="content-section" id="search" data-query="<?php echo htmlspecialchars($q); ?>">
    <div class="search-dashboard a11y-dashboard">
        <header class="a11y-hero search-hero">
            <div class="a11y-hero-content search-hero-content">
                <div>
                    <span class="hero-eyebrow search-hero-eyebrow">Unified Index</span>
                    <h2 class="a11y-hero-title search-hero-title">Unified Search</h2>
                    <p class="a11y-hero-subtitle search-hero-subtitle">Surface pages, posts, and media without leaving the dashboard.</p>
                </div>
                <div class="a11y-hero-actions search-hero-actions">
                    <span class="a11y-hero-meta search-hero-meta">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <?php echo $resultSummary . $querySuffix; ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid search-overview-grid">
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Pages</div>
                    <div class="a11y-overview-value" id="searchCountPages"><?php echo number_format($typeCounts['Page']); ?></div>
                </div>
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Posts</div>
                    <div class="a11y-overview-value" id="searchCountPosts"><?php echo number_format($typeCounts['Post']); ?></div>
                </div>
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Media</div>
                    <div class="a11y-overview-value" id="searchCountMedia"><?php echo number_format($typeCounts['Media']); ?></div>
                </div>
            </div>
        </header>

        <section class="a11y-detail-card table-card search-results-card">
            <header class="table-header search-results-card__header">
                <div class="search-results-card__intro">
                    <h3 class="search-results-card__title">Search results</h3>
                    <p class="search-results-card__description">Review matches across pages, posts, and media.</p>
                </div>
                <span class="table-meta search-results-card__meta"><?php echo $resultSummary . $querySuffix; ?></span>
            </header>
            <div class="table-content search-results-table">
                <table class="data-table search-table">
                    <thead>
                        <tr><th scope="col">Type</th><th scope="col">Title</th><th scope="col">Slug</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($results): ?>
                            <?php foreach ($results as $r): ?>
                                <?php
                                    if(($r['type'] ?? '') === 'Post') {
                                        $viewUrl = '../' . urlencode($r['slug']);
                                        $status = ucfirst($r['status'] ?? 'draft');
                                    } elseif(($r['type'] ?? '') === 'Media') {
                                        $viewUrl = '../' . ltrim($r['file'], '/');
                                        $status = !empty($r['size']) ? round($r['size']/1024).' KB' : '';
                                    } else {
                                        $viewUrl = '../?page=' . urlencode($r['slug']);
                                        if(isset($_SESSION['user'])) {
                                            $viewUrl = '../liveed/builder.php?id=' . urlencode($r['id']);
                                        }
                                        $status = !empty($r['published']) ? 'Published' : 'Draft';
                                    }
                                ?>
                                <?php
                                    $type = $r['type'] ?? '';
                                    $typeLabel = $type !== '' ? $type : 'Unknown';
                                    $iconClass = '';
                                    $icon = 'fa-solid fa-file-lines';
                                    if ($type === 'Page') {
                                        $icon = 'fa-solid fa-file-lines';
                                        $iconClass = 'table-cell__icon--page';
                                    } elseif ($type === 'Post') {
                                        $icon = 'fa-solid fa-newspaper';
                                        $iconClass = 'table-cell__icon--post';
                                    } elseif ($type === 'Media') {
                                        $icon = 'fa-solid fa-image';
                                        $iconClass = 'table-cell__icon--media';
                                    }

                                    $statusClass = 'status-neutral';
                                    $statusLabel = '';

                                    if ($type === 'Post') {
                                        $statusValue = strtolower($r['status'] ?? 'draft');
                                        if ($statusValue === 'published') {
                                            $statusClass = 'status-published';
                                        } elseif ($statusValue === 'scheduled') {
                                            $statusClass = 'status-scheduled';
                                        } else {
                                            $statusClass = 'status-draft';
                                        }
                                        $statusLabel = ucfirst($statusValue);
                                    } elseif ($type === 'Media') {
                                        $statusClass = 'status-info';
                                        $sizeBytes = isset($r['size']) ? (int) $r['size'] : 0;
                                        if ($sizeBytes > 0) {
                                            $sizeKilobytes = max(1, (int) round($sizeBytes / 1024));
                                            $statusLabel = $sizeKilobytes . ' KB';
                                        } else {
                                            $statusLabel = 'Media asset';
                                        }
                                    } else {
                                        $isPublished = !empty($r['published']);
                                        $statusClass = $isPublished ? 'status-published' : 'status-draft';
                                        $statusLabel = $isPublished ? 'Published' : 'Draft';
                                    }

                                    if ($statusLabel === '') {
                                        $statusLabel = 'Unknown';
                                    }
                                ?>
                                <tr data-id="<?php echo $r['id']; ?>">
                                    <td>
                                        <span class="table-cell">
                                            <span class="table-cell__icon <?php echo $iconClass; ?>">
                                                <i class="<?php echo $icon; ?>" aria-hidden="true"></i>
                                            </span>
                                            <span class="table-cell__title"><?php echo htmlspecialchars($typeLabel); ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-cell__title"><?php echo htmlspecialchars($r['title']); ?></span>
                                    </td>
                                    <td><span class="table-cell__subtitle table-cell__slug"><?php echo htmlspecialchars($r['slug']); ?></span></td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($statusLabel); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-actions">
                                            <a class="table-action table-action--ghost" href="<?php echo $viewUrl; ?>" target="_blank" rel="noopener">
                                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                                <span>Open</span>
                                            </a>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No results found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
