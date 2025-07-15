<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

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
}
?>
<div class="content-section" id="search" data-query="<?php echo htmlspecialchars($q); ?>">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Search Results<?php if($q!=='') echo ' for \''.htmlspecialchars($q).'\''; ?></div>
        </div>
        <table class="data-table">
            <thead>
                <tr><th>Type</th><th>Title</th><th>Slug</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($results): ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                            if(($r['type'] ?? '') === 'Post') {
                                $viewUrl = '../' . urlencode($r['slug']);
                                $status = ucfirst($r['status'] ?? 'draft');
                            } else {
                                $viewUrl = '../?page=' . urlencode($r['slug']);
                                if(isset($_SESSION['user'])) {
                                    $viewUrl = '../liveed/builder.php?id=' . urlencode($r['id']);
                                }
                                $status = !empty($r['published']) ? 'Published' : 'Draft';
                            }
                        ?>
                        <tr data-id="<?php echo $r['id']; ?>">
                            <td><?php echo htmlspecialchars($r['type'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                            <td><?php echo htmlspecialchars($r['slug']); ?></td>
                            <td><?php echo htmlspecialchars($status); ?></td>
                            <td><a class="btn btn-secondary" href="<?php echo $viewUrl; ?>" target="_blank">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No results found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
