// File: sanitizer.js
const DEFAULT_ALLOWED_TAGS = new Set([
  'a',
  'abbr',
  'article',
  'aside',
  'b',
  'blockquote',
  'br',
  'button',
  'caption',
  'code',
  'col',
  'colgroup',
  'dd',
  'details',
  'div',
  'dl',
  'dt',
  'em',
  'figcaption',
  'figure',
  'footer',
  'form',
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'header',
  'hr',
  'i',
  'iframe',
  'img',
  'input',
  'label',
  'li',
  'main',
  'nav',
  'ol',
  'option',
  'p',
  'picture',
  'section',
  'select',
  'small',
  'source',
  'span',
  'strong',
  'summary',
  'sup',
  'table',
  'tbody',
  'td',
  'textarea',
  'tfoot',
  'th',
  'thead',
  'time',
  'tr',
  'u',
  'ul',
  'video',
]);

const DEFAULT_ALLOWED_ATTRS = new Set([
  'accept',
  'action',
  'alt',
  'autocomplete',
  'autoplay',
  'checked',
  'cite',
  'class',
  'cols',
  'colspan',
  'controls',
  'data-block-id',
  'datetime',
  'disabled',
  'download',
  'enctype',
  'for',
  'height',
  'href',
  'id',
  'lang',
  'loading',
  'loop',
  'max',
  'maxlength',
  'method',
  'min',
  'multiple',
  'name',
  'pattern',
  'placeholder',
  'poster',
  'preload',
  'rel',
  'required',
  'rows',
  'selected',
  'src',
  'step',
  'style',
  'target',
  'title',
  'type',
  'value',
  'width',
]);

const URI_ATTRS = new Set(['href', 'src', 'action', 'formaction', 'poster']);

const DATA_URI_PATTERN = /^data:image\/(?:gif|png|jpeg|jpg|webp|svg\+xml);base64,[a-z0-9+/=\s-]+$/i;
const SAFE_URL_PATTERN = /^(?:https?:|mailto:|tel:|ftp:|\#|\/)/i;

function isSafeUrl(value = '') {
  const trimmed = value.trim();
  if (trimmed === '') return true;
  if (DATA_URI_PATTERN.test(trimmed)) return true;
  return SAFE_URL_PATTERN.test(trimmed);
}

function sanitizeStyle(value = '') {
  const lower = value.toLowerCase();
  if (lower.includes('expression') || lower.includes('javascript:')) {
    return '';
  }
  return value;
}

function sanitizeAttributes(element) {
  const toRemove = [];
  for (const attr of Array.from(element.attributes)) {
    const name = attr.name.toLowerCase();
    if (name.startsWith('on')) {
      toRemove.push(attr.name);
      continue;
    }
    if (name === 'style') {
      const sanitized = sanitizeStyle(attr.value || '');
      if (sanitized === '') {
        toRemove.push(attr.name);
      } else if (sanitized !== attr.value) {
        element.setAttribute(attr.name, sanitized);
      }
      continue;
    }
    if (URI_ATTRS.has(name) && !isSafeUrl(attr.value)) {
      toRemove.push(attr.name);
      continue;
    }
    if (DEFAULT_ALLOWED_ATTRS.has(name)) {
      continue;
    }
    if (/^data-[\w-]+$/.test(name)) {
      continue;
    }
    if (/^aria-[\w-]+$/.test(name)) {
      continue;
    }
    toRemove.push(attr.name);
  }
  toRemove.forEach((attr) => element.removeAttribute(attr));
}

function sanitizeNode(node) {
  if (!node) return;
  if (node.nodeType === Node.COMMENT_NODE) {
    node.remove();
    return;
  }
  if (node.nodeType === Node.ELEMENT_NODE) {
    const tagName = node.tagName.toLowerCase();
    if (!DEFAULT_ALLOWED_TAGS.has(tagName)) {
      const parent = node.parentNode;
      if (!parent) {
        node.remove();
        return;
      }
      while (node.firstChild) {
        parent.insertBefore(node.firstChild, node);
      }
      parent.removeChild(node);
      return;
    }
    sanitizeAttributes(node);
  }
  let child = node.firstChild;
  while (child) {
    const next = child.nextSibling;
    sanitizeNode(child);
    child = next;
  }
}

export function sanitizeTemplateMarkup(markup = '') {
  if (!markup) return '';
  const purify = typeof window !== 'undefined' ? window.DOMPurify : undefined;
  const allowedTags = Array.from(DEFAULT_ALLOWED_TAGS);
  const allowedAttrs = Array.from(DEFAULT_ALLOWED_ATTRS).concat(['data-*', 'aria-*']);
  if (purify && typeof purify.sanitize === 'function') {
    return purify.sanitize(markup, {
      ALLOWED_TAGS: allowedTags,
      ALLOWED_ATTR: allowedAttrs,
      KEEP_CONTENT: true,
    });
  }
  const template = document.createElement('template');
  template.innerHTML = markup;
  sanitizeNode(template.content);
  return template.innerHTML;
}

const SAFE_TEMPLATE_NAME = /^[a-z0-9._-]+$/i;

export function normalizeTemplateName(name = '') {
  const trimmed = (name || '').trim();
  if (!SAFE_TEMPLATE_NAME.test(trimmed)) {
    return '';
  }
  return trimmed;
}
