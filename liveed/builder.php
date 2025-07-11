<?php
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
$blocksDir = __DIR__ . '/../theme/templates/blocks';
$blocks = [];
if (is_dir($blocksDir)) {
    foreach (scandir($blocksDir) as $f) {
        if (substr($f, -4) === '.php') $blocks[] = $f;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit <?php echo htmlspecialchars($page['title']); ?></title>
    <link rel="stylesheet" href="<?php echo $scriptBase; ?>/liveed/builder.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
</head>
<body>
<header class="builder-header">
    <span class="title">Editing: <?php echo htmlspecialchars($page['title']); ?></span>
    <button id="saveBtn" class="btn btn-primary">Save</button>
</header>
<div class="builder">
    <aside class="block-palette">
        <h2>Blocks</h2>
        <div class="palette-items">
        <?php foreach ($blocks as $b): ?>
            <div class="block-item" draggable="true" data-file="<?php echo htmlspecialchars($b); ?>">
                <?php echo htmlspecialchars(basename($b, '.php')); ?>
            </div>
        <?php endforeach; ?>
        </div>
    </aside>
    <main class="canvas-container">
        <div id="canvas" class="canvas">
            <?php echo $page['content'] ?: '<div class="canvas-placeholder">Drag blocks here</div>'; ?>
        </div>
    </main>
    <div id="settingsPanel" class="settings-panel">
        <div class="settings-content"></div>
    </div>
</div>
<script>
window.builderPageId = <?php echo json_encode($page['id']); ?>;
window.builderBase = <?php echo json_encode($scriptBase); ?>;
</script>
<script type="module" src="<?php echo $scriptBase; ?>/liveed/builder.js"></script>
</body>
</html>
