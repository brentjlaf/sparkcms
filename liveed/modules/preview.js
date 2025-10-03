function removePreviewClasses(container) {
  container.classList.remove('preview-desktop', 'preview-tablet', 'preview-phone');
}

export function initPreview({
  container,
  buttons = [],
  modal,
  frame,
  closeButton,
  wrapper,
  builderBase,
  builderSlug,
}) {
  const buttonList = Array.from(buttons || []);
  if (!container || !buttonList.length) {
    return {
      updatePreview: () => {},
    };
  }

  let previewLoaded = false;

  const updatePreview = (size) => {
    removePreviewClasses(container);
    if (size === 'desktop') {
      container.classList.add('preview-desktop');
    } else if (size === 'tablet') {
      container.classList.add('preview-tablet');
    } else if (size === 'phone') {
      container.classList.add('preview-phone');
    }
    buttonList.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.size === size);
    });
  };

  const openPreview = (size) => {
    if (!modal || !frame) {
      updatePreview(size);
      return;
    }

    if (wrapper) {
      if (size === 'tablet') wrapper.style.width = '768px';
      else if (size === 'phone') wrapper.style.width = '375px';
      else wrapper.style.width = '100%';
      wrapper.style.height = '90vh';
    }

    modal.classList.add('active');
    if (!previewLoaded) {
      const base = `${window.location.origin}${builderBase}/`;
      const url = new URL(`?page=${builderSlug}&preview=1`, base);
      frame.src = url.toString();
      previewLoaded = true;
    }
    updatePreview(size);
  };

  if (closeButton && modal) {
    closeButton.addEventListener('click', () => {
      modal.classList.remove('active');
      if (frame) frame.src = '';
      previewLoaded = false;
      updatePreview('desktop');
    });
  }

  buttonList.forEach((btn) => {
    btn.addEventListener('click', () => {
      const size = btn.dataset.size || 'desktop';
      if (size === 'desktop') {
        if (modal) modal.classList.remove('active');
        if (frame) frame.src = '';
        previewLoaded = false;
        updatePreview('desktop');
      } else {
        openPreview(size);
      }
    });
  });

  updatePreview('desktop');

  return {
    updatePreview,
  };
}
