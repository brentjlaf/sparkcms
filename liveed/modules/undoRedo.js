export function initUndoRedo(options = {}) {
  const canvas = options.canvas;
  const restore = options.restore;
  const onChange = options.onChange;
  if (!canvas) return;
  let history = [];
  let index = -1;
  let recording = true;
  let timer;

  const record = () => {
    if (!recording) return;
    const html = canvas.innerHTML;
    if (history[index] === html) return;
    history = history.slice(0, index + 1);
    history.push(html);
    index = history.length - 1;
    if (typeof onChange === 'function') onChange(html);
  };

  const scheduleRecord = () => {
    clearTimeout(timer);
    timer = setTimeout(record, 100);
  };

  const observer = new MutationObserver(scheduleRecord);
  observer.observe(canvas, { childList: true, subtree: true, characterData: true, attributes: true });
  record();

  const applyState = (html) => {
    recording = false;
    canvas.innerHTML = html;
    if (restore) restore();
    recording = true;
    if (typeof onChange === 'function') onChange(html);
  };

  const undo = () => {
    if (index > 0) {
      index--;
      applyState(history[index]);
    }
  };

  const redo = () => {
    if (index < history.length - 1) {
      index++;
      applyState(history[index]);
    }
  };

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
      e.preventDefault();
      undo();
    } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'Z'))) {
      e.preventDefault();
      redo();
    }
  });

  return { record, undo, redo };
}
