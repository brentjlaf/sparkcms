<?php
// File: 404.php
// Template: errors/404
// Variables provided by index.php: $settings, $menus, $page, $scriptBase, $themeBase
$siteName = $settings['site_name'] ?? 'My Site';
if (!empty($settings['logo'])) {
    $logo = $scriptBase . '/CMS/' . ltrim($settings['logo'], '/');
} else {
    $logo = $themeBase . '/images/logo.png';
}
$mainMenu = $menus[0]['items'] ?? [];
$footerMenu = $menus[1]['items'] ?? [];
$social = $settings['social'] ?? [];

function renderMenu($items, $isDropdown = false){
    foreach ($items as $it) {
        $hasChildren = !empty($it['children']);
        if ($hasChildren) {
            echo '<li class="nav-item dropdown">';
            echo '<a class="nav-link dropdown-toggle" href="'.htmlspecialchars($it['link']).'"'.(!empty($it['new_tab']) ? ' target="_blank"' : '').' role="button" data-bs-toggle="dropdown" aria-expanded="false">'.htmlspecialchars($it['label']).'</a>';
            echo '<ul class="dropdown-menu">';
            renderMenu($it['children'], true);
            echo '</ul>';
        } else {
            echo '<li class="nav-item'.($isDropdown ? '' : '').'">';
            echo '<a class="nav-link" href="'.htmlspecialchars($it['link']).'"'.(!empty($it['new_tab']) ? ' target="_blank"' : '').'>'.htmlspecialchars($it['label']).'</a>';
        }
        echo '</li>';
    }
}

function renderFooterMenu($items){
    foreach ($items as $it) {
        echo '<li class="nav-item">';
        echo '<a class="nav-link px-2" href="'.htmlspecialchars($it['link']).'"'.(!empty($it['new_tab']) ? ' target="_blank"' : '').'>'.htmlspecialchars($it['label']).'</a>';
        echo '</li>';
    }
}
?>
<?php include __DIR__ . "/../../partials/head.php"; ?>


		<!-- Default Page -->
		<div id="app" class="page-template default-page">

                        <!-- Header -->
                        <!-- Header moved to block palette -->

                        <!-- Main -->
                        <main id="main-area">
                                <div class="drop-area"></div>
                        </main>

                        <!-- Footer -->
                        <?php include __DIR__ . "/../../partials/footer.php"; ?>
