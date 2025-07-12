// File: mediaPicker.js
let basePath = '';
let mediaPickerModal;
let pickerFolderList;
let pickerImageGrid;
let pickerCloseBtn;
let pickerFileInput;
let pickerUploadDrop;
let pickerEditModal;
let pickerEditImage;
let pickerScale;
let pickerEditSave;
let pickerEditCancel;
let cropper = null;
let currentFolder = null;
let currentEditId = null;
let pickerTargetId = null;

export function initMediaPicker(options = {}) {
  basePath = options.basePath || '';
  mediaPickerModal = document.getElementById('mediaPickerModal');
  pickerFolderList = document.getElementById('pickerFolderList');
  pickerImageGrid = document.getElementById('pickerImageGrid');
  pickerCloseBtn = document.getElementById('mediaPickerClose');
  pickerFileInput = document.getElementById('pickerFileInput');
  pickerUploadDrop = document.getElementById('pickerUploadDrop');
  pickerEditModal = document.getElementById('pickerEditModal');
  pickerEditImage = document.getElementById('pickerEditImage');
  pickerScale = document.getElementById('pickerScale');
  pickerEditSave = document.getElementById('pickerEditSave');
  pickerEditCancel = document.getElementById('pickerEditCancel');

  if (pickerCloseBtn) pickerCloseBtn.addEventListener('click', closeMediaPicker);
  if (mediaPickerModal) {
    mediaPickerModal.addEventListener('click', (e) => {
      if (e.target === mediaPickerModal) closeMediaPicker();
    });
  }

  if (pickerUploadDrop) {
    pickerUploadDrop.addEventListener('click', () => {
      if (pickerFileInput) pickerFileInput.click();
    });
    pickerUploadDrop.addEventListener('dragover', (e) => {
      e.preventDefault();
      pickerUploadDrop.classList.add('drag-over');
    });
    pickerUploadDrop.addEventListener('dragleave', () => {
      pickerUploadDrop.classList.remove('drag-over');
    });
    pickerUploadDrop.addEventListener('drop', (e) => {
      e.preventDefault();
      pickerUploadDrop.classList.remove('drag-over');
      uploadFiles(e.dataTransfer.files);
    });
  }

  if (pickerFileInput) {
    pickerFileInput.addEventListener('change', () => {
      uploadFiles(pickerFileInput.files);
      pickerFileInput.value = '';
    });
  }

  if (pickerFolderList) {
    pickerFolderList.addEventListener('click', (e) => {
      const li = e.target.closest('li[data-folder]');
      if (li) selectPickerFolder(li.dataset.folder);
    });
  }

  if (pickerImageGrid) {
    pickerImageGrid.addEventListener('click', (e) => {
      const img = e.target.closest('img[data-file]');
      if (img) {
        const input = document.getElementById(pickerTargetId);
        if (input) input.value = img.dataset.file;
        closeMediaPicker();
      }
    });
  }

  if (pickerEditCancel) pickerEditCancel.addEventListener('click', closeEdit);
  if (pickerEditSave) pickerEditSave.addEventListener('click', saveEditedImage);
  if (pickerScale) {
    pickerScale.addEventListener('input', () => {
      if (cropper) cropper.zoomTo(parseFloat(pickerScale.value));
    });
  }
}

export function openMediaPicker(targetId) {
  pickerTargetId = targetId;
  if (mediaPickerModal) {
    mediaPickerModal.classList.add('active');
    loadPickerFolders();
  }
}

export function closeMediaPicker() {
  pickerTargetId = null;
  if (mediaPickerModal) {
    mediaPickerModal.classList.remove('active');
  }
  if (pickerImageGrid) pickerImageGrid.innerHTML = '';
  if (pickerFolderList) pickerFolderList.innerHTML = '';
}

function loadPickerFolders() {
  fetch(basePath + '/CMS/modules/media/list_media.php')
    .then((r) => r.json())
    .then((data) => {
      if (!pickerFolderList) return;
      pickerFolderList.innerHTML = '';
      (data.folders || []).forEach((f) => {
        const li = document.createElement('li');
        li.textContent = f;
        li.dataset.folder = f;
        pickerFolderList.appendChild(li);
      });
    });
}

function selectPickerFolder(folder) {
  currentFolder = folder;
  if (pickerFolderList) {
    pickerFolderList.querySelectorAll('li').forEach((li) => {
      li.classList.toggle('active', li.dataset.folder === folder);
    });
  }
  fetch(basePath + '/CMS/modules/media/list_media.php?folder=' + encodeURIComponent(folder))
    .then((r) => r.json())
    .then((data) => {
      if (!pickerImageGrid) return;
      pickerImageGrid.innerHTML = '';
      const cmsBase = basePath + '/CMS';
      (data.media || []).forEach((img) => {
        const src = cmsBase + '/' + (img.thumbnail ? img.thumbnail : img.file);
        const full = cmsBase + '/' + img.file;
        const item = document.createElement('div');
        item.className = 'picker-image-item';
        const el = document.createElement('img');
        el.src = src;
        el.dataset.file = full;
        el.dataset.id = img.id;
        item.appendChild(el);
        const overlay = document.createElement('div');
        overlay.className = 'picker-image-overlay';
        const edit = document.createElement('button');
        edit.className = 'edit-btn';
        edit.textContent = '✎';
        edit.addEventListener('click', (e) => {
          e.stopPropagation();
          openEdit(img.id, full);
        });
        overlay.appendChild(edit);
        item.appendChild(overlay);
        pickerImageGrid.appendChild(item);
      });
    });
}

function uploadFiles(files) {
  if (!currentFolder || !files.length) return;
  const fd = new FormData();
  Array.from(files).forEach((f) => fd.append('files[]', f));
  fd.append('folder', currentFolder);
  fd.append('tags', '');
  fetch(basePath + '/CMS/modules/media/upload_media.php', {
    method: 'POST',
    body: fd,
  }).then(() => {
    loadPickerFolders();
    selectPickerFolder(currentFolder);
  });
}

function openEdit(id, src) {
  currentEditId = id;
  if (!pickerEditModal || !pickerEditImage) return;
  pickerEditImage.src = src;
  pickerEditModal.classList.add('active');
  if (cropper) cropper.destroy();
  cropper = new Cropper(pickerEditImage, { viewMode: 1 });
  if (pickerScale) pickerScale.value = 1;
}

function closeEdit() {
  currentEditId = null;
  if (pickerEditModal) pickerEditModal.classList.remove('active');
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
}

function saveEditedImage() {
  if (!cropper || !currentEditId) return;
  const canvas = cropper.getCroppedCanvas();
  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
  const fd = new FormData();
  fd.append('id', currentEditId);
  fd.append('image', dataUrl);
  fd.append('new_version', window.confirm('Create a new version?') ? '1' : '0');
  fd.append('format', 'jpeg');
  fetch(basePath + '/CMS/modules/media/crop_media.php', {
    method: 'POST',
    body: fd,
  }).then(() => {
    closeEdit();
    loadPickerFolders();
    if (currentFolder) selectPickerFolder(currentFolder);
  });
}
