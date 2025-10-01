// File: builder.js
import { initDragDrop, addBlockControls } from './modules/dragDrop.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import { ensureBlockState, getSettings, setSetting } from './modules/state.js';
import { initUndoRedo } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';
import { initMediaPicker, openMediaPicker } from './modules/mediaPicker.js';
import { executeScripts } from "./modules/executeScripts.js";

let allBlockFiles = [];
let favorites = [];
let builderDraftKey = '';
let lastSavedTimestamp = 0;
let canvas;
let paletteEl;
// Delay before auto-saving after a change. A longer delay prevents rapid
// successive saves while the user is still actively editing.
const SAVE_DEBOUNCE_DELAY = 1000;
// Cache of link statuses across checks to avoid repeating requests
const linkStatusCache = {};

function storeDraft() {
  if (!canvas) return;
  const data = {
    html: canvas.innerHTML,
    timestamp: Date.now(),
  };
  localStorage.setItem(builderDraftKey, JSON.stringify(data));
  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', data.html);
  fd.append('timestamp', data.timestamp);
  fetch(window.builderBase + '/liveed/save-draft.php', {
    method: 'POST',
    body: fd,
  }).catch(() => {});
}

function renderGroupItems(details) {
  const items = details.querySelector('.group-items');
  if (!items || details._rendered) return;
  const favs = favorites;
  const list = details._items || [];
  const frag = document.createDocumentFragment();
  list.forEach((it) => {
    const item = document.createElement('div');
    item.className = 'block-item';
    item.setAttribute('draggable', 'true');
    item.dataset.file = it.file;
    const label = it.label
      .replace(/[-_]/g, ' ')
      .replace(/\b\w/g, (c) => c.toUpperCase());
    item.textContent = label;
    const favBtn = document.createElement('span');
    favBtn.className = 'fav-toggle';
    if (favs.includes(it.file)) favBtn.classList.add('active');
    favBtn.textContent = '★';
    favBtn.title = favs.includes(it.file) ? 'Unfavorite' : 'Favorite';
    favBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleFavorite(it.file);
    });
    item.appendChild(favBtn);
    frag.appendChild(item);
  });
  items.appendChild(frag);
  details._rendered = true;
}

function animateAccordion(details) {
  const summary = details.querySelector('summary');
  const items = details.querySelector('.group-items');
  if (!summary || !items) return;
  if (!details.open) {
    items.style.display = 'none';
  } else {
    renderGroupItems(details);
  }
  summary.addEventListener('click', (e) => {
    e.preventDefault();
    const isOpen = details.open;
    if (isOpen) {
      details.open = false;
      items.style.display = 'none';
      items.innerHTML = '';
      details._rendered = false;
    } else {
      document.querySelectorAll('.palette-group[open]').forEach((other) => {
        if (other !== details) {
          other.open = false;
          const otherItems = other.querySelector('.group-items');
          if (otherItems) {
            otherItems.style.display = 'none';
            otherItems.innerHTML = '';
            other._rendered = false;
          }
        }
      });
      details.open = true;
      renderGroupItems(details);
      items.style.display = 'grid';
    }
  });
}


function renderPalette(palette, files = []) {
  const container = palette.querySelector('.palette-items');
  if (!container) return;
  container.innerHTML = '';

  const favs = favorites;
  const groups = {};
  if (favs.length) groups.Favorites = [];
  files.forEach((f) => {
    if (!f.endsWith('.php')) return;
    const base = f.replace(/\.php$/, '');
    const parts = base.split('.');
    const group = parts.shift();
    const label = parts.join(' ') || group;
    if (!groups[group]) groups[group] = [];
    const info = { file: f, label };
    groups[group].push(info);
    if (favs.includes(f)) {
      groups.Favorites.push(info);
    }
  });

  Object.keys(groups)
    .sort((a, b) => (a === 'Favorites' ? -1 : b === 'Favorites' ? 1 : a.localeCompare(b)))
    .forEach((g) => {
      const details = document.createElement('details');
      details.className = 'palette-group';

      const summary = document.createElement('summary');
      summary.textContent = g.charAt(0).toUpperCase() + g.slice(1);
      details.appendChild(summary);

      const wrap = document.createElement('div');
      wrap.className = 'group-items';

      details._items = groups[g]
        .slice()
        .sort((a, b) => a.label.localeCompare(b.label));
      details.appendChild(wrap);
      container.appendChild(details);
      animateAccordion(details);
    });
}

function toggleFavorite(file) {
  const idx = favorites.indexOf(file);
  if (idx >= 0) {
    favorites.splice(idx, 1);
  } else {
    favorites.push(file);
  }
  localStorage.setItem('favoriteBlocks', JSON.stringify(favorites));
  if (paletteEl) renderPalette(paletteEl, allBlockFiles);
}

let saveTimer;

function checkLinks(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const warnings = [];
  const checks = [];

  const check = (url, type) => {
    if (!url) return;
    try {
      const full = new URL(url, window.location.href).href;
      checks.push(
        fetch(full, { method: 'HEAD' })
          .then((r) => {
            if (!r.ok) warnings.push(`${type} ${url} returned ${r.status}`);
          })
          .catch(() => warnings.push(`${type} ${url} unreachable`))
      );
    } catch (e) {
      warnings.push(`${type} ${url} invalid`);
    }
  };

  doc.querySelectorAll('a[href]').forEach((el) => check(el.getAttribute('href'), 'Link'));
  doc.querySelectorAll('img[src]').forEach((el) => check(el.getAttribute('src'), 'Image'));

  return Promise.all(checks).then(() => warnings);
}

function checkSeo(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const issues = [];
  if (!doc.querySelector('h1')) {
    issues.push('Missing H1 heading');
  }
  const wordCount = doc.body.textContent.trim().split(/\s+/).length;
  if (wordCount < 300) {
    issues.push('Low word count');
  }
  return issues;
}
function savePage() {
  if (!canvas) return;
  const statusEl = document.getElementById('saveStatus');
  const html = canvas.innerHTML;

  if (statusEl) {
    statusEl.textContent = 'Checking links...';
    statusEl.classList.add('saving');
    statusEl.classList.remove('error');
  }

  checkLinks(html).then((warnings) => {
    if (warnings.length) {
      console.warn('Link issues found:', warnings.join('\n'));
      if (statusEl) {
        statusEl.textContent = 'Link issues found';
        statusEl.classList.add('error');
        statusEl.classList.remove('saving');
        setTimeout(() => {
          if (statusEl.textContent === 'Link issues found') {
            statusEl.textContent = '';
            statusEl.classList.remove('error');
          }
        }, 4000);
      }
    }

    const fd = new FormData();
    fd.append('id', window.builderPageId);
    fd.append('content', html);

    if (statusEl) statusEl.textContent = 'Saving...';

    fetch(window.builderBase + '/liveed/save-content.php', {
      method: 'POST',
      body: fd,
    })
      .then((r) => {
        if (!r.ok) throw new Error('Save failed');
        return r.text();
      })
      .then(() => {
        localStorage.removeItem(builderDraftKey);
        lastSavedTimestamp = Date.now();
        if (statusEl) {
          statusEl.textContent = 'Saved';
          statusEl.classList.remove('saving');
        }
        const lastSavedEl = document.getElementById('lastSavedTime');
        if (lastSavedEl) {
          const now = new Date();
          lastSavedEl.textContent = 'Last saved: ' + now.toLocaleString();
        }
        setTimeout(() => {
          if (statusEl && statusEl.textContent === 'Saved') statusEl.textContent = '';
        }, 2000);
      })
      .catch(() => {
        if (statusEl) {
          statusEl.textContent = 'Error saving';
          statusEl.classList.add('error');
          statusEl.classList.remove('saving');
        }
      });
  });
}

function scheduleSave() {
  clearTimeout(saveTimer);
  storeDraft();
  saveTimer = setTimeout(savePage, SAVE_DEBOUNCE_DELAY);
}

document.addEventListener('DOMContentLoaded', () => {
  canvas = document.getElementById('canvas');
  const palette = (paletteEl = document.querySelector('.block-palette'));
  const settingsPanel = document.getElementById('settingsPanel');
  const previewContainer = document.querySelector('.canvas-container');
  const previewButtons = document.querySelectorAll('.preview-toolbar button');
  const previewModal = document.getElementById('previewModal');
  const previewFrame = document.getElementById('previewFrame');
  const closePreview = document.getElementById('closePreview');
  const previewWrapper = previewModal
    ? previewModal.querySelector('.frame-wrapper')
    : null;
  const builderEl = document.querySelector('.builder');
  const viewToggle = document.getElementById('viewModeToggle');
  const paletteHeader = palette ? palette.querySelector('.builder-header') : null;

  builderDraftKey = 'builderDraft-' + window.builderPageId;
  lastSavedTimestamp = window.builderLastModified || 0;
  const draft = localStorage.getItem(builderDraftKey);
  if (draft) {
    try {
      const data = JSON.parse(draft);
      if (data.timestamp > lastSavedTimestamp && data.html) {
        canvas.innerHTML = data.html;
        lastSavedTimestamp = data.timestamp;
      } else {
        localStorage.removeItem(builderDraftKey);
      }
    } catch (e) {
      localStorage.removeItem(builderDraftKey);
    }
  }

  fetch(
    window.builderBase + '/liveed/load-draft.php?id=' + window.builderPageId
  )
    .then((r) => (r.ok ? r.json() : null))
    .then((serverDraft) => {
      if (serverDraft && serverDraft.timestamp > lastSavedTimestamp) {
        canvas.innerHTML = serverDraft.content;
        lastSavedTimestamp = serverDraft.timestamp;
        localStorage.setItem(builderDraftKey, JSON.stringify(serverDraft));
      }
    })
    .catch(() => {});

  // Restore palette position
  const storedPos = palette ? localStorage.getItem('palettePosition') : null;
  if (palette && storedPos) {
    try {
      const pos = JSON.parse(storedPos);
      if (pos.left) palette.style.left = pos.left;
      if (pos.top) palette.style.top = pos.top;
    } catch (e) {}
  }

  // Dragging
  if (palette && paletteHeader) {
    let dragging = false;
    let offsetX = 0;
    let offsetY = 0;
    const SNAP_THRESHOLD = 30; // pixels from left edge to trigger snapping
    const onMove = (e) => {
      if (!dragging) return;
      palette.style.left = e.clientX - offsetX + 'px';
      palette.style.top = e.clientY - offsetY + 'px';
    };
    const onUp = () => {
      if (!dragging) return;
      dragging = false;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      const rect = palette.getBoundingClientRect();
      if (rect.left < SNAP_THRESHOLD) {
        palette.style.left = '0px';
        palette.style.top = '0px';
      }
      localStorage.setItem(
        'palettePosition',
        JSON.stringify({ left: palette.style.left, top: palette.style.top })
      );
    };
    paletteHeader.addEventListener('mousedown', (e) => {
      dragging = true;
      const rect = palette.getBoundingClientRect();
      offsetX = e.clientX - rect.left;
      offsetY = e.clientY - rect.top;
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  if (viewToggle) {
    viewToggle.addEventListener('click', () => {
      const viewing = builderEl.classList.toggle('view-mode');
      viewToggle.innerHTML = viewing
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';
      if (viewing) {
        if (settingsPanel) settingsPanel.classList.remove('open');
      }
    });
  }

  function updatePreview(size) {
    if (!previewContainer) return;
    previewContainer.classList.remove(
      'preview-desktop',
      'preview-tablet',
      'preview-phone'
    );
    if (size === 'desktop') {
      previewContainer.classList.add('preview-desktop');
    }
    previewButtons.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.size === size);
    });
  }

  let previewLoaded = false;

  function openPreview(size) {
    if (!previewModal || !previewFrame) return;
    if (previewWrapper) {
      if (size === 'tablet') previewWrapper.style.width = '768px';
      else if (size === 'phone') previewWrapper.style.width = '375px';
      else previewWrapper.style.width = '100%';
      previewWrapper.style.height = '90vh';
    }
    previewModal.classList.add('active');
    if (!previewLoaded) {
      const base = window.location.origin + window.builderBase + '/';
      const url = new URL('?page=' + window.builderSlug + '&preview=1', base);
      previewFrame.src = url.toString();
      previewLoaded = true;
    }
    updatePreview(size);
  }

  if (closePreview) {
    closePreview.addEventListener('click', () => {
      previewModal.classList.remove('active');
      previewFrame.src = '';
      previewLoaded = false;
      updatePreview('desktop');
    });
  }

  previewButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const size = btn.dataset.size;
      if (size === 'desktop') {
        updatePreview('desktop');
      } else {
        openPreview(size);
      }
    });
  });

  updatePreview('desktop');

  favorites = JSON.parse(localStorage.getItem('favoriteBlocks') || '[]');


  initSettings({ canvas, settingsPanel, savePage: scheduleSave });

  const searchInput = palette.querySelector('.palette-search');

  fetch(window.builderBase + '/liveed/list-blocks.php')
    .then((r) => r.json())
    .then((data) => {
      allBlockFiles = data.blocks || [];
      renderPalette(palette, allBlockFiles);
    });

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const term = searchInput.value.toLowerCase();
      const filtered = allBlockFiles.filter((f) => f.toLowerCase().includes(term));
      renderPalette(palette, filtered);
    });
  }

  initDragDrop({
    palette,
    canvas,
    basePath: window.builderBase,
    loggedIn: true,
    openSettings,
    applyStoredSettings,
  });

  const history = initUndoRedo({ canvas, onChange: scheduleSave, maxHistory: 15 });
  const undoBtn = palette.querySelector('.undo-btn');
  const redoBtn = palette.querySelector('.redo-btn');
  const historyBtn = palette.querySelector('.page-history-btn');
  const linkCheckBtn = palette.querySelector('.link-check-btn');
  const seoCheckBtn = palette.querySelector('.seo-check-btn');
  const a11yCheckBtn = palette.querySelector('.a11y-check-btn');
  const saveBtn = palette.querySelector('.manual-save-btn');
  const historyPanel = document.getElementById('historyPanel');
  if (historyPanel) {
    historyPanel.classList.remove('open');
    historyPanel.style.left = '0px';
  }
  if (undoBtn) undoBtn.addEventListener('click', () => history.undo());
  if (redoBtn) redoBtn.addEventListener('click', () => history.redo());
  if (saveBtn)
    saveBtn.addEventListener('click', () => {
      clearTimeout(saveTimer);
      savePage();
    });
  if (linkCheckBtn) {
    let legendVisible = false;
    const legend = document.getElementById('link-check-legend');

    const fetchWithTimeout = (url, opts, timeout = 5000) => {
      return Promise.race([
        fetch(url, opts),
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error('timeout')), timeout)
        ),
      ]);
    };

    const MAX_CONCURRENT_REQUESTS = 5;

    const runLinkCheck = async () => {
      let brokenLinks = 0,
        workingLinks = 0,
        externalLinks = 0,
        unsetLinks = 0;

      if (!legend) return;
      legend.innerHTML = `
            <div class="legend-item">
              <span class="legend-color broken"></span>
              Broken Links: <span id="broken-count">0</span>
              <span class="tooltip-icon">?
                <span class="tooltip-text">Links that are not working or return errors.</span>
              </span>
            </div>
            <div class="legend-item">
              <span class="legend-color working"></span>
              Working Links: <span id="working-count">0</span>
              <span class="tooltip-icon">?
                <span class="tooltip-text">Links that are working properly.</span>
              </span>
            </div>
            <div class="legend-item">
              <span class="legend-color external"></span>
              External Links: <span id="external-count">0</span>
              <span class="tooltip-icon">?
                <span class="tooltip-text">Links that lead to external websites.</span>
              </span>
            </div>
            <div class="legend-item">
              <span class="legend-color unset"></span>
              Unset Links: <span id="unset-count">0</span>
              <span class="tooltip-icon">?
                <span class="tooltip-text">Links that do not have a proper URL set.</span>
              </span>
            </div>`;
      legend.style.display = 'block';

      const updateLegend = () => {
        legend.querySelector('#broken-count').textContent = brokenLinks;
        legend.querySelector('#working-count').textContent = workingLinks;
        legend.querySelector('#external-count').textContent = externalLinks;
        legend.querySelector('#unset-count').textContent = unsetLinks;
      };

      const applyLinkStatus = (link, status) => {
        if (status === 'working') {
          link.classList.add('working-link', 'link-status');
        } else if (status === 'broken') {
          link.classList.add('broken-link', 'link-status');
        }
      };

      const links = canvas.querySelectorAll('a');
      links.forEach((l) =>
        l.classList.remove(
          'broken-link',
          'working-link',
          'external-link',
          'unset-link',
          'link-status'
        )
      );

      const queue = [];
      links.forEach((link) => {
        const url = link.getAttribute('href');
        if (!url || url === '#' || url === '' || url === '/' || url === 'false') {
          link.classList.add('unset-link');
          unsetLinks++;
          updateLegend();
          return;
        }

        let isExternal = false;
        try {
          const linkUrl = new URL(url, window.location.href);
          isExternal = linkUrl.hostname !== window.location.hostname;
        } catch (e) {
          link.classList.add('unset-link');
          unsetLinks++;
          updateLegend();
          return;
        }

        if (isExternal) {
          link.classList.add('external-link');
          externalLinks++;
          updateLegend();
          return;
        }

        queue.push({ link, url });
      });

      const running = [];
      const all = [];

      const runTask = async ({ link, url }) => {
        if (linkStatusCache[url]) {
          applyLinkStatus(link, linkStatusCache[url]);
          if (linkStatusCache[url] === 'working') workingLinks++;
          else brokenLinks++;
          updateLegend();
          return;
        }

        try {
          const r = await fetchWithTimeout(url, { method: 'HEAD' }, 5000);
          linkStatusCache[url] = r.ok ? 'working' : 'broken';
        } catch (_) {
          linkStatusCache[url] = 'broken';
        }

        applyLinkStatus(link, linkStatusCache[url]);
        if (linkStatusCache[url] === 'working') workingLinks++;
        else brokenLinks++;
        updateLegend();
      };

      for (const item of queue) {
        const p = runTask(item).finally(() => {
          running.splice(running.indexOf(p), 1);
        });
        running.push(p);
        all.push(p);
        if (running.length >= MAX_CONCURRENT_REQUESTS) {
          await Promise.race(running);
        }
      }

      await Promise.all(all);
      updateLegend();
    };

    linkCheckBtn.addEventListener('click', () => {
      if (legendVisible) {
        legend.style.display = 'none';
        canvas
          .querySelectorAll('a')
          .forEach((l) =>
            l.classList.remove(
              'broken-link',
              'working-link',
              'external-link',
              'unset-link',
              'link-status'
            )
          );
        legendVisible = false;
      } else {
        runLinkCheck();
        legendVisible = true;
      }
    });
  }
  if (seoCheckBtn) {
    const seoModal = document.getElementById('seoModal');
    const seoIssues = document.getElementById('seoIssues');
    const seoClose = seoModal ? seoModal.querySelector('.close') : null;

    const runSeoCheck = () => {
      if (!seoModal || !seoIssues) return;
      let results = '';
      $('.highlight-error').removeClass('highlight-error');

      if ($('title').length === 0) {
        results += '<li class="error">Missing <code>&lt;title&gt;</code> tag.</li>';
      } else {
        results += '<li class="pass">Title tag is present.</li>';
        if ($('title').length > 1) {
          results += '<li class="error">Duplicate <code>&lt;title&gt;</code> tags found.</li>';
        }
      }

      if ($('meta[name="description"]').length === 0) {
        results += '<li class="error">Missing meta description.</li>';
      } else {
        results += '<li class="pass">Meta description is present.</li>';
        if ($('meta[name="description"]').length > 1) {
          results += '<li class="error">Duplicate meta description tags found.</li>';
        }
      }

      if ($('h1').length === 0) {
        results += '<li class="error">Missing <code>&lt;h1&gt;</code> tag.</li>';
      } else {
        results += '<li class="pass">H1 tag is present.</li>';
      }

      if ($('h2').length === 0) {
        results += '<li class="error">No <code>&lt;h2&gt;</code> tag found. Consider adding subheadings for better structure.</li>';
      } else {
        results += '<li class="pass">H2 tags are present.</li>';
      }

      const imagesWithoutAlt = $('img:not([alt])');
      if (imagesWithoutAlt.length > 0) {
        results += `<li class="error">${imagesWithoutAlt.length} image(s) missing alt attribute.</li>`;
        imagesWithoutAlt.addClass('highlight-error');
      } else {
        results += '<li class="pass">All images have alt attributes.</li>';
      }

      const imagesWithoutLazy = $('img:not([loading])');
      if (imagesWithoutLazy.length > 0) {
        results += `<li class="error">${imagesWithoutLazy.length} image(s) missing lazy loading attribute (consider using loading=\"lazy\").</li>`;
        imagesWithoutLazy.addClass('highlight-error');
      } else {
        results += '<li class="pass">All images have lazy loading attribute.</li>';
      }

      if ($('link[rel="canonical"]').length === 0) {
        results += '<li class="error">Missing canonical tag.</li>';
      } else {
        results += '<li class="pass">Canonical tag is present.</li>';
      }

      if ($('link[rel="sitemap"]').length === 0) {
        results += '<li class="error">Missing sitemap link.</li>';
      } else {
        results += '<li class="pass">Sitemap link is present.</li>';
      }

      if ($('meta[name="viewport"]').length === 0) {
        results += '<li class="error">Missing viewport meta tag.</li>';
      } else {
        results += '<li class="pass">Viewport meta tag is present.</li>';
      }

      if ($('html').attr('lang') === undefined) {
        results += '<li class="error">Missing language attribute on <code>&lt;html&gt;</code> tag.</li>';
      } else {
        results += '<li class="pass">Language attribute is present on <code>&lt;html&gt;</code> tag.</li>';
      }

      if ($('meta[name="robots"]').length === 0) {
        results += '<li class="error">Missing meta robots tag.</li>';
      } else {
        results += '<li class="pass">Meta robots tag is present.</li>';
      }

      if (
        $('meta[property="og:title"]').length === 0 ||
        $('meta[property="og:description"]').length === 0 ||
        $('meta[property="og:image"]').length === 0
      ) {
        results += '<li class="error">One or more Open Graph tags (og:title, og:description, og:image) are missing.</li>';
      } else {
        results += '<li class="pass">Open Graph tags are present.</li>';
      }

      if ($('meta[name="twitter:card"]').length === 0) {
        results += '<li class="error">Missing Twitter Card meta tag (twitter:card).</li>';
      } else {
        results += '<li class="pass">Twitter Card meta tag is present.</li>';
      }

      if ($('script[type="application/ld+json"]').length === 0) {
        results += '<li class="error">Missing structured data (JSON-LD).</li>';
      } else {
        results += '<li class="pass">Structured data (JSON-LD) is present.</li>';
      }

      seoIssues.innerHTML = results;
      seoModal.style.display = 'block';
    };

    seoCheckBtn.addEventListener('click', runSeoCheck);

    if (seoClose) seoClose.addEventListener('click', () => (seoModal.style.display = 'none'));
    window.addEventListener('click', (e) => {
      if (e.target === seoModal) seoModal.style.display = 'none';
    });
  }
  if (a11yCheckBtn)
    a11yCheckBtn.addEventListener('click', () => {
      if (window.runAccessibilityCheck) {
        window.runAccessibilityCheck();
      }
    });
  if (historyBtn && historyPanel) {
    const closeBtn = historyPanel.querySelector('.close-btn');
    const renderHistory = () => {
      fetch(
        window.builderBase + '/liveed/get-history.php?id=' + window.builderPageId
      )
        .then((r) => {
          if (!r.ok) throw new Error('fetch failed');
          return r.json();
        })
        .then((data) => {
          const cont = historyPanel.querySelector('.history-content');
          cont.innerHTML = '';
          if (data.history && data.history.length) {
            const ul = document.createElement('ul');
            const entries = data.history
              .slice()
              .sort((a, b) => b.time - a.time);
            entries.forEach((h) => {
              const li = document.createElement('li');
              const d = new Date(h.time * 1000);
              const action = h.action ? ' - ' + h.action : '';
              li.textContent = d.toLocaleString() + ' - ' + h.user + action;
              ul.appendChild(li);
            });
            cont.appendChild(ul);
          } else {
            cont.textContent = 'No history yet.';
          }
        })
        .catch(() => {
          const cont = historyPanel.querySelector('.history-content');
          cont.textContent = 'Error loading history.';
        });
    };
    historyBtn.addEventListener('click', () => {
      const rect = palette.getBoundingClientRect();
      historyPanel.style.left = rect.right + 'px';
      historyPanel.style.top = rect.top + 'px';
      renderHistory();
      historyPanel.classList.add('open');
    });
    if (closeBtn)
      closeBtn.addEventListener('click', () => {
        historyPanel.classList.remove('open');
        historyPanel.style.left = '0px';
      });
  }
  initWysiwyg(canvas, true);
  initMediaPicker({ basePath: window.builderBase });
  window.openMediaPicker = openMediaPicker;

  canvas.addEventListener('input', scheduleSave);
  canvas.addEventListener('change', scheduleSave);

  canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);
  executeScripts(canvas);

  function updateCanvasPlaceholder() {
    const placeholder = canvas.querySelector('.canvas-placeholder');
    if (!placeholder) return;
    const hasBlocks = canvas.querySelector('.block-wrapper');
    placeholder.style.display = hasBlocks ? 'none' : '';
  }

  updateCanvasPlaceholder();

  document.addEventListener('canvasUpdated', updateCanvasPlaceholder);
  document.addEventListener('canvasUpdated', scheduleSave);

  canvas.addEventListener('click', (e) => {
    if (builderEl.classList.contains('view-mode')) return;
    const block = e.target.closest('.block-wrapper');
    if (!block) return;
    if (e.target.closest('.block-controls .edit')) {
      openSettings(block);
    } else if (e.target.closest('.block-controls .duplicate')) {
      const clone = block.cloneNode(true);
      clone.classList.remove('selected');
      delete clone.dataset.blockId;
      block.after(clone);
      ensureBlockState(clone);
      const settings = getSettings(block);
      for (const key in settings) {
        setSetting(clone, key, settings[key]);
      }
      addBlockControls(clone);
      applyStoredSettings(clone);
      executeScripts(clone);
      document.dispatchEvent(new Event('canvasUpdated'));
    } else if (e.target.closest('.block-controls .delete')) {
      confirmDelete('Delete this block?').then((ok) => {
        if (ok) {
          block.remove();
          updateCanvasPlaceholder();
          scheduleSave();
        }
      });
    }
  });


  document.addEventListener('mouseover', (e) => {
    const handle = e.target.closest('.control.drag');
    if (handle) {
      const block = handle.closest('.block-wrapper');
      if (block) block.style.transform = 'scale(1.02)';
    }
  });

  document.addEventListener('mouseout', (e) => {
    const handle = e.target.closest('.control.drag');
    if (handle) {
      const block = handle.closest('.block-wrapper');
      if (block) block.style.transform = '';
    }
  });

  window.addEventListener('beforeunload', () => {
    storeDraft();
  });

});
