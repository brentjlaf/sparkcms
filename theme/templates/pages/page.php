<?php
// File: page.php
// Template: page
// Variables provided by index.php: $settings, $menus, $page, $scriptBase, $themeBase
$siteName = $settings['site_name'] ?? 'My Site';
$tagline = $settings['tagline'] ?? 'Welcome';
if (!empty($settings['logo'])) {
    $logo = $scriptBase . '/CMS/' . ltrim($settings['logo'], '/');
} else {
    $logo = $themeBase . '/images/logo.png';
}
$mainMenu = $menus[0]['items'] ?? [];
$primaryMenuItems = array_slice($mainMenu, 0, 5);
$overflowMenuItems = array_slice($mainMenu, 5);
$footerMenu = $menus[1]['items'] ?? [];
$social = $settings['social'] ?? [];

$headExtra = $page['head'] ?? ($page['head_extra'] ?? '');
$bodyAttributes = 'class="d-flex flex-column min-vh-100"';

require_once __DIR__ . '/../partials/menu.php';
?>
<?php include __DIR__ . "/../partials/head.php"; ?>

    <!-- Default Page -->
    <div id="app" class="page-template default-page d-flex flex-column min-vh-100">

        <!-- Header -->
        <header class="site-header" role="banner">
            <div class="container header-inner">
                <!-- Brand/Logo -->
                <a class="logo d-inline-flex align-items-center" href="<?php echo $scriptBase; ?>/">
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
                    <span class="logo-text ms-2"><?php echo htmlspecialchars($siteName); ?></span>
                </a>

                <!-- Mobile Toggle Button -->
                <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>

                <!-- Navigation -->
                <nav class="main-nav" id="main-nav" role="navigation">
                    <ul>
                        <?php renderMenu($primaryMenuItems); ?>
                        <?php if (!empty($overflowMenuItems)): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="more-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    More
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="more-menu">
                                    <?php renderMenu($overflowMenuItems, true); ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>

                <div class="header-actions">
                    <!-- Search Form -->
                    <form class="search-box" action="<?php echo $scriptBase; ?>/search" method="get" role="search">
                        <label class="visually-hidden" for="header-search">Search</label>
                        <input id="header-search" class="search-input" type="search" name="q" placeholder="Search..." aria-label="Search">
                        <button class="search-icon" type="submit" aria-label="Submit search">
                            <i class="fas fa-search" aria-hidden="true"></i>
                        </button>
                    </form>

                    <!-- Contact Button -->
                    <a href="<?php echo $scriptBase; ?>/contact-us" class="cta-btn">
                        <span class="cta-label">Contact Us</span>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main id="main-area" class="flex-grow-1">
            <section class="page-hero">
                <div class="container">
                    <div class="hero-content">
                        <p class="eyebrow"><?php echo htmlspecialchars($tagline); ?></p>
                    </div>
                </div>
            </section>
            <div class="content-wrapper">
                <div class="container">
                    <header class="page-header">
                        <h1 class="page-title"><?php echo htmlspecialchars($page['title'] ?? $siteName); ?></h1>
                        <?php if (!empty($page['summary'])): ?>
                            <p class="page-subtitle"><?php echo htmlspecialchars($page['summary']); ?></p>
                        <?php endif; ?>
                    </header>
                    <div class="drop-area"></div>
                </div>
            </div>
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
                            <a href="<?php echo htmlspecialchars($social['facebook']); ?>" aria-label="Facebook" target="_blank">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['twitter'])): ?>
                            <a href="<?php echo htmlspecialchars($social['twitter']); ?>" aria-label="Twitter" target="_blank">
                                <i class="fab fa-x-twitter"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['instagram'])): ?>
                            <a href="<?php echo htmlspecialchars($social['instagram']); ?>" aria-label="Instagram" target="_blank">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['linkedin'])): ?>
                            <a href="<?php echo htmlspecialchars($social['linkedin']); ?>" aria-label="LinkedIn" target="_blank">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['youtube'])): ?>
                            <a href="<?php echo htmlspecialchars($social['youtube']); ?>" aria-label="YouTube" target="_blank">
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
        <button id="back-to-top-btn" aria-label="Back to Top" hidden aria-hidden="true">
            <span>
                <span>
                    <i class="fas fa-chevron-up" aria-hidden="true"></i>
                </span>
            </span>
            <span class="visually-hidden">Back to Top</span>
        </button>

    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <?php
        $bundleDirectory = dirname(__DIR__, 2) . '/js/dist';
        $bundleFiles = [
            'global' => 'global.bundle.js',
            'script' => 'script.bundle.js',
        ];
        $bundleVersions = [];
        foreach ($bundleFiles as $key => $fileName) {
            $filePath = $bundleDirectory . '/' . $fileName;
            $bundleVersions[$key] = is_file($filePath) ? (string) filemtime($filePath) : '1';
        }
    ?>
    <script>window.cmsBase = <?php echo json_encode($scriptBase); ?>;</script>
    <script src="<?php echo $themeBase; ?>/js/dist/<?php echo $bundleFiles['global']; ?>?v=<?php echo $bundleVersions['global']; ?>"></script>
    <script src="<?php echo $themeBase; ?>/js/dist/<?php echo $bundleFiles['script']; ?>?v=<?php echo $bundleVersions['script']; ?>"></script>

</body>
</html>
