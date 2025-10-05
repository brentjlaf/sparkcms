// File: dragDrop.js
import {
  ensureBlockState,
  createBlockElementFromSchema,
  serializeBlock,
  getTemplateCacheMetadata,
  invalidateTemplateCache as clearTemplateCacheEntry,
} from './state.js';
import { sanitizeTemplateMarkup, normalizeTemplateName } from './sanitizer.js';
import { getBlockPath, getPathLocation } from './undoRedo.js';
import { executeScripts } from './executeScripts.js';
import { encodeUnicodeBase64 } from './base64.js';

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

const liveRegionId = 'live-builder-dragdrop-region';

function getLiveRegion() {
  if (typeof document === 'undefined') return null;
  let region = document.getElementById(liveRegionId);
  if (!region) {
    region = document.createElement('div');
    region.id = liveRegionId;
    region.className = 'visually-hidden dragdrop-live-region';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    Object.assign(region.style, {
      position: 'absolute',
      width: '1px',
      height: '1px',
      margin: '-1px',
      border: '0',
      padding: '0',
      clip: 'rect(0 0 0 0)',
      overflow: 'hidden',
    });
    document.body.appendChild(region);
  }
  return region;
}

function announce(message) {
  const region = getLiveRegion();
  if (!region) return;
  region.textContent = '';
  const schedule =
    typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function'
      ? window.requestAnimationFrame.bind(window)
      : (cb) => setTimeout(cb, 0);
  schedule(() => {
    region.textContent = message;
  });
}

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

function reportTemplateSettingsIssue(phase, context = {}, error = null) {
  const block = context.block || null;
  const template =
    context.template || (block && block.dataset ? block.dataset.template || '' : '');
  const blockIdValue =
    context.blockId ||
    (block && block.id) ||
    (block && block.dataset ? block.dataset.id || '' : '');
  const blockId = blockIdValue ? String(blockIdValue) : '';
  const reason = context.reason || '';
  const messageParts = [`Failed to ${phase} template settings.`];
  if (template) messageParts.push(`Template: ${template}`);
  if (blockId) messageParts.push(`Block: ${blockId}`);
  if (reason) messageParts.push(`Reason: ${reason}`);
  const message = messageParts.join(' ');
  if (error) {
    console.warn(message, error);
  } else {
    console.warn(message);
  }
  if (
    typeof document !== 'undefined' &&
    typeof document.dispatchEvent === 'function' &&
    typeof CustomEvent === 'function'
  ) {
    try {
      document.dispatchEvent(
        new CustomEvent('liveBuilder:templateSettingsFailure', {
          detail: {
            phase,
            template,
            blockId,
            reason,
            message,
            error: error ? error.message || String(error) : '',
          },
        })
      );
    } catch (dispatchError) {
      // Ignore telemetry dispatch failures to avoid noisy logs.
    }
  }
}

function safeEncodeTemplateSetting(block, value, reason) {
  if (!value) {
    return { success: true, value: '' };
  }
  try {
    return { success: true, value: encodeUnicodeBase64(value) };
  } catch (error) {
    reportTemplateSettingsIssue('encode', { block, reason }, error);
    return { success: false, value: '' };
  }
}

function parsePaletteMeta(node) {
  if (!node || !node.dataset) return null;
  const raw = node.dataset.meta;
  if (!raw) return null;
  try {
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') return null;
    const meta = {};
    if (typeof parsed.id === 'string' && parsed.id.trim()) {
      meta.id = parsed.id.trim();
    }
    if (typeof parsed.label === 'string' && parsed.label.trim()) {
      meta.label = parsed.label.trim();
    }
    if (typeof parsed.group === 'string' && parsed.group.trim()) {
      meta.group = parsed.group.trim();
    }
    if (typeof parsed.template === 'string' && parsed.template.trim()) {
      meta.template = parsed.template.trim();
    }
    if (Array.isArray(parsed.capabilities)) {
      const caps = parsed.capabilities
        .map((cap) => (typeof cap === 'string' ? cap.trim().toLowerCase() : ''))
        .filter(Boolean);
      if (caps.length) {
        meta.capabilities = Array.from(new Set(caps)).sort();
      }
    }
    return Object.keys(meta).length ? meta : null;
  } catch (error) {
    return null;
  }
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
    templateMetadata: new Map(),
    onTemplateError: null,
    lastTemplateRequest: null,
    dropMetrics: null,
    dropMetricsObserver: null,
  };

  function setAriaGrabbed(element, grabbed) {
    if (!element) return;
    element.setAttribute('aria-grabbed', grabbed ? 'true' : 'false');
  }

  function getDropAreas() {
    if (!state.canvas) return [];
    const areas = new Set();
    if (state.canvas.dataset && state.canvas.dataset.dropArea === 'true') {
      areas.add(state.canvas);
    }
    state.canvas.querySelectorAll('[data-drop-area]').forEach((area) => areas.add(area));
    return Array.from(areas);
  }

  function updateDropAreasDropEffect(effect) {
    getDropAreas().forEach((area) => {
      area.setAttribute('aria-dropeffect', effect);
    });
  }

  function resetDropAreasDropEffect() {
    updateDropAreasDropEffect('none');
  }

  function clearDropMetrics() {
    if (state.dropMetrics && state.dropMetrics.containers) {
      state.dropMetrics.containers.clear();
    }
    state.dropMetrics = null;
  }

  function invalidateDropMetrics() {
    clearDropMetrics();
  }

  function containsBlockWrapper(node) {
    if (!node || node.nodeType !== 1) return false;
    if (node.classList && node.classList.contains('block-wrapper')) return true;
    if (typeof node.querySelector === 'function') {
      return !!node.querySelector('.block-wrapper');
    }
    return false;
  }

  function handleCanvasMutations(mutations) {
    for (const mutation of mutations) {
      if (mutation.type !== 'childList') continue;
      const added = Array.from(mutation.addedNodes || []);
      const removed = Array.from(mutation.removedNodes || []);
      if (added.some(containsBlockWrapper) || removed.some(containsBlockWrapper)) {
        invalidateDropMetrics();
        return;
      }
    }
  }

  function setupDropMetricsObserver(canvas) {
    if (state.dropMetricsObserver) {
      state.dropMetricsObserver.disconnect();
      state.dropMetricsObserver = null;
    }
    if (!canvas || typeof MutationObserver !== 'function') return;
    state.dropMetricsObserver = new MutationObserver(handleCanvasMutations);
    state.dropMetricsObserver.observe(canvas, { childList: true, subtree: true });
  }

  function collectDropMetricsForArea(area) {
    if (!area) return [];
    const metrics = [];
    const els = area.querySelectorAll('.block-wrapper:not(.dragging)');
    for (let i = 0; i < els.length; i++) {
      const rect = els[i].getBoundingClientRect();
      metrics.push({ element: els[i], top: rect.top, height: rect.height });
    }
    return metrics;
  }

  function prepareDropMetricsCache() {
    clearDropMetrics();
    if (typeof document === 'undefined') {
      state.dropMetrics = { containers: new Map() };
      return;
    }
    const containers = new Map();
    const areas = document.querySelectorAll('[data-drop-area]');
    areas.forEach((area) => {
      containers.set(area, collectDropMetricsForArea(area));
    });
    state.dropMetrics = { containers };
  }

  function getCachedDropMetrics(container) {
    if (!container) return [];
    if (!state.dropMetrics) {
      prepareDropMetricsCache();
    }
    if (!state.dropMetrics) return [];
    let metrics = state.dropMetrics.containers.get(container);
    if (!metrics) {
      metrics = collectDropMetricsForArea(container);
      state.dropMetrics.containers.set(container, metrics);
    }
    return metrics;
  }

  function registerTemplateMetadata(block) {
    if (!block) return;
    const template = block.dataset ? block.dataset.template || '' : '';
    if (!template) return;
    const revision = block.dataset.templateRevision || '';
    const expiresRaw = block.dataset.templateExpires || '';
    const expiresAt = Number(expiresRaw) || 0;
    if (revision || expiresAt) {
      state.templateMetadata.set(template, {
        revision,
        expiresAt,
      });
      return;
    }
    const cached = getTemplateCacheMetadata(template);
    if (cached) {
      state.templateMetadata.set(template, cached);
    }
  }

  function updateTemplateMetadata(template) {
    if (!template) return;
    const metadata = getTemplateCacheMetadata(template);
    if (metadata) {
      state.templateMetadata.set(template, metadata);
    }
  }

  function invalidateTemplateCache(template = null) {
    if (typeof template === 'string' && template) {
      state.templateMetadata.delete(template);
      clearTemplateCacheEntry(template);
      return;
    }
    state.templateMetadata.clear();
    clearTemplateCacheEntry(null);
  }

  function getTemplateCacheInfo(template) {
    if (!template) return null;
    return state.templateMetadata.get(template) || null;
  }

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
      item.setAttribute('aria-grabbed', 'false');
    });
  }

  function setOptions(opts = {}) {
    if ('palette' in opts) state.palette = opts.palette;
    if ('canvas' in opts) {
      if (state.canvas !== opts.canvas) {
        invalidateDropMetrics();
      }
      state.canvas = opts.canvas;
      setupDropMetricsObserver(state.canvas);
    }
    if ('basePath' in opts) state.basePath = opts.basePath || '';
    if ('loggedIn' in opts) state.loggedIn = !!opts.loggedIn;
    if ('openSettings' in opts) state.openSettings = opts.openSettings;
    if ('applyStoredSettings' in opts)
      state.applyStoredSettings = opts.applyStoredSettings;
    if ('recordOperation' in opts)
      state.recordOperation = typeof opts.recordOperation === 'function' ? opts.recordOperation : null;
    if ('onTemplateError' in opts)
      state.onTemplateError =
        typeof opts.onTemplateError === 'function' ? opts.onTemplateError : null;
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
      setAriaGrabbed(item, true);
      updateDropAreasDropEffect('copy');
      e.dataTransfer.setData('text/plain', normalized);
      e.dataTransfer.effectAllowed = 'copy';
      item.classList.add('dragging');

      prepareDropMetricsCache();

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
      setAriaGrabbed(state.dragSource, true);
      setAriaGrabbed(handle, true);
      updateDropAreasDropEffect('move');
      state.dragSourcePath = getBlockPath(state.dragSource, state.canvas);
      prepareDropMetricsCache();
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
    area.setAttribute('aria-dropeffect', 'none');
  }

  function addBlockControls(block) {
    if (!block) return;
    ensureBlockState(block);
    if (state.applyStoredSettings) state.applyStoredSettings(block);
    block.setAttribute('aria-grabbed', block.getAttribute('aria-grabbed') || 'false');
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
    if (dragHandle) {
      dragHandle.setAttribute('draggable', 'true');
      dragHandle.setAttribute('aria-grabbed', dragHandle.getAttribute('aria-grabbed') || 'false');
    }
    if (!block.dataset.original) {
      let html = block.innerHTML;
      const { ts, cleaned } = extractTemplateSetting(html);
      block.dataset.original = sanitizeTemplateMarkup(cleaned);
      if (ts) {
        const { success, value } = safeEncodeTemplateSetting(
          block,
          ts,
          'initialize block controls'
        );
        if (success && value) {
          block.dataset.ts = value;
        } else {
          delete block.dataset.ts;
        }
      }
    } else {
      const { ts, cleaned } = extractTemplateSetting(block.dataset.original);
      block.dataset.original = sanitizeTemplateMarkup(cleaned);
      if (ts && !block.dataset.ts) {
        const { success, value } = safeEncodeTemplateSetting(
          block,
          ts,
          'restore block controls'
        );
        if (success && value) {
          block.dataset.ts = value;
        } else {
          delete block.dataset.ts;
        }
      }
    }
    const tsEl = block.querySelector('templateSetting');
    if (tsEl) tsEl.remove();
    const areas = block.querySelectorAll('.drop-area');
    areas.forEach(setupDropArea);
    if (areas.length === 0) {
      setupDropArea(block);
    }

    registerTemplateMetadata(block);
  }

  function resetDragState(source = state.dragSource) {
    state.placeholder.remove();
    state.insertionIndicator.remove();
    if (source) {
      source.classList.remove('dragging');
      setAriaGrabbed(source, false);
      const sourceHandle = source.querySelector('.block-controls .drag');
      if (sourceHandle) setAriaGrabbed(sourceHandle, false);
    }
    getDropAreas().forEach((area) => area.classList.remove('drag-over'));
    resetDropAreasDropEffect();
    state.dragSource = null;
    state.dragSourcePath = null;
    state.fromPalette = false;
    clearDropMetrics();
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
    if (!area) {
      announce('Drop canceled. No valid drop target.');
      resetDragState();
      return;
    }
    e.preventDefault();
    const after = getDragAfterElement(area, e.clientY);
    const source = state.dragSource;

    if (state.fromPalette && source) {
      const file = normalizeTemplateName(source.dataset.file || '');
      if (file && state.allowedTemplates.has(file)) {
        const meta = parsePaletteMeta(source);
        const schema = { template: file, settings: {}, areas: [] };
        if (meta) {
          schema.meta = Object.assign({}, meta, { template: file });
        }
        const request = createBlockElementFromSchema(schema, {
          basePath: state.basePath,
          applyStoredSettings: state.applyStoredSettings,
          addBlockControls,
        });

        state.lastTemplateRequest = request;

        request
          .then((wrapper) => {
            if (!wrapper) {
              announce('Failed to add block.');
              return;
            }
            if (after == null) area.appendChild(wrapper);
            else area.insertBefore(wrapper, after);

            executeScripts(wrapper);

            updateTemplateMetadata(file);
            registerTemplateMetadata(wrapper);

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
            announce('Block added to canvas.');
          })
          .catch((error) => {
            announce('Failed to add block.');
            if (typeof state.onTemplateError === 'function') {
              try {
                state.onTemplateError(error, { template: file, meta });
              } catch (callbackError) {
                console.error('Template error handler failed', callbackError);
              }
            }
          })
          .finally(() => {
            if (state.lastTemplateRequest === request) {
              state.lastTemplateRequest = null;
            }
            area.classList.remove('drag-over');
            resetDragState(source);
          });
        return;
      }
      announce('Block cannot be dropped here.');
      area.classList.remove('drag-over');
      resetDragState(source);
      return;
    }

    if (source) {
      if (after == null) area.appendChild(source);
      else area.insertBefore(source, after);

      if (
        typeof state.recordOperation === 'function' &&
        Array.isArray(state.dragSourcePath) &&
        state.dragSourcePath.length
      ) {
        const newPath = getBlockPath(source, state.canvas);
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
      announce('Block moved.');
      area.classList.remove('drag-over');
      resetDragState(source);
      return;
    }

    announce('Drop canceled. Nothing to drop.');
    area.classList.remove('drag-over');
    resetDragState();
  }

  function handleDragEnd() {
    resetDragState();
  }

  function getDragAfterElement(container, y) {
    let metrics = getCachedDropMetrics(container);
    let closest = null;
    let closestOffset = Number.NEGATIVE_INFINITY;
    if (!metrics || !metrics.length) {
      metrics = collectDropMetricsForArea(container);
      if (state.dropMetrics) {
        state.dropMetrics.containers.set(container, metrics);
      }
    }
    for (let i = 0; i < metrics.length; i++) {
      const { element, top, height } = metrics[i];
      const offset = y - top - height / 2;
      if (offset < 0 && offset > closestOffset) {
        closestOffset = offset;
        closest = element;
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
      state.canvas
        .querySelectorAll('.block-wrapper')
        .forEach((block) => registerTemplateMetadata(block));
    }
  }

  return {
    init,
    setOptions,
    addBlockControls,
    setupDropArea,
    invalidateTemplateCache,
    getTemplateCacheInfo,
  };
}
