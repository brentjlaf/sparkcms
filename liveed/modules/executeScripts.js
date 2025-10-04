const blockScriptHooks = new Map();
let defaultsRegistered = false;

function normalizeName(name) {
  return typeof name === 'string' ? name.trim() : '';
}

function findMatches(root, selector) {
  if (!selector || !root) return [];
  const matches = [];
  if (typeof root.matches === 'function' && root.matches(selector)) {
    matches.push(root);
  }
  if (typeof root.querySelectorAll === 'function') {
    matches.push(...root.querySelectorAll(selector));
  }
  return matches;
}

function callGlobalRefresh(path) {
  if (typeof window === 'undefined') {
    return false;
  }
  const target = window[path];
  if (!target) {
    return false;
  }
  const method = typeof target === 'function' ? target : target.refresh;
  if (typeof method !== 'function') {
    return false;
  }
  try {
    method.call(target);
    return true;
  } catch (error) {
    console.error('[executeScripts] Error running hook for', path, error);
    return false;
  }
}

export function registerBlockScriptHook(name, options) {
  const key = normalizeName(name);
  if (!key || !options || typeof options !== 'object') {
    return;
  }
  const { selector, callback } = options;
  if (typeof selector !== 'string' || !selector.trim()) {
    return;
  }
  if (typeof callback !== 'function') {
    return;
  }
  blockScriptHooks.set(key, {
    selector: selector.trim(),
    callback,
  });
}

export function clearBlockScriptHooks() {
  blockScriptHooks.clear();
  defaultsRegistered = false;
}

function ensureDefaultHooks() {
  if (defaultsRegistered) return;
  defaultsRegistered = true;

  registerBlockScriptHook('SparkCMSBlogLists.refresh', {
    selector: '[data-blog-list]',
    callback(matches) {
      if (!matches.length) return;
      callGlobalRefresh('SparkCMSBlogLists');
    },
  });

  registerBlockScriptHook('SparkCMSBlogDetails.refresh', {
    selector: '[data-blog-detail]',
    callback(matches) {
      if (!matches.length) return;
      callGlobalRefresh('SparkCMSBlogDetails');
    },
  });

  registerBlockScriptHook('SparkCMSEvents.refresh', {
    selector: '[data-events-block]',
    callback(matches) {
      if (!matches.length) return;
      callGlobalRefresh('SparkCMSEvents');
    },
  });

  registerBlockScriptHook('SparkCMSCalendars.refresh', {
    selector: '[data-calendar-block]',
    callback(matches) {
      if (!matches.length) return;
      callGlobalRefresh('SparkCMSCalendars');
    },
  });

  registerBlockScriptHook('SparkCMSImageGalleries.refresh', {
    selector: '.image-gallery',
    callback(matches) {
      if (!matches.length) return;
      callGlobalRefresh('SparkCMSImageGalleries');
    },
  });

  registerBlockScriptHook('SparkCMSNavigation.refresh', {
    selector: '.nav-toggle, #main-nav, #back-to-top-btn, ._js-scroll-top',
    callback(matches) {
      if (!matches.length) return;
      callGlobalRefresh('SparkCMSNavigation');
    },
  });
}

/**
 * Run lifecycle hooks for blocks that require scripted behaviour.
 * Only registered hooks are executed, preventing arbitrary inline scripts.
 * @param {Element|Document} container
 */
export function executeScripts(container) {
  if (!container) return;
  ensureDefaultHooks();

  const triggered = new Set();
  blockScriptHooks.forEach((hook, name) => {
    if (triggered.has(name)) return;
    const matches = findMatches(container, hook.selector);
    if (!matches.length) return;
    triggered.add(name);
    try {
      hook.callback(matches, container);
    } catch (error) {
      console.error('[executeScripts] Failed running hook', name, error);
    }
  });
}

export function getRegisteredBlockScriptHooks() {
  return new Map(blockScriptHooks);
}
