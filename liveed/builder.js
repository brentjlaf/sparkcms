import { initDragDrop, addBlockControls } from './modules/dragDrop.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import { initUndoRedo } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';

let allBlockFiles = [];

function renderPalette(palette, files = []) {
  const container = palette.querySelector('.palette-items');
  if (!container) return;
  container.innerHTML = '';

  const groups = {};
  files.forEach((f) => {
    if (!f.endsWith('.php')) return;
    const base = f.replace(/\.php$/, '');
    const parts = base.split('.');
    const group = parts.shift();
    const label = parts.join(' ') || group;
    if (!groups[group]) groups[group] = [];
    groups[group].push({ file: f, label });
  });

  Object.keys(groups)
    .sort()
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
          wrap.appendChild(item);
        });

      details.appendChild(wrap);
      container.appendChild(details);
    });
}

function showModal(message) {
  const modal = document.createElement('div');
  modal.className = 'modal active';
  modal.innerHTML =
    '<div class="modal-content">' +
    '<p>' + message + '</p>' +
    '<div class="modal-footer">' +
    '<button class="btn btn-primary ok">OK</button>' +
    '</div></div>';
  document.body.appendChild(modal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) modal.remove();
  });
  modal.querySelector('.ok').addEventListener('click', () => {
    modal.remove();
  });
}

function savePage(showAlert = true) {
  const canvas = document.getElementById('canvas');
  const html = canvas.innerHTML;
  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', html);
  fetch(window.builderBase + '/liveed/save-content.php', {
    method: 'POST',
    body: fd,
  })
    .then((r) => r.text())
    .then(() => {
      if (showAlert) showModal('Saved');
    });
}

document.addEventListener('DOMContentLoaded', () => {
  const canvas = document.getElementById('canvas');
  const palette = document.querySelector('.block-palette');
  const settingsPanel = document.getElementById('settingsPanel');
  const saveBtn = document.getElementById('saveBtn');

  initSettings({ canvas, settingsPanel, savePage });

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

  initUndoRedo({ canvas, onChange: () => savePage(false) });
  initWysiwyg(canvas, true);

  canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);

  function updateCanvasPlaceholder() {
    const placeholder = canvas.querySelector('.canvas-placeholder');
    if (!placeholder) return;
    const hasBlocks = canvas.querySelector('.block-wrapper');
    placeholder.style.display = hasBlocks ? 'none' : '';
  }

  updateCanvasPlaceholder();

  document.addEventListener('canvasUpdated', updateCanvasPlaceholder);

  canvas.addEventListener('click', (e) => {
    const block = e.target.closest('.block-wrapper');
    if (!block) return;
    if (e.target.closest('.block-controls .edit')) {
      openSettings(block);
    } else if (e.target.closest('.block-controls .delete')) {
      confirmDelete('Delete this block?').then((ok) => {
        if (ok) {
          block.remove();
          updateCanvasPlaceholder();
        }
      });
    }
  });

  if (saveBtn) saveBtn.addEventListener('click', savePage);

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
});
