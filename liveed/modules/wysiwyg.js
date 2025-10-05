// File: wysiwyg.js
const EDITABLE_SELECTOR = '[data-editable]';
let tiptapLoader = null;

function applyEditableAttributes(el) {
  if (!el || typeof el.setAttribute !== 'function') return;
  if (el.getAttribute('contenteditable') !== 'true') {
    el.setAttribute('contenteditable', 'true');
  }
  if (!el.hasAttribute('tabindex')) {
    el.setAttribute('tabindex', '0');
  }
}

function forEachEditableTarget(target, callback) {
  if (!target || typeof callback !== 'function') return;
  const processed = new Set();
  const handle = (el) => {
    if (!el || processed.has(el)) return;
    processed.add(el);
    callback(el);
  };
  const visit = (node) => {
    if (!node) return;
    const nodeType = node.nodeType;
    if (nodeType !== 1 && nodeType !== 11) {
      return;
    }
    if (typeof node.matches === 'function' && node.matches(EDITABLE_SELECTOR)) {
      handle(node);
    }
    if (typeof node.querySelectorAll === 'function') {
      node.querySelectorAll(EDITABLE_SELECTOR).forEach(handle);
    }
  };
  const visitCollection = (collection) => {
    for (let i = 0; i < collection.length; i++) {
      visit(collection[i]);
    }
  };
  if (Array.isArray(target)) {
    target.forEach(visit);
    return;
  }
  if (typeof NodeList !== 'undefined' && target instanceof NodeList) {
    visitCollection(target);
    return;
  }
  if (typeof HTMLCollection !== 'undefined' && target instanceof HTMLCollection) {
    visitCollection(target);
    return;
  }
  visit(target);
}

export function enhanceElement(target) {
  forEachEditableTarget(target, applyEditableAttributes);
}

function loadTiptap() {
  if (!tiptapLoader) {
    tiptapLoader = Promise.all([
      import('https://esm.sh/@tiptap/core@2?bundle'),
      import('https://esm.sh/@tiptap/starter-kit@2?bundle'),
      import('https://esm.sh/@tiptap/extension-underline@2?bundle'),
      import('https://esm.sh/@tiptap/extension-text-align@2?bundle'),
      import('https://esm.sh/@tiptap/extension-link@2?bundle'),
    ])
      .then(([core, starter, underline, textAlign, link]) => ({
        Editor: core.Editor,
        StarterKit: starter.default,
        Underline: underline.default,
        TextAlign: textAlign.default,
        Link: link.default,
      }))
      .catch((error) => {
        tiptapLoader = null;
        throw error;
      });
  }
  return tiptapLoader;
}

function throttleRAF(fn) {
  let scheduled = false;
  return (...args) => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
      scheduled = false;
      fn(...args);
    });
  };
}

export function initWysiwyg(canvas, loggedIn) {
  if (!loggedIn || !canvas) return;

  const editorMap = new WeakMap();
  const pendingEditorMap = new WeakMap();
  let activeElement = null;
  let activeEditor = null;

  const rteToolbar = document.createElement('div');
  rteToolbar.className = 'wysiwyg-toolbar';
  rteToolbar.style.display = 'none';
  rteToolbar.innerHTML =
    '<button type="button" data-cmd="bold" aria-pressed="false"><b>B</b></button>' +
    '<button type="button" data-cmd="italic" aria-pressed="false"><i>I</i></button>' +
    '<button type="button" data-cmd="underline" aria-pressed="false"><u>U</u></button>' +
    '<button type="button" data-cmd="strike" aria-pressed="false"><s>S</s></button>' +
    '<button type="button" data-cmd="heading" data-value="1">H1</button>' +
    '<button type="button" data-cmd="heading" data-value="2">H2</button>' +
    '<button type="button" data-cmd="align" data-value="left">Left</button>' +
    '<button type="button" data-cmd="align" data-value="center">Center</button>' +
    '<button type="button" data-cmd="align" data-value="right">Right</button>' +
    '<button type="button" data-cmd="orderedList">OL</button>' +
    '<button type="button" data-cmd="bulletList">UL</button>' +
    '<button type="button" data-cmd="blockquote">&ldquo;</button>' +
    '<button type="button" data-cmd="clear">Clear</button>' +
    '<button type="button" data-cmd="link">Link</button>';
  document.body.appendChild(rteToolbar);

  const buttons = Array.from(rteToolbar.querySelectorAll('button[data-cmd]'));
  const builderEl = document.querySelector('.builder');

  const isViewModeActive = () =>
    Boolean(builderEl && builderEl.classList.contains('view-mode'));

  const setToolbarDisabled = (disabled) => {
    buttons.forEach((btn) => {
      btn.disabled = Boolean(disabled);
      btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    });
    if (disabled) {
      rteToolbar.style.display = 'none';
    }
  };

  const handleViewModeChange = (event) => {
    const viewing =
      event && event.detail && typeof event.detail.viewing === 'boolean'
        ? event.detail.viewing
        : isViewModeActive();
    setToolbarDisabled(viewing);
    if (viewing) {
      if (activeEditor && activeEditor.commands && typeof activeEditor.commands.blur === 'function') {
        activeEditor.commands.blur();
      }
      activeElement = null;
      activeEditor = null;
      rteToolbar.style.display = 'none';
    }
  };

  document.addEventListener('builderViewModeChange', handleViewModeChange);
  handleViewModeChange();

  const throttledPosition = throttleRAF(() => {
    if (isViewModeActive()) {
      rteToolbar.style.display = 'none';
      return;
    }
    if (!activeElement || !activeEditor) return;
    const selection = window.getSelection();
    if (!selection || !selection.rangeCount) {
      rteToolbar.style.display = 'none';
      return;
    }
    const range = selection.getRangeAt(0);
    if (!activeElement.contains(range.commonAncestorContainer)) {
      rteToolbar.style.display = 'none';
      return;
    }
    const rect = range.getBoundingClientRect();
    if (!rect || (rect.width === 0 && rect.height === 0)) {
      rteToolbar.style.display = 'none';
      return;
    }
    const toolbarRect = rteToolbar.getBoundingClientRect();
    let top = window.scrollY + rect.top - toolbarRect.height - 8;
    if (top < 0) {
      top = window.scrollY + rect.bottom + 8;
    }
    const left = window.scrollX + rect.left + rect.width / 2 - toolbarRect.width / 2;
    rteToolbar.style.top = Math.max(0, top) + 'px';
    rteToolbar.style.left = Math.max(0, left) + 'px';
    rteToolbar.style.display = 'block';
  });

  function updateToolbarState(editor) {
    if (!editor || isViewModeActive()) return;
    buttons.forEach((btn) => {
      const { cmd, value } = btn.dataset;
      let isActive = false;
      switch (cmd) {
        case 'bold':
          isActive = editor.isActive('bold');
          break;
        case 'italic':
          isActive = editor.isActive('italic');
          break;
        case 'underline':
          isActive = editor.isActive('underline');
          break;
        case 'strike':
          isActive = editor.isActive('strike');
          break;
        case 'heading':
          isActive = editor.isActive('heading', { level: Number(value) });
          break;
        case 'align':
          isActive = editor.isActive({ textAlign: value });
          break;
        case 'orderedList':
          isActive = editor.isActive('orderedList');
          break;
        case 'bulletList':
          isActive = editor.isActive('bulletList');
          break;
        case 'blockquote':
          isActive = editor.isActive('blockquote');
          break;
        case 'link':
          isActive = editor.isActive('link');
          break;
        default:
          isActive = false;
      }
      if (isActive) {
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
      } else {
        btn.classList.remove('active');
        btn.setAttribute('aria-pressed', 'false');
      }
    });
  }

  function destroyEditor(element) {
    const editor = editorMap.get(element);
    if (editor) {
      editor.destroy();
      editorMap.delete(element);
    }
    pendingEditorMap.delete(element);
    if (activeElement === element) {
      activeElement = null;
      activeEditor = null;
      rteToolbar.style.display = 'none';
    }
  }

  function createEditor(el) {
    if (!el || pendingEditorMap.has(el) || editorMap.has(el)) {
      return pendingEditorMap.get(el) || Promise.resolve(editorMap.get(el));
    }
    const promise = loadTiptap()
      .then(({ Editor, StarterKit, Underline, TextAlign, Link }) => {
        if (!el.isConnected) {
          return null;
        }
        let editor;
        try {
          editor = new Editor({
            element: el,
            content: el.innerHTML,
            extensions: [
              StarterKit.configure({
                heading: {
                  levels: [1, 2, 3, 4, 5, 6],
                },
                bulletList: {
                  keepMarks: true,
                  keepAttributes: false,
                },
                orderedList: {
                  keepMarks: true,
                  keepAttributes: false,
                },
              }),
              Underline,
              TextAlign.configure({
                types: ['heading', 'paragraph'],
              }),
              Link.configure({
                openOnClick: false,
                autolink: true,
                linkOnPaste: true,
              }),
            ],
            onUpdate: () => {
              const event = new Event('input', { bubbles: true });
              el.dispatchEvent(event);
            },
            onSelectionUpdate: () => {
              if (activeEditor === editor && !isViewModeActive()) {
                updateToolbarState(editor);
                throttledPosition();
              }
            },
            onFocus: () => {
              if (isViewModeActive()) {
                editor.commands.blur();
                return;
              }
              activeElement = el;
              activeEditor = editor;
              updateToolbarState(editor);
              throttledPosition();
            },
            onBlur: ({ event }) => {
              if (!event || !rteToolbar.contains(event.relatedTarget)) {
                if (activeElement === el) {
                  activeElement = null;
                  activeEditor = null;
                  rteToolbar.style.display = 'none';
                }
              }
            },
          });
        } catch (error) {
          console.error('Failed to initialize WYSIWYG editor', error);
          applyEditableAttributes(el);
          return null;
        }
        editorMap.set(el, editor);
        pendingEditorMap.delete(el);
        return editor;
      })
      .catch((error) => {
        console.error('Failed to load WYSIWYG editor', error);
        pendingEditorMap.delete(el);
        applyEditableAttributes(el);
        return null;
      });
    pendingEditorMap.set(el, promise);
    return promise;
  }

  function prepareEditableElement(el) {
    if (!el) return;
    applyEditableAttributes(el);
    if (editorMap.has(el) || pendingEditorMap.has(el)) return;
    createEditor(el);
  }

  function rescanEditors(root = canvas) {
    if (!root) return;
    forEachEditableTarget(root, (el) => {
      prepareEditableElement(el);
    });
  }

  rescanEditors(canvas);

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        forEachEditableTarget(node, (el) => {
          prepareEditableElement(el);
        });
      });
      mutation.removedNodes.forEach((node) => {
        forEachEditableTarget(node, (el) => {
          destroyEditor(el);
        });
      });
    });
  });

  observer.observe(canvas, { childList: true, subtree: true });

  document.addEventListener('canvasUpdated', () => {
    rescanEditors(canvas);
  });

  document.addEventListener('blockSettingsApplied', (event) => {
    const block = event && event.detail ? event.detail.block : null;
    if (!block) return;
    forEachEditableTarget(block, (el) => {
      applyEditableAttributes(el);
      const editor = editorMap.get(el);
      if (editor) {
        const html = el.innerHTML;
        editor.commands.setContent(html, true);
      } else {
        createEditor(el);
      }
    });
  });

  document.addEventListener('click', (e) => {
    if (rteToolbar.contains(e.target)) {
      return;
    }
    if (activeElement && activeElement.contains(e.target)) {
      return;
    }
    rteToolbar.style.display = 'none';
    activeElement = null;
    activeEditor = null;
  });

  rteToolbar.addEventListener('mousedown', (event) => {
    // Prevent editor blur when clicking toolbar buttons.
    event.preventDefault();
  });

  rteToolbar.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-cmd]');
    if (!btn || !activeEditor || isViewModeActive()) return;
    const { cmd, value } = btn.dataset;
    const editor = activeEditor;
    let handled = true;

    switch (cmd) {
      case 'bold':
        editor.chain().focus().toggleBold().run();
        break;
      case 'italic':
        editor.chain().focus().toggleItalic().run();
        break;
      case 'underline':
        editor.chain().focus().toggleUnderline().run();
        break;
      case 'strike':
        editor.chain().focus().toggleStrike().run();
        break;
      case 'heading':
        editor.chain().focus().toggleHeading({ level: Number(value) }).run();
        break;
      case 'align':
        editor.chain().focus().setTextAlign(value || 'left').run();
        break;
      case 'orderedList':
        editor.chain().focus().toggleOrderedList().run();
        break;
      case 'bulletList':
        editor.chain().focus().toggleBulletList().run();
        break;
      case 'blockquote':
        editor.chain().focus().toggleBlockquote().run();
        break;
      case 'clear':
        editor.chain().focus().unsetAllMarks().clearNodes().run();
        break;
      case 'link': {
        const previous = editor.getAttributes('link').href || '';
        const url = window.prompt('Enter URL', previous || '');
        if (url === null) {
          handled = false;
          break;
        }
        if (!url) {
          editor.chain().focus().unsetLink().run();
        } else {
          editor.chain().focus().setLink({ href: url }).run();
        }
        break;
      }
      default:
        handled = false;
    }

    if (handled) {
      updateToolbarState(editor);
      throttledPosition();
    }
  });
}
