<?php
// File: builder.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pagesFile = __DIR__ . '/../CMS/data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$page = null;
foreach ($pages as $p) {
    if ((int)$p['id'] === $id) { $page = $p; break; }
}
if (!$page) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -7) === '/liveed') {
    $scriptBase = substr($scriptBase, 0, -7);
}
$themeBase = $scriptBase . '/theme';

// Load settings and menus for the theme template
$settingsFile = __DIR__ . '/../CMS/data/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$menusFile = __DIR__ . '/../CMS/data/menus.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];

// Render the theme page template with a canvas placeholder
$templateFile = realpath(__DIR__ . '/../theme/templates/pages/page.php');
ob_start();
include $templateFile;
$themeHtml = ob_get_clean();
$placeholderText = !empty($settings['canvas_placeholder'])
    ? htmlspecialchars($settings['canvas_placeholder'])
    : 'Drag blocks from the palette to start building your page';
$canvasContent = $page['content'] ?: '<div class="canvas-placeholder">' . $placeholderText . '</div>';
$themeHtml = preg_replace('/<mwPageArea[^>]*><\\/mwPageArea>/', '<div id="canvas" class="canvas">' . $canvasContent . '</div>', $themeHtml);

$headInject = "<link rel=\"stylesheet\" href=\"{$scriptBase}/liveed/builder.css\">" .
    "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css\"/>";
$themeHtml = preg_replace('/<head>/', '<head>' . $headInject, $themeHtml, 1);

$builderHeader = '<header class="builder-header" title="Drag to reposition"><span class="title">Editing: ' . htmlspecialchars($page['title']) . '</span>'
    . '<div class="header-actions"><button type="button" class="palette-toggle-btn" title="Collapse Palette"><i class="fa-solid fa-chevron-left"></i></button>'
    . '<button type="button" class="palette-dock-btn" title="Dock palette"><i class="fa-solid fa-up-down-left-right"></i></button>'
    . '<button type="button" class="manual-save-btn btn btn-primary">Save</button>'
    . '<span id="saveStatus" class="save-status"></span>'
    . '<span id="a11yStatus" class="a11y-status"></span></div></header>';
$historyToolbar = '<div class="history-toolbar">'
    . '<button type="button" class="undo-btn" title="Undo"><i class="fa-solid fa-rotate-left"></i></button>'
    . '<button type="button" class="redo-btn" title="Redo"><i class="fa-solid fa-rotate-right"></i></button>'
    . '</div>';
$previewToolbar = '<div class="preview-toolbar">'
    . '<button type="button" data-size="desktop" class="active" title="Desktop"><i class="fa-solid fa-desktop"></i></button>'
    . '<button type="button" data-size="tablet" title="Tablet"><i class="fa-solid fa-tablet-screen-button"></i></button>'
    . '<button type="button" data-size="phone" title="Phone"><i class="fa-solid fa-mobile-screen-button"></i></button>'
    . '<button type="button" id="gridToggle" title="Toggle Grid"><i class="fa-solid fa-border-all"></i></button>'
    . '</div>';
$builderStart = '<div class="builder"><button type="button" id="viewModeToggle" class="view-toggle" title="View mode"><i class="fa-solid fa-eye"></i></button><aside class="block-palette">'
    . $builderHeader
    . $historyToolbar
    . $previewToolbar
    . '<h2>Blocks</h2><div class="palette-search-container"><input type="text" class="palette-search" placeholder="Search blocks"></div><div class="palette-items"></div></aside><main class="canvas-container">';

$mediaPickerHtml = '<div id="mediaPickerModal" class="modal">'
    . '<div class="modal-content media-picker">'
    . '<div class="picker-sidebar"><ul id="pickerFolderList"></ul>'
    . '<div id="pickerUploadDrop">Drop images here or click to upload</div>'
    . '<input type="file" id="pickerFileInput" multiple accept="image/*"></div>'
    . '<div class="picker-main"><div id="pickerImageGrid" class="picker-grid"></div>'
    . '<div class="modal-footer"><button type="button" class="btn btn-secondary" id="mediaPickerClose">Close</button></div>'
    . '</div></div></div>'
    . '<div id="pickerEditModal" class="modal">'
    . '<div class="modal-content"><div class="crop-container"><img id="pickerEditImage" src="" style="max-width:100%;"></div>'
    . '<div class="modal-footer"><input type="range" id="pickerScale" min="0.5" max="3" step="0.1" value="1">'
    . '<button class="btn btn-secondary" id="pickerEditCancel">Cancel</button>'
    . '<button class="btn btn-primary" id="pickerEditSave">Save</button></div>'
    . '</div></div>'
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">'
    . '<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>';

$builderEnd = '</main><div id="settingsPanel" class="settings-panel"><div class="settings-header"><span class="title">Settings</span><button type="button" class="close-btn">&times;</button></div><div class="settings-content"></div></div>'
    . $mediaPickerHtml . '</div>'
    . '<script>window.builderPageId = ' . json_encode($page['id']) . ';window.builderBase = ' . json_encode($scriptBase) . ';</script>'
    . '<script type="module" src="' . $scriptBase . '/liveed/builder.js"></script>';

$themeHtml = preg_replace('/<body([^>]*)>/', '<body$1>' . $builderStart, $themeHtml, 1);
$themeHtml = preg_replace('/<\/body>/', $builderEnd . '</body>', $themeHtml, 1);

echo $themeHtml;
