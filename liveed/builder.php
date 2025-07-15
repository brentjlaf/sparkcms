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
$themeHtml = preg_replace('/<div class="drop-area"><\\/div>/', '<div id="canvas" class="canvas">' . $canvasContent . '</div>', $themeHtml);

$headInject = "<link rel=\"stylesheet\" href=\"{$scriptBase}/liveed/builder.css\">" .
    "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css\"/>";
$themeHtml = preg_replace('/<head>/', '<head>' . $headInject, $themeHtml, 1);

$previewToolbar = '<div class="preview-toolbar">'
    . '<button type="button" class="preview-btn active" data-size="desktop" title="Desktop"><i class="fa-solid fa-desktop"></i></button>'
    . '<button type="button" class="preview-btn" data-size="tablet" title="Tablet"><i class="fa-solid fa-tablet-screen-button"></i></button>'
    . '<button type="button" class="preview-btn" data-size="phone" title="Phone"><i class="fa-solid fa-mobile-screen-button"></i></button>'
    . '</div>';

$builderHeader = '<header class="builder-header" title="Drag to reposition">'
    . '<div class="header-actions">'
    . '<button type="button" class="header-btn palette-toggle-btn" title="Collapse Palette"><i class="fa-solid fa-chevron-left"></i></button>'
    . '<button type="button" class="header-btn palette-dock-btn" title="Dock palette"><i class="fa-solid fa-up-down-left-right"></i></button>'
    . '</div>'
    . '<div  class="title-top"><div class="title">Editing: ' . htmlspecialchars($page['title']) . '</div> '
    . '<button type="button" class="manual-save-btn btn btn-primary">Save</button></div>'
    . '<div id="saveStatus" class="save-status"></div>'
    . '<div id="a11yStatus" class="a11y-status" title=""></div>'
    . $previewToolbar
    . '</header>';

$paletteFooter = '<div class="footer"><div class="action-row">'
    . '<button class="action-btn undo-btn"><i class="fas fa-undo"></i><span>Undo</span></button>'
    . '<button class="action-btn page-history-btn"><i class="fas fa-clock-rotate-left"></i><span>History</span></button>'
    . '<button class="action-btn redo-btn"><i class="fas fa-redo"></i><span>Redo</span></button>'
    . '</div><div class="action-row checker-row">'
    . '<button class="action-btn link-check-btn"><i class="fas fa-link"></i><span>Links</span></button>'
    . '<button class="action-btn seo-check-btn"><i class="fas fa-chart-line"></i><span>SEO</span></button>'
    . '<button class="action-btn a11y-check-btn"><i class="fas fa-universal-access"></i><span>A11y</span></button>'
    . '</div><div class="footer-links">'
    . '<span id="lastSavedTime" class="last-saved-time">Last saved: ' . date('Y-m-d H:i', $page['last_modified']) . '</span>'
    . '</div></div>';

$builderStart = '<div class="builder"><button type="button" id="viewModeToggle" class="view-toggle" title="View mode"><i class="fa-solid fa-eye"></i></button><aside class="block-palette">'
    . $builderHeader
    . '<h2 class="blocks-title">Blocks</h2><div class="palette-search-container"><i class="fa-solid fa-search search-icon"></i><input type="text" class="palette-search" placeholder="Search blocks"></div><div class="palette-items"></div>'
    . $paletteFooter
    . '</aside><main class="canvas-container">';

$mediaPickerHtml = '<div id="mediaPickerModal" class="modal">'
    . '<div class="modal-content media-picker">'
    . '<div class="picker-sidebar"><ul id="pickerFolderList"></ul></div>'
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
    . '<div id="historyPanel" class="history-panel"><div class="history-header"><span class="title">Page History</span><button type="button" class="close-btn">&times;</button></div><div class="history-content"></div></div>'
    . $mediaPickerHtml . '</div>'
    . '<script>window.builderPageId = ' . json_encode($page['id']) . ';window.builderBase = ' . json_encode($scriptBase) . ';</script>'
    . '<script type="module" src="' . $scriptBase . '/liveed/builder.js"></script>';

$themeHtml = preg_replace('/<body([^>]*)>/', '<body$1>' . $builderStart, $themeHtml, 1);
$themeHtml = preg_replace('/<\/body>/', $builderEnd . '</body>', $themeHtml, 1);

echo $themeHtml;
