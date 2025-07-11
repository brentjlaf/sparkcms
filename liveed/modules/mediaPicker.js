let basePath = '';
let mediaPickerModal;
let pickerFolderList;
let pickerImageGrid;
let pickerCloseBtn;
let pickerTargetId = null;

export function initMediaPicker(options = {}) {
  basePath = options.basePath || '';
  mediaPickerModal = document.getElementById('mediaPickerModal');
  pickerFolderList = document.getElementById('pickerFolderList');
  pickerImageGrid = document.getElementById('pickerImageGrid');
  pickerCloseBtn = document.getElementById('mediaPickerClose');

  if (pickerCloseBtn) pickerCloseBtn.addEventListener('click', closeMediaPicker);
  if (mediaPickerModal) {
    mediaPickerModal.addEventListener('click', (e) => {
      if (e.target === mediaPickerModal) closeMediaPicker();
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
        const el = document.createElement('img');
        el.src = src;
        el.dataset.file = full;
        pickerImageGrid.appendChild(el);
      });
    });
}
