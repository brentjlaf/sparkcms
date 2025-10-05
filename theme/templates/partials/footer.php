<?php
if (!function_exists('renderFooterSocialLinks')) {
    function renderFooterSocialLinks(array $social): void
    {
        $platforms = [
            'facebook' => ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f'],
            'twitter' => ['label' => 'Twitter', 'icon' => 'fab fa-x-twitter'],
            'instagram' => ['label' => 'Instagram', 'icon' => 'fab fa-instagram'],
            'linkedin' => ['label' => 'LinkedIn', 'icon' => 'fab fa-linkedin-in'],
            'youtube' => ['label' => 'YouTube', 'icon' => 'fab fa-youtube'],
        ];

        foreach ($platforms as $key => $meta) {
            $url = trim((string)($social[$key] ?? ''));
            if ($url === '') {
                continue;
            }

            echo '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                . ' aria-label="' . htmlspecialchars($meta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                . ' target="_blank" rel="noopener">'
                . '<i class="' . htmlspecialchars($meta['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></i>'
                . '</a>';
        }
    }
}

if (!function_exists('renderFooterContactItems')) {
    function renderFooterContactItems(array $settings): void
    {
        $items = [
            'address' => [
                'icon' => 'fas fa-map-marker-alt',
                'render' => function (string $value): string {
                    return '<span class="text-muted">' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
                },
            ],
            'phone' => [
                'icon' => 'fas fa-phone',
                'render' => function (string $value): string {
                    $tel = preg_replace('/[^0-9+]/', '', $value);
                    return '<a href="tel:' . htmlspecialchars($tel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                        . ' class="text-muted text-decoration-none">'
                        . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</a>';
                },
            ],
            'email' => [
                'icon' => 'fas fa-envelope',
                'render' => function (string $value): string {
                    return '<a href="mailto:' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                        . ' class="text-muted text-decoration-none">'
                        . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</a>';
                },
            ],
        ];

        foreach ($items as $key => $config) {
            $rawValue = trim((string)($settings[$key] ?? ''));
            if ($rawValue === '') {
                continue;
            }

            $icon = htmlspecialchars($config['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $content = $config['render']($rawValue);

            echo '<li class="mb-2">'
                . '<i class="' . $icon . ' me-2 text-primary"></i>'
                . $content
                . '</li>';
        }
    }
}
?>
    <!-- Footer -->
    <footer id="footer-area" class="site-footer mt-auto">
        <div class="container">
            <div class="footer-main">
                <div>
                    <a href="<?php echo htmlspecialchars($scriptBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>/" class="navbar-brand d-inline-block mb-3">
                        <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="height: 40px;" class="filter-invert">
                    </a>
                    <p class="small opacity-75 mb-3">Your trusted partner for exceptional service and innovative solutions.</p>
                    <div class="footer-social">
                        <?php renderFooterSocialLinks(is_array($social ?? null) ? $social : []); ?>
                    </div>
                </div>
                <nav class="footer-menu">
                    <h5 class="text-white mb-3">Quick Links</h5>
                    <ul>
                        <?php renderFooterMenu($footerMenu ?? []); ?>
                    </ul>
                </nav>
                <div>
                    <h5 class="text-white mb-3">Contact Info</h5>
                    <ul class="list-unstyled">
                        <?php renderFooterContactItems(is_array($settings ?? null) ? $settings : []); ?>
                    </ul>
                </div>
            </div>
            <div class="footer-copy d-flex flex-column flex-md-row justify-content-between align-items-center">
                <p class="mb-2 mb-md-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>. All rights reserved.</p>
                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link text-muted px-2" href="<?php echo htmlspecialchars($scriptBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>/privacy-policy">Privacy Policy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-muted px-2" href="<?php echo htmlspecialchars($scriptBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>/terms-of-service">Terms of Service</a>
                    </li>
                </ul>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="back-to-top-btn" class="_js-scroll-top" aria-label="Back to Top" hidden aria-hidden="true">
        <span>
            <span>
                <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
            </span>
        </span>
        <span class="visually-hidden">Back to Top</span>
    </button>
</div>

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
<script>window.cmsBase = <?php echo json_encode($scriptBase, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo htmlspecialchars($themeBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>/js/dist/<?php echo htmlspecialchars($bundleFiles['global'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?v=<?php echo htmlspecialchars($bundleVersions['global'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($themeBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>/js/dist/<?php echo htmlspecialchars($bundleFiles['script'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?v=<?php echo htmlspecialchars($bundleVersions['script'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    </body>
</html>
