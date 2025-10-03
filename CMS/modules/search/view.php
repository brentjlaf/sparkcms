<?php
// File: view.php
if (!isset($context) || !is_array($context)) {
    require_once __DIR__ . '/SearchController.php';
    $controller = new SearchController(new SearchService());
    $context = $controller->handle($_GET);
}

$query = (string) ($context['query'] ?? '');
$results = $context['results'] ?? [];
$typeCounts = $context['type_counts'] ?? ['Page' => 0, 'Post' => 0, 'Media' => 0];
$resultSummary = $context['result_summary'] ?? 'Showing 0 results';
$historyRecords = $context['history'] ?? [];
$suggestions = $context['suggestions'] ?? [];
$selectedTypes = $context['selected_types'] ?? [];

$querySuffix = $query !== ''
    ? ' for &ldquo;' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '&rdquo;'
    : '';

$historyDataAttr = htmlspecialchars(json_encode($historyRecords, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$suggestionsAttr = htmlspecialchars(json_encode($suggestions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$selectedTypesAttr = htmlspecialchars(json_encode($selectedTypes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="content-section" id="search" data-query="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" data-history="<?php echo $historyDataAttr; ?>" data-suggestions="<?php echo $suggestionsAttr; ?>" data-selected-types="<?php echo $selectedTypesAttr; ?>">
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
                        <?php echo htmlspecialchars($resultSummary, ENT_QUOTES, 'UTF-8') . $querySuffix; ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid search-overview-grid">
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Pages</div>
                    <div class="a11y-overview-value" id="searchCountPages"><?php echo number_format($typeCounts['Page'] ?? 0); ?></div>
                </div>
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Posts</div>
                    <div class="a11y-overview-value" id="searchCountPosts"><?php echo number_format($typeCounts['Post'] ?? 0); ?></div>
                </div>
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Media</div>
                    <div class="a11y-overview-value" id="searchCountMedia"><?php echo number_format($typeCounts['Media'] ?? 0); ?></div>
                </div>
            </div>
        </header>

        <?php if (!empty($historyRecords)): ?>
        <section class="search-history-card" aria-labelledby="searchHistoryTitle">
            <header class="search-history-card__header">
                <div>
                    <h3 class="search-history-card__title" id="searchHistoryTitle">Recent searches</h3>
                    <p class="search-history-card__description">Jump back to your frequent queries.</p>
                </div>
            </header>
            <div class="search-history-chips">
                <?php foreach ($historyRecords as $history): ?>
                    <button class="search-history-chip" type="button" data-search-term="<?php echo htmlspecialchars($history['term'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="search-history-chip__term"><?php echo htmlspecialchars($history['term'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="search-history-chip__meta"><?php echo number_format((int) ($history['count'] ?? 0)); ?>×</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="a11y-detail-card search-results-card">
            <header class="search-results-card__header">
                <div class="search-results-card__intro">
                    <h3 class="search-results-card__title">Search results</h3>
                    <p class="search-results-card__description">Review matches across pages, posts, and media.</p>
                </div>
                <span class="search-results-card__meta"><?php echo htmlspecialchars($resultSummary, ENT_QUOTES, 'UTF-8') . $querySuffix; ?></span>
            </header>
            <div class="search-filters" role="group" aria-label="Filter by content type">
                <?php foreach ($typeCounts as $typeLabel => $count):
                    $lowerType = strtolower($typeLabel);
                    $isChecked = empty($selectedTypes) || in_array($lowerType, $selectedTypes, true);
                ?>
                <label class="search-filters__option">
                    <input type="checkbox" value="<?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                    <span><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?> <small>(<?php echo number_format($count); ?>)</small></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="search-results-table">
                <table class="data-table search-table">
                    <thead>
                        <tr><th scope="col">Type</th><th scope="col">Title</th><th scope="col">Slug</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($results)): ?>
                            <?php foreach ($results as $r): ?>
                                <?php
                                    $record = $r['record'] ?? [];
                                    $type = $r['type'] ?? '';
                                    if ($type === 'Post') {
                                        $viewUrl = '../' . urlencode($record['slug'] ?? ($r['slug'] ?? ''));
                                        $status = ucfirst($record['status'] ?? 'draft');
                                    } elseif ($type === 'Media') {
                                        $viewUrl = '../' . ltrim($record['file'] ?? ($r['slug'] ?? ''), '/');
                                        $size = $record['size'] ?? 0;
                                        $status = $size ? round($size / 1024) . ' KB' : '';
                                    } else {
                                        $viewUrl = '../?page=' . urlencode($record['slug'] ?? ($r['slug'] ?? ''));
                                        if (isset($_SESSION['user'])) {
                                            $viewUrl = '../liveed/builder.php?id=' . urlencode($record['id'] ?? ($r['id'] ?? ''));
                                        }
                                        $status = !empty($record['published']) ? 'Published' : 'Draft';
                                    }
                                ?>
                                <tr data-id="<?php echo htmlspecialchars((string) ($r['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>" data-score="<?php echo htmlspecialchars(number_format((float) ($r['score'] ?? 0), 4, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <td><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="search-result-title"><?php echo htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($r['snippet'])): ?>
                                            <div class="search-result-snippet"><?php echo $r['snippet']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><a class="a11y-btn a11y-btn--ghost" href="<?php echo htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>View</span></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="search-empty-row" data-filter-empty="true" style="display:none;"><td colspan="5">No results match the selected filters.</td></tr>
                        <?php else: ?>
                            <tr class="search-empty-row"><td colspan="5">No results found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
