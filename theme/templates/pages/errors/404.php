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

require_once __DIR__ . '/../../partials/menu.php';
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
