// File: accessibility.js
let canvas;

export function initAccessibility(options = {}) {
  canvas = options.canvas;
  if (!canvas) return;
  checkAccessibility();
  document.addEventListener('canvasUpdated', checkAccessibility);
}

export function checkAccessibility() {
  if (!canvas) return;
  let issueCount = 0;
  canvas.querySelectorAll('.block-wrapper').forEach((block) => {
    block.classList.remove('accessibility-warning');
    block.removeAttribute('data-a11y-msg');
    let hasIssue = false;
    block.querySelectorAll('img').forEach((img) => {
      const alt = img.getAttribute('alt');
      if (!alt || !alt.trim()) {
        hasIssue = true;
      }
    });
    if (hasIssue) {
      block.classList.add('accessibility-warning');
      block.dataset.a11yMsg = 'Image missing alt text';
      issueCount += 1;
    }
  });
  const statusEl = document.getElementById('a11yStatus');
  if (statusEl) {
    statusEl.textContent = issueCount
      ? issueCount + ' accessibility issue' + (issueCount > 1 ? 's' : '')
      : '';
  }
}
