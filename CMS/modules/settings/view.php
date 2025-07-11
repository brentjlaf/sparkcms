                <form id="settingsForm">
                    <div class="content-section" id="settings">
                        <div class="form-card" id="settingsFormCard" style="margin-top:20px;">
                            <h3>General Settings</h3>
                            <div class="form-group">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-input" name="site_name" id="site_name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tagline</label>
                                <input type="text" class="form-input" name="tagline" id="tagline">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Admin Email</label>
                                <input type="email" class="form-input" name="admin_email" id="admin_email">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Site Logo</label>
                                <input type="file" class="form-input" id="logoFile" name="logo" accept="image/*">
                                <img id="logoPreview" src="" alt="Logo preview" style="max-height:50px;margin-top:10px;display:none;">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="timezone">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="America/New_York">Eastern Time (ET)</option>
                                    <option value="America/Chicago">Central Time (CT)</option>
                                    <option value="America/Denver">Mountain Time (MT)</option>
                                    <option value="America/Los_Angeles" selected>Pacific Time (PT)</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="googleAnalytics">Google Analytics ID</label>
                                <input type="text" class="form-input" id="googleAnalytics" name="googleAnalytics" placeholder="G-XXXXXXXXXX or UA-XXXXXXXX-X">
                                <div class="form-help">Your Google Analytics measurement ID</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="googleSearchConsole">Google Search Console Verification Code</label>
                                <input type="text" class="form-input" id="googleSearchConsole" name="googleSearchConsole" placeholder="google-site-verification=...">
                                <div class="form-help">Meta tag verification code from Google Search Console</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="facebookPixel">Facebook Pixel ID</label>
                                <input type="text" class="form-input" id="facebookPixel" name="facebookPixel" placeholder="1234567890123456">
                                <div class="form-help">Your Facebook Pixel ID for conversion tracking</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><input type="checkbox" id="generateSitemap" name="generateSitemap" checked> Auto-generate XML sitemap</label>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><input type="checkbox" id="allowIndexing" name="allowIndexing" checked> Allow search engines to index this site</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-section" id="social">
                    <div class="form-card" id="socialFormCard" style="margin-top:20px;">
                        <h3>Social Media</h3>
                        <p>Connect your social media accounts and configure sharing</p>
                            <div class="form-group">
                                <label class="form-label" for="facebookLink">Facebook</label>
                                <input type="url" class="form-input" id="facebookLink" name="facebook" placeholder="https://facebook.com/yourpage" value="https://facebook.com/mywebsite">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="twitterLink">Twitter</label>
                                <input type="url" class="form-input" id="twitterLink" name="twitter" placeholder="https://twitter.com/youraccount" value="https://twitter.com/mywebsite">
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
                    </div>
                </div>

                <div class="content-section" id="openGraph">
                    <div class="form-card" id="openGraphFormCard" style="margin-top:20px;">
                        <h3>Open Graph Settings</h3>
                        <p>Configure how your website appears when shared on social media</p>
                            <div class="form-group">
                                <label class="form-label" for="ogTitle">Default Share Title</label>
                                <input type="text" class="form-input" id="ogTitle" name="ogTitle" value="My Awesome Website">
                                <div class="form-help">Title that appears when pages are shared</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="ogDescription">Default Share Description</label>
                                <textarea class="form-textarea" id="ogDescription" name="ogDescription">Check out this amazing website with great content and resources!</textarea>
                                <div class="form-help">Description that appears when pages are shared</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="ogImageFile">Default Share Image</label>
                                <input type="file" id="ogImageFile" accept="image/*">
                                <div class="form-help">Upload default share image (1200x630px recommended)</div>
                            </div>
                    </div>
                </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
