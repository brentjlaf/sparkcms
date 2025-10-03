// File: templateRender.js
export function renderTemplate({
  originalHTML,
  templateSetting,
  settings,
  readValue,
}) {
  if (!templateSetting) {
    return { html: originalHTML, values: {} };
  }

  const inputs = templateSetting.querySelectorAll('input[name], textarea[name], select[name]');
  const processed = new Set();
  const values = {};

  inputs.forEach((input) => {
    const { name } = input;
    if (!name || processed.has(name)) return;

    let value = settings && Object.prototype.hasOwnProperty.call(settings, name)
      ? settings[name]
      : undefined;

    if (value === undefined && typeof readValue === 'function') {
      value = readValue(input, templateSetting);
    }

    values[name] = value ?? '';
    processed.add(name);
  });

  let html = originalHTML;
  Object.entries(values).forEach(([name, value]) => {
    const replacement = value ?? '';
    html = html.split(`{${name}}`).join(replacement);
  });

  html = html.replace(/<templateSetting[^>]*>[\s\S]*?<\/templateSetting>/i, '');

  return { html, values };
}
