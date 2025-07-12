// File: builder.js
import { initDragDrop, addBlockControls } from './modules/dragDrop.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import { ensureBlockState, getSettings, setSetting } from './modules/state.js';
import { initUndoRedo } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';
import { initMediaPicker, openMediaPicker } from './modules/mediaPicker.js';

let allBlockFiles = [];
let favorites = [];
let gridActive = false;

function snapBlockToGrid(block) {
  const grid = 20;
  const cs = window.getComputedStyle(block);
  const mt = parseFloat(cs.marginTop) || 0;
  const mb = parseFloat(cs.marginBottom) || 0;
  block.style.marginTop = Math.round(mt / grid) * grid + 'px';
  block.style.marginBottom = Math.round(mb / grid) * grid + 'px';
}

function snapAllBlocks() {
  document.querySelectorAll('#canvas .block-wrapper').forEach(snapBlockToGrid);
}

function renderPalette(palette, files = []) {
  const container = palette.querySelector('.palette-items');
  if (!container) return;
  container.innerHTML = '';

  const favs = favorites;
  const groups = {};
  if (favs.length) groups.Favorites = [];
  files.forEach((f) => {
    if (!f.endsWith('.php')) return;
    const base = f.replace(/\.php$/, '');
    const parts = base.split('.');
    const group = parts.shift();
    const label = parts.join(' ') || group;
    if (!groups[group]) groups[group] = [];
    const info = { file: f, label };
    groups[group].push(info);
    if (favs.includes(f)) {
      groups.Favorites.push(info);
    }
  });

  Object.keys(groups)
    .sort((a, b) => (a === 'Favorites' ? -1 : b === 'Favorites' ? 1 : a.localeCompare(b)))
    .forEach((g) => {
      const details = document.createElement('details');
      details.className = 'palette-group';

      const summary = document.createElement('summary');
      summary.textContent = g.charAt(0).toUpperCase() + g.slice(1);
      details.appendChild(summary);

      const wrap = document.createElement('div');
      wrap.className = 'group-items';

      groups[g]
        .sort((a, b) => a.label.localeCompare(b.label))
        .forEach((it) => {
          const item = document.createElement('div');
          item.className = 'block-item';
          item.setAttribute('draggable', 'true');
          item.dataset.file = it.file;
          const label = it.label
            .replace(/[-_]/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());
          item.textContent = label;
          const favBtn = document.createElement('span');
          favBtn.className = 'fav-toggle';
          if (favs.includes(it.file)) favBtn.classList.add('active');
          favBtn.textContent = '★';
          favBtn.title = favs.includes(it.file) ? 'Unfavorite' : 'Favorite';
          favBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleFavorite(it.file);
          });
          item.appendChild(favBtn);
          wrap.appendChild(item);
        });

      details.appendChild(wrap);
      container.appendChild(details);
    });
}

function toggleFavorite(file) {
  const idx = favorites.indexOf(file);
  if (idx >= 0) {
    favorites.splice(idx, 1);
  } else {
    favorites.push(file);
  }
  localStorage.setItem('favoriteBlocks', JSON.stringify(favorites));
  const palette = document.querySelector('.block-palette');
  if (palette) renderPalette(palette, allBlockFiles);
}

let saveTimer;
function savePage() {
  const canvas = document.getElementById('canvas');
  const statusEl = document.getElementById('saveStatus');
  const html = canvas.innerHTML;
  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', html);
  if (statusEl) {
    statusEl.textContent = 'Saving...';
    statusEl.classList.add('saving');
    statusEl.classList.remove('error');
  }
  fetch(window.builderBase + '/liveed/save-content.php', {
    method: 'POST',
    body: fd,
  })
    .then((r) => {
      if (!r.ok) throw new Error('Save failed');
      return r.text();
    })
    .then(() => {
      if (statusEl) {
        statusEl.textContent = 'Saved';
        statusEl.classList.remove('saving');
      }
      setTimeout(() => {
        if (statusEl && statusEl.textContent === 'Saved') statusEl.textContent = '';
      }, 2000);
    })
    .catch(() => {
      if (statusEl) {
        statusEl.textContent = 'Error saving';
        statusEl.classList.add('error');
        statusEl.classList.remove('saving');
      }
    });
}

function scheduleSave() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(savePage, 200);
}

document.addEventListener('DOMContentLoaded', () => {
  const canvas = document.getElementById('canvas');
  const palette = document.querySelector('.block-palette');
  const settingsPanel = document.getElementById('settingsPanel');
  const previewContainer = document.querySelector('.canvas-container');
  const previewButtons = document.querySelectorAll('.preview-toolbar button');
  const gridToggle = document.getElementById('gridToggle');

  function setGridActive(on) {
    gridActive = on;
    if (canvas) {
      canvas.classList.toggle('grid-overlay', on);
    }
    if (on) snapAllBlocks();
    if (gridToggle) gridToggle.classList.toggle('active', on);
    localStorage.setItem('gridActive', on ? '1' : '0');
  }

  if (gridToggle) {
    gridToggle.addEventListener('click', () => setGridActive(!gridActive));
  }

  function updatePreview(size) {
    if (!previewContainer) return;
    previewContainer.classList.remove('preview-desktop', 'preview-tablet', 'preview-phone');
    previewContainer.classList.add('preview-' + size);
    previewButtons.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.size === size);
    });
  }

  previewButtons.forEach((btn) => {
    btn.addEventListener('click', () => updatePreview(btn.dataset.size));
  });

  updatePreview('desktop');

  favorites = JSON.parse(localStorage.getItem('favoriteBlocks') || '[]');

  if (localStorage.getItem('gridActive') === '1') {
    setGridActive(true);
  }

  initSettings({ canvas, settingsPanel, savePage: scheduleSave });

  const searchInput = palette.querySelector('.palette-search');

  fetch(window.builderBase + '/liveed/list-blocks.php')
    .then((r) => r.json())
    .then((data) => {
      allBlockFiles = data.blocks || [];
      renderPalette(palette, allBlockFiles);
    });

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const term = searchInput.value.toLowerCase();
      const filtered = allBlockFiles.filter((f) => f.toLowerCase().includes(term));
      renderPalette(palette, filtered);
    });
  }

  initDragDrop({
    palette,
    canvas,
    basePath: window.builderBase,
    loggedIn: true,
    openSettings,
    applyStoredSettings,
  });

  const history = initUndoRedo({ canvas, onChange: scheduleSave });
  const undoBtn = palette.querySelector('.undo-btn');
  const redoBtn = palette.querySelector('.redo-btn');
  const saveBtn = palette.querySelector('.manual-save-btn');
  if (undoBtn) undoBtn.addEventListener('click', () => history.undo());
  if (redoBtn) redoBtn.addEventListener('click', () => history.redo());
  if (saveBtn)
    saveBtn.addEventListener('click', () => {
      clearTimeout(saveTimer);
      savePage();
    });
  initWysiwyg(canvas, true);
  initMediaPicker({ basePath: window.builderBase });
  window.openMediaPicker = openMediaPicker;

  canvas.addEventListener('input', scheduleSave);
  canvas.addEventListener('change', scheduleSave);

  canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);

  function updateCanvasPlaceholder() {
    const placeholder = canvas.querySelector('.canvas-placeholder');
    if (!placeholder) return;
    const hasBlocks = canvas.querySelector('.block-wrapper');
    placeholder.style.display = hasBlocks ? 'none' : '';
  }

  updateCanvasPlaceholder();

  document.addEventListener('canvasUpdated', updateCanvasPlaceholder);
  document.addEventListener('canvasUpdated', scheduleSave);

  canvas.addEventListener('click', (e) => {
    const block = e.target.closest('.block-wrapper');
    if (!block) return;
    if (e.target.closest('.block-controls .edit')) {
      openSettings(block);
    } else if (e.target.closest('.block-controls .duplicate')) {
      const clone = block.cloneNode(true);
      clone.classList.remove('selected');
      delete clone.dataset.blockId;
      block.after(clone);
      ensureBlockState(clone);
      const settings = getSettings(block);
      for (const key in settings) {
        setSetting(clone, key, settings[key]);
      }
      addBlockControls(clone);
      applyStoredSettings(clone);
      if (gridActive) snapBlockToGrid(clone);
      document.dispatchEvent(new Event('canvasUpdated'));
    } else if (e.target.closest('.block-controls .delete')) {
      confirmDelete('Delete this block?').then((ok) => {
        if (ok) {
          block.remove();
          updateCanvasPlaceholder();
          scheduleSave();
        }
      });
    }
  });


  document.addEventListener('mouseover', (e) => {
    const handle = e.target.closest('.control.drag');
    if (handle) {
      const block = handle.closest('.block-wrapper');
      if (block) block.style.transform = 'scale(1.02)';
    }
  });

  document.addEventListener('mouseout', (e) => {
    const handle = e.target.closest('.control.drag');
    if (handle) {
      const block = handle.closest('.block-wrapper');
      if (block) block.style.transform = '';
    }
  });

  window.snapBlockToGrid = snapBlockToGrid;
  window.isGridSnapActive = () => gridActive;
});
