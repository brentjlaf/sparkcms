// File: builder.js
import { createDragDropController } from './modules/dragDrop.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import {
  serializeCanvas,
  renderCanvasFromSchema,
  createBlockElementFromSchema,
  serializeBlock,
  decodeDraftContent,
} from './modules/state.js';
import { initUndoRedo, getBlockPath, getPathLocation } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';
import { createMediaPicker } from './modules/mediaPicker.js';
import { executeScripts } from './modules/executeScripts.js';

let blockManifest = [];
let favorites = [];
let builderDraftKey = '';
let lastSavedTimestamp = 0;
let canvas;
let paletteEl;
let pageRevision = window.builderRevision || '';
let draftRevision = '';
let historyEntries = [];
let conflictActive = false;
let conflictPromptShown = false;
let historyApi = null;
let linkCheckWorker = null;
let linkWarningPanel = null;
let latestLinkCheckJobId = 0;
let linkCheckJobSeq = 0;
let currentSearchTerm = '';
// Delay before auto-saving after a change. A longer delay prevents rapid
// successive saves while the user is still actively editing.
const SAVE_DEBOUNCE_DELAY = 1000;

function setPageRevision(value = '') {
  pageRevision = value || '';
  if (typeof window !== 'undefined') {
    window.builderPageRevision = pageRevision;
  }
}

function setDraftRevision(value = '') {
  draftRevision = value || '';
  if (typeof window !== 'undefined') {
    window.builderDraftRevision = draftRevision;
  }
}

function setHistoryEntriesCache(entries = []) {
  historyEntries = Array.isArray(entries) ? entries : [];
  if (typeof window !== 'undefined') {
    window.builderHistoryEntries = historyEntries;
  }
}

setPageRevision(pageRevision);
setDraftRevision(draftRevision);
setHistoryEntriesCache(historyEntries);

function humanizeLabel(value = '') {
  return value
    .replace(/\.php$/gi, '')
    .replace(/[._-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatGroupLabel(group = '') {
  const normalized = (group || '')
    .toString()
    .replace(/[._-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  if (!normalized) return 'General';
  return normalized.replace(/\b\w/g, (c) => c.toUpperCase());
}

function normalizeManifestEntry(entry = {}) {
  if (!entry || typeof entry !== 'object') return null;
  const template = typeof entry.template === 'string' && entry.template.trim()
    ? entry.template.trim()
    : typeof entry.file === 'string'
    ? entry.file.trim()
    : '';
  if (!template) return null;
  const id = typeof entry.id === 'string' && entry.id.trim()
    ? entry.id.trim()
    : template.replace(/\.php$/i, '');
  const group = typeof entry.group === 'string' && entry.group.trim()
    ? entry.group.trim().toLowerCase()
    : (id.split('.')[0] || 'general').toLowerCase();
  const label = typeof entry.label === 'string' && entry.label.trim()
    ? entry.label.trim()
    : humanizeLabel(id);
  const caps = Array.isArray(entry.capabilities)
    ? entry.capabilities
        .map((cap) => (typeof cap === 'string' ? cap.trim().toLowerCase() : ''))
        .filter(Boolean)
    : [];
  const uniqueCaps = Array.from(new Set(caps)).sort();
  return {
    id,
    template,
    file: template,
    label,
    group,
    groupLabel: formatGroupLabel(group),
    capabilities: uniqueCaps,
  };
}

function sanitizeFavoritesList(list = []) {
  if (!Array.isArray(list)) return [];
  const sanitized = [];
  const seen = new Set();
  list.forEach((value) => {
    if (typeof value !== 'string') return;
    const trimmed = value.trim();
    if (!trimmed || seen.has(trimmed)) return;
    seen.add(trimmed);
    sanitized.push(trimmed);
  });
  return sanitized;
}

function normalizeFavoritesWithManifest(manifest = []) {
  if (!Array.isArray(manifest) || !manifest.length) {
    favorites = sanitizeFavoritesList(favorites);
    return;
  }
  const idSet = new Set();
  const templateToId = new Map();
  manifest.forEach((entry) => {
    if (!entry || typeof entry !== 'object') return;
    idSet.add(entry.id);
    templateToId.set(entry.template, entry.id);
  });

  const converted = favorites.map((value) => {
    if (idSet.has(value)) return value;
    if (templateToId.has(value)) return templateToId.get(value);
    return value;
  });

  const sanitized = [];
  const seen = new Set();
  converted.forEach((value) => {
    if (!idSet.has(value) || seen.has(value)) return;
    seen.add(value);
    sanitized.push(value);
  });
  if (sanitized.length !== favorites.length || sanitized.some((val, idx) => val !== favorites[idx])) {
    favorites = sanitized;
    localStorage.setItem('favoriteBlocks', JSON.stringify(favorites));
  } else {
    favorites = sanitized;
  }
}

function filterManifest(term = '') {
  const normalized = term.trim().toLowerCase();
  if (!normalized) {
    return blockManifest.slice();
  }
  return blockManifest.filter((entry) => {
    if (!entry) return false;
    const haystack = [
      entry.label || '',
      entry.group || '',
      entry.groupLabel || '',
      entry.id || '',
      entry.template || '',
      Array.isArray(entry.capabilities) ? entry.capabilities.join(' ') : '',
    ]
      .join(' ')
      .toLowerCase();
    return haystack.includes(normalized);
  });
}

function initLinkCheckWorker() {
  if (linkCheckWorker || typeof window === 'undefined' || typeof Worker === 'undefined') {
    return;
  }
  try {
    linkCheckWorker = new Worker(new URL('./modules/link-check-worker.js', import.meta.url), {
      type: 'module',
    });
    linkCheckWorker.addEventListener('message', handleLinkCheckMessage);
    linkCheckWorker.addEventListener('error', (event) => {
      console.error('Link check worker error', event);
    });
  } catch (error) {
    console.warn('Link check worker unavailable', error);
    linkCheckWorker = null;
  }
}

function queueLinkCheck(html) {
  if (!linkCheckWorker) return 0;
  const jobId = ++linkCheckJobSeq;
  latestLinkCheckJobId = jobId;
  try {
    linkCheckWorker.postMessage({
      type: 'checkLinks',
      html,
      baseUrl: typeof window !== 'undefined' ? window.location.href : '',
      jobId,
    });
  } catch (error) {
    console.error('Unable to start link check', error);
    return 0;
  }
  return jobId;
}

function displayLinkWarnings(warnings = []) {
  if (!linkWarningPanel) return;
  linkWarningPanel.innerHTML = '';
  if (!warnings.length) {
    linkWarningPanel.classList.add('hidden');
    return;
  }
  const title = document.createElement('div');
  title.className = 'link-warning-title';
  title.textContent = 'Link warnings';
  const list = document.createElement('ul');
  list.className = 'link-warning-list';
  warnings.forEach((warning) => {
    const item = document.createElement('li');
    item.textContent = warning;
    list.appendChild(item);
  });
  linkWarningPanel.appendChild(title);
  linkWarningPanel.appendChild(list);
  linkWarningPanel.classList.remove('hidden');
}

function handleLinkCheckMessage(event) {
  const data = event.data || {};
  if (data.type !== 'linkCheckResult') return;
  const { warnings = [], jobId, error } = data;
  if (jobId && jobId !== latestLinkCheckJobId) {
    return;
  }
  const statusEl = document.getElementById('saveStatus');
  if (!statusEl) return;

  if (error) {
    statusEl.textContent = 'Link check failed';
    statusEl.classList.add('error');
    statusEl.classList.remove('saving');
    displayLinkWarnings([]);
    setTimeout(() => {
      if (statusEl.textContent === 'Link check failed') {
        statusEl.textContent = '';
        statusEl.classList.remove('error');
      }
    }, 4000);
    return;
  }

  if (!warnings.length) {
    if (statusEl.textContent === '' || statusEl.textContent === 'Saved') {
      statusEl.textContent = 'Links look good';
      setTimeout(() => {
        if (statusEl.textContent === 'Links look good') statusEl.textContent = '';
      }, 2500);
    }
    displayLinkWarnings([]);
    return;
  }

  statusEl.textContent = 'Link issues found';
  statusEl.classList.add('error');
  statusEl.classList.remove('saving');
  if (warnings.length) {
    console.warn('Link issues found:', warnings.join('\n'));
  }
  displayLinkWarnings(warnings);
}

function handleConflict(source = 'content', message = '') {
  conflictActive = true;
  const statusEl = document.getElementById('saveStatus');
  const defaultMessage =
    source === 'draft'
      ? 'Draft update rejected. Reload to sync with the latest changes.'
      : 'Update rejected. Reload to sync with the latest changes.';
  if (statusEl) {
    statusEl.textContent = message || defaultMessage;
    statusEl.classList.add('error');
    statusEl.classList.remove('saving');
  }
  if (conflictPromptShown) return;
  conflictPromptShown = true;
  const promptMessage =
    (message ? message + '\n\n' : '') +
    'Another session saved changes to this page. Reload now to discard your draft and use the latest version?\n\nSelect Cancel to keep your edits visible here so you can copy and merge them manually.';
  const shouldReload = window.confirm(promptMessage);
  if (shouldReload) {
    window.location.reload();
  }
}

function displayTemplateLoadError(error, context = {}) {
  const statusEl = document.getElementById('saveStatus');
  if (!statusEl) return;
  const meta = context && typeof context === 'object' ? context.meta || {} : {};
  const rawLabel =
    (meta && typeof meta.label === 'string' && meta.label.trim() ? meta.label.trim() : '') ||
    (context && typeof context.template === 'string' && context.template.trim()
      ? context.template.trim().replace(/\.php$/i, '').split('.').pop().replace(/[-_]/g, ' ')
      : 'block');
  const label = rawLabel.replace(/\b\w/g, (c) => c.toUpperCase());
  statusEl.textContent = 'Unable to load ' + label + ' block.';
  statusEl.classList.add('error');
  statusEl.classList.remove('saving');
  if (error) {
    console.error('Unable to load block template', context && context.template, error);
  }
}

function storeDraft() {
  if (!canvas) return;
  const data = {
    schema: serializeCanvas(canvas),
    timestamp: Date.now(),
    revision: draftRevision || '',
  };
  localStorage.setItem(builderDraftKey, JSON.stringify(data));
  if (conflictActive) return;
  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', JSON.stringify(data));
  fd.append('timestamp', data.timestamp);
  fd.append('revision', draftRevision || '');
  fetch(window.builderBase + '/liveed/save-draft.php', {
    method: 'POST',
    body: fd,
  })
    .then((response) => {
      if (response.status === 409) {
        return response
          .json()
          .catch(() => ({}))
          .then((payload) => {
            handleConflict('draft', payload.error);
            throw new Error('conflict');
          });
      }
      if (!response.ok) throw new Error('Draft save failed');
      return response.json().catch(() => ({}));
    })
    .then((payload) => {
      if (payload && payload.revision) {
        setDraftRevision(payload.revision);
        data.revision = draftRevision;
        localStorage.setItem(builderDraftKey, JSON.stringify(data));
      }
    })
    .catch((error) => {
      if (error && error.message === 'conflict') return;
    });
}

function renderGroupItems(details) {
  const items = details.querySelector('.group-items');
  if (!items || details._rendered) return;
  const list = Array.isArray(details._items) ? details._items : [];
  const favs = favorites;
  const isFavoritesGroup = details.dataset.groupKey === 'favorites';
  const favoriteOrder = new Map();
  favs.forEach((id, index) => favoriteOrder.set(id, index));
  const sorted = list.slice().sort((a, b) => {
    if (isFavoritesGroup) {
      const orderA = favoriteOrder.has(a.id) ? favoriteOrder.get(a.id) : Number.MAX_SAFE_INTEGER;
      const orderB = favoriteOrder.has(b.id) ? favoriteOrder.get(b.id) : Number.MAX_SAFE_INTEGER;
      if (orderA !== orderB) return orderA - orderB;
    }
    return (a.label || '').localeCompare(b.label || '');
  });
  const frag = document.createDocumentFragment();
  sorted.forEach((entry, idx) => {
    if (!entry || typeof entry !== 'object') return;
    const item = document.createElement('div');
    item.className = 'block-item';
    item.setAttribute('draggable', 'true');
    item.dataset.file = entry.template;
    item.dataset.blockId = entry.id;
    item.dataset.group = entry.group;
    if (Array.isArray(entry.capabilities) && entry.capabilities.length) {
      item.dataset.capabilities = entry.capabilities.join(',');
    } else {
      delete item.dataset.capabilities;
    }
    const meta = {
      id: entry.id,
      label: entry.label,
      group: entry.group,
      capabilities: entry.capabilities || [],
      template: entry.template,
    };
    try {
      item.dataset.meta = JSON.stringify(meta);
    } catch (error) {
      item.dataset.meta = JSON.stringify({ id: entry.id, template: entry.template });
    }
    item.style.setProperty('--block-animation-delay', `${(idx + 1) * 0.05}s`);
    const itemLabel = entry.label || humanizeLabel(entry.id || entry.template || '');
    item.textContent = itemLabel;
    item.title = itemLabel;
    const favBtn = document.createElement('span');
    favBtn.className = 'fav-toggle';
    const isFavorite = favs.includes(entry.id);
    if (isFavorite) favBtn.classList.add('active');
    favBtn.textContent = '★';
    favBtn.title = isFavorite ? 'Unfavorite' : 'Favorite';
    favBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleFavorite(entry.id);
    });
    item.appendChild(favBtn);
    frag.appendChild(item);
  });
  items.appendChild(frag);
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


function renderPalette(palette, manifestEntries = []) {
  const container = palette.querySelector('.palette-items');
  if (!container) return;
  container.innerHTML = '';

  const entries = Array.isArray(manifestEntries) ? manifestEntries : [];
  if (!entries.length) {
    const empty = document.createElement('p');
    empty.className = 'palette-empty';
    empty.textContent = 'No blocks available.';
    container.appendChild(empty);
    return;
  }

  const favs = favorites;
  const groupMap = new Map();
  const ensureGroup = (key, label) => {
    if (!groupMap.has(key)) {
      groupMap.set(key, { key, label, items: [] });
    }
    return groupMap.get(key);
  };

  entries.forEach((entry) => {
    if (!entry || typeof entry !== 'object') return;
    const groupKey = entry.group || 'general';
    const groupLabel = entry.groupLabel || formatGroupLabel(groupKey);
    ensureGroup(groupKey, groupLabel).items.push(entry);
    if (favs.includes(entry.id)) {
      ensureGroup('favorites', 'Favorites').items.push(entry);
    }
  });

  const groups = Array.from(groupMap.values()).filter((group) => group.items.length);
  groups.sort((a, b) => {
    if (a.key === 'favorites') return -1;
    if (b.key === 'favorites') return 1;
    return (a.label || '').localeCompare(b.label || '');
  });

  groups.forEach((group) => {
    const details = document.createElement('details');
    details.className = 'palette-group';
    details.dataset.groupKey = group.key;

    const summary = document.createElement('summary');
    summary.textContent = group.label || formatGroupLabel(group.key);
    details.appendChild(summary);

    const wrap = document.createElement('div');
    wrap.className = 'group-items';

    details._items = group.items.slice();
    details.appendChild(wrap);
    container.appendChild(details);
    animateAccordion(details);
  });
}

function toggleFavorite(blockId) {
  if (typeof blockId !== 'string' || !blockId.trim()) return;
  const id = blockId.trim();
  const idx = favorites.indexOf(id);
  if (idx >= 0) {
    favorites.splice(idx, 1);
  } else {
    favorites.push(id);
  }
  favorites = sanitizeFavoritesList(favorites);
  localStorage.setItem('favoriteBlocks', JSON.stringify(favorites));
  if (paletteEl) {
    renderPalette(paletteEl, filterManifest(currentSearchTerm));
  }
}

let saveTimer;

function savePage() {
  if (!canvas) return;
  if (conflictActive) {
    handleConflict('content');
    return;
  }
  const statusEl = document.getElementById('saveStatus');
  const html = canvas.innerHTML;

  const linkJobId = queueLinkCheck(html);
  displayLinkWarnings([]);

  if (statusEl) {
    statusEl.dataset.linkJobId = linkJobId ? String(linkJobId) : '';
    statusEl.textContent = linkJobId ? 'Saving... (link check running)' : 'Saving...';
    statusEl.classList.add('saving');
    statusEl.classList.remove('error');
  }

  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', html);
  fd.append('revision', pageRevision || '');

  fetch(window.builderBase + '/liveed/save-content.php', {
    method: 'POST',
    body: fd,
  })
    .then((response) => {
      if (response.status === 409) {
        return response
          .json()
          .catch(() => ({}))
          .then((payload) => {
            handleConflict('content', payload.error);
            throw new Error('conflict');
          });
      }
      if (!response.ok) throw new Error('Save failed');
      return response.json().catch(() => ({}));
    })
    .then((payload) => {
      localStorage.removeItem(builderDraftKey);
      setDraftRevision('');
      const serverTimestamp = payload && payload.timestamp ? Number(payload.timestamp) : null;
      const serverMs = serverTimestamp ? serverTimestamp * 1000 : Date.now();
      lastSavedTimestamp = serverMs;
      if (payload && payload.revision) {
        setPageRevision(payload.revision);
      }
      if (statusEl) {
        statusEl.textContent = 'Saved';
        statusEl.classList.remove('saving');
        statusEl.classList.remove('error');
      }
      const lastSavedEl = document.getElementById('lastSavedTime');
      if (lastSavedEl) {
        const displayDate = new Date(serverMs);
        lastSavedEl.textContent = 'Last saved: ' + displayDate.toLocaleString();
      }
      setTimeout(() => {
        if (statusEl && statusEl.textContent === 'Saved') statusEl.textContent = '';
      }, 2000);
    })
    .catch((error) => {
      if (error && error.message === 'conflict') return;
      if (statusEl) {
        statusEl.textContent = 'Error saving';
        statusEl.classList.add('error');
        statusEl.classList.remove('saving');
      }
    });
}

function scheduleSave() {
  clearTimeout(saveTimer);
  storeDraft();
  if (conflictActive) return;
  saveTimer = setTimeout(savePage, SAVE_DEBOUNCE_DELAY);
}

document.addEventListener('DOMContentLoaded', async () => {
  initLinkCheckWorker();
  canvas = document.getElementById('canvas');
  const palette = (paletteEl = document.querySelector('.block-palette'));
  const settingsPanel = document.getElementById('settingsPanel');
  const builderEl = document.querySelector('.builder');
  const viewToggle = document.getElementById('viewModeToggle');
  const paletteHeader = palette ? palette.querySelector('.builder-header') : null;
  const statusEl = document.getElementById('saveStatus');

  if (statusEl && !linkWarningPanel) {
    linkWarningPanel = document.createElement('div');
    linkWarningPanel.id = 'linkWarningPanel';
    linkWarningPanel.className = 'link-warning-panel hidden';
    statusEl.insertAdjacentElement('afterend', linkWarningPanel);
  }

  document
    .querySelectorAll('.history-toolbar button')
    .forEach((btn) => btn.classList.add('builder-btn'));

  builderDraftKey = 'builderDraft-' + window.builderPageId;
  lastSavedTimestamp = (window.builderLastModified || 0) * 1000;
  const dragDropController = createDragDropController({
    palette,
    canvas,
    basePath: window.builderBase,
    loggedIn: true,
    openSettings,
    applyStoredSettings,
    onTemplateError: displayTemplateLoadError,
  });
  dragDropController.init();
  const { addBlockControls } = dragDropController;

  const rendererOptions = {
    basePath: window.builderBase,
    applyStoredSettings,
    addBlockControls,
  };

  const applyDraftData = async (draft, { persistLocal = false } = {}) => {
    if (!draft) return false;
    const data =
      typeof draft === 'string' ? decodeDraftContent(draft) : draft;
    if (!data) return false;
    const timestamp = Number(data.timestamp) || 0;
    if (timestamp <= lastSavedTimestamp) return false;
    const revision =
      typeof data.revision === 'string' && data.revision !== ''
        ? data.revision
        : draftRevision;
    if (revision) {
      setDraftRevision(revision);
    }
    if (data.schema && typeof data.schema === 'object') {
      await renderCanvasFromSchema(canvas, data.schema, rendererOptions);
      lastSavedTimestamp = timestamp;
      if (historyApi) historyApi.resetFromSchema(data.schema);
      if (persistLocal) {
        localStorage.setItem(
          builderDraftKey,
          JSON.stringify({ schema: data.schema, timestamp, revision: draftRevision })
        );
      }
      return true;
    }
    if (data.html) {
      canvas.innerHTML = data.html;
      lastSavedTimestamp = timestamp;
      canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);
      const schema = serializeCanvas(canvas);
      if (schema && schema.blocks) {
        localStorage.setItem(
          builderDraftKey,
          JSON.stringify({ schema, timestamp, revision: draftRevision })
        );
      }
      if (historyApi && schema) historyApi.resetFromSchema(schema);
      return true;
    }
    return false;
  };

  const localDraft = localStorage.getItem(builderDraftKey);
  if (localDraft) {
    const applied = await applyDraftData(localDraft);
    if (!applied) {
      localStorage.removeItem(builderDraftKey);
    }
  }

  (async () => {
    try {
      const response = await fetch(
        window.builderBase + '/liveed/load-draft.php?id=' + window.builderPageId
      );
      if (!response.ok) return;
      const serverDraft = await response.json();
      if (!serverDraft) return;
      const decoded = decodeDraftContent(serverDraft.content);
      if (!decoded) return;
      decoded.timestamp = serverDraft.timestamp || decoded.timestamp;
      if (typeof serverDraft.revision === 'string') {
        decoded.revision = serverDraft.revision;
      }
      await applyDraftData(decoded, { persistLocal: true });
    } catch (e) {}
  })();

  // Restore palette position
  const storedPos = palette ? localStorage.getItem('palettePosition') : null;
  if (palette && storedPos) {
    try {
      const pos = JSON.parse(storedPos);
      if (pos.left) palette.style.left = pos.left;
      if (pos.top) palette.style.top = pos.top;
    } catch (e) {}
  }

  // Dragging
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

  try {
    favorites = sanitizeFavoritesList(JSON.parse(localStorage.getItem('favoriteBlocks') || '[]'));
  } catch (error) {
    favorites = [];
  }

  initSettings({ canvas, settingsPanel, savePage: scheduleSave, addBlockControls });

  const searchInput = palette.querySelector('.palette-search');

  fetch(window.builderBase + '/liveed/list-blocks.php')
    .then((r) => r.json())
    .then((data) => {
      const entries = Array.isArray(data.blocks) ? data.blocks : [];
      blockManifest = entries
        .map((entry) => normalizeManifestEntry(entry))
        .filter(Boolean);
      normalizeFavoritesWithManifest(blockManifest);
      renderPalette(palette, filterManifest(currentSearchTerm));
    })
    .catch(() => {
      blockManifest = [];
      renderPalette(palette, []);
    });

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      currentSearchTerm = searchInput.value || '';
      const filtered = filterManifest(currentSearchTerm);
      renderPalette(palette, filtered);
    });
  }

  const history = initUndoRedo({
    canvas,
    rendererOptions,
    onChange: scheduleSave,
    maxHistory: 15,
  });
  historyApi = history;
  dragDropController.setOptions({ recordOperation: history.recordOperation });
  const undoBtn = palette.querySelector('.undo-btn');
  const redoBtn = palette.querySelector('.redo-btn');
  const historyBtn = palette.querySelector('.page-history-btn');
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
            setHistoryEntriesCache(
              entries.map((entry) => Object.assign({}, entry))
            );
            entries.forEach((h) => {
              const li = document.createElement('li');
              const d = new Date(h.time * 1000);
              const action = h.action ? ' - ' + h.action : '';
              li.textContent = d.toLocaleString() + ' - ' + h.user + action;
              if (h.revision) {
                li.dataset.revision = h.revision;
                li.title = 'Revision ' + h.revision;
              }
              ul.appendChild(li);
            });
            cont.appendChild(ul);
          } else {
            setHistoryEntriesCache([]);
            cont.textContent = 'No history yet.';
          }
        })
        .catch(() => {
          setHistoryEntriesCache([]);
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
  const mediaPickerApi = createMediaPicker({
    basePath: window.builderBase,
    document,
    fetchImpl: window.fetch.bind(window),
  });
  window.openMediaPicker = mediaPickerApi.open;
  window.closeMediaPicker = mediaPickerApi.close;

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

  document.addEventListener('blockSettingsApplied', (event) => {
    if (!history || !history.recordOperation) return;
    const detail = event && event.detail ? event.detail : {};
    const block = detail.block;
    if (!block || !canvas.contains(block)) return;
    const path = getBlockPath(block, canvas);
    if (!path || !path.length) return;
    const schema = serializeBlock(block);
    if (!schema) return;
    history.recordOperation({ type: 'replace', path, block: schema });
  });

  canvas.addEventListener('click', (e) => {
    if (builderEl.classList.contains('view-mode')) return;
    const block = e.target.closest('.block-wrapper');
    if (!block) return;
    if (e.target.closest('.block-controls .edit')) {
      openSettings(block);
    } else if (e.target.closest('.block-controls .duplicate')) {
      const schema = serializeBlock(block);
      if (!schema) return;
      createBlockElementFromSchema(schema, rendererOptions)
        .then((clone) => {
          if (!clone) return;
          clone.classList.remove('selected');
          block.after(clone);
          executeScripts(clone);
          if (history && history.recordOperation) {
            const path = getBlockPath(clone, canvas);
            if (path && path.length) {
              const location = getPathLocation(path);
              history.recordOperation({
                type: 'insert',
                parentPath: location.parentPath,
                areaIndex: location.areaIndex,
                index: location.index,
                block: serializeBlock(clone),
              });
            }
          }
          document.dispatchEvent(new Event('canvasUpdated'));
        })
        .catch(() => {});
    } else if (e.target.closest('.block-controls .delete')) {
      confirmDelete('Delete this block?').then((ok) => {
        if (ok) {
          const path = history && history.recordOperation ? getBlockPath(block, canvas) : null;
          block.remove();
          updateCanvasPlaceholder();
          scheduleSave();
          if (history && history.recordOperation && path && path.length) {
            history.recordOperation({ type: 'delete', path });
          }
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
