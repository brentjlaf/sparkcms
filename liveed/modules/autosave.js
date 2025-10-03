const SAVE_DEBOUNCE_DELAY = 1000;

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

function updateStatus(statusEl, text, { saving = false, error = false } = {}) {
  if (!statusEl) return;
  statusEl.textContent = text;
  statusEl.classList.toggle('saving', saving);
  statusEl.classList.toggle('error', error);
}

export function initAutosave({
  canvas,
  statusEl,
  lastSavedEl,
  builderPageId,
  builderBase,
  lastModified = 0,
}) {
  if (!canvas || !builderPageId || !builderBase) {
    const noop = () => {};
    return {
      scheduleSave: noop,
      storeDraft: noop,
      savePage: () => Promise.resolve(),
      saveNow: () => Promise.resolve(),
    };
  }

  const builderDraftKey = `builderDraft-${builderPageId}`;
  let lastSavedTimestamp = lastModified;
  let saveTimer;

  function storeDraft() {
    const data = {
      html: canvas.innerHTML,
      timestamp: Date.now(),
    };
    try {
      localStorage.setItem(builderDraftKey, JSON.stringify(data));
    } catch (e) {
      // Ignore storage errors (e.g., quota exceeded)
    }

    const fd = new FormData();
    fd.append('id', builderPageId);
    fd.append('content', data.html);
    fd.append('timestamp', data.timestamp);

    fetch(`${builderBase}/liveed/save-draft.php`, {
      method: 'POST',
      body: fd,
    }).catch(() => {});
  }

  function savePage() {
    const html = canvas.innerHTML;

    updateStatus(statusEl, 'Checking links...', { saving: true, error: false });

    return checkLinks(html).then((warnings) => {
      if (warnings.length) {
        console.warn('Link issues found:', warnings.join('\n'));
        updateStatus(statusEl, 'Link issues found', { error: true });
        setTimeout(() => {
          if (statusEl && statusEl.textContent === 'Link issues found') {
            updateStatus(statusEl, '', { error: false });
          }
        }, 4000);
      }

      const fd = new FormData();
      fd.append('id', builderPageId);
      fd.append('content', html);

      updateStatus(statusEl, 'Saving...', { saving: true, error: false });

      return fetch(`${builderBase}/liveed/save-content.php`, {
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
          updateStatus(statusEl, 'Saved', { saving: false, error: false });
          if (lastSavedEl) {
            const now = new Date();
            lastSavedEl.textContent = 'Last saved: ' + now.toLocaleString();
          }
          setTimeout(() => {
            if (statusEl && statusEl.textContent === 'Saved') {
              updateStatus(statusEl, '', { saving: false, error: false });
            }
          }, 2000);
        })
        .catch(() => {
          updateStatus(statusEl, 'Error saving', { saving: false, error: true });
        });
    });
  }

  function cancelScheduledSave() {
    clearTimeout(saveTimer);
    saveTimer = null;
  }

  function scheduleSave() {
    cancelScheduledSave();
    storeDraft();
    saveTimer = setTimeout(savePage, SAVE_DEBOUNCE_DELAY);
  }

  function saveNow() {
    cancelScheduledSave();
    storeDraft();
    return savePage();
  }

  function restoreLocalDraft() {
    const draft = localStorage.getItem(builderDraftKey);
    if (!draft) return;
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

  function loadServerDraft() {
    fetch(`${builderBase}/liveed/load-draft.php?id=${builderPageId}`)
      .then((r) => (r.ok ? r.json() : null))
      .then((serverDraft) => {
        if (serverDraft && serverDraft.timestamp > lastSavedTimestamp) {
          canvas.innerHTML = serverDraft.content;
          lastSavedTimestamp = serverDraft.timestamp;
          try {
            localStorage.setItem(builderDraftKey, JSON.stringify(serverDraft));
          } catch (e) {
            // Ignore storage errors
          }
        }
      })
      .catch(() => {});
  }

  restoreLocalDraft();
  loadServerDraft();

  return {
    scheduleSave,
    storeDraft,
    savePage,
    saveNow,
  };
}
