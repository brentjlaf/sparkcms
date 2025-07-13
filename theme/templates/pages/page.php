<?php
// File: page.php
// Template: page
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

function renderMenu($items){
    foreach ($items as $it) {
        echo '<li>';
        echo '<a href="'.htmlspecialchars($it['link']).'"'.(!empty($it['new_tab']) ? ' target="_blank"' : '').'>'.htmlspecialchars($it['label']).'</a>';
        if (!empty($it['children'])) {
            echo '<ul>';
            renderMenu($it['children']);
            echo '</ul>';
        }
        echo '</li>';
    }
}

function renderFooterMenu($items){
    foreach ($items as $it) {
        echo '<li>';
        echo '<a href="'.htmlspecialchars($it['link']).'"'.(!empty($it['new_tab']) ? ' target="_blank"' : '').'>'.htmlspecialchars($it['label']).'</a>';
        echo '</li>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
        <head>

		<!-- Metas & Morweb CMS Assets -->
 <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
		
		

		<!-- Favicon -->
                <link rel="shortcut icon" href="<?php echo $themeBase; ?>/images/favicon.png" type="image/x-icon"/>

		
		
		<!-- Fonts -->
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,600;0,700;1,400&family=PT+Serif:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

		<!-- Preload Vendor Stylesheets -->
		<link rel="preload" as="style" crossorigin="anonymous" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>

		<!-- Vendor Stylesheets -->
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
		<link nocache="nocache" rel="stylesheet" crossorigin="anonymous" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>

                <!-- Preload Stylesheet -->
                <link rel="preload" as="style" href="<?php echo $themeBase; ?>/css/skin.css?v=mw3.2"/>

                <!-- Stylesheets -->
                <link nocache="nocache" rel="stylesheet" href="<?php echo $themeBase; ?>/css/root.css?v=mw3.2"/>
                <link nocache="nocache" rel="stylesheet" href="<?php echo $themeBase; ?>/css/skin.css?v=mw3.2"/>
                <link nocache="nocache" rel="stylesheet" href="<?php echo $themeBase; ?>/css/override.css?v=mw3.2"/>
	</head>
	<body>

		<!-- Default Page -->
		<div id="app" class="page-template default-page">

                        <!-- Header -->
                        <header class="site-header">
                                <div class="container header-inner">
                                        <a href="<?php echo $scriptBase; ?>/" class="logo">
                                                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
                                        </a>
                                        <nav class="main-nav" id="main-nav">
                                                <ul>
                                                        <?php renderMenu($mainMenu); ?>
                                                </ul>
                                        </nav>
                                        <form class="search-box" action="<?php echo $scriptBase; ?>/search" method="get">
                                                <input type="text" name="q" class="search-input" placeholder="Search..." />
                                                <button type="submit" class="search-icon" aria-label="Search">
                                                        <i class="fa-solid fa-search"></i>
                                                </button>
                                        </form>
                                        <a href="<?php echo $scriptBase; ?>/contact-us" class="cta-btn">Contact Us</a>
                                        <button class="nav-toggle" aria-label="Toggle Menu"><i class="fa-solid fa-bars"></i></button>
                                </div>
                        </header>

                        <!-- Main -->
                        <main id="main-area">
                                <div class="drop-area"></div>
                        </main>

                        <!-- Footer -->
                        <footer id="footer-area" class="site-footer">
                                <div class="container footer-main">
                                        <a href="<?php echo $scriptBase; ?>/" class="logo"><img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo"></a>
                                        <nav class="footer-menu">
                                                <ul>
                                                        <?php renderFooterMenu($footerMenu); ?>
                                                </ul>
                                        </nav>
                                        <div class="footer-social">
                                                <?php if (!empty($social['facebook'])): ?>
                                                <a href="<?php echo htmlspecialchars($social['facebook']); ?>" aria-label="Facebook" target="_blank"><i class="fa-brands fa-facebook-f"></i></a>
                                                <?php endif; ?>
                                                <?php if (!empty($social['twitter'])): ?>
                                                <a href="<?php echo htmlspecialchars($social['twitter']); ?>" aria-label="Twitter" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                                                <?php endif; ?>
                                                <?php if (!empty($social['instagram'])): ?>
                                                <a href="<?php echo htmlspecialchars($social['instagram']); ?>" aria-label="Instagram" target="_blank"><i class="fa-brands fa-instagram"></i></a>
                                                <?php endif; ?>
                                        </div>
                                </div>
                                <div class="footer-copy">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?></div>
                        </footer>

			<!-- Back to Top Button -->
			<button id="back-to-top-btn" class="_js-scroll-top" aria-label="Back to Top">
    <span>
        <span>
            <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
        </span>
    </span>
    <span>Back to Top</span>
</button>

		</div>

		<!-- Vendor Javascript -->

		<!-- Javascript -->
                <script src="<?php echo $themeBase; ?>/js/global.js?v=mw3.2"></script>
                <script src="<?php echo $themeBase; ?>/js/script.js?v=mw3.2"></script>

		
		
		
		
		
	</body>
</html>
