<!-- File: view.php -->
                <div class="content-section" id="import">
                    <div class="import-dashboard a11y-dashboard">
                        <header class="a11y-hero import-hero">
                            <div class="a11y-hero-content import-hero-content">
                                <div>
                                    <span class="hero-eyebrow import-hero-eyebrow">Transfer Toolkit</span>
                                    <h2 class="a11y-hero-title import-hero-title">Import &amp; Export</h2>
                                    <p class="a11y-hero-subtitle import-hero-subtitle">Transfer site content, menus, media, and settings between environments with confidence.</p>
                                </div>
                                <div class="a11y-hero-actions import-hero-actions">
                                    <button type="button" class="a11y-btn a11y-btn--primary" id="startImportBtn">
                                        <i class="fas fa-file-arrow-up" aria-hidden="true"></i>
                                        <span>Start import</span>
                                    </button>
                                    <button type="button" class="a11y-btn a11y-btn--ghost" id="startExportBtn">
                                        <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                                        <span>Generate export</span>
                                    </button>
                                    <input type="file" id="importFileInput" accept="application/json" style="display:none;" aria-hidden="true" tabindex="-1">
                                </div>
                            </div>
                            <div class="a11y-overview-grid import-overview-grid">
                                <div class="a11y-overview-card import-overview-card">
                                    <div class="a11y-overview-label">Last import</div>
                                    <div class="a11y-overview-value" id="importLastRun">—</div>
                                </div>
                                <div class="a11y-overview-card import-overview-card">
                                    <div class="a11y-overview-label">Last export</div>
                                    <div class="a11y-overview-value" id="exportLastRun">—</div>
                                </div>
                                <div class="a11y-overview-card import-overview-card">
                                    <div class="a11y-overview-label">Available profiles</div>
                                    <div class="a11y-overview-value" id="importProfilesCount">0</div>
                                </div>
                                <div class="a11y-overview-card import-overview-card">
                                    <div class="a11y-overview-label">Exports generated</div>
                                    <div class="a11y-overview-value" id="exportGeneratedCount">0</div>
                                </div>
                            </div>
                        </header>
                        <div class="import-placeholder">
                            <p id="importExportIntro">Import or export CMS data.</p>
                            <div class="import-datasets" id="importDatasetSection" hidden>
                                <div class="import-datasets__title">What's included in exports</div>
                                <p class="import-datasets__subtitle">Review each data set before you transfer content between environments.</p>
                                <div class="import-datasets__summary" id="importDatasetSummary"></div>
                                <ul class="import-datasets__list" id="importDatasetList" aria-live="polite"></ul>
                            </div>
                            <div class="import-history" id="importHistorySection" hidden>
                                <div class="import-history__header">
                                    <div class="import-history__title">Activity history</div>
                                    <p class="import-history__description">Track recent imports and exports for visibility into your transfer activity.</p>
                                </div>
                                <ul class="import-history__list" id="importHistoryList" aria-live="polite"></ul>
                                <p class="import-history__empty" id="importHistoryEmpty" hidden>No import or export activity recorded yet.</p>
                            </div>
                            <div class="import-status" id="importExportStatus" role="status" aria-live="polite" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
