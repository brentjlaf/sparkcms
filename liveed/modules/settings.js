import { ensureBlockState, getSetting, setSetting, getSettings } from './state.js';
import { addBlockControls } from './dragDrop.js';

let canvas;
let settingsPanel;
let settingsContent;
let savePageFn;

function getTemplateSettingElement(block) {
  return (
    block.querySelector('templateSetting') ||
    (() => {
      const wrap = document.createElement('div');
      wrap.innerHTML = block.dataset.original || block.innerHTML;
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
        if (block) applySettings(template, block);
        settingsPanel.classList.remove('open');
        settingsPanel.block = null;
        canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
        savePageFn();
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
        setSetting(block, input.name, input.value);
        renderBlock(block);
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
  const inputs = settingsPanel.querySelectorAll('input[name], textarea[name], select[name]');
  inputs.forEach((input) => {
    const name = input.name;
    const val = getSetting(block, name);
    if (val !== undefined) {
      input.value = val;
    }
  });
}

function renderBlock(block) {
  ensureBlockState(block);
  const settings = getSettings(block);
  const original = block.dataset.original || block.innerHTML;
  let html = original;
  const templateSetting = getTemplateSettingElement(block);
  if (!templateSetting) return;
  const inputs = templateSetting.querySelectorAll('input[name], textarea[name], select[name]');
  inputs.forEach((input) => {
    const name = input.name;
    const value = settings[name] !== undefined ? settings[name] : input.value || '';
    settings[name] = value;
    html = html.split('{' + name + '}').join(value);
  });
  html = html.replace(/<templateSetting[^>]*>[\s\S]*?<\/templateSetting>/i, '');
  block.innerHTML = html;
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
  inputs.forEach((input) => {
    const name = input.name;
    const value = input.value;
    setSetting(block, name, value);
  });
  renderBlock(block);
  addBlockControls(block);
}
