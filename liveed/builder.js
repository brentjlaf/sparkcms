// File: builder.js
import { initAutosave } from './modules/autosave.js';
import { initDragDrop, addBlockControls } from './modules/dragDrop.js';
import {
  initSettings,
  openSettings,
  applyStoredSettings,
  confirmDelete,
} from './modules/settings.js';
import { initPalette } from './modules/palette.js';
import { ensureBlockState, getSettings, setSetting } from './modules/state.js';
import { initUndoRedo } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';
import { initMediaPicker, openMediaPicker } from './modules/mediaPicker.js';
import { initPreview } from './modules/preview.js';
import { executeScripts } from "./modules/executeScripts.js";

document.addEventListener('DOMContentLoaded', () => {
  const canvas = document.getElementById('canvas');
  const palette = document.querySelector('.block-palette');
  const settingsPanel = document.getElementById('settingsPanel');
  const previewContainer = document.querySelector('.canvas-container');
  const previewButtons = document.querySelectorAll('.preview-toolbar button');
  const previewModal = document.getElementById('previewModal');
  const previewFrame = document.getElementById('previewFrame');
  const closePreview = document.getElementById('closePreview');
  const previewWrapper = previewModal
    ? previewModal.querySelector('.frame-wrapper')
    : null;
  const builderEl = document.querySelector('.builder');
  const viewToggle = document.getElementById('viewModeToggle');
  const paletteHeader = palette ? palette.querySelector('.builder-header') : null;
  const statusEl = document.getElementById('saveStatus');
  const lastSavedEl = document.getElementById('lastSavedTime');

  const { scheduleSave, storeDraft, saveNow } = initAutosave({
    canvas,
    statusEl,
    lastSavedEl,
    builderPageId: window.builderPageId,
    builderBase: window.builderBase,
    lastModified: window.builderLastModified || 0,
  });

  initPalette({ paletteEl: palette, builderBase: window.builderBase });

  initPreview({
    container: previewContainer,
    buttons: previewButtons,
    modal: previewModal,
    frame: previewFrame,
    closeButton: closePreview,
    wrapper: previewWrapper,
    builderBase: window.builderBase,
    builderSlug: window.builderSlug,
  });

  // Restore palette position
  const storedPos = palette ? localStorage.getItem('palettePosition') : null;
  if (palette && storedPos) {
    try {
      const pos = JSON.parse(storedPos);
      if (pos.left) palette.style.left = pos.left;
      if (pos.top) palette.style.top = pos.top;
    } catch (e) {}
  }

  // Palette dragging
  if (palette && paletteHeader) {
    let dragging = false;
    let offsetX = 0;
    let offsetY = 0;
    const SNAP_THRESHOLD = 30; // pixels from left edge to trigger snapping
    const onMove = (e) => {
      if (!dragging) return;
      palette.style.left = e.clientX - offsetX + 'px';
      palette.style.top = e.clientY - offsetY + 'px';
    };
    const onUp = () => {
      if (!dragging) return;
      dragging = false;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      const rect = palette.getBoundingClientRect();
      if (rect.left < SNAP_THRESHOLD) {
        palette.style.left = '0px';
        palette.style.top = '0px';
      }
      localStorage.setItem(
        'palettePosition',
        JSON.stringify({ left: palette.style.left, top: palette.style.top })
      );
    };
    paletteHeader.addEventListener('mousedown', (e) => {
      dragging = true;
      const rect = palette.getBoundingClientRect();
      offsetX = e.clientX - rect.left;
      offsetY = e.clientY - rect.top;
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  if (viewToggle && builderEl) {
    viewToggle.addEventListener('click', () => {
      const viewing = builderEl.classList.toggle('view-mode');
      viewToggle.innerHTML = viewing
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';
      if (viewing && settingsPanel) {
        settingsPanel.classList.remove('open');
      }
    });
  }

  initSettings({ canvas, settingsPanel, savePage: scheduleSave });

  if (canvas && palette) {
    initDragDrop({
      palette,
      canvas,
      basePath: window.builderBase,
      loggedIn: true,
      openSettings,
      applyStoredSettings,
    });
  }

  const history = canvas
    ? initUndoRedo({ canvas, onChange: scheduleSave, maxHistory: 15 })
    : null;
  const undoBtn = palette ? palette.querySelector('.undo-btn') : null;
  const redoBtn = palette ? palette.querySelector('.redo-btn') : null;
  const historyBtn = palette ? palette.querySelector('.page-history-btn') : null;
  const saveBtn = palette ? palette.querySelector('.manual-save-btn') : null;
  const historyPanel = document.getElementById('historyPanel');
  if (historyPanel) {
    historyPanel.classList.remove('open');
    historyPanel.style.left = '0px';
  }
  if (undoBtn && history) undoBtn.addEventListener('click', () => history.undo());
  if (redoBtn && history) redoBtn.addEventListener('click', () => history.redo());
  if (saveBtn) saveBtn.addEventListener('click', () => saveNow());
  if (historyBtn && historyPanel && palette) {
    const closeBtn = historyPanel.querySelector('.close-btn');
    const renderHistory = () => {
      fetch(`${window.builderBase}/liveed/get-history.php?id=${window.builderPageId}`)
        .then((r) => {
          if (!r.ok) throw new Error('fetch failed');
          return r.json();
        })
        .then((data) => {
          const cont = historyPanel.querySelector('.history-content');
          cont.innerHTML = '';
          if (data.history && data.history.length) {
            const ul = document.createElement('ul');
            const entries = data.history
              .slice()
              .sort((a, b) => b.time - a.time);
            entries.forEach((h) => {
              const li = document.createElement('li');
              const d = new Date(h.time * 1000);
              const action = h.action ? ' - ' + h.action : '';
              li.textContent = d.toLocaleString() + ' - ' + h.user + action;
              ul.appendChild(li);
            });
            cont.appendChild(ul);
          } else {
            cont.textContent = 'No history yet.';
          }
        })
        .catch(() => {
          const cont = historyPanel.querySelector('.history-content');
          cont.textContent = 'Error loading history.';
        });
    };
    historyBtn.addEventListener('click', () => {
      const rect = palette.getBoundingClientRect();
      historyPanel.style.left = rect.right + 'px';
      historyPanel.style.top = rect.top + 'px';
      renderHistory();
      historyPanel.classList.add('open');
    });
    if (closeBtn)
      closeBtn.addEventListener('click', () => {
        historyPanel.classList.remove('open');
        historyPanel.style.left = '0px';
      });
  }

  if (canvas) {
    initWysiwyg(canvas, true);
  }
  initMediaPicker({ basePath: window.builderBase });
  window.openMediaPicker = openMediaPicker;

  if (canvas) {
    canvas.addEventListener('input', scheduleSave);
    canvas.addEventListener('change', scheduleSave);

    canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);
    executeScripts(canvas);

    function updateCanvasPlaceholder() {
      const placeholder = canvas.querySelector('.canvas-placeholder');
      if (!placeholder) return;
      const hasBlocks = canvas.querySelector('.block-wrapper');
      placeholder.style.display = hasBlocks ? 'none' : '';
    }

    updateCanvasPlaceholder();

    document.addEventListener('canvasUpdated', updateCanvasPlaceholder);

    canvas.addEventListener('click', (e) => {
      if (builderEl && builderEl.classList.contains('view-mode')) return;
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
        executeScripts(clone);
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
  }

  document.addEventListener('canvasUpdated', scheduleSave);

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

  window.addEventListener('beforeunload', () => {
    storeDraft();
  });
});
