function renderGroupItems(details, favorites, toggleFavorite) {
  const items = details.querySelector('.group-items');
  if (!items || details._rendered) return;
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
    if (favorites.includes(it.file)) favBtn.classList.add('active');
    favBtn.textContent = '★';
    favBtn.title = favorites.includes(it.file) ? 'Unfavorite' : 'Favorite';
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

function animateAccordion(details, favorites, toggleFavorite) {
  const summary = details.querySelector('summary');
  const items = details.querySelector('.group-items');
  if (!summary || !items) return;
  if (!details.open) {
    items.style.display = 'none';
  } else {
    renderGroupItems(details, favorites, toggleFavorite);
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
      renderGroupItems(details, favorites, toggleFavorite);
      items.style.display = 'grid';
    }
  });
}

export function initPalette({ paletteEl, builderBase }) {
  if (!paletteEl) return;
  const container = paletteEl.querySelector('.palette-items');
  if (!container) return;

  let favorites = [];
  let allBlockFiles = [];
  let lastSearchTerm = '';

  try {
    favorites = JSON.parse(localStorage.getItem('favoriteBlocks') || '[]');
  } catch (e) {
    favorites = [];
  }

  const renderPalette = (files) => {
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
      if (favs.includes(f) && groups.Favorites) {
        groups.Favorites.push(info);
      }
    });

    Object.keys(groups)
      .sort((a, b) => (a === 'Favorites' ? -1 : b === 'Favorites' ? 1 : a.localeCompare(b)))
      .forEach((g) => {
        if (!groups[g].length) return;
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
        animateAccordion(details, favorites, toggleFavorite);
      });
  };

  const applySearch = (term) => {
    lastSearchTerm = term;
    if (!term) {
      renderPalette(allBlockFiles);
      return;
    }
    const lower = term.toLowerCase();
    const filtered = allBlockFiles.filter((f) => f.toLowerCase().includes(lower));
    renderPalette(filtered);
  };

  function toggleFavorite(file) {
    const idx = favorites.indexOf(file);
    if (idx >= 0) {
      favorites.splice(idx, 1);
    } else {
      favorites.push(file);
    }
    localStorage.setItem('favoriteBlocks', JSON.stringify(favorites));
    applySearch(lastSearchTerm);
  }

  const searchInput = paletteEl.querySelector('.palette-search');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      applySearch(searchInput.value.trim());
    });
  }

  fetch(`${builderBase}/liveed/list-blocks.php`)
    .then((r) => r.json())
    .then((data) => {
      allBlockFiles = data.blocks || [];
      applySearch(lastSearchTerm);
    })
    .catch(() => {
      container.innerHTML = '<p class="palette-error">Unable to load blocks.</p>';
    });
}
