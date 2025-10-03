// Shared utility for resolving the CMS base path consistently across scripts.
export default function basePath() {
  if (typeof window !== 'object' || window == null) {
    return '';
  }
  var base = typeof window.cmsBase === 'string' ? window.cmsBase : '';
  base = base ? String(base).trim() : '';
  if (!base || base === '/') {
    return '';
  }
  if (base.charAt(0) !== '/') {
    base = '/' + base;
  }
  return base.replace(/\/+$/, '');
}
