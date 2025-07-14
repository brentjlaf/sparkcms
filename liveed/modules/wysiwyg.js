// File: wysiwyg.js
export function initWysiwyg(canvas, loggedIn) {
  if (!loggedIn) return;

  const rteToolbar = document.createElement('div');
  rteToolbar.className = 'wysiwyg-toolbar';
  rteToolbar.style.display = 'none';
  rteToolbar.innerHTML =
    '<button data-cmd="bold"><b>B</b></button>' +
    '<button data-cmd="italic"><i>I</i></button>' +
    '<button data-cmd="underline"><u>U</u></button>' +
    '<button data-cmd="strikeThrough"><s>S</s></button>' +
    '<button data-cmd="formatBlock" data-value="h1">H1</button>' +
    '<button data-cmd="formatBlock" data-value="h2">H2</button>' +
    '<button data-cmd="justifyLeft">Left</button>' +
    '<button data-cmd="justifyCenter">Center</button>' +
    '<button data-cmd="justifyRight">Right</button>' +
    '<button data-cmd="insertOrderedList">OL</button>' +
    '<button data-cmd="insertUnorderedList">UL</button>' +
    '<button data-cmd="formatBlock" data-value="blockquote">&ldquo;</button>' +
    '<button data-cmd="removeFormat">Clear</button>' +
    '<button data-cmd="createLink">Link</button>';
  document.body.appendChild(rteToolbar);

  let currentEditable = null;

  function positionToolbar() {
    const sel = window.getSelection();
    if (!sel.rangeCount) return;
    const range = sel.getRangeAt(0);
    if (!currentEditable || !currentEditable.contains(range.commonAncestorContainer)) {
      return;
    }
    const rect = range.getBoundingClientRect();
    rteToolbar.style.position = 'absolute';
    let top = window.scrollY + rect.top - rteToolbar.offsetHeight - 5;
    if (top < 0) {
      top = window.scrollY + rect.bottom + 5;
    }
    const left = window.scrollX + rect.left + rect.width / 2 - rteToolbar.offsetWidth / 2;
    rteToolbar.style.top = top + 'px';
    rteToolbar.style.left = Math.max(0, left) + 'px';
    rteToolbar.style.display = 'block';
  }

  canvas.querySelectorAll('[data-editable]').forEach((el) => {
    el.setAttribute('contenteditable', 'true');
  });

  canvas.addEventListener('focusin', (e) => {
    const el = e.target.closest('[contenteditable]');
    if (el) {
      currentEditable = el;
      positionToolbar();
    }
  });

  canvas.addEventListener('mouseup', () => {
    if (currentEditable) positionToolbar();
  });

  canvas.addEventListener('keyup', () => {
    if (currentEditable) positionToolbar();
  });

  document.addEventListener('click', (e) => {
    if (!rteToolbar.contains(e.target) && !e.target.closest('[contenteditable]')) {
      rteToolbar.style.display = 'none';
      currentEditable = null;
    }
  });

  function wrapSelection(tagName, attrs = {}) {
    const sel = window.getSelection();
    if (!sel.rangeCount || sel.isCollapsed) return;
    const range = sel.getRangeAt(0);
    const wrapper = document.createElement(tagName);
    Object.entries(attrs).forEach(([k, v]) => wrapper.setAttribute(k, v));
    range.surroundContents(wrapper);
    sel.removeAllRanges();
    sel.addRange(range);
  }

  function setBlock(tagName) {
    const sel = window.getSelection();
    if (!sel.rangeCount) return;
    const range = sel.getRangeAt(0);
    let block = range.startContainer;
    while (block && block !== currentEditable && block.nodeType !== 1) {
      block = block.parentNode;
    }
    if (!block || block === currentEditable) {
      wrapSelection(tagName);
      return;
    }
    const newBlock = document.createElement(tagName);
    newBlock.innerHTML = block.innerHTML;
    block.replaceWith(newBlock);
    const newRange = document.createRange();
    newRange.selectNodeContents(newBlock);
    sel.removeAllRanges();
    sel.addRange(newRange);
  }

  function setAlign(alignment) {
    const sel = window.getSelection();
    if (!sel.rangeCount) return;
    const range = sel.getRangeAt(0);
    let block = range.startContainer;
    while (block && block !== currentEditable && block.nodeType !== 1) {
      block = block.parentNode;
    }
    if (block && block.nodeType === 1) {
      block.style.textAlign = alignment;
    }
  }

  function createLink(url) {
    if (!url) return;
    wrapSelection('a', { href: url });
  }

  rteToolbar.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-cmd]');
    if (!btn) return;
    const cmd = btn.dataset.cmd;
    const value = btn.dataset.value || null;

    switch (cmd) {
      case 'bold':
        wrapSelection('strong');
        break;
      case 'italic':
        wrapSelection('em');
        break;
      case 'underline':
        wrapSelection('u');
        break;
      case 'strikeThrough':
        wrapSelection('s');
        break;
      case 'formatBlock':
        setBlock(value);
        break;
      case 'justifyLeft':
        setAlign('left');
        break;
      case 'justifyCenter':
        setAlign('center');
        break;
      case 'justifyRight':
        setAlign('right');
        break;
      case 'createLink': {
        const url = prompt('Enter URL:');
        if (url) createLink(url);
        break;
      }
      default:
        document.execCommand(cmd, false, value);
    }

    if (currentEditable) currentEditable.focus();
  });
}
