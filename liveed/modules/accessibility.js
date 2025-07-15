// File: accessibility.js
let canvas;

export function initAccessibility(options = {}) {
  canvas = options.canvas;
}

export function checkAccessibility() {
  if (!canvas) return { count: 0, messages: [] };
  let issueCount = 0;
  const allMessages = [];
  canvas.querySelectorAll('.block-wrapper').forEach((block) => {
    block.classList.remove('accessibility-warning');
    block.removeAttribute('data-a11y-msg');
    const blockMessages = [];

    block.querySelectorAll('img').forEach((img) => {
      const alt = img.getAttribute('alt');
      if (!alt || !alt.trim()) {
        blockMessages.push('Image missing alt text');
      }
    });

    let lastLevel = 0;
    block.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach((heading) => {
      const level = parseInt(heading.tagName.charAt(1));
      if (lastLevel && level > lastLevel + 1) {
        blockMessages.push('Incorrect heading hierarchy');
      }
      lastLevel = level;
    });

    block.querySelectorAll('[role],button,a').forEach((el) => {
      const role = el.getAttribute('role');
      const label =
        el.getAttribute('aria-label') ||
        el.getAttribute('aria-labelledby') ||
        el.textContent;
      if (!label || !label.trim()) {
        if (role || el.tagName === 'BUTTON' || el.tagName === 'A') {
          blockMessages.push('Missing ARIA label');
        }
      }
    });

    const textEls = block.querySelectorAll(
      'p, span, li, a, h1, h2, h3, h4, h5, h6'
    );
    for (const el of textEls) {
      const style = window.getComputedStyle(el);
      const ratio = getContrastRatio(style.color, style.backgroundColor);
      if (ratio && ratio < 4.5) {
        blockMessages.push('Low color contrast');
        break;
      }
    }

    if (blockMessages.length) {
      block.classList.add('accessibility-warning');
      block.dataset.a11yMsg = blockMessages.join('; ');
      issueCount += blockMessages.length;
      allMessages.push(...blockMessages);
    }
  });

  return { count: issueCount, messages: allMessages };
}

function getContrastRatio(fg, bg) {
  const f = parseColor(fg);
  const b = parseColor(bg);
  if (!f || !b) return 21;
  const fLum = luminance(f.r, f.g, f.b);
  const bLum = luminance(b.r, b.g, b.b);
  const l1 = Math.max(fLum, bLum);
  const l2 = Math.min(fLum, bLum);
  return (l1 + 0.05) / (l2 + 0.05);
}

function parseColor(str) {
  if (!str) return null;
  str = str.trim();
  if (str.startsWith('rgb')) {
    const vals = str
      .replace(/rgba?\(/, '')
      .replace(/\)/, '')
      .split(',')
      .map((v) => parseFloat(v.trim()));
    return { r: vals[0], g: vals[1], b: vals[2] };
  }
  if (str.startsWith('#')) {
    let hex = str.slice(1);
    if (hex.length === 3)
      hex = hex
        .split('')
        .map((c) => c + c)
        .join('');
    if (hex.length === 6) {
      const num = parseInt(hex, 16);
      return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
    }
  }
  return null;
}

function luminance(r, g, b) {
  const a = [r, g, b].map((v) => {
    v /= 255;
    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
}
