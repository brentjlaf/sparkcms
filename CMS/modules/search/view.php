<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$lower = strtolower($q);
$results = [];
if ($lower !== '') {
    foreach ($pages as $p) {
        if (stripos($p['title'], $lower) !== false || stripos($p['slug'], $lower) !== false || stripos($p['content'], $lower) !== false) {
            $results[] = $p;
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
                <tr><th>Title</th><th>Slug</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($results): ?>
                    <?php foreach ($results as $r): ?>
                        <?php $viewUrl = '../?page=' . urlencode($r['slug']);
                              if(isset($_SESSION['user'])) {
                                  $viewUrl = '../liveed/builder.php?id=' . urlencode($r['id']);
                              }
                        ?>
                        <tr data-id="<?php echo $r['id']; ?>">
                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                            <td><?php echo htmlspecialchars($r['slug']); ?></td>
                            <td><?php echo !empty($r['published']) ? 'Published' : 'Draft'; ?></td>
                            <td><a class="btn btn-secondary" href="<?php echo $viewUrl; ?>" target="_blank">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No results found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
