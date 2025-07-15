// File: settings.js
import { ensureBlockState, getSetting, setSetting, getSettings } from './state.js';
import { addBlockControls } from './dragDrop.js';
import { executeScripts } from "./executeScripts.js";

let canvas;
let settingsPanel;
let settingsContent;
let savePageFn;

function suggestAltText(block) {
  const src = getSetting(block, 'custom_src', '').trim();
  let alt = getSetting(block, 'custom_alt', '').trim();
  if (!src || alt) return;
  let name = src.split('/').pop();
  name = name.split('?')[0];
  name = name.replace(/\.[^/.]+$/, '');
  name = name.replace(/[-_]+/g, ' ').trim();
  if (!name) return;
  name = name.charAt(0).toUpperCase() + name.slice(1);
  setSetting(block, 'custom_alt', name);
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
        const template = settingsPanel.template;
        if (block && validateSettings()) {
          applySettings(template, block);
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

    settingsPanel.addEventListener('input', (e) => {
      const input = e.target.closest('input[name], textarea[name], select[name]');
      const block = settingsPanel.block;
      if (input && block) {
        let val;
        if (input.type === 'checkbox') {
          val = input.checked ? (input.value || 'on') : '';
        } else {
          val = input.value;
        }
        setSetting(block, input.name, val);
        if (input.name === 'custom_src') suggestAltText(block);
        renderBlock(block);
        // Automatically schedule a save whenever a setting changes so that
        // media selections are persisted even if the user forgets to press
        // the "Apply" button.
        if (typeof savePageFn === 'function') savePageFn();
      }
    });
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
  suggestAltText(block);
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
  suggestAltText(block);
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
    } else {
      value = input.value;
    }
    processed.add(name);
    setSetting(block, name, value);
  });
  renderBlock(block);
  addBlockControls(block);
}
