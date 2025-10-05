// File: state.js
import { sanitizeTemplateMarkup } from './sanitizer.js';
import { encodeUnicodeBase64 } from './base64.js';
const stateMap = new Map();
let nextId = 1;
const RESERVED_KEYS = new Set(['blockId', 'template', 'original', 'active', 'ts']);

const templateCache = new Map();
const TEMPLATE_CACHE_TTL = 5 * 60 * 1000; // 5 minutes

async function computeRevisionHash(content = '', ts = '') {
  const payload = String(content || '') + '|' + String(ts || '');
  try {
    const cryptoObj = typeof globalThis !== 'undefined' ? globalThis.crypto : null;
    if (cryptoObj && cryptoObj.subtle && typeof globalThis.TextEncoder === 'function') {
      const encoder = new globalThis.TextEncoder();
      const data = encoder.encode(payload);
      const hashBuffer = await cryptoObj.subtle.digest('SHA-256', data);
      const hashArray = Array.from(new Uint8Array(hashBuffer));
      return hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
    }
  } catch (error) {
    // fall back to non-cryptographic hash below
  }

  let hash = 0;
  for (let i = 0; i < payload.length; i++) {
    hash = (hash << 5) - hash + payload.charCodeAt(i);
    hash |= 0; // convert to 32-bit integer
  }
  return (hash >>> 0).toString(16);
}

function normalizeCacheEntry(entry) {
  if (!entry) return null;
  if (entry.promise) return entry;
  const revision = typeof entry.revision === 'string' ? entry.revision : '';
  const expiresAt = Number(entry.expiresAt) || 0;
  return {
    cleaned: entry.cleaned || '',
    ts: entry.ts || '',
    revision,
    expiresAt,
  };
}

function isCacheEntryValid(entry) {
  if (!entry || entry.promise) return false;
  const expiresAt = Number(entry.expiresAt) || 0;
  if (!expiresAt) return true;
  return expiresAt > Date.now();
}

function setResolvedCacheEntry(template, entry) {
  if (!template) return entry;
  const normalized = normalizeCacheEntry(entry);
  if (!normalized) return entry;
  templateCache.set(template, normalized);
  return normalized;
}

async function fetchTemplate(basePath = '', template) {
  const response = await fetch(
    basePath + '/liveed/load-block.php?file=' + encodeURIComponent(template)
  );
  if (!response.ok) {
    const error = new Error('Failed to load block template.');
    error.status = response.status;
    throw error;
  }
  const html = await response.text();
  const parsed = extractTemplateSetting(html);
  const sanitized = {
    cleaned: sanitizeTemplateMarkup(parsed.cleaned),
    ts: parsed.ts,
  };
  const revision = await computeRevisionHash(sanitized.cleaned, sanitized.ts);
  return {
    cleaned: sanitized.cleaned,
    ts: sanitized.ts,
    revision,
    expiresAt: Date.now() + TEMPLATE_CACHE_TTL,
  };
}

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

function normalizeBlockMetadata(meta, template = '') {
  const source = meta && typeof meta === 'object' ? meta : {};
  const normalized = {};
  const providedTemplate =
    typeof source.template === 'string' && source.template.trim() ? source.template.trim() : '';
  const fallbackTemplate = typeof template === 'string' && template.trim() ? template.trim() : '';
  const templateName = fallbackTemplate || providedTemplate;
  if (templateName) {
    normalized.template = templateName.endsWith('.php') ? templateName : templateName + '.php';
  }

  const providedId = typeof source.id === 'string' && source.id.trim() ? source.id.trim() : '';
  const derivedId = providedId || (normalized.template ? normalized.template.replace(/\.php$/i, '') : '');
  if (derivedId) {
    normalized.id = derivedId;
  }

  const providedGroup = typeof source.group === 'string' && source.group.trim() ? source.group.trim() : '';
  const derivedGroup = providedGroup || (derivedId ? derivedId.split('.')[0] : '');
  if (derivedGroup) {
    normalized.group = derivedGroup.toLowerCase();
  }

  const tooltipSource = normalized.template || (derivedId ? derivedId + '.php' : '');
  const providedLabel = typeof source.label === 'string' && source.label.trim() ? source.label.trim() : '';
  const derivedLabel = providedLabel || computeTooltip(tooltipSource);
  if (derivedLabel) {
    normalized.label = derivedLabel;
  }

  if (Array.isArray(source.capabilities)) {
    const caps = source.capabilities
      .map((cap) => (typeof cap === 'string' ? cap.trim().toLowerCase() : ''))
      .filter(Boolean);
    if (caps.length) {
      normalized.capabilities = Array.from(new Set(caps)).sort();
    }
  }

  return normalized;
}

function applyBlockMetadata(block, meta, template = '') {
  if (!block) return {};
  const normalized = normalizeBlockMetadata(meta, template);
  if (normalized.template) {
    block.dataset.template = normalized.template;
  }
  if (normalized.label) {
    block.setAttribute('data-tpl-tooltip', normalized.label);
  } else if (block.dataset.template) {
    const fallbackTooltip = computeTooltip(block.dataset.template);
    if (fallbackTooltip) {
      block.setAttribute('data-tpl-tooltip', fallbackTooltip);
    }
  }
  if (normalized.id && normalized.id.endsWith('.php')) {
    normalized.id = normalized.id.replace(/\.php$/i, '');
  }
  if (normalized.template && !normalized.template.endsWith('.php')) {
    normalized.template += '.php';
  }
  try {
    block.dataset.blockMeta = JSON.stringify(normalized);
  } catch (error) {
    block.dataset.blockMeta = JSON.stringify({
      id: normalized.id || '',
      template: normalized.template || template || '',
    });
  }
  return normalized;
}

function readBlockMetadata(block, template = '') {
  if (!block) return null;
  let parsed = null;
  if (block.dataset.blockMeta) {
    try {
      parsed = JSON.parse(block.dataset.blockMeta);
    } catch (error) {
      parsed = null;
    }
  }
  const normalized = normalizeBlockMetadata(parsed || {}, template || block.dataset.template || '');
  if (normalized.label && normalized.template && normalized.id) {
    return normalized;
  }
  if (block.dataset.template) {
    return normalizeBlockMetadata({}, block.dataset.template);
  }
  return normalized;
}

async function loadTemplate(basePath = '', template) {
  if (!template) return { cleaned: '', ts: '', revision: '', expiresAt: 0 };
  const cached = templateCache.get(template);
  if (cached) {
    if (cached.promise) {
      return cached.promise;
    }
    if (isCacheEntryValid(cached)) {
      return cached;
    }
    templateCache.delete(template);
  }

  const requestPromise = fetchTemplate(basePath, template)
    .then((entry) => setResolvedCacheEntry(template, entry))
    .catch((error) => {
      templateCache.delete(template);
      throw error;
    });

  templateCache.set(template, { promise: requestPromise });
  return requestPromise;
}

export function getTemplateCacheMetadata(template) {
  if (!template) return null;
  const entry = normalizeCacheEntry(templateCache.get(template));
  if (!entry) return null;
  return {
    revision: entry.revision || '',
    expiresAt: Number(entry.expiresAt) || 0,
  };
}

export function invalidateTemplateCache(template = null) {
  if (typeof template === 'string' && template) {
    templateCache.delete(template);
    return;
  }
  templateCache.clear();
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
  const template = block.dataset.template || '';
  const metadata = readBlockMetadata(block, template);
  const serialized = {
    template,
    settings,
    areas,
  };
  if (metadata && Object.keys(metadata).length) {
    serialized.meta = metadata;
  }
  return serialized;
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
  const { cleaned, ts, revision, expiresAt } = await loadTemplate(
    basePath,
    schema.template
  );
  block.innerHTML = cleaned || '';
  block.dataset.original = cleaned || '';
  if (ts) {
    try {
      block.dataset.ts = encodeUnicodeBase64(ts);
    } catch (e) {
      block.dataset.ts = '';
    }
  }
  if (revision) {
    block.dataset.templateRevision = revision;
  }
  if (expiresAt) {
    block.dataset.templateExpires = String(expiresAt);
  }
  applyBlockMetadata(block, schema.meta || null, schema.template || '');
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
