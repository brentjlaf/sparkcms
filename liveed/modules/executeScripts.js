/**
 * Ensure any script tags within the provided element are executed.
 * Replaces each script with a new one so the browser runs it.
 * @param {Element} container
 */
export function executeScripts(container) {
  if (!container) return;
  container.querySelectorAll('script').forEach((oldScript) => {
    const newScript = document.createElement('script');
    [...oldScript.attributes].forEach((attr) => {
      newScript.setAttribute(attr.name, attr.value);
    });
    newScript.textContent = oldScript.textContent;
    oldScript.replaceWith(newScript);
  });
}
