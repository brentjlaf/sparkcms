<?php
// File: liveed/templates/modals.php
?>
<div id="mediaPickerModal" class="modal">
    <div class="modal-content media-picker">
        <div class="picker-sidebar">
            <ul id="pickerFolderList"></ul>
        </div>
        <div class="picker-main">
            <div id="pickerImageGrid" class="picker-grid"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="mediaPickerClose">
                <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i>
                <span class="btn-label">Close</span>
            </button>
        </div>
    </div>
</div>
<div id="pickerEditModal" class="modal">
    <div class="modal-content">
        <div class="crop-container">
            <img id="pickerEditImage" src="" style="max-width:100%;">
        </div>
        <div class="modal-footer">
            <input type="range" id="pickerScale" min="0.5" max="3" step="0.1" value="1">
            <button class="btn btn-secondary" id="pickerEditCancel">
                <i class="fa-solid fa-circle-xmark btn-icon" aria-hidden="true"></i>
                <span class="btn-label">Cancel</span>
            </button>
            <button class="btn btn-primary" id="pickerEditSave">
                <i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i>
                <span class="btn-label">Save</span>
            </button>
        </div>
    </div>
</div>
<div id="previewModal" class="modal">
    <div class="modal-content preview-frame">
        <div class="frame-wrapper">
            <iframe id="previewFrame" src=""></iframe>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="closePreview">
                <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i>
                <span class="btn-label">Close</span>
            </button>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
