// File: settings.js
import { ensureBlockState, getSetting, setSetting, getSettings } from './state.js';
import { executeScripts } from "./executeScripts.js";

let canvas;
let settingsPanel;
let settingsContent;
let savePageFn;
let renderDebounce;
let addBlockControlsFn;
let templateNameEl;

const FORMS_SELECT_ATTR = 'data-forms-select';
const BLOG_CATEGORY_SELECT_ATTR = 'data-blog-category-select';
const EVENTS_CATEGORY_SELECT_ATTR = 'data-events-category-select';
let cachedForms = null;
let formsRequest = null;
let cachedBlogCategories = null;
let blogCategoriesRequest = null;
let cachedEventCategories = null;
let eventCategoriesRequest = null;

function getFormsEndpoint() {
  const base = (window.builderBase || window.cmsBase || '').replace(/\/$/, '');
  return (base || '') + '/CMS/modules/forms/list_forms.php';
}

function fetchFormsList() {
  if (cachedForms) return Promise.resolve(cachedForms);
  if (formsRequest) return formsRequest;
  const endpoint = getFormsEndpoint();
  formsRequest = fetch(endpoint, { credentials: 'same-origin' })
    .then((response) => {
      if (!response.ok) throw new Error('Failed to load forms');
      return response.json();
    })
    .then((data) => {
      cachedForms = Array.isArray(data) ? data : [];
      return cachedForms;
    })
    .catch(() => {
      cachedForms = [];
      return cachedForms;
    });
  return formsRequest;
}

function getBlogCategoriesEndpoint() {
  const base = (window.builderBase || window.cmsBase || '').replace(/\/$/, '');
  return (base || '') + '/CMS/modules/blogs/list_categories.php';
}

function fetchBlogCategories() {
  if (cachedBlogCategories) return Promise.resolve(cachedBlogCategories);
  if (blogCategoriesRequest) return blogCategoriesRequest;
  const endpoint = getBlogCategoriesEndpoint();
  blogCategoriesRequest = fetch(endpoint, { credentials: 'same-origin' })
    .then((response) => {
      if (!response.ok) throw new Error('Failed to load blog categories');
      return response.json();
    })
    .then((data) => {
      const result = [];
      const seen = new Set();
      if (Array.isArray(data)) {
        data.forEach((entry) => {
          if (typeof entry !== 'string') return;
          const trimmed = entry.trim();
          if (!trimmed) return;
          const key = trimmed.toLowerCase();
          if (seen.has(key)) return;
          seen.add(key);
          result.push(trimmed);
        });
      }
      cachedBlogCategories = result;
      return cachedBlogCategories;
    })
    .catch(() => {
      cachedBlogCategories = [];
      return cachedBlogCategories;
    });
  return blogCategoriesRequest;
}

function getEventCategoriesEndpoint() {
  const base = (window.builderBase || window.cmsBase || '').replace(/\/$/, '');
  return (base || '') + '/CMS/modules/events/api.php?action=list_categories';
}

function fetchEventCategories() {
  if (cachedEventCategories) return Promise.resolve(cachedEventCategories);
  if (eventCategoriesRequest) return eventCategoriesRequest;
  const endpoint = getEventCategoriesEndpoint();
  eventCategoriesRequest = fetch(endpoint, { credentials: 'same-origin' })
    .then((response) => {
      if (!response.ok) throw new Error('Failed to load event categories');
      return response.json();
    })
    .then((data) => {
      const categories = data && Array.isArray(data.categories) ? data.categories : [];
      const seen = new Set();
      const result = [];
      categories.forEach((entry) => {
        if (!entry || typeof entry !== 'object') return;
        const name = typeof entry.name === 'string' ? entry.name.trim() : '';
        const slug = typeof entry.slug === 'string' ? entry.slug.trim() : '';
        const id = typeof entry.id === 'string' ? entry.id.trim() : '';
        const value = slug || id || name;
        if (!value) return;
        const normalized = value.toLowerCase();
        if (seen.has(normalized)) return;
        seen.add(normalized);
        result.push({
          value,
          label: name || slug || id,
        });
      });
      cachedEventCategories = result;
      return cachedEventCategories;
    })
    .catch(() => {
      cachedEventCategories = [];
      return cachedEventCategories;
    });
  return eventCategoriesRequest;
}

function formatSegment(text = '') {
  return text
    .replace(/[-_]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatTemplateName(template) {
  if (!template) return '';
  const base = String(template).replace(/\.php$/i, '');
  const parts = base.split('.');
  if (!parts.length) return '';
  const group = parts.shift() || '';
  const name = parts.length ? parts.join(' ') : group;
  const groupLabel = formatSegment(group);
  const nameLabel = formatSegment(name);
  if (groupLabel && nameLabel && groupLabel !== nameLabel) {
    return `${groupLabel} / ${nameLabel}`;
  }
  return nameLabel || groupLabel;
}

function updateTemplateHeading(template = '') {
  if (!templateNameEl) return;
  const label = formatTemplateName(template);
  if (label) {
    templateNameEl.textContent = `Template: ${label}`;
    templateNameEl.title = template;
  } else {
    templateNameEl.textContent = '';
    templateNameEl.removeAttribute('title');
  }
}

function splitCommaValues(value) {
  if (value == null || value === '') return [];
  if (Array.isArray(value)) {
    return value
      .map((entry) => (typeof entry === 'string' ? entry.trim() : ''))
      .filter((entry) => entry.length > 0);
  }
  return String(value)
    .split(',')
    .map((entry) => entry.trim())
    .filter((entry) => entry.length > 0);
}

function applySelectValue(select, value) {
  if (!select) return;
  if (select.multiple) {
    const useAll = value == null || value === '';
    const selectedValues = new Set(splitCommaValues(value));
    Array.from(select.options).forEach((option) => {
      if (option.value === '') {
        option.selected = useAll;
      } else {
        option.selected = !useAll && selectedValues.has(option.value);
      }
    });
  } else {
    const target = value ?? '';
    select.value = target;
    if (target && select.value !== target) {
      const manualOption = document.createElement('option');
      manualOption.value = target;
      manualOption.textContent = target;
      select.appendChild(manualOption);
      select.value = target;
    }
  }
}

function readSelectValue(select) {
  if (!select) return '';
  if (!select.multiple) {
    return select.value;
  }
  const values = [];
  let hasAll = false;
  Array.from(select.options).forEach((option) => {
    if (!option.selected) return;
    if (option.value === '') {
      hasAll = true;
    } else if (!values.includes(option.value)) {
      values.push(option.value);
    }
  });
  if (hasAll && values.length > 0) {
    return '';
  }
  if (hasAll) {
    return '';
  }
  return values.join(',');
}

function enforceMultiSelectAllOption(select) {
  if (!select || !select.multiple) return;
  const selected = Array.from(select.selectedOptions);
  const hasAll = selected.some((option) => option.value === '');
  if (hasAll && selected.length > 1) {
    Array.from(select.options).forEach((option) => {
      option.selected = option.value === '';
    });
  }
}

function populateFormsSelects(container, block) {
  if (!container) return;
  const selects = container.querySelectorAll(`select[${FORMS_SELECT_ATTR}]`);
  if (!selects.length) return;

  fetchFormsList().then((forms) => {
    selects.forEach((select) => {
      const placeholder = select.dataset.placeholder || 'Select a form...';
      const storedValue = block ? getSetting(block, select.name) : select.value;
      const fragment = document.createDocumentFragment();
      const placeholderOption = document.createElement('option');
      placeholderOption.value = '';
      placeholderOption.textContent = placeholder;
      fragment.appendChild(placeholderOption);

      forms.forEach((form) => {
        if (!form || typeof form !== 'object') return;
        const option = document.createElement('option');
        option.value = String(form.id ?? '');
        option.textContent = form.name || `Form ${form.id}`;
        fragment.appendChild(option);
      });

      const previousValue = select.value;
      select.innerHTML = '';
      select.appendChild(fragment);
      const targetValue = storedValue || previousValue || '';
      if (targetValue) {
        select.value = targetValue;
        if (select.value !== targetValue) {
          // Ensure value is set even if the option list changed type
          const manualOption = document.createElement('option');
          manualOption.value = targetValue;
          manualOption.textContent = targetValue;
          select.appendChild(manualOption);
          select.value = targetValue;
        }
      }
    });
  });
}

function populateBlogCategorySelects(container, block) {
  if (!container) return;
  const selects = container.querySelectorAll(`select[${BLOG_CATEGORY_SELECT_ATTR}]`);
  if (!selects.length) return;

  fetchBlogCategories().then((categories) => {
    selects.forEach((select) => {
      const storedValue = block ? getSetting(block, select.name) : select.value;
      const fragment = document.createDocumentFragment();
      const placeholder = select.dataset.placeholder || 'All categories';

      const allOption = document.createElement('option');
      allOption.value = '';
      allOption.textContent = placeholder;
      fragment.appendChild(allOption);

      categories.forEach((category) => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        fragment.appendChild(option);
      });

      select.innerHTML = '';
      select.appendChild(fragment);
      applySelectValue(select, storedValue);
    });
  });
}

function populateEventCategorySelects(container, block) {
  if (!container) return;
  const selects = container.querySelectorAll(`select[${EVENTS_CATEGORY_SELECT_ATTR}]`);
  if (!selects.length) return;

  fetchEventCategories().then((categories) => {
    selects.forEach((select) => {
      const storedValue = block ? getSetting(block, select.name) : select.value;
      const fragment = document.createDocumentFragment();
      const placeholder = select.dataset.placeholder || 'All categories';

      const allOption = document.createElement('option');
      allOption.value = '';
      allOption.textContent = placeholder;
      fragment.appendChild(allOption);

      categories.forEach((category) => {
        const option = document.createElement('option');
        option.value = category.value;
        option.textContent = category.label;
        fragment.appendChild(option);
      });

      select.innerHTML = '';
      select.appendChild(fragment);
      applySelectValue(select, storedValue);
    });
  });
}

function scheduleRender(block) {
  clearTimeout(renderDebounce);
  renderDebounce = setTimeout(() => renderBlock(block), 100);
}

function deriveAltText(src) {
  if (!src) return '';
  let name = src.split('/').pop();
  name = name.split('?')[0];
  name = name.replace(/\.[^/.]+$/, '');
  name = name.replace(/[-_]+/g, ' ').trim();
  if (!name) return '';
  return name.charAt(0).toUpperCase() + name.slice(1);
}

function suggestAltText(
  block,
  altName = 'custom_alt',
  srcName = 'custom_src',
  updateInput = false
) {
  const src = getSetting(block, srcName, '').trim();
  let alt = getSetting(block, altName, '').trim();
  const suggestion = deriveAltText(src);
  if (!suggestion || alt) return suggestion;
  setSetting(block, altName, suggestion);
  if (updateInput && settingsPanel) {
    const input = settingsPanel.querySelector(`input[name="${altName}"]`);
    if (input && !input.value) input.value = suggestion;
  }
  return suggestion;
}

function getTemplateSettingElement(block) {
  return (
    block.querySelector('templateSetting') ||
    (() => {
      const wrap = document.createElement('div');
      const settings = getSettings(block) || {};
      const encoded = block.dataset.ts || settings.ts;
      if (encoded) {
        try {
          wrap.innerHTML = atob(encoded);
        } catch (e) {
          wrap.innerHTML = '';
        }
      } else {
        wrap.innerHTML = block.dataset.original || block.innerHTML;
      }
      return wrap.querySelector('templateSetting');
    })()
  );
}

export function initSettings(options = {}) {
  canvas = options.canvas;
  settingsPanel = options.settingsPanel;
  savePageFn = options.savePage || function () {};
  addBlockControlsFn = options.addBlockControls;
  if (settingsPanel) {
    settingsContent = settingsPanel.querySelector('.settings-content');
    templateNameEl = settingsPanel.querySelector('.template-name');
    updateTemplateHeading();
    settingsPanel.addEventListener('click', (e) => {
      if (e.target.id === 'apply-settings') {
        const block = settingsPanel.block;
        const template = settingsPanel.template;
        if (block && validateSettings()) {
          applySettings(template, block);
          settingsPanel.classList.remove('open');
          settingsPanel.block = null;
          settingsPanel.template = null;
          updateTemplateHeading();
          canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
          savePageFn();
        }
      } else if (e.target.id === 'cancel-settings' || e.target.classList.contains('close-btn')) {
        settingsPanel.classList.remove('open');
        settingsPanel.block = null;
        settingsPanel.template = null;
        updateTemplateHeading();
        canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
      }
    });

    const handleSettingsInput = (e) => {
      const input = e.target.closest('input[name], textarea[name], select[name]');
      const block = settingsPanel.block;
      if (!input || !block) return;
      if (e.type === 'change' && input.tagName !== 'SELECT') return;

      let val;
      if (input.type === 'checkbox') {
        val = input.checked ? (input.value || 'on') : '';
      } else if (input.tagName === 'SELECT') {
        if (input.multiple) {
          enforceMultiSelectAllOption(input);
        }
        val = readSelectValue(input);
      } else {
        val = input.value;
      }
      setSetting(block, input.name, val);
      if (input.name === 'custom_src') {
        suggestAltText(block, 'custom_alt', 'custom_src', true);
      } else if (/^custom_img(\d+)$/.test(input.name)) {
        const idx = input.name.match(/^custom_img(\d+)$/)[1];
        suggestAltText(block, `custom_alt${idx}`, `custom_img${idx}`, true);
      }
      scheduleRender(block);
      // Automatically schedule a save whenever a setting changes so that
      // media selections are persisted even if the user forgets to press
      // the "Apply" button.
      if (typeof savePageFn === 'function') savePageFn();
    };

    settingsPanel.addEventListener('input', handleSettingsInput);
    settingsPanel.addEventListener('change', handleSettingsInput);
  }
}

export function openSettings(block) {
  if (!block) return;
  ensureBlockState(block);
  const template = block.dataset.template;
  canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
  block.classList.add('selected');
  if (settingsContent) {
    settingsContent.innerHTML = getSettingsForm(template, block);
    initTemplateSettingValues(block);
  }
  if (settingsPanel) {
    settingsPanel.classList.add('open');
    settingsPanel.block = block;
    settingsPanel.template = template;
    updateTemplateHeading(template);
  }
}

export function applyStoredSettings(block) {
  ensureBlockState(block);
  const settings = getSettings(block);
  const hasSettings = Object.keys(settings).length > 0;
  if (!hasSettings && !block.innerHTML.includes('{')) {
    return;
  }
  // Prefill alt text suggestions for stored settings as well
  const templateSetting = getTemplateSettingElement(block);
  if (templateSetting) {
    const altInputs = templateSetting.querySelectorAll('input[name^="custom_alt"]');
    altInputs.forEach((inp) => {
      const altName = inp.name;
      const srcName = altName === 'custom_alt' ? 'custom_src' : altName.replace('alt', 'img');
      suggestAltText(block, altName, srcName);
    });
  } else {
    suggestAltText(block);
  }
  renderBlock(block);
}

export function confirmDelete(message) {
  return new Promise((resolve) => {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML =
      '<div class="modal-content">' +
      '<p>' + message + '</p>' +
      '<div class="modal-footer">' +
      '<button class="btn btn-secondary cancel">Cancel</button>' +
      '<button class="btn btn-danger ok">Delete</button>' +
      '</div></div>';
    const container = document.querySelector('.builder') || document.body;
    container.appendChild(modal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.remove();
        resolve(false);
      }
    });
    modal.querySelector('.cancel').addEventListener('click', () => {
      modal.remove();
      resolve(false);
    });
    modal.querySelector('.ok').addEventListener('click', () => {
      modal.remove();
      resolve(true);
    });
  });
}

function getSettingsForm(template, block) {
  const templateSetting = getTemplateSettingElement(block);
  let form = '';
  if (templateSetting) {
    form += templateSetting.innerHTML;
  } else {
    form += '<p>No settings available for this block.</p>';
  }
  form += '<button id="apply-settings" class="btn btn-primary">Apply</button>';
  form += '<button id="cancel-settings" class="btn btn-secondary">Cancel</button>';
  return form;
}

function initTemplateSettingValues(block) {
  const templateSetting = getTemplateSettingElement(block);
  if (!templateSetting || !settingsPanel) return;
  populateFormsSelects(settingsPanel, block);
  populateBlogCategorySelects(settingsPanel, block);
  populateEventCategorySelects(settingsPanel, block);
  // Prefill alt text suggestions for any alt inputs
  const altInputs = templateSetting.querySelectorAll('input[name^="custom_alt"]');
  altInputs.forEach((inp) => {
    const altName = inp.name;
    const srcName = altName === 'custom_alt' ? 'custom_src' : altName.replace('alt', 'img');
    suggestAltText(block, altName, srcName, true);
  });

  const inputs = settingsPanel.querySelectorAll('input[name], textarea[name], select[name]');
  inputs.forEach((input) => {
    const name = input.name;
    const val = getSetting(block, name);
    if (input.type === 'checkbox') {
      input.checked = !!val;
    } else if (input.type === 'radio') {
      if (val !== undefined) {
        input.checked = input.value === val;
      }
    } else if (input.tagName === 'SELECT') {
      applySelectValue(input, val);
    } else if (val !== undefined) {
      input.value = val;
    }
  });
}

function validateSettings() {
  if (!settingsPanel) return true;
  let valid = true;
  const inputs = settingsPanel.querySelectorAll('input[name], textarea[name], select[name]');
  inputs.forEach((input) => {
    input.classList.remove('invalid');
    if (!input.checkValidity()) {
      input.classList.add('invalid');
      input.reportValidity();
      valid = false;
    }
  });
  return valid;
}

function renderBlock(block) {
  ensureBlockState(block);
  const settings = getSettings(block);
  const original = block.dataset.original || block.innerHTML;
  let html = original;
  const templateSetting = getTemplateSettingElement(block);
  if (!templateSetting) return;
  const inputs = templateSetting.querySelectorAll('input[name], textarea[name], select[name]');
  const processed = new Set();
  inputs.forEach((input) => {
    const name = input.name;
    if (processed.has(name)) return;
    let value;
    if (settings[name] !== undefined) {
      value = settings[name];
    } else if (input.type === 'checkbox') {
      value = input.checked ? (input.value || 'on') : '';
    } else if (input.type === 'radio') {
      const sel = templateSetting.querySelector('input[name="' + name + '"]:checked');
      value = sel ? sel.value : '';
    } else if (input.tagName === 'SELECT') {
      value = readSelectValue(input);
    } else {
      value = input.value || '';
    }
    setSetting(block, name, value);
    processed.add(name);
    html = html.split('{' + name + '}').join(value);
  });
  html = html.replace(/<templateSetting[^>]*>[\s\S]*?<\/templateSetting>/i, '');
  const existingAreas = Array.from(block.querySelectorAll('.drop-area')).map((a) => Array.from(a.childNodes));
  const temp = document.createElement('div');
  temp.innerHTML = html;
  const newAreas = temp.querySelectorAll('.drop-area');
  newAreas.forEach((area, i) => {
    const contents = existingAreas[i];
    if (contents) contents.forEach((n) => area.appendChild(n));
  });
  block.innerHTML = temp.innerHTML;
  executeScripts(block);
  block.querySelectorAll('.drop-area').forEach((a) => (a.dataset.dropArea = 'true'));
  inputs.forEach((input) => {
    const name = input.name;
    const value = settings[name];
    block.querySelectorAll('toggle[rel="' + name + '"]').forEach((tog) => {
      const match = tog.getAttribute('value') === value;
      tog.dataset.active = match ? 'true' : 'false';
      tog.style.display = match ? '' : 'none';
    });
    if (name === 'custom_src') {
      block.querySelectorAll('img').forEach((img) => {
        img.setAttribute('src', value);
      });
    }
    if (name === 'custom_alt') {
      block.querySelectorAll('img').forEach((img) => {
        img.setAttribute('alt', value);
      });
    }
  });
}

function applySettings(template, block) {
  if (!settingsPanel) return;
  const inputs = settingsPanel.querySelectorAll('input[name], textarea[name], select[name]');
  const processed = new Set();
  inputs.forEach((input) => {
    const name = input.name;
    if (processed.has(name)) return;
    let value;
    if (input.type === 'checkbox') {
      value = input.checked ? (input.value || 'on') : '';
    } else if (input.type === 'radio') {
      const sel = settingsPanel.querySelector('input[name="' + name + '"]:checked');
      value = sel ? sel.value : '';
    } else if (input.tagName === 'SELECT') {
      if (input.multiple) {
        enforceMultiSelectAllOption(input);
      }
      value = readSelectValue(input);
    } else {
      value = input.value;
    }
    processed.add(name);
    setSetting(block, name, value);
  });
  renderBlock(block);
  if (typeof addBlockControlsFn === 'function') {
    addBlockControlsFn(block);
  }
}
