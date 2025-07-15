// File: builder.js
import { initDragDrop, addBlockControls } from './modules/dragDrop.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import { ensureBlockState, getSettings, setSetting } from './modules/state.js';
import { initUndoRedo } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';
import { initMediaPicker, openMediaPicker } from './modules/mediaPicker.js';
import { initAccessibility, checkAccessibility } from './modules/accessibility.js';
import { executeScripts } from "./modules/executeScripts.js";

let allBlockFiles = [];
let favorites = [];
let builderDraftKey = '';
let lastSavedTimestamp = 0;
// Delay before auto-saving after a change. A longer delay prevents rapid
// successive saves while the user is still actively editing.
const SAVE_DEBOUNCE_DELAY = 1000;

function storeDraft() {
  const canvas = document.getElementById('canvas');
  if (!canvas) return;
  const data = {
    html: canvas.innerHTML,
    timestamp: Date.now(),
  };
  localStorage.setItem(builderDraftKey, JSON.stringify(data));
  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', data.html);
  fd.append('timestamp', data.timestamp);
  fetch(window.builderBase + '/liveed/save-draft.php', {
    method: 'POST',
    body: fd,
  }).catch(() => {});
}

function renderGroupItems(details) {
  const items = details.querySelector('.group-items');
  if (!items || details._rendered) return;
  const favs = favorites;
  const list = (details._items || []).sort((a, b) => a.label.localeCompare(b.label));
  list.forEach((it) => {
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
    items.appendChild(item);
  });
  details._rendered = true;
}

function animateAccordion(details) {
  const summary = details.querySelector('summary');
  const items = details.querySelector('.group-items');
  if (!summary || !items) return;
  if (!details.open) {
    items.style.display = 'none';
  } else {
    renderGroupItems(details);
  }
  summary.addEventListener('click', (e) => {
    e.preventDefault();
    const isOpen = details.open;
    if (isOpen) {
      details.open = false;
      items.style.display = 'none';
      items.innerHTML = '';
      details._rendered = false;
    } else {
      document.querySelectorAll('.palette-group[open]').forEach((other) => {
        if (other !== details) {
          other.open = false;
          const otherItems = other.querySelector('.group-items');
          if (otherItems) {
            otherItems.style.display = 'none';
            otherItems.innerHTML = '';
            other._rendered = false;
          }
        }
      });
      details.open = true;
      renderGroupItems(details);
      items.style.display = 'grid';
    }
  });
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

      details._items = groups[g].slice();
      details.appendChild(wrap);
      container.appendChild(details);
      animateAccordion(details);
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

function checkLinks(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const warnings = [];
  const checks = [];

  const check = (url, type) => {
    if (!url) return;
    try {
      const full = new URL(url, window.location.href).href;
      checks.push(
        fetch(full, { method: 'HEAD' })
          .then((r) => {
            if (!r.ok) warnings.push(`${type} ${url} returned ${r.status}`);
          })
          .catch(() => warnings.push(`${type} ${url} unreachable`))
      );
    } catch (e) {
      warnings.push(`${type} ${url} invalid`);
    }
  };

  doc.querySelectorAll('a[href]').forEach((el) => check(el.getAttribute('href'), 'Link'));
  doc.querySelectorAll('img[src]').forEach((el) => check(el.getAttribute('src'), 'Image'));

  return Promise.all(checks).then(() => warnings);
}

function generateSeoReport() {
  const canvas = document.getElementById('canvas');
  if (!canvas) return [];

  const head = document.head;
  const htmlEl = document.documentElement;
  const report = [];

  const titles = head.querySelectorAll('title');
  if (titles.length === 0) {
    report.push({ msg: 'Missing <code>&lt;title&gt;</code> tag.', error: true });
  } else {
    report.push({ msg: 'Title tag is present.', error: false });
    if (titles.length > 1) {
      report.push({ msg: 'Duplicate <code>&lt;title&gt;</code> tags found.', error: true });
    }
  }

  const desc = head.querySelectorAll('meta[name="description"]');
  if (desc.length === 0) {
    report.push({ msg: 'Missing meta description.', error: true });
  } else {
    report.push({ msg: 'Meta description is present.', error: false });
    if (desc.length > 1) {
      report.push({ msg: 'Duplicate meta description tags found.', error: true });
    }
  }

  if (!canvas.querySelector('h1')) {
    report.push({ msg: 'Missing <code>&lt;h1&gt;</code> tag.', error: true });
  } else {
    report.push({ msg: 'H1 tag is present.', error: false });
  }

  if (!canvas.querySelector('h2')) {
    report.push({
      msg: 'No <code>&lt;h2&gt;</code> tag found. Consider adding subheadings for better structure.',
      error: true,
    });
  } else {
    report.push({ msg: 'H2 tags are present.', error: false });
  }

  const imgsNoAlt = canvas.querySelectorAll('img:not([alt])');
  if (imgsNoAlt.length) {
    report.push({ msg: `${imgsNoAlt.length} image(s) missing alt attribute.`, error: true });
  } else {
    report.push({ msg: 'All images have alt attributes.', error: false });
  }

  const imgsNoLazy = canvas.querySelectorAll('img:not([loading])');
  if (imgsNoLazy.length) {
    report.push({
      msg: `${imgsNoLazy.length} image(s) missing lazy loading attribute (consider using loading="lazy").`,
      error: true,
    });
  } else {
    report.push({ msg: 'All images have lazy loading attribute.', error: false });
  }

  if (!head.querySelector('link[rel="canonical"]')) {
    report.push({ msg: 'Missing canonical tag.', error: true });
  } else {
    report.push({ msg: 'Canonical tag is present.', error: false });
  }

  if (!head.querySelector('link[rel="sitemap"]')) {
    report.push({ msg: 'Missing sitemap link.', error: true });
  } else {
    report.push({ msg: 'Sitemap link is present.', error: false });
  }

  if (!head.querySelector('meta[name="viewport"]')) {
    report.push({ msg: 'Missing viewport meta tag.', error: true });
  } else {
    report.push({ msg: 'Viewport meta tag is present.', error: false });
  }

  if (!htmlEl.getAttribute('lang')) {
    report.push({ msg: 'Missing language attribute on <code>&lt;html&gt;</code> tag.', error: true });
  } else {
    report.push({ msg: 'Language attribute is present on <code>&lt;html&gt;</code> tag.', error: false });
  }

  if (!head.querySelector('meta[name="robots"]')) {
    report.push({ msg: 'Missing meta robots tag.', error: true });
  } else {
    report.push({ msg: 'Meta robots tag is present.', error: false });
  }

  if (
    !head.querySelector('meta[property="og:title"]') ||
    !head.querySelector('meta[property="og:description"]') ||
    !head.querySelector('meta[property="og:image"]')
  ) {
    report.push({
      msg: 'One or more Open Graph tags (og:title, og:description, og:image) are missing.',
      error: true,
    });
  } else {
    report.push({ msg: 'Open Graph tags are present.', error: false });
  }

  if (!head.querySelector('meta[name="twitter:card"]')) {
    report.push({ msg: 'Missing Twitter Card meta tag (twitter:card).', error: true });
  } else {
    report.push({ msg: 'Twitter Card meta tag is present.', error: false });
  }

  if (!head.querySelector('script[type="application/ld+json"]')) {
    report.push({ msg: 'Missing structured data (JSON-LD).', error: true });
  } else {
    report.push({ msg: 'Structured data (JSON-LD) is present.', error: false });
  }

  return report;
}

function openSeoModal() {
  const modal = document.getElementById('seoModal');
  const list = document.getElementById('seoIssues');
  if (!modal || !list) return;

  const report = generateSeoReport();
  list.innerHTML = report
    .map((r) => `<li class="${r.error ? 'error' : 'pass'}">${r.msg}</li>`)
    .join('');

  modal.classList.add('active');

  const closeBtn = modal.querySelector('.close');
  const close = () => modal.classList.remove('active');
  if (closeBtn) closeBtn.addEventListener('click', close, { once: true });
  modal.addEventListener('click', (e) => {
    if (e.target === modal) close();
  }, { once: true });
}
function savePage() {
  const canvas = document.getElementById('canvas');
  const statusEl = document.getElementById('saveStatus');
  const html = canvas.innerHTML;

  if (statusEl) {
    statusEl.textContent = 'Checking links...';
    statusEl.classList.add('saving');
    statusEl.classList.remove('error');
  }

  checkLinks(html).then((warnings) => {
    if (warnings.length) {
      alert('Link issues found:\n' + warnings.join('\n'));
    }

    const fd = new FormData();
    fd.append('id', window.builderPageId);
    fd.append('content', html);

    if (statusEl) statusEl.textContent = 'Saving...';

    fetch(window.builderBase + '/liveed/save-content.php', {
      method: 'POST',
      body: fd,
    })
      .then((r) => {
        if (!r.ok) throw new Error('Save failed');
        return r.text();
      })
      .then(() => {
        localStorage.removeItem(builderDraftKey);
        lastSavedTimestamp = Date.now();
        if (statusEl) {
          statusEl.textContent = 'Saved';
          statusEl.classList.remove('saving');
        }
        const lastSavedEl = document.getElementById('lastSavedTime');
        if (lastSavedEl) {
          const now = new Date();
          lastSavedEl.textContent = 'Last saved: ' + now.toLocaleString();
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
  });
}

function scheduleSave() {
  clearTimeout(saveTimer);
  storeDraft();
  saveTimer = setTimeout(savePage, SAVE_DEBOUNCE_DELAY);
}

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
  const toggleBtn = palette.querySelector('.palette-toggle-btn');
  const dockBtn = palette.querySelector('.palette-dock-btn');
  const paletteHeader = palette.querySelector('.builder-header');

  builderDraftKey = 'builderDraft-' + window.builderPageId;
  lastSavedTimestamp = window.builderLastModified || 0;
  const draft = localStorage.getItem(builderDraftKey);
  if (draft) {
    try {
      const data = JSON.parse(draft);
      if (data.timestamp > lastSavedTimestamp && data.html) {
        canvas.innerHTML = data.html;
        lastSavedTimestamp = data.timestamp;
      } else {
        localStorage.removeItem(builderDraftKey);
      }
    } catch (e) {
      localStorage.removeItem(builderDraftKey);
    }
  }

  fetch(
    window.builderBase + '/liveed/load-draft.php?id=' + window.builderPageId
  )
    .then((r) => (r.ok ? r.json() : null))
    .then((serverDraft) => {
      if (serverDraft && serverDraft.timestamp > lastSavedTimestamp) {
        canvas.innerHTML = serverDraft.content;
        lastSavedTimestamp = serverDraft.timestamp;
        localStorage.setItem(builderDraftKey, JSON.stringify(serverDraft));
      }
    })
    .catch(() => {});

  // Restore palette position
  const storedPos = localStorage.getItem('palettePosition');
  if (storedPos) {
    try {
      const pos = JSON.parse(storedPos);
      if (pos.left) palette.style.left = pos.left;
      if (pos.top) palette.style.top = pos.top;
    } catch (e) {}
  }

  // Collapse state
  const storedCollapsed = localStorage.getItem('paletteCollapsed') === '1';
  if (storedCollapsed) {
    palette.classList.add('collapsed');
    if (builderEl) builderEl.classList.add('palette-collapsed');
    if (toggleBtn) toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const collapsed = palette.classList.toggle('collapsed');
      if (builderEl) builderEl.classList.toggle('palette-collapsed', collapsed);
      toggleBtn.innerHTML = collapsed
        ? '<i class="fa-solid fa-chevron-right"></i>'
        : '<i class="fa-solid fa-chevron-left"></i>';
      localStorage.setItem('paletteCollapsed', collapsed ? '1' : '0');
    });
  }

  if (dockBtn) {
    dockBtn.addEventListener('click', () => {
      palette.style.left = '0px';
      palette.style.top = '0px';
      localStorage.setItem('palettePosition', JSON.stringify({ left: '0px', top: '0px' }));
    });
  }

  // Dragging
  if (paletteHeader) {
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
      if (e.target.closest('.palette-toggle-btn') || e.target.closest('.palette-dock-btn')) return;
      dragging = true;
      const rect = palette.getBoundingClientRect();
      offsetX = e.clientX - rect.left;
      offsetY = e.clientY - rect.top;
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  if (viewToggle) {
    viewToggle.addEventListener('click', () => {
      const viewing = builderEl.classList.toggle('view-mode');
      viewToggle.innerHTML = viewing
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';
      if (viewing) {
        if (settingsPanel) settingsPanel.classList.remove('open');
      }
    });
  }

  function updatePreview(size) {
    if (!previewContainer) return;
    previewContainer.classList.remove(
      'preview-desktop',
      'preview-tablet',
      'preview-phone'
    );
    if (size === 'desktop') {
      previewContainer.classList.add('preview-desktop');
    }
    previewButtons.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.size === size);
    });
  }

  let previewLoaded = false;

  function openPreview(size) {
    if (!previewModal || !previewFrame) return;
    if (previewWrapper) {
      if (size === 'tablet') previewWrapper.style.width = '768px';
      else if (size === 'phone') previewWrapper.style.width = '375px';
      else previewWrapper.style.width = '100%';
      previewWrapper.style.height = '90vh';
    }
    previewModal.classList.add('active');
    if (!previewLoaded) {
      const base = window.location.origin + window.builderBase + '/';
      const url = new URL('?page=' + window.builderSlug + '&preview=1', base);
      previewFrame.src = url.toString();
      previewLoaded = true;
    }
    updatePreview(size);
  }

  if (closePreview) {
    closePreview.addEventListener('click', () => {
      previewModal.classList.remove('active');
      previewFrame.src = '';
      previewLoaded = false;
      updatePreview('desktop');
    });
  }

  previewButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const size = btn.dataset.size;
      if (size === 'desktop') {
        updatePreview('desktop');
      } else {
        openPreview(size);
      }
    });
  });

  updatePreview('desktop');

  favorites = JSON.parse(localStorage.getItem('favoriteBlocks') || '[]');


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

  const history = initUndoRedo({ canvas, onChange: scheduleSave, maxHistory: 15 });
  const undoBtn = palette.querySelector('.undo-btn');
  const redoBtn = palette.querySelector('.redo-btn');
  const historyBtn = palette.querySelector('.page-history-btn');
  const linkCheckBtn = palette.querySelector('.link-check-btn');
  const seoCheckBtn = palette.querySelector('.seo-check-btn');
  const a11yCheckBtn = palette.querySelector('.a11y-check-btn');
  const saveBtn = palette.querySelector('.manual-save-btn');
  const historyPanel = document.getElementById('historyPanel');
  if (historyPanel) {
    historyPanel.classList.remove('open');
    historyPanel.style.left = '0px';
  }
  if (undoBtn) undoBtn.addEventListener('click', () => history.undo());
  if (redoBtn) redoBtn.addEventListener('click', () => history.redo());
  if (saveBtn)
    saveBtn.addEventListener('click', () => {
      clearTimeout(saveTimer);
      savePage();
    });
  if (linkCheckBtn)
    linkCheckBtn.addEventListener('click', () => {
      const html = canvas.innerHTML;
      checkLinks(html).then((warnings) => {
        if (warnings.length) {
          alert('Link issues found:\n' + warnings.join('\n'));
        } else {
          alert('No link issues found.');
        }
      });
    });
  if (seoCheckBtn) seoCheckBtn.addEventListener('click', openSeoModal);
  if (a11yCheckBtn)
    a11yCheckBtn.addEventListener('click', () => {
      const { count, messages } = checkAccessibility();
      if (count) {
        alert('Accessibility issues:\n' + messages.join('\n'));
      } else {
        alert('No accessibility issues found.');
      }
    });
  if (historyBtn && historyPanel) {
    const closeBtn = historyPanel.querySelector('.close-btn');
    const renderHistory = () => {
      fetch(
        window.builderBase + '/liveed/get-history.php?id=' + window.builderPageId
      )
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
  initWysiwyg(canvas, true);
  initMediaPicker({ basePath: window.builderBase });
  initAccessibility({ canvas });
  window.openMediaPicker = openMediaPicker;

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
  document.addEventListener('canvasUpdated', scheduleSave);

  canvas.addEventListener('click', (e) => {
    if (builderEl.classList.contains('view-mode')) return;
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
