// File: forms.js
import { getSetting } from './state.js';

const FORMS_SELECT_ATTR = 'data-forms-select';
let cachedForms = null;
let formsRequest = null;

function getFormsEndpoint() {
  const base = (window.builderBase || window.cmsBase || '').replace(/\/$/, '');
  return (base || '') + '/CMS/modules/forms/list_forms.php';
}

export function fetchFormsList() {
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

export function populateFormsSelects(container, block) {
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

export function resetFormsCache() {
  cachedForms = null;
  formsRequest = null;
}
