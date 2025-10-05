// File: mediaPicker.js
const DEFAULT_CACHE_TTL = 30000; // 30 seconds

export class MediaPicker {
  #basePath;
  #fetch;
  #document;
  #modal;
  #folderList;
  #imageGrid;
  #closeBtn;
  #editModal;
  #editImage;
  #scaleInput;
  #editSaveBtn;
  #editCancelBtn;
  #feedback;
  #cropper = null;
  #currentFolder = null;
  #currentEditId = null;
  #targetId = null;
  #cacheTTL;
  #folderCache = { data: null, time: 0 };
  #imageCache = new Map();

  constructor(options = {}) {
    this.#document =
      options.document || (typeof document !== 'undefined' ? document : null);
    if (!this.#document) {
      throw new Error('MediaPicker requires a document reference.');
    }

    const fetchImpl =
      options.fetchImpl || (typeof fetch === 'function' ? fetch : null);
    if (!fetchImpl) {
      throw new Error('MediaPicker requires a fetch implementation.');
    }

    this.#fetch = fetchImpl;
    this.#basePath = options.basePath || '';
    this.#cacheTTL =
      typeof options.cacheTTL === 'number' ? options.cacheTTL : DEFAULT_CACHE_TTL;

    this.#modal =
      options.modal || this.#document.getElementById('mediaPickerModal');
    this.#folderList =
      options.folderList || this.#document.getElementById('pickerFolderList');
    this.#imageGrid =
      options.imageGrid || this.#document.getElementById('pickerImageGrid');
    this.#closeBtn =
      options.closeBtn || this.#document.getElementById('mediaPickerClose');
    this.#editModal =
      options.editModal || this.#document.getElementById('pickerEditModal');
    this.#editImage =
      options.editImage || this.#document.getElementById('pickerEditImage');
    this.#scaleInput =
      options.scaleInput || this.#document.getElementById('pickerScale');
    this.#editSaveBtn =
      options.editSaveBtn || this.#document.getElementById('pickerEditSave');
    this.#editCancelBtn =
      options.editCancelBtn || this.#document.getElementById('pickerEditCancel');
    this.#feedback =
      options.feedbackEl || this.#modal?.querySelector('.picker-feedback') || null;

    this.#bindEvents();
  }

  open(targetId) {
    this.#targetId = targetId;
    if (!this.#modal) return;

    this.#modal.classList.add('active');
    this.#clearError();
    this.#loadFolders().then(() => {
      if (this.#currentFolder) {
        this.#selectFolder(this.#currentFolder);
      }
    });
  }

  close() {
    this.#targetId = null;
    if (this.#modal) {
      this.#modal.classList.remove('active');
    }
    if (this.#imageGrid) this.#imageGrid.innerHTML = '';
    if (this.#folderList) this.#folderList.innerHTML = '';
    this.#clearError();
  }

  #bindEvents() {
    if (this.#closeBtn) {
      this.#closeBtn.addEventListener('click', () => this.close());
    }

    if (this.#modal) {
      this.#modal.addEventListener('click', (event) => {
        if (event.target === this.#modal) {
          this.close();
        }
      });
    }

    if (this.#folderList) {
      this.#folderList.addEventListener('click', this.#handleFolderClick);
    }

    if (this.#imageGrid) {
      this.#imageGrid.addEventListener('click', this.#handleImageClick);
    }

    if (this.#editCancelBtn) {
      this.#editCancelBtn.addEventListener('click', this.#closeEdit);
    }

    if (this.#editSaveBtn) {
      this.#editSaveBtn.addEventListener('click', this.#saveEditedImage);
    }

    if (this.#scaleInput) {
      this.#scaleInput.addEventListener('input', this.#handleScaleChange);
    }
  }

  #handleFolderClick = (event) => {
    const li = event.target.closest('li[data-folder]');
    if (!li) return;
    this.#selectFolder(li.dataset.folder);
  };

  #handleImageClick = (event) => {
    const img = event.target.closest('img[data-file]');
    if (!img) return;
    if (!this.#targetId) {
      this.#handleError('No target input selected.', new Error('Missing target'));
      return;
    }

    const targetInput = this.#document.getElementById(this.#targetId);
    if (!targetInput) {
      this.#handleError(
        'Could not find the input associated with the media picker.',
        new Error('Missing input element')
      );
      return;
    }

    targetInput.value = img.dataset.file;
    targetInput.dispatchEvent(new Event('input', { bubbles: true }));
    targetInput.dispatchEvent(new Event('change', { bubbles: true }));
    this.close();
  };

  #handleScaleChange = () => {
    if (!this.#cropper || !this.#scaleInput) return;
    const zoom = parseFloat(this.#scaleInput.value);
    if (!Number.isNaN(zoom)) {
      this.#cropper.zoomTo(zoom);
    }
  };

  async #loadFolders() {
    const data = await this.#getFolderData();
    if (!data) {
      this.#renderFolders({ folders: [] });
      return null;
    }
    this.#clearError();
    this.#renderFolders(data);
    return data;
  }

  async #selectFolder(folder) {
    this.#currentFolder = folder;
    if (this.#folderList) {
      this.#folderList.querySelectorAll('li').forEach((li) => {
        li.classList.toggle('active', li.dataset.folder === folder);
      });
    }

    const data = await this.#getMediaData(folder);
    if (!data) {
      this.#renderMedia({ media: [] });
      return;
    }

    this.#clearError();
    this.#renderMedia(data);
  }

  #renderFolders(data) {
    if (!this.#folderList) return;

    this.#folderList.innerHTML = '';
    const fragment = this.#document.createDocumentFragment();
    const cmsBase = this.#basePath + '/CMS';
    const folders = Array.isArray(data?.folders) ? data.folders : [];

    folders.forEach((folder) => {
      const name = typeof folder === 'string' ? folder : folder.name;
      if (!name) return;
      const li = this.#document.createElement('li');
      li.dataset.folder = name;
      li.className = 'picker-folder-item';
      if (name === this.#currentFolder) {
        li.classList.add('active');
      }

      const thumbPath = folder && folder.thumbnail ? folder.thumbnail : null;
      if (thumbPath) {
        const img = this.#document.createElement('img');
        img.src = cmsBase + '/' + thumbPath;
        img.alt = name;
        li.appendChild(img);
      }

      const label = this.#document.createElement('span');
      label.textContent = name;
      li.appendChild(label);
      fragment.appendChild(li);
    });

    this.#folderList.appendChild(fragment);
  }

  #renderMedia(data) {
    if (!this.#imageGrid) return;

    this.#imageGrid.innerHTML = '';
    const fragment = this.#document.createDocumentFragment();
    const cmsBase = this.#basePath + '/CMS';
    const mediaItems = Array.isArray(data?.media) ? data.media : [];

    if (!mediaItems.length) {
      const emptyState = this.#document.createElement('div');
      emptyState.className = 'picker-empty';
      emptyState.textContent = 'No media available in this folder.';
      this.#imageGrid.appendChild(emptyState);
      return;
    }

    mediaItems.forEach((item) => {
      const src = cmsBase + '/' + (item.thumbnail ? item.thumbnail : item.file);
      const full = cmsBase + '/' + item.file;
      const wrapper = this.#document.createElement('div');
      wrapper.className = 'picker-image-item';

      const img = this.#document.createElement('img');
      img.src = src;
      img.dataset.file = full;
      img.dataset.id = item.id;
      wrapper.appendChild(img);

      const overlay = this.#document.createElement('div');
      overlay.className = 'picker-image-overlay';
      const editBtn = this.#document.createElement('button');
      editBtn.className = 'edit-btn';
      editBtn.type = 'button';
      editBtn.textContent = '✎';
      editBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        this.#openEdit(item.id, full);
      });
      overlay.appendChild(editBtn);
      wrapper.appendChild(overlay);
      fragment.appendChild(wrapper);
    });

    this.#imageGrid.appendChild(fragment);
  }

  #openEdit(id, src) {
    this.#currentEditId = id;
    if (!this.#editModal || !this.#editImage) return;

    this.#editImage.src = src;
    this.#editModal.classList.add('active');
    if (this.#cropper) {
      this.#cropper.destroy();
    }
    this.#cropper = new Cropper(this.#editImage, { viewMode: 1 });
    if (this.#scaleInput) {
      this.#scaleInput.value = '1';
    }
  }

  #closeEdit = () => {
    this.#currentEditId = null;
    if (this.#editModal) {
      this.#editModal.classList.remove('active');
    }
    if (this.#cropper) {
      this.#cropper.destroy();
      this.#cropper = null;
    }
  };

  #saveEditedImage = async () => {
    if (!this.#cropper || !this.#currentEditId) return;

    try {
      const canvas = this.#cropper.getCroppedCanvas();
      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      const formData = new FormData();
      formData.append('id', this.#currentEditId);
      formData.append('image', dataUrl);
      const shouldCreateNew =
        typeof window !== 'undefined'
          ? window.confirm('Create a new version?')
          : confirm('Create a new version?');
      formData.append('new_version', shouldCreateNew ? '1' : '0');
      formData.append('format', 'jpeg');

      const response = await this.#fetch(
        this.#basePath + '/CMS/modules/media/crop_media.php',
        {
          method: 'POST',
          body: formData,
        }
      );

      if (!response.ok) {
        throw new Error(`Crop request failed with status ${response.status}`);
      }

      this.#closeEdit();
      await this.#loadFolders();
      if (this.#currentFolder) {
        await this.#selectFolder(this.#currentFolder);
      }
    } catch (error) {
      this.#handleError('Failed to save edited image.', error);
    }
  };

  async #getFolderData() {
    if (this.#isCacheValid(this.#folderCache)) {
      return this.#folderCache.data;
    }

    const url = this.#basePath + '/CMS/modules/media/list_media.php';
    const data = await this.#requestJson(url, 'Failed to load folders.');
    if (data) {
      this.#folderCache = { data, time: Date.now() };
    }
    return data;
  }

  async #getMediaData(folder) {
    const cache = this.#imageCache.get(folder);
    if (this.#isCacheValid(cache)) {
      return cache.data;
    }

    const url =
      this.#basePath +
      '/CMS/modules/media/list_media.php?folder=' +
      encodeURIComponent(folder);
    const data = await this.#requestJson(
      url,
      `Failed to load media for "${folder}".`
    );
    if (data) {
      this.#imageCache.set(folder, { data, time: Date.now() });
    }
    return data;
  }

  async #requestJson(url, message) {
    try {
      const response = await this.#fetch(url);
      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }
      return await response.json();
    } catch (error) {
      this.#handleError(message, error);
      return null;
    }
  }

  #isCacheValid(entry) {
    return !!(entry && entry.data && Date.now() - entry.time < this.#cacheTTL);
  }

  #handleError(message, error) {
    console.error(message, error);
    this.#showError(message);
  }

  #showError(message) {
    const el = this.#ensureFeedbackElement();
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
  }

  #clearError() {
    if (!this.#feedback) return;
    this.#feedback.textContent = '';
    this.#feedback.hidden = true;
  }

  #ensureFeedbackElement() {
    if (this.#feedback) {
      return this.#feedback;
    }
    if (!this.#modal) return null;
    const container = this.#modal.querySelector('.picker-main');
    if (!container) return null;
    const el = this.#document.createElement('div');
    el.className = 'picker-feedback';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.hidden = true;
    container.insertBefore(el, container.firstChild);
    this.#feedback = el;
    return el;
  }
}

export function createMediaPicker(options = {}) {
  const picker = new MediaPicker(options);
  return {
    open: picker.open.bind(picker),
    close: picker.close.bind(picker),
    instance: picker,
  };
}

export { DEFAULT_CACHE_TTL };
