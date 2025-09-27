<!-- File: view.php -->
                <div class="content-section active" id="dashboard">
                    <div class="dashboard-shell">
                        <header class="dashboard-hero">
                            <div class="dashboard-hero-content">
                                <span class="dashboard-hero-label">Control centre</span>
                                <h2 class="dashboard-hero-title">Dashboard overview</h2>
                                <p class="dashboard-hero-subtitle">
                                    Monitor the health of your content, media, and optimisation efforts from one unified view.
                                </p>
                                <div class="dashboard-hero-actions">
                                    <button type="button" class="dashboard-btn dashboard-btn--primary" id="dashboardRefresh">
                                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                                        <span>Refresh insights</span>
                                    </button>
                                    <a class="dashboard-btn dashboard-btn--ghost" href="../" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                                        <span>View site</span>
                                    </a>
                                </div>
                            </div>
                            <div class="dashboard-hero-meta" id="dashboardLastUpdated" aria-live="polite">
                                Insights update automatically every few moments.
                            </div>
                        </header>

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
                            <div class="dashboard-table-card">
                                <div class="dashboard-table-header">
                                    <h4 class="dashboard-table-title">Latest signals</h4>
                                    <p class="dashboard-table-subtitle">Primary metrics from each module are refreshed alongside the dashboard.</p>
                                </div>
                                <div class="dashboard-table-wrapper">
                                    <table class="data-table" id="moduleSummaryTable">
                                        <thead>
                                            <tr>
                                                <th scope="col">Module</th>
                                                <th scope="col">Primary Metric</th>
                                                <th scope="col">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="3">Loading module data…</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
