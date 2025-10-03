// File: altText.js
import { getSetting, setSetting } from './state.js';

export function deriveAltText(src) {
  if (!src) return '';
  let name = src.split('/').pop();
  name = name.split('?')[0];
  name = name.replace(/\.[^/.]+$/, '');
  name = name.replace(/[-_]+/g, ' ').trim();
  if (!name) return '';
  return name.charAt(0).toUpperCase() + name.slice(1);
}

export function suggestAltText(block, {
  altName = 'custom_alt',
  srcName = 'custom_src',
  updateInput = false,
  panel = null,
} = {}) {
  const src = getSetting(block, srcName, '').trim();
  const alt = getSetting(block, altName, '').trim();
  const suggestion = deriveAltText(src);
  if (!suggestion || alt) return suggestion;

  setSetting(block, altName, suggestion);

  if (updateInput && panel) {
    const input = panel.querySelector(`input[name="${altName}"]`);
    if (input && !input.value) {
      input.value = suggestion;
    }
  }

  return suggestion;
}
