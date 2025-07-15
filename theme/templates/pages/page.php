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
        echo '<a class="nav-link text-light px-2" href="'.htmlspecialchars($it['link']).'"'.(!empty($it['new_tab']) ? ' target="_blank"' : '').'>'.htmlspecialchars($it['label']).'</a>';
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

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom Stylesheets -->
    <link rel="stylesheet" href="<?php echo $themeBase; ?>/css/root.css?v=mw3.2"/>
    <link rel="stylesheet" href="<?php echo $themeBase; ?>/css/skin.css?v=mw3.2"/>
    <link rel="stylesheet" href="<?php echo $themeBase; ?>/css/override.css?v=mw3.2"/>
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Default Page -->
    <div id="app" class="page-template default-page d-flex flex-column min-vh-100">

        <!-- Header -->
        <header class="bg-white shadow-sm border-bottom">
            <nav class="navbar navbar-expand-lg navbar-light" role="navigation">
                <div class="container">
                    <!-- Brand/Logo -->
                    <a class="navbar-brand d-flex align-items-center" href="<?php echo $scriptBase; ?>/">
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="d-inline-block align-text-top" style="height: 40px;">
                        <span class="ms-2 fw-bold d-none d-sm-inline"><?php echo htmlspecialchars($siteName); ?></span>
                    </a>
                    
                    <!-- Mobile Toggle Button -->
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <!-- Navigation -->
                    <div class="collapse navbar-collapse" id="main-nav">
                        <!-- Main Menu -->
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <?php renderMenu($mainMenu); ?>
                        </ul>
                        
                        <!-- Search Form -->
                        <form class="d-flex me-3" action="<?php echo $scriptBase; ?>/search" method="get" role="search">
                            <div class="input-group">
                                <input class="form-control" type="search" name="q" placeholder="Search..." aria-label="Search" />
                                <button class="btn btn-outline-secondary" type="submit" aria-label="Search">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Contact Button -->
                        <a href="<?php echo $scriptBase; ?>/contact-us" class="btn btn-primary">
                            <i class="fas fa-envelope me-1"></i>Contact Us
                        </a>
                    </div>
                </div>
            </nav>
        </header>

        <!-- Main Content -->
        <main id="main-area" class="flex-grow-1">
            <div class="drop-area"></div>
        </main>

        <!-- Footer -->
        <footer id="footer-area" class="site-footer mt-auto">
            <div class="container">
                <div class="footer-main">
                    <div>
                        <a href="<?php echo $scriptBase; ?>/" class="navbar-brand d-inline-block mb-3">
                            <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" style="height: 40px;" class="filter-invert">
                        </a>
                        <p class="small opacity-75 mb-3">Your trusted partner for exceptional service and innovative solutions.</p>
                        <div class="footer-social">
                            <?php if (!empty($social['facebook'])): ?>
                            <a href="<?php echo htmlspecialchars($social['facebook']); ?>" class="btn btn-outline-light btn-sm me-2" aria-label="Facebook" target="_blank">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['twitter'])): ?>
                            <a href="<?php echo htmlspecialchars($social['twitter']); ?>" class="btn btn-outline-light btn-sm me-2" aria-label="Twitter" target="_blank">
                                <i class="fab fa-x-twitter"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['instagram'])): ?>
                            <a href="<?php echo htmlspecialchars($social['instagram']); ?>" class="btn btn-outline-light btn-sm me-2" aria-label="Instagram" target="_blank">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['linkedin'])): ?>
                            <a href="<?php echo htmlspecialchars($social['linkedin']); ?>" class="btn btn-outline-light btn-sm me-2" aria-label="LinkedIn" target="_blank">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['youtube'])): ?>
                            <a href="<?php echo htmlspecialchars($social['youtube']); ?>" class="btn btn-outline-light btn-sm" aria-label="YouTube" target="_blank">
                                <i class="fab fa-youtube"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <nav class="footer-menu">
                        <h5 class="text-white mb-3">Quick Links</h5>
                        <ul>
                            <?php renderFooterMenu($footerMenu); ?>
                        </ul>
                    </nav>
                    <div>
                        <h5 class="text-white mb-3">Contact Info</h5>
                        <ul class="list-unstyled">
                            <?php if (!empty($settings['address'])): ?>
                            <li class="mb-2">
                                <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                <span class="text-muted"><?php echo htmlspecialchars($settings['address']); ?></span>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($settings['phone'])): ?>
                            <li class="mb-2">
                                <i class="fas fa-phone me-2 text-primary"></i>
                                <a href="tel:<?php echo htmlspecialchars($settings['phone']); ?>" class="text-muted text-decoration-none"><?php echo htmlspecialchars($settings['phone']); ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($settings['email'])): ?>
                            <li class="mb-2">
                                <i class="fas fa-envelope me-2 text-primary"></i>
                                <a href="mailto:<?php echo htmlspecialchars($settings['email']); ?>" class="text-muted text-decoration-none"><?php echo htmlspecialchars($settings['email']); ?></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="footer-copy d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <p class="mb-2 mb-md-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. All rights reserved.</p>
                    <ul class="nav">
                        <li class="nav-item">
                            <a class="nav-link text-muted px-2" href="<?php echo $scriptBase; ?>/privacy-policy">Privacy Policy</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-muted px-2" href="<?php echo $scriptBase; ?>/terms-of-service">Terms of Service</a>
                        </li>
                    </ul>
                </div>
            </div>
        </footer>

        <!-- Back to Top Button -->
        <button id="back-to-top-btn" class="btn btn-primary position-fixed shadow" style="bottom: 20px; right: 20px; z-index: 1000; display: none;" aria-label="Back to Top">
            <i class="fas fa-chevron-up"></i>
        </button>

    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>window.cmsBase = <?php echo json_encode($scriptBase); ?>;</script>
    <script src="<?php echo $themeBase; ?>/js/combined.js?v=mw3.2"></script>
    
    <!-- Back to Top Button Script -->
    <script>
        // Show/hide back to top button
        window.addEventListener('scroll', function() {
            const backToTopBtn = document.getElementById('back-to-top-btn');
            if (window.scrollY > 100) {
                backToTopBtn.style.display = 'block';
            } else {
                backToTopBtn.style.display = 'none';
            }
        });
        
        // Smooth scroll to top
        document.getElementById('back-to-top-btn').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>

</body>
</html>
