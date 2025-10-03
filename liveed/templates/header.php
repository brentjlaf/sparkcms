<?php
// File: liveed/templates/header.php
/** @var string $pageTitle */
$toolbarHtml = render_partial(__DIR__ . '/toolbar.php');
?>
<header class="builder-header" title="Drag to reposition">
    <div class="title-top">
        <div class="title">Editing: <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></div>
        <button type="button" class="manual-save-btn btn btn-primary">
            <i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i>
            <span class="btn-label">Save</span>
        </button>
    </div>
    <div id="saveStatus" class="save-status"></div>
    <?php echo $toolbarHtml; ?>
</header>
