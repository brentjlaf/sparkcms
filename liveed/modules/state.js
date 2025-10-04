// File: state.js
const stateMap = new Map();
let nextId = 1;
const RESERVED_KEYS = new Set(['blockId', 'template', 'original', 'active', 'ts']);

const templateCache = new Map();

function cloneSettings(settings = {}) {
  return Object.fromEntries(Object.entries(settings).map(([k, v]) => [k, v]));
}

function normalizeNextId(id) {
  const match = /^b(\d+)$/.exec(id);
  if (!match) return;
  const value = parseInt(match[1], 10);
  if (!Number.isNaN(value)) {
    nextId = Math.max(nextId, value + 1);
  }
}

export function ensureBlockState(block, initialSettings = null) {
  if (!block) return null;
  if (!block.dataset.blockId) {
    block.dataset.blockId = 'b' + nextId++;
  } else {
    normalizeNextId(block.dataset.blockId);
  }
  const id = block.dataset.blockId;
  if (!stateMap.has(id)) {
    let data = {};
    if (initialSettings && typeof initialSettings === 'object') {
      data = cloneSettings(initialSettings);
    } else {
      for (const [key, val] of Object.entries(block.dataset)) {
        if (!RESERVED_KEYS.has(key)) {
          data[key] = val;
        }
      }
    }
    stateMap.set(id, data);
  } else if (initialSettings && typeof initialSettings === 'object') {
    stateMap.set(id, cloneSettings(initialSettings));
  }
  return id;
}

export function getSettings(block) {
  ensureBlockState(block);
  return stateMap.get(block.dataset.blockId) || {};
}

export function setSetting(block, name, value) {
  const s = getSettings(block);
  s[name] = value;
  if (block) {
    block.dataset[name] = value;
  }
}

export function getSetting(block, name, defaultValue = '') {
  const s = getSettings(block);
  return s[name] !== undefined ? s[name] : defaultValue;
}

function extractTemplateSetting(html) {
  const match = html.match(/<templateSetting[^>]*>[\s\S]*?<\/templateSetting>/i);
  const ts = match ? match[0] : '';
  const cleaned = match ? html.replace(match[0], '') : html;
  return { ts, cleaned };
}

function computeTooltip(template) {
  if (!template) return '';
  const base = template.replace(/\.php$/, '');
  const parts = base.split('.');
  const group = parts.shift();
  const raw = parts.join(' ') || group || '';
  return raw
    .replace(/[-_]/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

async function loadTemplate(basePath = '', template) {
  if (!template) return { cleaned: '', ts: '' };
  const cached = templateCache.get(template);
  if (cached) {
    return cached;
  }
  const request = fetch(
    basePath + '/liveed/load-block.php?file=' + encodeURIComponent(template)
  )
    .then((r) => r.text())
    .then((html) => {
      const parsed = extractTemplateSetting(html);
      templateCache.set(template, parsed);
      return parsed;
    })
    .catch((error) => {
      templateCache.delete(template);
      throw error;
    });
  templateCache.set(template, request);
  return request;
}

function getDropAreas(block) {
  return Array.from(block.querySelectorAll('.drop-area')).filter(
    (area) => area.closest('.block-wrapper') === block
  );
}

export function serializeBlock(block) {
  if (!block) return null;
  ensureBlockState(block);
  const settings = cloneSettings(getSettings(block));
  const dropAreas = getDropAreas(block);
  const areas = dropAreas.map((area) =>
    Array.from(area.children)
      .filter((child) => child.classList && child.classList.contains('block-wrapper'))
      .map((child) => serializeBlock(child))
      .filter(Boolean)
  );
  return {
    template: block.dataset.template || '',
    settings,
    areas,
  };
}

export function serializeCanvas(canvas) {
  if (!canvas) return { version: 1, blocks: [] };
  const blocks = Array.from(canvas.children)
    .filter((child) => child.classList && child.classList.contains('block-wrapper'))
    .map((child) => serializeBlock(child))
    .filter(Boolean);
  return { version: 1, blocks };
}

export function decodeDraftContent(raw) {
  if (!raw) return null;
  if (typeof raw === 'object' && raw !== null) {
    return raw;
  }
  if (typeof raw !== 'string') return null;
  try {
    const data = JSON.parse(raw);
    if (data && typeof data === 'object') {
      return data;
    }
  } catch (e) {
    // fall through
  }
  return { html: raw };
}

export async function createBlockElementFromSchema(schema, options = {}) {
  if (!schema) return null;
  const { basePath = '', applyStoredSettings, addBlockControls } = options;
  const block = document.createElement('div');
  block.className = 'block-wrapper';
  block.dataset.template = schema.template || '';
  const { cleaned, ts } = await loadTemplate(basePath, schema.template);
  block.innerHTML = cleaned || '';
  block.dataset.original = cleaned || '';
  if (ts) {
    try {
      block.dataset.ts = btoa(ts);
    } catch (e) {
      block.dataset.ts = '';
    }
  }
  const tooltip = computeTooltip(schema.template);
  if (tooltip) {
    block.setAttribute('data-tpl-tooltip', tooltip);
  }
  const initialSettings = schema.settings || {};
  ensureBlockState(block, initialSettings);
  for (const [key, value] of Object.entries(initialSettings)) {
    block.dataset[key] = value;
  }

  const dropAreas = getDropAreas(block);
  const areas = Array.isArray(schema.areas) ? schema.areas : [];
  for (let i = 0; i < areas.length; i++) {
    const areaEl = dropAreas[i];
    if (!areaEl) continue;
    const children = Array.isArray(areas[i]) ? areas[i] : [];
    for (const childSchema of children) {
      const child = await createBlockElementFromSchema(childSchema, options);
      if (child) {
        areaEl.appendChild(child);
      }
    }
  }

  if (typeof applyStoredSettings === 'function') {
    applyStoredSettings(block);
  }

  if (typeof addBlockControls === 'function') {
    addBlockControls(block);
  }

  return block;
}

export async function renderCanvasFromSchema(canvas, schema, options = {}) {
  if (!canvas || !schema) return;
  const blocks = Array.isArray(schema.blocks) ? schema.blocks : [];
  stateMap.clear();
  nextId = 1;
  Array.from(canvas.querySelectorAll('.block-wrapper')).forEach((el) => el.remove());
  for (const blockSchema of blocks) {
    const block = await createBlockElementFromSchema(blockSchema, options);
    if (block) {
      canvas.appendChild(block);
    }
  }
}

export { stateMap };
