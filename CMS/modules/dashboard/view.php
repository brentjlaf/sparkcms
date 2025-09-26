<!-- File: view.php -->
                <div class="content-section active" id="dashboard">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon pages"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Total Pages</div>
                                    <div class="stat-number" id="statPages">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statPagesBreakdown">Published: 0 • Drafts: 0 • Scheduled: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon media"><i class="fa-solid fa-images" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Media Files</div>
                                    <div class="stat-number" id="statMedia">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon users"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Users</div>
                                    <div class="stat-number" id="statUsers">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statUsersBreakdown">Admins: 0 • Editors: 0 • Inactive: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon views"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Total Views</div>
                                    <div class="stat-number" id="statViews">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statViewsAverage">Average per page: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon seo"><i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">SEO Health</div>
                                    <div class="stat-number" id="statSeoScore">0%</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statSeoBreakdown">Optimized: 0 • Needs attention: 0</div>
                            <div class="stat-subtext" id="statSeoMetadata">Metadata gaps: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon accessibility"><i class="fa-solid fa-universal-access" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Accessibility</div>
                                    <div class="stat-number" id="statAccessibilityScore">0%</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statAccessibilityBreakdown">Compliant: 0 • Needs review: 0</div>
                            <div class="stat-subtext" id="statAccessibilityAlt">Alt text issues: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon alerts"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Open Alerts</div>
                                    <div class="stat-number" id="statAlerts">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statAlertsBreakdown">SEO: 0 • Accessibility: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon blogs"><i class="fa-solid fa-rss" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Blog Posts</div>
                                    <div class="stat-number" id="statBlogPosts">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statBlogBreakdown">Published: 0 • Drafts: 0 • Scheduled: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon forms"><i class="fa-solid fa-wpforms" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Forms</div>
                                    <div class="stat-number" id="statForms">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statFormsBreakdown">Fields: 0 • Required: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon menus"><i class="fa-solid fa-bars" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Menus</div>
                                    <div class="stat-number" id="statMenus">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statMenusBreakdown">Items: 0 • Nested groups: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon settings"><i class="fa-solid fa-sliders" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Site Settings</div>
                                    <div class="stat-number" id="statSettings">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statSettingsBreakdown">Social profiles: 0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon sitemap"><i class="fa-solid fa-sitemap" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Sitemap URLs</div>
                                    <div class="stat-number" id="statSitemap">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statSitemapBreakdown">Last generated: Not yet generated</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon logs"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Change Log</div>
                                    <div class="stat-number" id="statLogs">0</div>
                                </div>
                            </div>
                            <div class="stat-subtext" id="statLogsBreakdown">Last activity: No history recorded</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon analytics"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></div>
                                <div class="stat-content">
                                    <div class="stat-label">Top Pages</div>
                                    <div class="stat-number">Top 5</div>
                                </div>
                            </div>
                            <ul class="stat-list" id="statTopPages">
                                <li class="stat-list-empty">Loading…</li>
                            </ul>
                        </div>
                    </div>
                </div>

