const CONCURRENCY_LIMIT = 5;

function createCheckTask(value, type, baseUrl, seen) {
  if (!value) return null;
  let fullUrl;
  try {
    fullUrl = new URL(value, baseUrl).href;
  } catch (error) {
    return async () => `${type} ${value} invalid`;
  }
  const key = `${type}:${fullUrl}`;
  if (seen.has(key)) return null;
  seen.add(key);
  return async () => {
    try {
      const response = await fetch(fullUrl, { method: 'HEAD' });
      if (!response.ok) {
        return `${type} ${value} returned ${response.status}`;
      }
    } catch (error) {
      return `${type} ${value} unreachable`;
    }
    return null;
  };
}

function extractTargets(html) {
  if (typeof DOMParser === 'function') {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    return {
      links: Array.from(doc.querySelectorAll('a[href]')).map((el) => el.getAttribute('href')), 
      images: Array.from(doc.querySelectorAll('img[src]')).map((el) => el.getAttribute('src')),
    };
  }

  const anchors = [];
  const images = [];
  const anchorRegex = /<a\b[^>]*href\s*=\s*("([^"]*)"|'([^']*)'|([^'">\s]+))/gi;
  const imageRegex = /<img\b[^>]*src\s*=\s*("([^"]*)"|'([^']*)'|([^'">\s]+))/gi;
  let match = anchorRegex.exec(html);
  while (match) {
    const value = match[2] || match[3] || match[4] || '';
    if (value) anchors.push(value);
    match = anchorRegex.exec(html);
  }
  match = imageRegex.exec(html);
  while (match) {
    const value = match[2] || match[3] || match[4] || '';
    if (value) images.push(value);
    match = imageRegex.exec(html);
  }

  return { links: anchors, images };
}

async function runChecks(html, baseUrl) {
  const seen = new Set();
  const tasks = [];
  const targets = extractTargets(html);

  targets.links.forEach((href) => {
    const task = createCheckTask(href, 'Link', baseUrl, seen);
    if (task) tasks.push(task);
  });
  targets.images.forEach((src) => {
    const task = createCheckTask(src, 'Image', baseUrl, seen);
    if (task) tasks.push(task);
  });

  const warnings = [];
  const queue = tasks.slice();
  const workers = new Array(Math.min(CONCURRENCY_LIMIT, queue.length)).fill(null).map(async () => {
    while (queue.length) {
      const task = queue.shift();
      if (!task) continue;
      const warning = await task();
      if (warning) warnings.push(warning);
    }
  });

  await Promise.all(workers);
  return warnings;
}

self.addEventListener('message', async (event) => {
  const { data } = event;
  if (!data || data.type !== 'checkLinks') return;
  const { html = '', baseUrl = self.location.href, jobId } = data;
  try {
    const warnings = await runChecks(html, baseUrl);
    self.postMessage({ type: 'linkCheckResult', jobId, warnings });
  } catch (error) {
    self.postMessage({
      type: 'linkCheckResult',
      jobId,
      warnings: [],
      error: error ? String(error.message || error) : 'Unknown error',
    });
  }
});
