// File: accessibility.js
let canvas, palette;

export function initAccessibility(opts = {}) {
  canvas = opts.canvas;
  palette = opts.palette;
  checkAccessibility();
}

export function checkAccessibility() {
  if (!canvas) return;
  const blocks = canvas.querySelectorAll('.block-wrapper');
  blocks.forEach((block) => {
    block.classList.remove('a11y-warning');
    block.removeAttribute('data-a11y');
    const issues = [];
    block.querySelectorAll('img').forEach((img) => {
      const alt = img.getAttribute('alt');
      if (!alt || !alt.trim()) {
        issues.push('Image missing alt text');
      }
    });
    if (issues.length) {
      block.classList.add('a11y-warning');
      block.setAttribute('data-a11y', issues.join('; '));
    }
  });
  highlightPalette();
}

function highlightPalette() {
  if (!palette) return;
  const items = palette.querySelectorAll('.block-item');
  items.forEach((it) => it.classList.remove('needs-a11y'));
  const issueTemplates = new Set(
    Array.from(canvas.querySelectorAll('.block-wrapper.a11y-warning')).map(
      (b) => b.dataset.template
    )
  );
  items.forEach((it) => {
    if (issueTemplates.has(it.dataset.file)) {
      it.classList.add('needs-a11y');
    }
  });
}
