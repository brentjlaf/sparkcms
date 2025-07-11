import { ensureBlockState } from './state.js';

let palette;
let canvas;
let basePath = '';
let loggedIn = false;
let openSettings;
let applyStoredSettings;

let dragSource = null;
let fromPalette = false;
let currentDropArea = null;

const placeholder = document.createElement('div');
placeholder.className = 'block-placeholder';
placeholder.innerHTML = '<span class="drop-text">Drop block here</span>';

const insertionIndicator = document.createElement('div');
insertionIndicator.className = 'insertion-indicator';

export function initDragDrop(options = {}) {
  palette = options.palette;
  canvas = options.canvas;
  basePath = options.basePath || '';
  loggedIn = options.loggedIn || false;
  openSettings = options.openSettings;
  applyStoredSettings = options.applyStoredSettings;

  if (palette) palette.addEventListener('dragstart', paletteDragStart);
  if (canvas) {
    canvas.addEventListener('dragstart', canvasDragStart);
    canvas.addEventListener('dragenter', handleDragEnter, true);
    canvas.addEventListener('dragleave', handleDragLeave, true);
    canvas.addEventListener('dragover', handleDragOver, true);
    canvas.addEventListener('drop', handleDrop, true);
    canvas.addEventListener('dragend', handleDragEnd, true);
  }
  setupDropArea(canvas);
}

function paletteDragStart(e) {
  const item = e.target.closest('.block-item');
  if (item) {
    dragSource = item;
    fromPalette = true;
    e.dataTransfer.setData('text/plain', item.dataset.file || '');
    e.dataTransfer.effectAllowed = 'copy';
    item.classList.add('dragging');

    const dragImage = item.cloneNode(true);
    dragImage.classList.add('drag-ghost');
    dragImage.style.position = 'absolute';
    dragImage.style.top = '-1000px';
    document.body.appendChild(dragImage);
    e.dataTransfer.setDragImage(dragImage, dragImage.offsetWidth / 2, dragImage.offsetHeight / 2);
    setTimeout(() => document.body.removeChild(dragImage), 0);
  }
}

function canvasDragStart(e) {
  const handle = e.target.closest('.control.drag');
  if (handle) {
    dragSource = handle.closest('.block-wrapper');
    fromPalette = false;
    dragSource.classList.add('dragging');
    e.dataTransfer.setData('text/plain', 'reorder');
    e.dataTransfer.effectAllowed = 'move';

    const dragImage = dragSource.cloneNode(true);
    dragImage.classList.add('drag-ghost');
    dragImage.style.position = 'absolute';
    dragImage.style.top = '-1000px';
    document.body.appendChild(dragImage);
    e.dataTransfer.setDragImage(dragImage, dragImage.offsetWidth / 2, dragImage.offsetHeight / 2);
    setTimeout(() => document.body.removeChild(dragImage), 0);
  } else if (e.target.closest('.block-wrapper')) {
    e.preventDefault();
  }
}

export function addBlockControls(block) {
  ensureBlockState(block);
  if (!loggedIn) {
    const existing = block.querySelector('.block-controls');
    if (existing) existing.remove();
    block.removeAttribute('draggable');
    return;
  }
  if (!block.querySelector('.block-controls')) {
    const controls = document.createElement('div');
    controls.className = 'block-controls';
    controls.innerHTML =
      '<span class="control edit" title="Edit"><i class="fa-solid fa-pen"></i></span>' +
      '<span class="control drag" title="Drag"><i class="fa-solid fa-arrows-up-down-left-right"></i></span>' +
      '<span class="control delete" title="Delete"><i class="fa-solid fa-trash"></i></span>';
    block.style.position = 'relative';
    block.appendChild(controls);
  }
  block.removeAttribute('draggable');
  const dragHandle = block.querySelector('.block-controls .drag');
  if (dragHandle) dragHandle.setAttribute('draggable', 'true');
  if (!block.dataset.original) {
    block.dataset.original = block.innerHTML;
  }
  const ts = block.querySelector('templateSetting');
  if (ts) ts.remove();
  const areas = block.querySelectorAll('.drop-area');
  areas.forEach(setupDropArea);
  if (areas.length === 0) {
    setupDropArea(block);
  }
  if (applyStoredSettings) applyStoredSettings(block);
}

export function setupDropArea(area) {
  if (!area) return;
  area.dataset.dropArea = 'true';
}

function handleDragEnter(e) {
  const area = e.target.closest('[data-drop-area]');
  if (area) {
    currentDropArea = area;
    area.classList.add('drag-over');
  }
}

function handleDragLeave(e) {
  const area = e.target.closest('[data-drop-area]');
  if (area && (!e.relatedTarget || !area.contains(e.relatedTarget))) {
    area.classList.remove('drag-over');
    placeholder.remove();
    insertionIndicator.remove();
    if (currentDropArea === area) currentDropArea = null;
  }
}

function handleDragOver(e) {
  const area = e.target.closest('[data-drop-area]');
  if (!area) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = fromPalette ? 'copy' : 'move';
  const after = getDragAfterElement(area, e.clientY);
  if (after == null) {
    area.appendChild(placeholder);
  } else {
    area.insertBefore(placeholder, after);
  }
  insertionIndicator.remove();
  if (placeholder.parentNode) {
    placeholder.parentNode.insertBefore(insertionIndicator, placeholder);
  }
}

function handleDrop(e) {
  const area = e.target.closest('[data-drop-area]');
  if (!area) return;
  e.preventDefault();
  const after = getDragAfterElement(area, e.clientY);
  if (fromPalette && dragSource) {
    const file = dragSource.dataset.file;
    if (file) {
      fetch(basePath + '/liveed/load-block.php?file=' + encodeURIComponent(file))
        .then((r) => r.text())
        .then((html) => {
          const wrapper = document.createElement('div');
          wrapper.className = 'block-wrapper';
          wrapper.dataset.template = file;
          wrapper.dataset.original = html;
          wrapper.innerHTML = html;
          addBlockControls(wrapper);
          if (applyStoredSettings) applyStoredSettings(wrapper);
          if (after == null) area.appendChild(wrapper);
          else area.insertBefore(wrapper, after);
          if (openSettings) openSettings(wrapper);
          document.dispatchEvent(new Event('canvasUpdated'));
        });
    }
  } else if (dragSource) {
    dragSource.classList.remove('dragging');
    if (after == null) area.appendChild(dragSource);
    else area.insertBefore(dragSource, after);
    document.dispatchEvent(new Event('canvasUpdated'));
  }
  placeholder.remove();
  insertionIndicator.remove();
  area.classList.remove('drag-over');
  dragSource = null;
  fromPalette = false;
  currentDropArea = null;
}

function handleDragEnd() {
  placeholder.remove();
  insertionIndicator.remove();
  if (dragSource) dragSource.classList.remove('dragging');
  dragSource = null;
  currentDropArea = null;
}

function getDragAfterElement(container, y) {
  const els = [...container.querySelectorAll('.block-wrapper:not(.dragging)')];
  return els.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) {
      return { offset: offset, element: child };
    }
    return closest;
  }, { offset: Number.NEGATIVE_INFINITY }).element;
}
