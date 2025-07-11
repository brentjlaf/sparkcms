import { initDragDrop, addBlockControls } from './modules/dragDrop.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import { initUndoRedo } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';

function savePage() {
  const canvas = document.getElementById('canvas');
  const html = canvas.innerHTML;
  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', html);
  fetch(window.builderBase + '/liveed/save-content.php', {
    method: 'POST',
    body: fd,
  }).then((r) => r.text()).then(() => {
    alert('Saved');
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const canvas = document.getElementById('canvas');
  const palette = document.querySelector('.block-palette');
  const settingsPanel = document.getElementById('settingsPanel');
  const saveBtn = document.getElementById('saveBtn');

  initSettings({ canvas, settingsPanel, savePage });

  initDragDrop({
    palette,
    canvas,
    basePath: window.builderBase,
    loggedIn: true,
    openSettings,
    applyStoredSettings,
  });

  initUndoRedo({ canvas });
  initWysiwyg(canvas, true);

  canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);

  canvas.addEventListener('click', (e) => {
    const block = e.target.closest('.block-wrapper');
    if (!block) return;
    if (e.target.closest('.block-controls .edit')) {
      openSettings(block);
    } else if (e.target.closest('.block-controls .move-up')) {
      const prev = block.previousElementSibling;
      if (prev) block.parentNode.insertBefore(block, prev);
    } else if (e.target.closest('.block-controls .move-down')) {
      const next = block.nextElementSibling;
      if (next) block.parentNode.insertBefore(next, block);
    } else if (e.target.closest('.block-controls .delete')) {
      confirmDelete('Delete this block?').then((ok) => {
        if (ok) block.remove();
      });
    }
  });

  if (saveBtn) saveBtn.addEventListener('click', savePage);
});
