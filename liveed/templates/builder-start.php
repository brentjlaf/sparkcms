<?php
// File: liveed/templates/builder-start.php
/** @var string $paletteHtml */
?>
<div id="liveed-root" class="builder">
    <button type="button" id="viewModeToggle" class="view-toggle" title="View mode">
        <i class="fa-solid fa-eye"></i>
    </button>
    <?php echo $paletteHtml; ?>
    <main class="canvas-container">
