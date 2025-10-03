<?php
// File: liveed/templates/palette.php
/** @var string $pageTitle */
/** @var string $lastSavedLabel */
$headerHtml = render_partial(__DIR__ . '/header.php', [
    'pageTitle' => $pageTitle,
]);
?>
<aside class="block-palette">
    <?php echo $headerHtml; ?>
    <h2 class="blocks-title">Blocks</h2>
    <div class="palette-search-container">
        <i class="fa-solid fa-search search-icon"></i>
        <input type="text" class="palette-search" placeholder="Search blocks">
    </div>
    <div class="palette-items"></div>
    <div class="footer">
        <div class="action-row">
            <button class="action-btn undo-btn">
                <i class="fas fa-undo"></i><span>Undo</span>
            </button>
            <button class="action-btn page-history-btn">
                <i class="fas fa-clock-rotate-left"></i><span>History</span>
            </button>
            <button class="action-btn redo-btn">
                <i class="fas fa-redo"></i><span>Redo</span>
            </button>
        </div>
        <div class="footer-links">
            <span id="lastSavedTime" class="last-saved-time">
                Last saved: <?php echo htmlspecialchars($lastSavedLabel, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>
</aside>
