// File: dragDrop.js
import { ensureBlockState, createBlockElementFromSchema, serializeBlock } from './state.js';
import { sanitizeTemplateMarkup, normalizeTemplateName } from './sanitizer.js';
import { getBlockPath, getPathLocation } from './undoRedo.js';
import { executeScripts } from './executeScripts.js';

// caching block control markup avoids rebuilding the DOM for each block
const controlsTemplate = `
  <span class="control edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></span>
  <span class="control drag" title="Drag"><i class="fa-solid fa-arrows-up-down-left-right"></i></span>
  <span class="control duplicate" title="Duplicate"><i class="fa-solid fa-clone"></i></span>
  <span class="control delete" title="Delete"><i class="fa-solid fa-trash"></i></span>
`;
const controlsFragment = document.createElement('div');
controlsFragment.className = 'block-controls';
controlsFragment.innerHTML = controlsTemplate;

function extractTemplateSetting(html) {
  const match = html.match(/<templateSetting[^>]*>[\s\S]*?<\/templateSetting>/i);
  const ts = match ? match[0] : '';
  const cleaned = match ? html.replace(match[0], '') : html;
  return { ts, cleaned };
}

function createPlaceholder() {
  const el = document.createElement('div');
  el.className = 'block-placeholder';
  el.innerHTML = '<span class="drop-text">Drop block here</span>';
  el.style.pointerEvents = 'none';
  return el;
}

function createInsertionIndicator() {
  const el = document.createElement('div');
  el.className = 'insertion-indicator';
  el.style.pointerEvents = 'none';
  return el;
}

export function createDragGhost(node) {
  if (!node) return null;
  const dragImage = node.cloneNode(true);
  dragImage.classList.add('drag-ghost');
  dragImage.style.position = 'absolute';
  dragImage.style.top = '-1000px';
  document.body.appendChild(dragImage);
  setTimeout(() => {
    if (dragImage.parentNode) {
      dragImage.parentNode.removeChild(dragImage);
    }
  }, 0);
  return dragImage;
}

function throttleRAF(fn) {
  let running = false;
  let lastEvent;
  return function (e) {
    e.preventDefault();
    lastEvent = e;
    if (running) return;
    running = true;
    requestAnimationFrame(() => {
      running = false;
      fn(lastEvent);
    });
  };
}

export function createDragDropController(options = {}) {
  const state = {
    palette: null,
    canvas: null,
    basePath: '',
    loggedIn: false,
    openSettings: null,
    applyStoredSettings: null,
    dragSource: null,
    dragSourcePath: null,
    fromPalette: false,
    allowedTemplates: new Set(),
    placeholder: createPlaceholder(),
    insertionIndicator: createInsertionIndicator(),
    recordOperation: null,
  };

  function refreshAllowedTemplates() {
    state.allowedTemplates.clear();
    if (!state.palette) return;
    const items = state.palette.querySelectorAll('.block-item[data-file]');
    items.forEach((item) => {
      const normalized = normalizeTemplateName(item.dataset.file || '');
      if (normalized) {
        state.allowedTemplates.add(normalized);
        item.dataset.file = normalized;
      }
    });
  }

  function setOptions(opts = {}) {
    if ('palette' in opts) state.palette = opts.palette;
    if ('canvas' in opts) state.canvas = opts.canvas;
    if ('basePath' in opts) state.basePath = opts.basePath || '';
    if ('loggedIn' in opts) state.loggedIn = !!opts.loggedIn;
    if ('openSettings' in opts) state.openSettings = opts.openSettings;
    if ('applyStoredSettings' in opts)
      state.applyStoredSettings = opts.applyStoredSettings;
    if ('recordOperation' in opts)
      state.recordOperation = typeof opts.recordOperation === 'function' ? opts.recordOperation : null;
    if ('palette' in opts) refreshAllowedTemplates();
  }

  setOptions(options);

  function paletteDragStart(e) {
    const item = e.target.closest('.block-item');
    if (item) {
      const normalized = normalizeTemplateName(item.dataset.file || '');
      if (!normalized || !state.allowedTemplates.has(normalized)) {
        return;
      }
      state.dragSource = item;
      state.fromPalette = true;
      state.dragSourcePath = null;
      e.dataTransfer.setData('text/plain', normalized);
      e.dataTransfer.effectAllowed = 'copy';
      item.classList.add('dragging');

      const dragImage = createDragGhost(item);
      if (dragImage) {
        e.dataTransfer.setDragImage(
          dragImage,
          dragImage.offsetWidth / 2,
          dragImage.offsetHeight / 2
        );
      }
    }
  }

  function canvasDragStart(e) {
    const handle = e.target.closest('.control.drag');
    if (handle) {
      state.dragSource = handle.closest('.block-wrapper');
      state.fromPalette = false;
      if (!state.dragSource) return;
      state.dragSource.classList.add('dragging');
      state.dragSourcePath = getBlockPath(state.dragSource, state.canvas);
      e.dataTransfer.setData('text/plain', 'reorder');
      e.dataTransfer.effectAllowed = 'move';

      const dragImage = createDragGhost(state.dragSource);
      if (dragImage) {
        e.dataTransfer.setDragImage(
          dragImage,
          dragImage.offsetWidth / 2,
          dragImage.offsetHeight / 2
        );
      }
    } else if (e.target.closest('.block-wrapper')) {
      e.preventDefault();
    }
  }

  function setupDropArea(area) {
    if (!area) return;
    area.dataset.dropArea = 'true';
  }

  function addBlockControls(block) {
    if (!block) return;
    ensureBlockState(block);
    if (state.applyStoredSettings) state.applyStoredSettings(block);
    if (!state.loggedIn) {
      const existing = block.querySelector('.block-controls');
      if (existing) existing.remove();
      block.removeAttribute('draggable');
      return;
    }
    if (!block.querySelector('.block-controls')) {
      const controls = controlsFragment.cloneNode(true);
      block.style.position = 'relative';
      block.appendChild(controls);
    }
    block.removeAttribute('draggable');
    const dragHandle = block.querySelector('.block-controls .drag');
    if (dragHandle) dragHandle.setAttribute('draggable', 'true');
    if (!block.dataset.original) {
      let html = block.innerHTML;
      const { ts, cleaned } = extractTemplateSetting(html);
      block.dataset.original = sanitizeTemplateMarkup(cleaned);
      if (ts) {
        block.dataset.ts = btoa(ts);
      }
    } else {
      const { ts, cleaned } = extractTemplateSetting(block.dataset.original);
      block.dataset.original = sanitizeTemplateMarkup(cleaned);
      if (ts && !block.dataset.ts) {
        block.dataset.ts = btoa(ts);
      }
    }
    const tsEl = block.querySelector('templateSetting');
    if (tsEl) tsEl.remove();
    const areas = block.querySelectorAll('.drop-area');
    areas.forEach(setupDropArea);
    if (areas.length === 0) {
      setupDropArea(block);
    }
  }

  function handleDragEnter(e) {
    const area = e.target.closest('[data-drop-area]');
    if (area) {
      area.classList.add('drag-over');
    }
  }

  function handleDragLeave(e) {
    const area = e.target.closest('[data-drop-area]');
    if (area && (!e.relatedTarget || !area.contains(e.relatedTarget))) {
      area.classList.remove('drag-over');
      state.placeholder.remove();
      state.insertionIndicator.remove();
    }
  }

  function handleDragOver(e) {
    const area = e.target.closest('[data-drop-area]');
    if (!area) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = state.fromPalette ? 'copy' : 'move';
    const after = getDragAfterElement(area, e.clientY);
    if (after == null) {
      area.appendChild(state.placeholder);
    } else {
      area.insertBefore(state.placeholder, after);
    }
    state.insertionIndicator.remove();
    if (state.placeholder.parentNode) {
      state.placeholder.parentNode.insertBefore(
        state.insertionIndicator,
        state.placeholder
      );
    }
  }

  function handleDrop(e) {
    const area = e.target.closest('[data-drop-area]');
    if (!area) return;
    e.preventDefault();
    const after = getDragAfterElement(area, e.clientY);
    if (state.fromPalette && state.dragSource) {
      const file = normalizeTemplateName(state.dragSource.dataset.file || '');
      if (file && state.allowedTemplates.has(file)) {
        const schema = { template: file, settings: {}, areas: [] };
        createBlockElementFromSchema(schema, {
          basePath: state.basePath,
          applyStoredSettings: state.applyStoredSettings,
          addBlockControls,
        })
          .then((wrapper) => {
            if (!wrapper) return;
            if (after == null) area.appendChild(wrapper);
            else area.insertBefore(wrapper, after);

            executeScripts(wrapper);

            if (typeof state.recordOperation === 'function') {
              const path = getBlockPath(wrapper, state.canvas);
              if (path && path.length) {
                const location = getPathLocation(path);
                state.recordOperation({
                  type: 'insert',
                  parentPath: location.parentPath,
                  areaIndex: location.areaIndex,
                  index: location.index,
                  block: serializeBlock(wrapper),
                });
              }
            }

            if (state.openSettings) state.openSettings(wrapper);
            document.dispatchEvent(new Event('canvasUpdated'));
          })
          .catch(() => {});
      }
    } else if (state.dragSource) {
      state.dragSource.classList.remove('dragging');
      if (after == null) area.appendChild(state.dragSource);
      else area.insertBefore(state.dragSource, after);

      if (
        typeof state.recordOperation === 'function' &&
        Array.isArray(state.dragSourcePath) &&
        state.dragSourcePath.length
      ) {
        const newPath = getBlockPath(state.dragSource, state.canvas);
        const oldPath = state.dragSourcePath.slice();
        const samePath =
          Array.isArray(newPath) &&
          newPath.length === oldPath.length &&
          newPath.every((value, index) => value === oldPath[index]);
        if (Array.isArray(newPath) && newPath.length && !samePath) {
          state.recordOperation({
            type: 'move',
            fromPath: oldPath,
            toPath: newPath.slice(),
          });
        }
      }

      document.dispatchEvent(new Event('canvasUpdated'));
    }
    state.placeholder.remove();
    state.insertionIndicator.remove();
    area.classList.remove('drag-over');
    state.dragSource = null;
    state.dragSourcePath = null;
    state.fromPalette = false;
  }

  function handleDragEnd() {
    state.placeholder.remove();
    state.insertionIndicator.remove();
    if (state.dragSource) state.dragSource.classList.remove('dragging');
    state.dragSource = null;
    state.dragSourcePath = null;
  }

  function getDragAfterElement(container, y) {
    const els = container.querySelectorAll('.block-wrapper:not(.dragging)');
    let closest = null;
    let closestOffset = Number.NEGATIVE_INFINITY;
    for (let i = 0; i < els.length; i++) {
      const box = els[i].getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closestOffset) {
        closestOffset = offset;
        closest = els[i];
      }
    }
    return closest;
  }

  const throttledDragOver = throttleRAF(handleDragOver);
  const throttledDrop = throttleRAF(handleDrop);

  function delegateDragEvents(e) {
    switch (e.type) {
      case 'dragstart':
        canvasDragStart(e);
        break;
      case 'dragenter':
        handleDragEnter(e);
        break;
      case 'dragleave':
        handleDragLeave(e);
        break;
      case 'dragover':
        throttledDragOver(e);
        break;
      case 'drop':
        throttledDrop(e);
        break;
      case 'dragend':
        handleDragEnd(e);
        break;
    }
  }

  function init(initOptions = {}) {
    setOptions(initOptions);
    refreshAllowedTemplates();
    if (state.palette) state.palette.addEventListener('dragstart', paletteDragStart);
    if (state.canvas) {
      ['dragstart', 'dragenter', 'dragleave', 'dragover', 'drop', 'dragend'].forEach(
        (ev) => state.canvas.addEventListener(ev, delegateDragEvents, true)
      );
    }
    setupDropArea(state.canvas);
    if (state.canvas) {
      state.canvas.querySelectorAll('.drop-area').forEach(setupDropArea);
    }
  }

  return {
    init,
    setOptions,
    addBlockControls,
    setupDropArea,
  };
}
