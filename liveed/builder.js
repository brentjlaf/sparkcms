import { initMediaPicker, openMediaPicker } from './modules/mediaPicker.js';
import { initWysiwyg } from './modules/wysiwyg.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import { initDragDrop, addBlockControls, setupDropArea } from './modules/dragDrop.js';
import { initUndoRedo } from './modules/undoRedo.js';

const palette = document.getElementById('palette');
const canvas = document.getElementById('canvas');
const loggedIn = typeof builderLoggedIn !== 'undefined' ? builderLoggedIn : true;
const scriptEl = document.currentScript || document.querySelector('script[src*="liveed/builder.js"]');
const autoBase = scriptEl ? scriptEl.src.replace(/\/liveed\/builder\.js(?:\?.*)?$/, '') : '';
const basePath = typeof builderBase !== 'undefined' ? builderBase : autoBase;
let settingsPanel = document.getElementById('settings-panel');
const saveStatus = document.getElementById('save-status');
const searchInput = document.getElementById('block-search');

function wrapUnwrappedBlocks() {
  if (!canvas) return;
  canvas.querySelectorAll('[data-tpl-tooltip]').forEach((el) => {
    if (el.closest('.block-wrapper')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'block-wrapper';
    const prev = el.previousElementSibling;
    const parent = el.parentNode;
    if (prev && prev.tagName && prev.tagName.toLowerCase() === 'templatesetting') {
      parent.insertBefore(wrapper, prev);
      wrapper.appendChild(prev);
    } else {
      parent.insertBefore(wrapper, el);
    }
    wrapper.appendChild(el);
    wrapper.dataset.original = wrapper.innerHTML;
    addBlockControls(wrapper);
  });
}

if (!settingsPanel) {
  settingsPanel = document.createElement('div');
  settingsPanel.id = 'settings-panel';
  settingsPanel.className = 'settings-panel';
  settingsPanel.innerHTML = '<h3 class="settings-title">Settings</h3><div class="settings-content"></div>';
  document.body.appendChild(settingsPanel);
}

function getCleanHtml() {
  const clone = canvas.cloneNode(true);
  clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
  clone.querySelectorAll('toggle').forEach((tog) => {
    if (tog.dataset.active === 'true') {
      while (tog.firstChild) tog.parentNode.insertBefore(tog.firstChild, tog);
    }
    tog.remove();
  });
  return clone.innerHTML;
}

function savePage() {
  if (!canvas) return;
  const id = canvas.dataset.id || '';
  const html = getCleanHtml();
  fetch(basePath + '/liveed/save-content.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id, content: html })
  })
    .then((r) => r.text())
    .then(() => {
      if (saveStatus) {
        saveStatus.textContent = 'Saved!';
        saveStatus.style.display = 'inline';
        setTimeout(() => {
          saveStatus.textContent = '';
          saveStatus.style.display = 'none';
        }, 2000);
      }
    });
}

initMediaPicker({ basePath });
initSettings({ canvas, settingsPanel, savePage });
initDragDrop({ palette, canvas, basePath, loggedIn, openSettings, applyStoredSettings });
initWysiwyg(canvas, loggedIn);

if (searchInput) {
  searchInput.addEventListener('input', () => {
    const term = searchInput.value.toLowerCase().trim();
    palette.querySelectorAll('.palette-section').forEach((section) => {
      let visible = false;
      section.querySelectorAll('.block-item').forEach((item) => {
        const match = item.textContent.toLowerCase().includes(term);
        item.style.display = match ? '' : 'none';
        if (match) visible = true;
      });
      section.style.display = visible ? '' : 'none';
    });
  });
}

function restoreControls() {
  canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);
  canvas.querySelectorAll('.drop-area').forEach(setupDropArea);
}

initUndoRedo({ canvas, restore: restoreControls });

wrapUnwrappedBlocks();
canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);
canvas.querySelectorAll('.drop-area').forEach(setupDropArea);

document.addEventListener('mousedown', (e) => {
  if (e.target.closest('.block-controls .drag')) {
    e.stopPropagation();
  }
});

document.addEventListener('click', (e) => {
  const block = e.target.closest('.block-wrapper');
  if (block) {
    e.stopPropagation();
    canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
    block.classList.add('selected');
  } else if (!e.target.closest('.settings-panel')) {
    canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
    if (settingsPanel) {
      settingsPanel.classList.remove('open');
      settingsPanel.block = null;
    }
  }
});

document.addEventListener('click', (e) => {
  const delBtn = e.target.closest('.control.delete');
  const editBtn = e.target.closest('.control.edit');
  if (delBtn) {
    e.stopPropagation();
    const block = delBtn.closest('.block-wrapper');
    if (block) {
      confirmDelete('Delete this block? Make sure you have saved the page first.')
        .then((ok) => { if (ok) block.remove(); });
    }
  } else if (editBtn) {
    e.stopPropagation();
    const block = editBtn.closest('.block-wrapper');
    if (block) openSettings(block);
  }
});

document.addEventListener('click', (e) => {
  const browse = e.target.closest('.browse-media');
  if (browse) {
    e.preventDefault();
    openMediaPicker(browse.dataset.target);
  }
});

if (palette) {
  palette.querySelectorAll('.palette-section-title').forEach((title) => {
    const section = title.parentElement;
    title.addEventListener('click', () => {
      section.classList.toggle('collapsed');
    });
  });
}
