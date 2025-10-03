// File: settings.js
import { ensureBlockState, setSetting, getSettings } from './state.js';
import { addBlockControls } from './dragDrop.js';
import { executeScripts } from "./executeScripts.js";
import { populateFormsSelects } from './forms.js';
import { suggestAltText } from './altText.js';
import { renderTemplate } from './templateRender.js';

let canvas;
let settingsPanel;
let settingsContent;
let savePageFn;
let renderDebounce;

function findCheckedRadio(scope, name) {
  if (!scope || !name) return null;
  if (typeof CSS !== 'undefined' && CSS.escape) {
    return scope.querySelector(`input[name="${CSS.escape(name)}"]:checked`);
  }
  const radios = scope.querySelectorAll('input[type="radio"]');
  return Array.from(radios).find((radio) => radio.name === name && radio.checked) || null;
}

function readInputValue(input, scope = document) {
  if (!input) return '';
  if (input.type === 'checkbox') {
    return input.checked ? input.value || 'on' : '';
  }
  if (input.type === 'radio') {
    const selected = findCheckedRadio(scope, input.name);
    return selected ? selected.value : '';
  }
  return input.value;
}

function writeInputValue(input, value, { hasStoredValue = true } = {}) {
  if (!input) return;
  const normalized = value ?? '';
  if (input.type === 'checkbox') {
    input.checked = Boolean(normalized);
    if (normalized && normalized !== 'on') {
      input.value = normalized;
    }
    return;
  }
  if (input.type === 'radio') {
    if (hasStoredValue) {
      input.checked = input.value === String(normalized);
    }
    return;
  }
  if (hasStoredValue) {
    input.value = String(normalized);
  }
}

function scheduleRender(block) {
  clearTimeout(renderDebounce);
  renderDebounce = setTimeout(() => renderBlock(block), 100);
}

function handleAltTextSuggestions(block, name) {
  if (!block || !name) return;
  if (name === 'custom_src') {
    suggestAltText(block, {
      altName: 'custom_alt',
      srcName: 'custom_src',
      updateInput: true,
      panel: settingsPanel,
    });
    return;
  }
  const match = name.match(/^custom_img(\d+)$/);
  if (match) {
    const idx = match[1];
    suggestAltText(block, {
      altName: `custom_alt${idx}`,
      srcName: `custom_img${idx}`,
      updateInput: true,
      panel: settingsPanel,
    });
  }
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
  if (settingsPanel) {
    settingsContent = settingsPanel.querySelector('.settings-content');
    settingsPanel.addEventListener('click', (e) => {
      if (e.target.id === 'apply-settings') {
        const block = settingsPanel.block;
        if (block && validateSettings()) {
          applySettings(block);
          settingsPanel.classList.remove('open');
          settingsPanel.block = null;
          canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
          savePageFn();
        }
      } else if (e.target.id === 'cancel-settings' || e.target.classList.contains('close-btn')) {
        settingsPanel.classList.remove('open');
        settingsPanel.block = null;
        canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
      }
    });

    const handleSettingsInput = (e) => {
      const input = e.target.closest('input[name], textarea[name], select[name]');
      const block = settingsPanel.block;
      if (!input || !block) return;
      if (e.type === 'change' && input.tagName !== 'SELECT') return;

      const value = readInputValue(input, settingsPanel);
      setSetting(block, input.name, value);
      handleAltTextSuggestions(block, input.name);
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
      suggestAltText(block, { altName, srcName });
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
  const settings = getSettings(block);
  populateFormsSelects(settingsPanel, block);
  // Prefill alt text suggestions for any alt inputs
  const altInputs = templateSetting.querySelectorAll('input[name^="custom_alt"]');
  altInputs.forEach((inp) => {
    const altName = inp.name;
    const srcName = altName === 'custom_alt' ? 'custom_src' : altName.replace('alt', 'img');
    suggestAltText(block, {
      altName,
      srcName,
      updateInput: true,
      panel: settingsPanel,
    });
  });

  const inputs = settingsPanel.querySelectorAll('input[name], textarea[name], select[name]');
  inputs.forEach((input) => {
    const name = input.name;
    if (!name) return;
    const value = Object.prototype.hasOwnProperty.call(settings, name) ? settings[name] : '';
    writeInputValue(input, value, { hasStoredValue: true });
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
  const templateSetting = getTemplateSettingElement(block);
  if (!templateSetting) return;

  const originalHTML = block.dataset.original || block.innerHTML;
  const { html, values } = renderTemplate({
    originalHTML,
    templateSetting,
    settings,
    readValue: (input, scope) => readInputValue(input, scope),
  });

  Object.entries(values).forEach(([name, value]) => {
    setSetting(block, name, value);
  });

  const existingAreas = Array.from(block.querySelectorAll('.drop-area')).map((area) =>
    Array.from(area.childNodes),
  );

  const temp = document.createElement('div');
  temp.innerHTML = html;
  const newAreas = temp.querySelectorAll('.drop-area');
  newAreas.forEach((area, index) => {
    const contents = existingAreas[index];
    if (contents) contents.forEach((node) => area.appendChild(node));
  });

  block.innerHTML = temp.innerHTML;
  executeScripts(block);
  block.querySelectorAll('.drop-area').forEach((area) => (area.dataset.dropArea = 'true'));

  Object.entries(values).forEach(([name, rawValue]) => {
    const value = rawValue ?? '';
    block.querySelectorAll(`toggle[rel="${name}"]`).forEach((toggleEl) => {
      const match = toggleEl.getAttribute('value') === value;
      toggleEl.dataset.active = match ? 'true' : 'false';
      toggleEl.style.display = match ? '' : 'none';
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

function applySettings(block) {
  if (!settingsPanel) return;
  const inputs = settingsPanel.querySelectorAll('input[name], textarea[name], select[name]');
  const processed = new Set();
  inputs.forEach((input) => {
    const name = input.name;
    if (processed.has(name)) return;
    const value = readInputValue(input, settingsPanel);
    processed.add(name);
    setSetting(block, name, value);
  });
  renderBlock(block);
  addBlockControls(block);
}
