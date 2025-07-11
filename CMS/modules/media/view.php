                <div class="content-section" id="media">
                    <div class="media-container">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-icon media">🗂️</div>
                                    <div class="stat-content">
                                        <div class="stat-label">Folders</div>
                                        <div class="stat-number" id="totalFolders">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-icon media">🖼️</div>
                                    <div class="stat-content">
                                        <div class="stat-label">Files</div>
                                        <div class="stat-number" id="totalImages">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-icon media">📦</div>
                                    <div class="stat-content">
                                        <div class="stat-label">Storage Used</div>
                                        <div class="stat-number" id="totalSize">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="main-layout">
                            <div class="media-sidebar">
                                <div class="sidebar-header">
                                    <h2>Folders</h2>
                                    <button class="btn btn-primary" id="createFolderBtn">+ New</button>
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
                                        <button class="btn btn-secondary" id="renameFolderBtn">Rename</button>
                                        <button class="btn btn-danger" id="deleteFolderBtn">Delete</button>
                                        <button class="btn btn-success" id="uploadBtn">Upload Images</button>
                                    </div>
                                </div>
                                <div class="gallery-content" id="galleryContent">
                                    <div class="form-row media-toolbar" id="mediaToolbar" style="display:none;">
                                        <div class="form-group">
                                            <label class="form-label" for="sort-by">Sort By:</label>
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
                                            <label class="form-label" for="sort-order">Order:</label>
                                            <select id="sort-order" class="form-select w-auto">
                                                <option value="asc">Ascending</option>
                                                <option value="desc">Descending</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="view-type">View:</label>
                                            <select id="view-type" class="form-select w-auto">
                                                <option value="extra-large">Extra Large</option>
                                                <option value="large">Large</option>
                                                <option value="medium" selected>Medium</option>
                                                <option value="small">Small</option>
                                                <option value="details">Details</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="items-per-page">Items/Page:</label>
                                            <select id="items-per-page" class="form-select w-auto">
                                                <option value="8">8</option>
                                                <option value="12" selected>12</option>
                                                <option value="24">24</option>
                                                <option value="48">48</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="empty-state" id="selectFolderState">
                                        <h3>Select a folder to view images</h3>
                                        <p>Choose a folder from the sidebar to manage its images</p>
                                    </div>
                                    <div class="empty-state" id="emptyFolderState" style="display: none;">
                                        <h3>No images in this folder</h3>
                                        <p>Click "Upload Images" to add some photos</p>
                                    </div>
                                    <div class="image-grid" id="imageGrid" style="display: none;"></div>
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
                        <div class="loading"></div>
                    </div>
                </div>
