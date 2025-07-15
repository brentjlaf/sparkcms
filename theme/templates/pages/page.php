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
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

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
                        <nav class="navbar navbar-expand-lg navbar-light bg-light site-header">
                                <div class="container">
                                        <a class="navbar-brand" href="<?php echo $scriptBase; ?>/">
                                                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="d-inline-block align-text-top">
                                        </a>
                                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
                                                <span class="navbar-toggler-icon"></span>
                                        </button>
                                        <div class="collapse navbar-collapse" id="main-nav">
                                                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                                                        <?php renderMenu($mainMenu); ?>
                                                </ul>
                                                <form class="d-flex me-2" action="<?php echo $scriptBase; ?>/search" method="get">
                                                        <input class="form-control me-2" type="text" name="q" placeholder="Search..." />
                                                        <button class="btn btn-outline-secondary" type="submit" aria-label="Search">
                                                                <i class="fa-solid fa-search"></i>
                                                        </button>
                                                </form>
                                                <a href="<?php echo $scriptBase; ?>/contact-us" class="btn btn-primary">Contact Us</a>
                                        </div>
                                </div>
                        </nav>

                        <!-- Main -->
                        <main id="main-area">
                                <div class="drop-area"></div>
                        </main>

                        <!-- Footer -->
                        <footer id="footer-area" class="site-footer bg-dark text-light mt-auto">
                                <div class="container py-4">
                                        <div class="row align-items-center">
                                                <div class="col-md-4 mb-3 mb-md-0">
                                                        <a href="<?php echo $scriptBase; ?>/" class="navbar-brand"><img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo"></a>
                                                </div>
                                                <div class="col-md-4 mb-3 mb-md-0">
                                                        <ul class="nav justify-content-center">
                                                                <?php renderFooterMenu($footerMenu); ?>
                                                        </ul>
                                                </div>
                                                <div class="col-md-4 text-md-end">
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
                                        </div>
                                        <div class="text-center mt-3 footer-copy">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?></div>
                                </div>
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
                <script>window.cmsBase = <?php echo json_encode($scriptBase); ?>;</script>
                <script src="<?php echo $themeBase; ?>/js/global.js?v=mw3.2"></script>
                <script src="<?php echo $themeBase; ?>/js/script.js?v=mw3.2"></script>
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

		
		
		
		
		
	</body>
</html>
