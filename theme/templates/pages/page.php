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
        <?php include __DIR__ . '/../partials/footer.php'; ?>
