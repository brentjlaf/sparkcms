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
<script src="<?php echo $themeBase; ?>/js/global.js?v=mw3.2"></script>
<script src="<?php echo $themeBase; ?>/js/script.js?v=mw3.2"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    </body>
</html>
