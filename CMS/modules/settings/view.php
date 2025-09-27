<!-- File: view.php -->
<div class="content-section" id="settings">
    <form id="settingsForm" class="settings-dashboard" enctype="multipart/form-data">
        <div class="a11y-dashboard" id="settingsDashboard" data-last-saved="">
            <header class="a11y-hero">
                <div class="a11y-hero-content">
                    <div>
                        <h2 class="a11y-hero-title">Site Settings</h2>
                        <p class="a11y-hero-subtitle">Fine-tune your brand voice, analytics, and sharing defaults from a single, polished workspace.</p>
                    </div>
                    <div class="a11y-hero-actions">
                        <button type="submit" class="a11y-btn a11y-btn--primary" id="saveSettingsButton">
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <span>Save Settings</span>
                        </button>
                        <span class="a11y-hero-meta">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            Last updated <span id="settingsLastSaved">—</span>
                        </span>
                    </div>
                </div>
                <div class="a11y-overview-grid">
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewName">—</div>
                        <div class="a11y-overview-label">Site Name</div>
                    </div>
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewSocials">0</div>
                        <div class="a11y-overview-label">Social Profiles</div>
                    </div>
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewTracking">0</div>
                        <div class="a11y-overview-label">Tracking IDs</div>
                    </div>
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewVisibility">—</div>
                        <div class="a11y-overview-label">Visibility</div>
                    </div>
                </div>
            </header>

            <section class="a11y-detail-grid settings-grid" aria-label="Settings sections">
                <article class="a11y-detail-card">
                    <h2>Branding &amp; Basics</h2>
                    <p class="settings-card-description">Control the essentials that shape how your site appears to visitors.</p>
                    <div class="form-group">
                        <label class="form-label" for="site_name">Site Name</label>
                        <input type="text" class="form-input" name="site_name" id="site_name" autocomplete="organization">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tagline">Tagline</label>
                        <input type="text" class="form-input" name="tagline" id="tagline" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin_email">Admin Email</label>
                        <input type="email" class="form-input" name="admin_email" id="admin_email" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="logoFile">Site Logo</label>
                        <div class="settings-file-input">
                            <input type="file" class="form-input" id="logoFile" name="logo" accept="image/*">
                            <img id="logoPreview" class="settings-file-preview" src="" alt="Logo preview" hidden>
                        </div>
                        <div class="form-option">
                            <label class="form-checkbox">
                                <input type="checkbox" id="clearLogo" name="clear_logo" value="1">
                                <span>Remove current logo</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="timezone">Timezone</label>
                        <select class="form-select" id="timezone" name="timezone">
                            <option value="America/New_York">Eastern Time (ET)</option>
                            <option value="America/Chicago">Central Time (CT)</option>
                            <option value="America/Denver">Mountain Time (MT)</option>
                            <option value="America/Los_Angeles">Pacific Time (PT)</option>
                            <option value="UTC">UTC</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="googleAnalytics">Google Analytics ID</label>
                        <input type="text" class="form-input" id="googleAnalytics" name="googleAnalytics" placeholder="G-XXXXXXXXXX or UA-XXXXXXXX-X">
                        <div class="form-help">Your Google Analytics measurement ID.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="googleSearchConsole">Google Search Console Verification Code</label>
                        <input type="text" class="form-input" id="googleSearchConsole" name="googleSearchConsole" placeholder="google-site-verification=...">
                        <div class="form-help">Meta tag verification code from Google Search Console.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="facebookPixel">Facebook Pixel ID</label>
                        <input type="text" class="form-input" id="facebookPixel" name="facebookPixel" placeholder="1234567890123456">
                        <div class="form-help">Your Facebook Pixel ID for conversion tracking.</div>
                    </div>
                    <div class="settings-toggle-group" role="group" aria-label="Search visibility preferences">
                        <label class="settings-toggle">
                            <input type="checkbox" id="generateSitemap" name="generateSitemap" checked>
                            <div>
                                <span class="settings-toggle__title">Auto-generate XML sitemap</span>
                                <p class="settings-toggle__description">Keep search engines in sync with your latest pages.</p>
                            </div>
                        </label>
                        <label class="settings-toggle">
                            <input type="checkbox" id="allowIndexing" name="allowIndexing" checked>
                            <div>
                                <span class="settings-toggle__title">Allow search indexing</span>
                                <p class="settings-toggle__description">Let search engines discover and list your site.</p>
                            </div>
                        </label>
                    </div>
                </article>

                <article class="a11y-detail-card">
                    <h2>Social Profiles</h2>
                    <p class="settings-card-description">Connect your brand channels to power sharing and automation.</p>
                    <div class="settings-social-grid">
                        <div class="form-group">
                            <label class="form-label" for="facebookLink">Facebook</label>
                            <input type="url" class="form-input" id="facebookLink" name="facebook" placeholder="https://facebook.com/yourpage">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="twitterLink">Twitter</label>
                            <input type="url" class="form-input" id="twitterLink" name="twitter" placeholder="https://twitter.com/youraccount">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="instagramLink">Instagram</label>
                            <input type="url" class="form-input" id="instagramLink" name="instagram" placeholder="https://instagram.com/youraccount">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="linkedinLink">LinkedIn</label>
                            <input type="url" class="form-input" id="linkedinLink" name="linkedin" placeholder="https://linkedin.com/company/yourcompany">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="youtubeLink">YouTube</label>
                            <input type="url" class="form-input" id="youtubeLink" name="youtube" placeholder="https://youtube.com/c/yourchannel">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="tiktokLink">TikTok</label>
                            <input type="url" class="form-input" id="tiktokLink" name="tiktok" placeholder="https://tiktok.com/@youraccount">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="pinterestLink">Pinterest</label>
                            <input type="url" class="form-input" id="pinterestLink" name="pinterest" placeholder="https://pinterest.com/yourprofile">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="snapchatLink">Snapchat</label>
                            <input type="url" class="form-input" id="snapchatLink" name="snapchat" placeholder="https://snapchat.com/add/yourusername">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="redditLink">Reddit</label>
                            <input type="url" class="form-input" id="redditLink" name="reddit" placeholder="https://reddit.com/u/yourusername">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="threadsLink">Threads</label>
                            <input type="url" class="form-input" id="threadsLink" name="threads" placeholder="https://threads.net/@yourusername">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="mastodonLink">Mastodon</label>
                            <input type="url" class="form-input" id="mastodonLink" name="mastodon" placeholder="https://mastodon.social/@yourusername">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="githubLink">GitHub</label>
                            <input type="url" class="form-input" id="githubLink" name="github" placeholder="https://github.com/yourusername">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="dribbbleLink">Dribbble</label>
                            <input type="url" class="form-input" id="dribbbleLink" name="dribbble" placeholder="https://dribbble.com/yourprofile">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="twitchLink">Twitch</label>
                            <input type="url" class="form-input" id="twitchLink" name="twitch" placeholder="https://twitch.tv/yourchannel">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="whatsappLink">WhatsApp</label>
                            <input type="url" class="form-input" id="whatsappLink" name="whatsapp" placeholder="https://wa.me/yourphonenumber">
                        </div>
                    </div>
                </article>

                <article class="a11y-detail-card">
                    <h2>Open Graph Defaults</h2>
                    <p class="settings-card-description">Define how your pages look when shared across social platforms.</p>
                    <div class="form-group">
                        <label class="form-label" for="ogTitle">Default Share Title</label>
                        <input type="text" class="form-input" id="ogTitle" name="ogTitle" placeholder="My Awesome Website">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ogDescription">Default Share Description</label>
                        <textarea class="form-textarea" id="ogDescription" name="ogDescription" rows="4" placeholder="Give people a compelling reason to click."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ogImageFile">Default Share Image</label>
                        <div class="settings-file-input">
                            <input type="file" id="ogImageFile" name="ogImage" accept="image/*">
                            <img id="ogImagePreview" class="settings-file-preview" src="" alt="Open graph image preview" hidden>
                        </div>
                        <div class="form-option">
                            <label class="form-checkbox">
                                <input type="checkbox" id="clearOgImage" name="clear_og_image" value="1">
                                <span>Remove current image</span>
                            </label>
                        </div>
                        <div class="form-help">Upload a 1200 × 630px image for social sharing cards.</div>
                    </div>
                </article>
            </section>
        </div>
    </form>
</div>
