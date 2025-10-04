// File: settings.js
import { ensureBlockState, getSetting, setSetting, getSettings } from './state.js';
import { executeScripts } from './executeScripts.js';

let canvas;
let settingsPanel;
let settingsContent;
let savePageFn;
let renderDebounce;
let addBlockControlsFn;
let templateNameEl;

let cachedForms = null;
let formsRequest = null;
let cachedBlogCategories = null;
let blogCategoriesRequest = null;
let cachedEventCategories = null;
let eventCategoriesRequest = null;
const schemaCache = new WeakMap();
const REMOTE_OPTION_LOADERS = {
  forms: loadFormsOptions,
  blogCategories: loadBlogCategoryOptions,
  eventCategories: loadEventCategoryOptions,
};

function getFormsEndpoint() {
  const base = (window.builderBase || window.cmsBase || '').replace(/\/$/, '');
  return (base || '') + '/CMS/modules/forms/list_forms.php';
}

function loadFormsOptions() {
  if (cachedForms) return Promise.resolve(cachedForms);
  if (formsRequest) return formsRequest;
  const endpoint = getFormsEndpoint();
  formsRequest = fetch(endpoint, { credentials: 'same-origin' })
    .then((response) => {
      if (!response.ok) throw new Error('Failed to load forms');
      return response.json();
    })
    .then((data) => {
      const forms = Array.isArray(data) ? data : [];
      cachedForms = forms
        .map((form) => {
          if (!form || typeof form !== 'object') return null;
          const id = form.id != null ? String(form.id) : '';
          const name = typeof form.name === 'string' && form.name.trim()
            ? form.name.trim()
            : id
            ? `Form ${id}`
            : '';
          if (!id || !name) return null;
          return { value: id, label: name };
        })
        .filter(Boolean);
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

function loadBlogCategoryOptions() {
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
      cachedBlogCategories = result.map((category) => ({
        value: category,
        label: category,
      }));
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

function loadEventCategoryOptions() {
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
    if (target === '' || target == null) {
      const placeholderOption = select.querySelector('option[value=""]');
      if (placeholderOption) {
        placeholderOption.selected = true;
      } else {
        select.value = '';
      }
      return;
    }
    select.value = target;
    if (
      target &&
      select.value !== target &&
      select.dataset.allowCustomValue === 'true'
    ) {
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

function normalizeField(field = {}) {
  if (!field || typeof field !== 'object') return null;
  const normalized = { ...field };
  normalized.name = normalized.name || '';
  if (!normalized.name) return null;
  normalized.type = normalized.type || 'text';
  if (!normalized.label) {
    const fromName = normalized.name
      .replace(/^custom_/, '')
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    normalized.label = fromName
      ? fromName.replace(/\b\w/g, (c) => c.toUpperCase())
      : normalized.name;
  }
  if (normalized.remote) {
    if (typeof normalized.remote === 'string') {
      normalized.remote = { type: normalized.remote };
    } else if (typeof normalized.remote === 'object') {
      normalized.remote = { ...normalized.remote };
    } else {
      normalized.remote = null;
    }
  }
  if (normalized.type === 'checkbox') {
    if (normalized.checkedValue === undefined && normalized.value !== undefined) {
      normalized.checkedValue = normalized.value;
    }
    if (normalized.default === undefined) {
      if (typeof normalized.defaultChecked === 'boolean') {
        normalized.default = normalized.defaultChecked
          ? normalized.checkedValue ?? 'on'
          : '';
      } else if (typeof normalized.default === 'boolean') {
        normalized.default = normalized.default
          ? normalized.checkedValue ?? 'on'
          : '';
      } else {
        normalized.default = '';
      }
    }
  }
  if (normalized.type === 'radio' || normalized.type === 'select') {
    const options = Array.isArray(normalized.options) ? normalized.options : [];
    normalized.options = options
      .map((opt) => {
        if (!opt || typeof opt !== 'object') return null;
        const option = { ...opt };
        if (option.value === undefined) {
          option.value = option.label !== undefined ? option.label : '';
        }
        if (option.label === undefined) {
          option.label = option.value;
        }
        if (option.default && normalized.default === undefined) {
          normalized.default = option.value;
        }
        if (option.selected && normalized.default === undefined) {
          normalized.default = option.value;
        }
        return option;
      })
      .filter(Boolean);
  }
  if (normalized.default === undefined) {
    if (normalized.type === 'checkbox') {
      normalized.default = '';
    } else if (Array.isArray(normalized.options)) {
      const selected = normalized.options.find(
        (opt) => opt && (opt.selected || opt.default)
      );
      if (selected) {
        normalized.default = selected.value;
      }
    }
  }
  return normalized;
}

function normalizeSchema(schema, templateSetting) {
  if (!schema || typeof schema !== 'object') return null;
  const normalized = {
    title: schema.title || templateSetting?.getAttribute('caption') || '',
    groups: [],
    fields: [],
    fieldMap: new Map(),
  };
  const groups = Array.isArray(schema.groups) ? schema.groups : [];
  groups.forEach((group) => {
    if (!group || typeof group !== 'object') return;
    const normalizedGroup = {
      title: group.title || normalized.title,
      description: group.description || '',
      fields: [],
    };
    const fields = Array.isArray(group.fields) ? group.fields : [];
    fields.forEach((field) => {
      const normalizedField = normalizeField(field);
      if (!normalizedField) return;
      normalizedGroup.fields.push(normalizedField);
      normalized.fields.push(normalizedField);
      normalized.fieldMap.set(normalizedField.name, normalizedField);
    });
    if (normalizedGroup.fields.length > 0) {
      normalized.groups.push(normalizedGroup);
    }
  });
  if (normalized.fields.length === 0) {
    return null;
  }
  return normalized;
}

function extractDescription(dd) {
  if (!dd) return null;
  const hint = dd.querySelector('small, p, div.help-text');
  if (!hint) return null;
  const description = hint.innerHTML.trim();
  if (!description) return null;
  return {
    text: description,
    className: hint.className || '',
  };
}

function deriveSelectField(select) {
  if (!select || !select.name) return null;
  const options = Array.from(select.options).map((opt) => ({
    value: opt.value,
    label: opt.textContent.trim(),
    selected: opt.selected,
    disabled: opt.disabled,
    hidden: opt.hidden,
    placeholder: opt.dataset.placeholder === 'true' || opt.value === '' && (opt.disabled || opt.hidden),
  }));
  const field = {
    type: 'select',
    name: select.name,
    inputClass: select.className || undefined,
    options,
    multiple: select.multiple || undefined,
    default: select.multiple ? readSelectValue(select) : select.value,
  };
  if (select.dataset.placeholder) {
    field.placeholder = select.dataset.placeholder;
  }
  if ('formsSelect' in select.dataset) {
    field.remote = {
      type: 'forms',
      placeholder: select.dataset.placeholder || options[0]?.label || 'Select a form...',
      allowCustomValue: true,
    };
    field.allowCustomValue = true;
  } else if ('blogCategorySelect' in select.dataset) {
    field.remote = {
      type: 'blogCategories',
      placeholder: select.dataset.placeholder || options[0]?.label || 'All categories',
    };
  } else if ('eventsCategorySelect' in select.dataset) {
    field.remote = {
      type: 'eventCategories',
      placeholder: select.dataset.placeholder || options[0]?.label || 'All categories',
    };
  }
  if (field.remote && options.length && options[0].value === '') {
    options[0].placeholder = true;
  }
  return field;
}

function deriveCheckboxField(input) {
  if (!input || !input.name) return null;
  const label = input.closest('label');
  return {
    type: 'checkbox',
    name: input.name,
    checkedValue: input.value || 'on',
    default: input.checked ? input.value || 'on' : '',
    checkboxLabel: label ? label.textContent.trim().replace(/^\s+/, '') : undefined,
  };
}

function deriveRadioField(radios, dd) {
  if (!radios.length) return null;
  const name = radios[0].name;
  if (!name) return null;
  const container =
    (radios[0].closest('.color-picker')) ||
    (dd && dd.classList.contains('align-options') ? dd : null);
  const options = radios.map((radio) => {
    const label = radio.closest('label');
    const option = {
      value: radio.value,
      label: label ? label.textContent.trim() : radio.value,
      selected: radio.checked,
    };
    if (label && label.className) {
      option.className = label.className;
    }
    if (label) {
      const swatch = label.querySelector('.color-swatch');
      if (swatch && swatch.style && swatch.style.backgroundColor) {
        option.swatch = swatch.style.backgroundColor;
      }
    }
    return option;
  });
  const field = {
    type: 'radio',
    name,
    options,
    default: options.find((opt) => opt.selected)?.value,
  };
  if (container && container.classList.contains('color-picker')) {
    field.inputContainerClass = container.className;
    field.variant = 'color-swatch';
  } else if (container && container !== dd) {
    field.inputContainerClass = container.className;
  } else if (dd && dd.className) {
    field.inputContainerClass = dd.className;
  }
  return field;
}

function deriveTextField(input) {
  if (!input || !input.name) return null;
  let fieldType = 'text';
  if (input.type === 'number') {
    fieldType = 'number';
  } else if (input.type === 'url') {
    fieldType = 'url';
  } else if (input.type === 'email') {
    fieldType = 'email';
  }
  return {
    type: fieldType,
    name: input.name,
    default: input.value || '',
    inputClass: input.className || undefined,
    placeholder: input.placeholder || undefined,
    pattern: input.pattern || undefined,
    min: input.min !== '' && !Number.isNaN(Number(input.min)) ? Number(input.min) : undefined,
    max: input.max !== '' && !Number.isNaN(Number(input.max)) ? Number(input.max) : undefined,
    step:
      input.step && input.step !== '' && !Number.isNaN(Number(input.step))
        ? Number(input.step)
        : undefined,
  };
}

function deriveTextareaField(textarea) {
  if (!textarea || !textarea.name) return null;
  return {
    type: 'textarea',
    name: textarea.name,
    default: textarea.value || '',
    inputClass: textarea.className || undefined,
    rows: textarea.rows || undefined,
  };
}

function deriveMediaField(dd, input) {
  if (!input || !input.name) return null;
  const button = dd.querySelector('button');
  if (!button || !/openMediaPicker\(/.test(button.getAttribute('onclick') || '')) {
    return null;
  }
  return {
    type: 'media',
    name: input.name,
    default: input.value || '',
    inputClass: input.className || undefined,
    buttonClass: button.className || undefined,
    buttonLabel: button.innerHTML || button.textContent || 'Browse',
  };
}

function deriveFieldFromDl(dl) {
  if (!dl) return null;
  const dd = dl.querySelector('dd');
  if (!dd) return null;
  const textarea = dd.querySelector('textarea[name]');
  if (textarea) {
    const field = deriveTextareaField(textarea);
    const desc = extractDescription(dd);
    if (field && desc) {
      field.description = desc.text;
      field.descriptionClass = desc.className;
    }
    return field;
  }
  const select = dd.querySelector('select[name]');
  if (select) {
    const field = deriveSelectField(select);
    const desc = extractDescription(dd);
    if (field && desc) {
      field.description = desc.text;
      field.descriptionClass = desc.className;
    }
    return field;
  }
  const radios = Array.from(dd.querySelectorAll('input[type="radio"][name]'));
  if (radios.length) {
    const field = deriveRadioField(radios, dd);
    const desc = extractDescription(dd);
    if (field && desc) {
      field.description = desc.text;
      field.descriptionClass = desc.className;
    }
    return field;
  }
  const checkbox = dd.querySelector('input[type="checkbox"][name]');
  if (checkbox) {
    const field = deriveCheckboxField(checkbox);
    const desc = extractDescription(dd);
    if (field && desc) {
      field.description = desc.text;
      field.descriptionClass = desc.className;
    }
    return field;
  }
  const input = dd.querySelector('input[name]');
  if (input) {
    const mediaField = deriveMediaField(dd, input);
    if (mediaField) {
      const desc = extractDescription(dd);
      if (desc) {
        mediaField.description = desc.text;
        mediaField.descriptionClass = desc.className;
      }
      return mediaField;
    }
    const field = deriveTextField(input);
    const desc = extractDescription(dd);
    if (field && desc) {
      field.description = desc.text;
      field.descriptionClass = desc.className;
    }
    return field;
  }
  return null;
}

function deriveSchemaFromMarkup(templateSetting) {
  if (!templateSetting) return null;
  const caption = templateSetting.getAttribute('caption') || 'Settings';
  const dls = Array.from(templateSetting.querySelectorAll('dl'));
  const fields = dls
    .map((dl) => {
      const field = deriveFieldFromDl(dl);
      if (field) {
        const dt = dl.querySelector('dt');
        if (dt && dt.textContent) {
          field.label = dt.textContent.trim();
        }
        if (!field.className && dl.querySelector('dd') && dl.querySelector('dd').className) {
          field.className = dl.querySelector('dd').className;
        }
        const extraClasses = Array.from(dl.classList || []).filter(
          (cls) => cls && cls !== 'sparkDialog' && cls !== '_tpl-box'
        );
        if (extraClasses.length) {
          field.wrapperClass = extraClasses.join(' ');
        }
      }
      return field;
    })
    .filter(Boolean);
  if (!fields.length) return null;
  return {
    title: caption,
    groups: [
      {
        title: caption,
        fields,
      },
    ],
  };
}

function getSettingsSchema(block) {
  if (!block) return null;
  if (schemaCache.has(block)) {
    return schemaCache.get(block);
  }
  const templateSetting = getTemplateSettingElement(block);
  if (!templateSetting) {
    schemaCache.set(block, null);
    return null;
  }
  const encodedSchema = templateSetting.getAttribute('data-settings-schema');
  let parsed = null;
  if (encodedSchema) {
    try {
      parsed = JSON.parse(encodedSchema);
    } catch (e) {
      parsed = null;
    }
  }
  if (!parsed) {
    parsed = deriveSchemaFromMarkup(templateSetting);
  }
  const normalized = normalizeSchema(parsed, templateSetting);
  schemaCache.set(block, normalized);
  return normalized;
}

function flattenSchemaFields(schema) {
  if (!schema || !Array.isArray(schema.fields)) return [];
  return schema.fields;
}

function getFieldDefaultValue(field) {
  if (!field) return '';
  if (field.default !== undefined && field.default !== null) {
    return field.default;
  }
  if (Array.isArray(field.options)) {
    const selected = field.options.find((opt) => opt && (opt.selected || opt.default));
    if (selected) return selected.value;
  }
  if (field.type === 'checkbox') {
    return '';
  }
  return '';
}

function createSelectOption(option) {
  const opt = document.createElement('option');
  opt.value = option.value != null ? String(option.value) : '';
  opt.textContent = option.label != null ? String(option.label) : opt.value;
  if (option.disabled) opt.disabled = true;
  if (option.hidden) opt.hidden = true;
  if (option.selected) opt.selected = true;
  if (option.placeholder) opt.dataset.placeholderOption = 'true';
  return opt;
}

function applyFieldAttributes(field, element) {
  if (!field || !element) return;
  if (field.required) {
    element.required = true;
  }
  if (field.readOnly) {
    element.readOnly = true;
  }
  if (field.placeholder && 'placeholder' in element) {
    element.placeholder = field.placeholder;
  }
  if (field.min !== undefined && element.tagName === 'INPUT') {
    element.min = field.min;
  }
  if (field.max !== undefined && element.tagName === 'INPUT') {
    element.max = field.max;
  }
  if (field.step !== undefined && element.tagName === 'INPUT') {
    element.step = field.step;
  }
  if (field.rows !== undefined && element.tagName === 'TEXTAREA') {
    element.rows = field.rows;
  }
  if (field.pattern && element.tagName === 'INPUT') {
    element.pattern = field.pattern;
  }
}

function createFieldControl(field) {
  const dd = document.createElement('dd');
  if (field.className) {
    dd.className = field.className;
  }
  let control = null;
  switch (field.type) {
    case 'textarea': {
      control = document.createElement('textarea');
      control.name = field.name;
      if (field.inputClass) control.className = field.inputClass;
      applyFieldAttributes(field, control);
      dd.appendChild(control);
      break;
    }
    case 'number':
    case 'text':
    case 'url':
    case 'email': {
      control = document.createElement('input');
      control.type = field.type === 'url' || field.type === 'email' ? field.type : field.type === 'number' ? 'number' : 'text';
      control.name = field.name;
      if (field.inputClass) control.className = field.inputClass;
      applyFieldAttributes(field, control);
      dd.appendChild(control);
      break;
    }
    case 'media': {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = field.name;
      if (field.inputClass) {
        input.className = field.inputClass;
      }
      applyFieldAttributes(field, input);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = field.buttonClass || 'btn btn-secondary';
      button.innerHTML = field.buttonLabel || 'Browse';
      button.addEventListener('click', () => {
        if (typeof window.openMediaPicker === 'function') {
          window.openMediaPicker(field.name);
        }
      });
      dd.appendChild(input);
      dd.appendChild(button);
      break;
    }
    case 'checkbox': {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.name = field.name;
      if (field.inputClass) input.className = field.inputClass;
      if (field.checkedValue !== undefined) {
        input.value = field.checkedValue;
      }
      applyFieldAttributes(field, input);
      label.appendChild(input);
      label.appendChild(
        document.createTextNode(` ${field.checkboxLabel || field.toggleLabel || field.label}`)
      );
      dd.appendChild(label);
      break;
    }
    case 'radio': {
      const container = document.createElement('div');
      if (field.inputContainerClass) {
        container.className = field.inputContainerClass;
      }
      const options = Array.isArray(field.options) ? field.options : [];
      options.forEach((option) => {
        const label = document.createElement('label');
        if (field.optionClass) {
          label.className = field.optionClass;
        }
        if (option.className) {
          option.className
            .split(/\s+/)
            .filter(Boolean)
            .forEach((cls) => label.classList.add(cls));
        }
        const input = document.createElement('input');
        input.type = 'radio';
        input.name = field.name;
        input.value = option.value;
        if (field.inputClass) {
          input.classList.add(field.inputClass);
        }
        applyFieldAttributes(field, input);
        if (option.title) {
          input.title = option.title;
        }
        label.appendChild(input);
        if (field.variant === 'color-swatch') {
          const span = document.createElement('span');
          span.className = 'color-swatch';
          const color = option.swatch || option.color || option.value;
          if (color) {
            span.style.backgroundColor = color;
          }
          label.appendChild(span);
        } else {
          label.appendChild(document.createTextNode(` ${option.label}`));
        }
        container.appendChild(label);
      });
      dd.appendChild(container);
      break;
    }
    case 'select': {
      const select = document.createElement('select');
      select.name = field.name;
      if (field.inputClass) select.className = field.inputClass;
      if (field.multiple) select.multiple = true;
      if (field.allowCustomValue) {
        select.dataset.allowCustomValue = 'true';
      }
      applyFieldAttributes(field, select);
      const options = Array.isArray(field.options) ? field.options : [];
      options.forEach((option) => {
        const opt = createSelectOption(option);
        if (option.placeholder) {
          opt.dataset.placeholder = 'true';
        }
        select.appendChild(opt);
      });
      if (field.remote && field.remote.type) {
        select.dataset.remoteSource = field.remote.type;
        if (field.remote.placeholder) {
          select.dataset.placeholder = field.remote.placeholder;
        }
        if (field.remote.allowCustomValue) {
          select.dataset.allowCustomValue = 'true';
        }
      }
      dd.appendChild(select);
      break;
    }
    default: {
      control = document.createElement('input');
      control.type = 'text';
      control.name = field.name;
      if (field.inputClass) control.className = field.inputClass;
      applyFieldAttributes(field, control);
      dd.appendChild(control);
      break;
    }
  }
  if (field.description) {
    const small = document.createElement('small');
    small.className = field.descriptionClass || 'form-text text-muted';
    small.innerHTML = field.description;
    dd.appendChild(small);
  }
  return dd;
}

function renderField(field) {
  const dl = document.createElement('dl');
  dl.className = 'sparkDialog _tpl-box';
  if (field.wrapperClass) {
    field.wrapperClass
      .split(/\s+/)
      .filter(Boolean)
      .forEach((cls) => dl.classList.add(cls));
  }
  const dt = document.createElement('dt');
  dt.textContent = field.label || field.name;
  dl.appendChild(dt);
  dl.appendChild(createFieldControl(field));
  return dl;
}

function renderSettingsContent(block) {
  const schema = getSettingsSchema(block);
  const fragment = document.createDocumentFragment();
  if (!schema) {
    const message = document.createElement('p');
    message.textContent = 'No settings available for this block.';
    fragment.appendChild(message);
    return { fragment, schema: null };
  }
  schema.groups.forEach((group) => {
    if (group.title && schema.groups.length > 1) {
      const heading = document.createElement('h3');
      heading.className = 'settings-group-title';
      heading.textContent = group.title;
      fragment.appendChild(heading);
    }
    group.fields.forEach((field) => {
      const fieldEl = renderField(field);
      fragment.appendChild(fieldEl);
    });
  });
  return { fragment, schema };
}

function populateRemoteSelect(select, field, value) {
  if (!select || !field || !field.remote) return;
  const remote = field.remote.type;
  const loader = remote ? REMOTE_OPTION_LOADERS[remote] : null;
  if (!loader) return;
  loader()
    .then((options) => {
      if (!Array.isArray(options)) return;
      const placeholderOptions = Array.from(select.options).filter(
        (option) => option.dataset.placeholder === 'true' || option.dataset.placeholderOption === 'true'
      );
      select.innerHTML = '';
      placeholderOptions.forEach((option) => select.appendChild(option));
      options.forEach((option) => {
        if (!option || typeof option !== 'object') return;
        const opt = createSelectOption(option);
        select.appendChild(opt);
      });
      if (value !== undefined) {
        applySelectValue(select, value);
      }
    })
    .catch(() => {
      if (value !== undefined) {
        applySelectValue(select, value);
      }
    });
}

function setFieldValue(field, container, value) {
  if (!field || !container) return;
  const name = field.name;
  const targetValue = value !== undefined ? value : getFieldDefaultValue(field);
  if (field.type === 'checkbox') {
    const input = container.querySelector(`input[name="${name}"]`);
    if (!input) return;
    const checkedValue = field.checkedValue ?? input.value ?? 'on';
    input.checked = targetValue === checkedValue;
    return;
  }
  if (field.type === 'radio') {
    const inputs = container.querySelectorAll(`input[name="${name}"]`);
    inputs.forEach((input) => {
      input.checked = input.value === targetValue;
    });
    return;
  }
  if (field.type === 'select') {
    const select = container.querySelector(`select[name="${name}"]`);
    if (!select) return;
    if (field.remote) {
      populateRemoteSelect(select, field, targetValue);
    } else {
      applySelectValue(select, targetValue);
    }
    return;
  }
  const input = container.querySelector(`[name="${name}"]`);
  if (!input) return;
  input.value = targetValue != null ? targetValue : '';
}

function readFieldValue(field, container) {
  if (!field || !container) return '';
  const name = field.name;
  if (field.type === 'checkbox') {
    const input = container.querySelector(`input[name="${name}"]`);
    if (!input) return '';
    const checkedValue = field.checkedValue ?? input.value ?? 'on';
    return input.checked ? checkedValue : '';
  }
  if (field.type === 'radio') {
    const input = container.querySelector(`input[name="${name}"]:checked`);
    return input ? input.value : '';
  }
  if (field.type === 'select') {
    const select = container.querySelector(`select[name="${name}"]`);
    if (!select) return '';
    if (select.multiple) {
      enforceMultiSelectAllOption(select);
    }
    return readSelectValue(select);
  }
  const input = container.querySelector(`[name="${name}"]`);
  return input ? input.value : '';
}

function initializeSettingsForm(block, schema) {
  if (!settingsPanel || !schema) return;
  const container = settingsPanel.querySelector('.settings-content');
  if (!container) return;
  const fields = flattenSchemaFields(schema);
  fields.forEach((field) => {
    const value = getSetting(block, field.name);
    setFieldValue(field, container, value);
  });
  const altFields = fields.filter((field) => /^custom_alt/.test(field.name));
  altFields.forEach((field) => {
    const altName = field.name;
    const srcName = altName === 'custom_alt' ? 'custom_src' : altName.replace('alt', 'img');
    suggestAltText(block, altName, srcName, true);
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
          document.dispatchEvent(
            new CustomEvent('blockSettingsApplied', { detail: { block } })
          );
          settingsPanel.classList.remove('open');
          settingsPanel.block = null;
          settingsPanel.template = null;
          settingsPanel.schema = null;
          updateTemplateHeading();
          canvas.querySelectorAll('.block-wrapper').forEach((b) => b.classList.remove('selected'));
          savePageFn();
        }
      } else if (e.target.id === 'cancel-settings' || e.target.classList.contains('close-btn')) {
        settingsPanel.classList.remove('open');
        settingsPanel.block = null;
        settingsPanel.template = null;
        settingsPanel.schema = null;
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
    settingsContent.innerHTML = '';
    const { fragment, schema } = renderSettingsContent(block);
    settingsContent.appendChild(fragment);
    const applyBtn = document.createElement('button');
    applyBtn.id = 'apply-settings';
    applyBtn.className = 'btn btn-primary';
    applyBtn.textContent = 'Apply';
    settingsContent.appendChild(applyBtn);
    const cancelBtn = document.createElement('button');
    cancelBtn.id = 'cancel-settings';
    cancelBtn.className = 'btn btn-secondary';
    cancelBtn.textContent = 'Cancel';
    settingsContent.appendChild(cancelBtn);
    initializeSettingsForm(block, schema);
    settingsPanel.schema = schema;
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
  const schema = getSettingsSchema(block);
  if (schema) {
    const fields = flattenSchemaFields(schema);
    fields
      .filter((field) => /^custom_alt/.test(field.name))
      .forEach((field) => {
        const altName = field.name;
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
  const schema = getSettingsSchema(block);
  if (!schema) return;
  const settings = getSettings(block);
  const original = block.dataset.original || block.innerHTML;
  let html = original;
  const processed = new Set();
  const fields = flattenSchemaFields(schema);
  fields.forEach((field) => {
    const name = field.name;
    if (!name || processed.has(name)) return;
    let value = settings[name];
    if (value === undefined) {
      value = getFieldDefaultValue(field);
      setSetting(block, name, value);
    }
    processed.add(name);
    const replacement = value != null ? value : '';
    html = html.split('{' + name + '}').join(replacement);
  });
  html = html.replace(/<templateSetting[^>]*>[\s\S]*?<\/templateSetting>/i, '');
  const existingAreas = Array.from(block.querySelectorAll('.drop-area')).map((a) =>
    Array.from(a.childNodes)
  );
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
  fields.forEach((field) => {
    const name = field.name;
    const value = getSetting(block, name, getFieldDefaultValue(field));
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
  const schema = getSettingsSchema(block);
  if (!schema) {
    renderBlock(block);
    if (typeof addBlockControlsFn === 'function') {
      addBlockControlsFn(block);
    }
    return;
  }
  const container = settingsPanel.querySelector('.settings-content');
  if (!container) return;
  const processed = new Set();
  const fields = flattenSchemaFields(schema);
  fields.forEach((field) => {
    const name = field.name;
    if (!name || processed.has(name)) return;
    const value = readFieldValue(field, container);
    processed.add(name);
    setSetting(block, name, value);
  });
  renderBlock(block);
  if (typeof addBlockControlsFn === 'function') {
    addBlockControlsFn(block);
  }
}
