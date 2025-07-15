<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$totalViews = 0;
foreach ($pages as $p) {
    $totalViews += $p['views'] ?? 0;
}
?>
<div class="content-section" id="analytics">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Analytics</div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon views">📈</div>
                    <div class="stat-content">
                        <div class="stat-label">Total Views</div>
                        <div class="stat-number"><?php echo $totalViews; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <table class="data-table" id="analyticsTable">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Views</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
