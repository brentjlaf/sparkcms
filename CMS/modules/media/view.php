<!-- File: view.php -->
                <div class="content-section" id="media">
                    <div class="media-dashboard">
                        <header class="media-hero">
                            <div class="media-hero-content">
                                <div>
                                    <h2 class="media-hero-title">Media Library</h2>
                                    <p class="media-hero-subtitle">Keep your images, documents, and videos organised with a modern, visual workspace that mirrors the accessibility dashboard experience.</p>
                                </div>
                                <div class="media-hero-actions">
                                    <button type="button" class="media-btn media-btn--ghost" id="createFolderBtn">
                                        <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                                        <span>New Folder</span>
                                    </button>
                                    <button type="button" class="media-btn media-btn--primary is-disabled" id="uploadBtn" disabled aria-disabled="true">
                                        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                        <span>Upload Media</span>
                                    </button>
                                </div>
                            </div>
                            <div class="media-hero-meta">
                                <span class="media-hero-chip">
                                    <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
                                    <span>Rich asset management with previews, cropping, and tagging tools.</span>
                                </span>
                                <span class="media-hero-chip" id="mediaHeroFolderChip">
                                    <i class="fa-solid fa-folder-tree" aria-hidden="true"></i>
                                    <span id="mediaHeroFolderName">No folder selected</span>
                                </span>
                                <span class="media-hero-chip" id="mediaHeroFolderMeta">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    <span id="mediaHeroFolderInfo">Select a folder to see file details</span>
                                </span>
                                <span class="media-hero-chip">
                                    <i class="fa-solid fa-database" aria-hidden="true"></i>
                                    <span id="mediaStorageSummary">0 used</span>
                                </span>
                            </div>
                        </header>

                        <div class="media-overview-grid">
                            <article class="media-overview-card">
                                <span class="media-overview-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                                <div class="media-overview-content">
                                    <span class="media-overview-label">Folders</span>
                                    <span class="media-overview-value" id="totalFolders">0</span>
                                </div>
                            </article>
                            <article class="media-overview-card">
                                <span class="media-overview-icon"><i class="fa-solid fa-images" aria-hidden="true"></i></span>
                                <div class="media-overview-content">
                                    <span class="media-overview-label">Files</span>
                                    <span class="media-overview-value" id="totalImages">0</span>
                                </div>
                            </article>
                            <article class="media-overview-card">
                                <span class="media-overview-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></span>
                                <div class="media-overview-content">
                                    <span class="media-overview-label">Storage Used</span>
                                    <span class="media-overview-value" id="totalSize">0</span>
                                </div>
                            </article>
                        </div>

                        <div class="media-workspace">
                            <div class="media-sidebar">
                                <div class="sidebar-header">
                                    <h2>Folders</h2>
                                </div>
                                <div class="folder-list" id="folderList"></div>
                            </div>
                            <div class="media-gallery">
                                <div class="gallery-header" id="galleryHeader" style="display: none;">
                                    <div>
                                        <h2 id="selectedFolderName">Select a folder</h2>
                                        <div class="folder-stats" id="folderStats"></div>
                                    </div>
                                    <div class="gallery-actions">
                                        <button class="btn btn-secondary" id="renameFolderBtn"><i class="fa-solid fa-pen" aria-hidden="true"></i><span>Rename</span></button>
                                        <button class="btn btn-danger" id="deleteFolderBtn"><i class="fa-solid fa-trash" aria-hidden="true"></i><span>Delete</span></button>
                                    </div>
                                </div>
                                <div class="gallery-content" id="galleryContent">
                                    <div class="form-row media-toolbar" id="mediaToolbar" style="display:none;">
                                        <div class="form-group">
                                            <label class="form-label" for="sort-by">Sort By</label>
                                            <select id="sort-by" class="form-select w-auto">
                                                <option value="custom">Custom (Manual)</option>
                                                <option value="name">Name</option>
                                                <option value="date">Date</option>
                                                <option value="type">Type</option>
                                                <option value="size">Size</option>
                                                <option value="tags">Tags</option>
                                                <option value="dimensions">Dimensions</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="sort-order">Order</label>
                                            <select id="sort-order" class="form-select w-auto">
                                                <option value="asc">Ascending</option>
                                                <option value="desc">Descending</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="view-type">View</label>
                                            <select id="view-type" class="form-select w-auto">
                                                <option value="extra-large">Extra Large</option>
                                                <option value="large">Large</option>
                                                <option value="medium" selected>Medium</option>
                                                <option value="small">Small</option>
                                                <option value="details">Details</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="items-per-page">Items / Page</label>
                                            <select id="items-per-page" class="form-select w-auto">
                                                <option value="8">8</option>
                                                <option value="12" selected>12</option>
                                                <option value="24">24</option>
                                                <option value="48">48</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="media-empty-state" id="selectFolderState">
                                        <i class="fa-solid fa-folder-tree" aria-hidden="true"></i>
                                        <h3>Select a folder to view images</h3>
                                        <p>Choose a folder from the sidebar to manage its assets.</p>
                                    </div>
                                    <div class="media-empty-state" id="emptyFolderState" style="display: none;">
                                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                                        <h3>No media in this folder</h3>
                                        <p>Click "Upload Media" to add new files.</p>
                                    </div>
                                    <div class="image-grid" id="imageGrid" style="display: none;"></div>
                                    <div class="gallery-pagination" id="galleryPagination" style="display:none;"></div>
                                    <div id="dropZone" class="upload-drop">Drop images here</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal" id="createFolderModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Create New Folder</h2>
                            </div>
                            <div class="modal-body">
                                <input type="text" id="newFolderName" placeholder="Folder name">
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                                <button class="btn btn-primary" id="confirmCreateBtn">Create</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal" id="imageInfoModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Image Details</h2>
                            </div>
                            <div class="modal-body info-layout">
                                <div class="info-preview">
                                    <img id="infoImage" src="" alt="Preview" style="max-width:100%;border:1px solid #ccc;">
                                </div>
                                <div class="info-meta">
                                    <div id="item-info" class="form-group">
                                        <p><strong>Type:</strong> <span id="infoType"></span></p>
                                        <p><strong>File:</strong> <span id="infoFile"></span></p>
                                        <p><strong>Size:</strong> <span id="infoSize"></span></p>
                                        <p><strong>Dimensions:</strong> <span id="infoDimensions"></span></p>
                                        <p><strong>Extension:</strong> <span id="infoExt"></span></p>
                                        <p><strong>Date:</strong> <span id="infoDate"></span></p>
                                        <p><strong>Folder:</strong> <span id="infoFolder"></span></p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="edit-name">Name/Title</label>
                                        <input type="text" id="edit-name" class="form-input">
                                    </div>
                                    <div class="form-group" id="rename-file-group">
                                        <label class="form-label" for="edit-fileName">Rename File</label>
                                        <input type="text" id="edit-fileName" class="form-input">
                                        <div class="form-check" style="margin-top:8px;">
                                            <input type="checkbox" id="renamePhysicalCheckbox">
                                            <label for="renamePhysicalCheckbox">Rename on disk</label>
                                        </div>
                                    </div>
                                    <div class="form-actions" id="infoActions">
                                        <button class="btn btn-danger" id="deleteBtn">Delete</button>
                                        <button class="btn btn-primary" id="saveEditBtn">Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal" id="imageEditModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Edit Image</h2>
                            </div>
                            <div class="modal-body edit-layout">
                                <div class="crop-container">
                                    <img id="editImage" src="" style="max-width:100%;display:block;">
                                </div>
                                <div class="crop-sidebar">
                                    <div class="form-group">
                                        <button class="btn btn-secondary" id="flipHorizontal">Flip Horizontal</button>
                                    </div>
                                    <div class="form-group">
                                        <button class="btn btn-secondary" id="flipVertical">Flip Vertical</button>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="scaleSlider">Scale</label>
                                        <input type="range" class="form-input" id="scaleSlider" min="0.5" max="3" step="0.1" value="1">
                                        <div id="sizeEstimate" class="size-estimate"></div>
                                    </div>
                                    <div class="control-group">
                                        <label for="crop-preset">Crop Presets:</label>
                                        <select id="crop-preset">
                                            <option value="NaN">Freeform</option>
                                            <option value="1">1:1 (Square)</option>
                                            <option value="1.7777">16:9 (Wide)</option>
                                            <option value="1.3333">4:3 (Standard)</option>
                                            <option value="0.6667">2:3 (3x5)</option>
                                            <option value="0.75">3:4 (4x6)</option>
                                            <option value="0.7143">5:7</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="saveFormat">Format</label>
                                        <select class="form-select" id="saveFormat">
                                            <option value="jpeg">JPG</option>
                                            <option value="png">PNG</option>
                                            <option value="webp">WEBP</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" id="imageEditCancel">Cancel</button>
                                <button class="btn btn-primary" id="imageEditSave">Save</button>
                            </div>
                        </div>
                    </div>
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
                    <input type="file" id="fileInput" class="upload-input" multiple accept="image/*">
                    <div id="uploadLoader" class="upload-loader" style="display:none;">
                        <div class="upload-loader-content" role="status" aria-live="polite">
                            <div class="loading" aria-hidden="true"></div>
                            <div class="upload-progress">
                                <div class="upload-progress-bar" aria-hidden="true">
                                    <div id="uploadProgressFill" class="upload-progress-fill"></div>
                                </div>
                                <div id="uploadProgressPercent" class="upload-progress-percent">0%</div>
                                <div id="uploadStatusMessage" class="upload-status-message"></div>
                            </div>
                        </div>
                    </div>
                </div>
