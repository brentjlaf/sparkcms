<?php
// File: liveed/templates/builder-end.php
/** @var string $modalsHtml */
/** @var string $scriptBase */
/** @var int|string $pageId */
/** @var string $pageSlug */
/** @var int $pageLastModified */
?>
    </main>
    <div id="settingsPanel" class="settings-panel">
        <div class="settings-header">
            <span class="title">Settings</span>
            <button type="button" class="close-btn">&times;</button>
        </div>
        <div class="settings-content"></div>
    </div>
    <div id="historyPanel" class="history-panel">
        <div class="history-header">
            <span class="title">Page History</span>
            <button type="button" class="close-btn">&times;</button>
        </div>
        <div class="history-content"></div>
    </div>
    <?php echo $modalsHtml; ?>
</div>
<script>
    window.builderPageId = <?php echo json_encode($pageId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.builderBase = <?php echo json_encode($scriptBase, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.builderSlug = <?php echo json_encode($pageSlug, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.builderLastModified = <?php echo json_encode($pageLastModified, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script type="module" src="<?php echo htmlspecialchars($scriptBase, ENT_QUOTES, 'UTF-8'); ?>/liveed/builder.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
