<!-- File: view.php -->
                <div class="content-section active" id="dashboard">
                    <div class="dashboard-shell">
                        <header class="a11y-hero dashboard-hero">
                            <div class="a11y-hero-content dashboard-hero-content">
                                <div>
                                    <span class="a11y-hero-label dashboard-hero-label">Control centre</span>
                                    <h2 class="a11y-hero-title dashboard-hero-title">Dashboard overview</h2>
                                    <p class="a11y-hero-subtitle dashboard-hero-subtitle">
                                    Monitor the health of your content, media, and optimisation efforts from one unified view.
                                    </p>
                                </div>
                                <div class="a11y-hero-actions dashboard-hero-actions">
                                    <button type="button" class="a11y-btn a11y-btn--primary" id="dashboardRefresh">
                                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                                        <span>Refresh insights</span>
                                    </button>
                                    <a class="a11y-btn a11y-btn--ghost" href="../" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                                        <span>View site</span>
                                    </a>
                                </div>
                            </div>
                            <div class="a11y-hero-meta dashboard-hero-meta" id="dashboardLastUpdated" aria-live="polite">
                                Insights update automatically every few moments.
                            </div>
                        </header>

                        <section class="dashboard-section" aria-labelledby="dashboardSectionQuick">
                            <div class="dashboard-section-header">
                                <div>
                                    <h3 class="dashboard-section-title" id="dashboardSectionQuick">Quick actions</h3>
                                    <p class="dashboard-section-description">Jump directly into the most used tools and keep work moving.</p>
                                </div>
                            </div>
                            <div class="dashboard-quick-actions" id="dashboardQuickActions" role="list">
                                <button type="button" class="dashboard-quick-card" data-module="pages" role="listitem">
                                    <span class="dashboard-quick-icon pages" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">Pages</span>
                                        <span class="dashboard-quick-description">Publish updates and organise your site structure.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                                <button type="button" class="dashboard-quick-card" data-module="blogs" role="listitem">
                                    <span class="dashboard-quick-icon blogs" aria-hidden="true"><i class="fa-solid fa-pen-nib"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">Blogs</span>
                                        <span class="dashboard-quick-description">Draft fresh stories and keep readers informed.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                                <button type="button" class="dashboard-quick-card" data-module="media" role="listitem">
                                    <span class="dashboard-quick-icon media" aria-hidden="true"><i class="fa-solid fa-images"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">Media</span>
                                        <span class="dashboard-quick-description">Upload assets and keep your library organised.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                                <button type="button" class="dashboard-quick-card" data-module="forms" role="listitem">
                                    <span class="dashboard-quick-icon forms" aria-hidden="true"><i class="fa-solid fa-inbox"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">Forms</span>
                                        <span class="dashboard-quick-description">Review submissions and fine-tune entry points.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                                <button type="button" class="dashboard-quick-card" data-module="users" role="listitem">
                                    <span class="dashboard-quick-icon users" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">Users</span>
                                        <span class="dashboard-quick-description">Manage team access and keep roles up to date.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                                <button type="button" class="dashboard-quick-card" data-module="settings" role="listitem">
                                    <span class="dashboard-quick-icon settings" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">Settings</span>
                                        <span class="dashboard-quick-description">Fine tune branding, metadata, and site defaults.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                                <button type="button" class="dashboard-quick-card" data-module="analytics" role="listitem">
                                    <span class="dashboard-quick-icon analytics" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">Analytics</span>
                                        <span class="dashboard-quick-description">Track performance and spot opportunities.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                                <button type="button" class="dashboard-quick-card" data-module="seo" role="listitem">
                                    <span class="dashboard-quick-icon seo" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
                                    <span class="dashboard-quick-content">
                                        <span class="dashboard-quick-label">SEO</span>
                                        <span class="dashboard-quick-description">Resolve search optimisations and boost visibility.</span>
                                    </span>
                                    <span class="dashboard-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </button>
                            </div>
                        </section>

                        <section class="dashboard-section" aria-labelledby="dashboardSectionMetrics">
                            <div class="dashboard-section-header">
                                <div>
                                    <h3 class="dashboard-section-title" id="dashboardSectionMetrics">Key metrics</h3>
                                    <p class="dashboard-section-description">Snapshot of activity from every module.</p>
                                </div>
                            </div>
                            <div class="dashboard-metric-grid">
                                <article class="stat-card dashboard-metric-card">
                                    <div class="stat-header">
                                        <div class="stat-icon pages"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div>
                                        <div class="stat-content">
                                            <div class="stat-label">Total Pages</div>
                                            <div class="stat-number" id="statPages">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-subtext" id="statPagesBreakdown">Published: 0 • Drafts: 0</div>
                                </article>
                                <article class="stat-card dashboard-metric-card">
                                    <div class="stat-header">
                                        <div class="stat-icon media"><i class="fa-solid fa-images" aria-hidden="true"></i></div>
                                        <div class="stat-content">
                                            <div class="stat-label">Media Files</div>
                                            <div class="stat-number" id="statMedia">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-subtext" id="statMediaSize">Library size: 0 KB</div>
                                </article>
                                <article class="stat-card dashboard-metric-card">
                                    <div class="stat-header">
                                        <div class="stat-icon users"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                                        <div class="stat-content">
                                            <div class="stat-label">Users</div>
                                            <div class="stat-number" id="statUsers">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-subtext" id="statUsersBreakdown">Admins: 0 • Editors: 0</div>
                                </article>
                                <article class="stat-card dashboard-metric-card">
                                    <div class="stat-header">
                                        <div class="stat-icon views"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                                        <div class="stat-content">
                                            <div class="stat-label">Total Views</div>
                                            <div class="stat-number" id="statViews">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-subtext" id="statViewsAverage">Average per page: 0</div>
                                </article>
                                <article class="stat-card dashboard-metric-card">
                                    <div class="stat-header">
                                        <div class="stat-icon seo"><i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i></div>
                                        <div class="stat-content">
                                            <div class="stat-label">SEO Health</div>
                                            <div class="stat-number" id="statSeoScore">0%</div>
                                        </div>
                                    </div>
                                    <div class="stat-subtext" id="statSeoBreakdown">Optimized: 0 • Needs attention: 0</div>
                                    <div class="stat-subtext" id="statSeoMetadata">Metadata gaps: 0</div>
                                </article>
                                <article class="stat-card dashboard-metric-card">
                                    <div class="stat-header">
                                        <div class="stat-icon accessibility"><i class="fa-solid fa-universal-access" aria-hidden="true"></i></div>
                                        <div class="stat-content">
                                            <div class="stat-label">Accessibility</div>
                                            <div class="stat-number" id="statAccessibilityScore">0%</div>
                                        </div>
                                    </div>
                                    <div class="stat-subtext" id="statAccessibilityBreakdown">Compliant: 0 • Needs review: 0</div>
                                    <div class="stat-subtext" id="statAccessibilityAlt">Alt text issues: 0</div>
                                </article>
                                <article class="stat-card dashboard-metric-card">
                                    <div class="stat-header">
                                        <div class="stat-icon alerts"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                                        <div class="stat-content">
                                            <div class="stat-label">Open Alerts</div>
                                            <div class="stat-number" id="statAlerts">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-subtext" id="statAlertsBreakdown">SEO: 0 • Accessibility: 0</div>
                                </article>
                            </div>
                        </section>

                        <section class="dashboard-section" aria-labelledby="dashboardSectionModules">
                            <div class="dashboard-section-header">
                                <div>
                                    <h3 class="dashboard-section-title" id="dashboardSectionModules">Module insights</h3>
                                    <p class="dashboard-section-description">Understand where attention is needed across features.</p>
                                </div>
                            </div>
                            <div class="dashboard-module-card-grid" aria-live="polite" aria-busy="true" id="dashboardModuleCards" role="list">
                                <article class="dashboard-module-card placeholder" role="listitem" tabindex="0" aria-label="Module data loading">
                                    <header class="dashboard-module-card-header">
                                        <div class="dashboard-module-card-title">
                                            <span class="dashboard-module-name">Loading…</span>
                                            <span class="dashboard-module-status" aria-hidden="true">Please wait</span>
                                        </div>
                                        <p class="dashboard-module-primary">Fetching module insights</p>
                                    </header>
                                    <p class="dashboard-module-secondary">Module metrics will appear here once data is available.</p>
                                    <footer class="dashboard-module-card-footer">
                                        <span class="dashboard-module-trend" aria-hidden="true">Preparing data</span>
                                        <button type="button" class="dashboard-module-cta" disabled aria-disabled="true">Loading</button>
                                    </footer>
                                </article>
                            </div>
                        </section>
                    </div>
                </div>
