/* File: combined.js - merged from global.js, script.js */
/* File: global.js */
// File: global.js
(function () {
  var blogPostsPromise = null;

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  function normalizeBasePath() {
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

  function fetchBlogPosts() {
    if (blogPostsPromise) {
      return blogPostsPromise;
    }
    var base = normalizeBasePath();
    var url = base + '/CMS/data/blog_posts.json';
    blogPostsPromise = fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Failed to load blog posts');
        }
        return response.json();
      })
      .then(function (posts) {
        if (!Array.isArray(posts)) {
          return [];
        }
        return posts.filter(function (post) {
          return post && String(post.status || '').toLowerCase() === 'published';
        });
      })
      .catch(function (error) {
        console.error('[SparkCMS] Blog list error:', error);
        blogPostsPromise = null;
        throw error;
      });
    return blogPostsPromise;
  }

  function parseLimit(value, fallback) {
    var limit = parseInt(value, 10);
    if (!Number.isFinite(limit) || limit < 1) {
      var defaultLimit = Number.isFinite(fallback) && fallback > 0 ? fallback : 6;
      return defaultLimit;
    }
    return limit;
  }

  function normalizeCategory(value) {
    return (value || '').toString().toLowerCase().trim();
  }

  function parseCategoriesList(value) {
    if (value == null) {
      return [];
    }
    return String(value)
      .split(',')
      .map(function (entry) {
        return entry.toLowerCase().trim();
      })
      .filter(function (entry) {
        return entry.length > 0;
      });
  }

  function resolveDetailUrl(prefix, slug) {
    if (!slug) {
      return '#';
    }
    var detail = (prefix || '').toString().trim();
    if (!detail) {
      detail = '/blog';
    }
    if (/^https?:\/\//i.test(detail)) {
      return detail.replace(/\/+$/, '') + '/' + slug;
    }
    var base = normalizeBasePath();
    detail = detail.replace(/\/+$/, '');
    detail = detail.replace(/^\/+/, '');
    var path = detail ? detail + '/' + slug : slug;
    var start = base;
    if (start && start.charAt(0) !== '/') {
      start = '/' + start;
    }
    if (!start) {
      return '/' + path;
    }
    return start.replace(/\/+$/, '') + '/' + path;
  }

  function parseBooleanOption(value, fallback) {
    if (value == null || value === '') {
      return fallback;
    }
    var normalized = String(value).toLowerCase().trim();
    if (['false', 'no', '0', 'off', 'hide'].indexOf(normalized) !== -1) {
      return false;
    }
    if (['true', 'yes', '1', 'on', 'show'].indexOf(normalized) !== -1) {
      return true;
    }
    return fallback;
  }

  function getSlugFromQuery(param) {
    if (!param) {
      return '';
    }
    try {
      var search = typeof window.location === 'object' ? window.location.search || '' : '';
      var params = new URLSearchParams(search);
      var value = params.get(param);
      return value ? value.trim() : '';
    } catch (err) {
      return '';
    }
  }

  function extractSlugFromLocation(basePath) {
    if (typeof window.location !== 'object') {
      return '';
    }
    var path = window.location.pathname || '';
    var base = normalizeBasePath();
    if (base && path.indexOf(base) === 0) {
      path = path.slice(base.length);
    }
    path = path.replace(/[#?].*$/, '');
    path = path.replace(/^\/+/, '').replace(/\/+$/, '');
    if (basePath) {
      var normalized = String(basePath).trim().replace(/^\/+/, '').replace(/\/+$/, '');
      if (normalized) {
        var lowerPath = path.toLowerCase();
        var lowerBase = normalized.toLowerCase();
        if (lowerPath === lowerBase) {
          path = '';
        } else if (lowerPath.indexOf(lowerBase + '/') === 0) {
          path = path.slice(normalized.length + 1);
        }
      }
    }
    if (!path) {
      return '';
    }
    var segments = path.split('/').filter(function (segment) {
      return segment;
    });
    if (!segments.length) {
      return '';
    }
    var last = segments[segments.length - 1];
    try {
      return decodeURIComponent(last);
    } catch (err) {
      return last;
    }
  }

  function formatDate(value) {
    if (!value) {
      return '';
    }
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }
    try {
      return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (err) {
      return date.toISOString().slice(0, 10);
    }
  }

  function clearContainer(container) {
    while (container.firstChild) {
      container.removeChild(container.firstChild);
    }
  }

  function showError(container, message) {
    var itemsHost = container.querySelector('[data-blog-items]') || container;
    clearContainer(itemsHost);
    var error = document.createElement('div');
    error.className = 'blog-item blog-item--error';
    error.textContent = message || 'Unable to load blog posts.';
    itemsHost.appendChild(error);
    var emptyState = container.querySelector('[data-blog-empty]');
    if (emptyState) {
      emptyState.classList.add('d-none');
    }
  }

  function hydrate(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    if (container.dataset.blogRendered === 'server') {
      return;
    }
    var itemsHost = container.querySelector('[data-blog-items]');
    if (!itemsHost) {
      itemsHost = container;
    }
    clearContainer(itemsHost);
    var placeholder = document.createElement('div');
    placeholder.className = 'blog-item blog-item--placeholder';
    placeholder.textContent = 'Loading latest posts…';
    itemsHost.appendChild(placeholder);
    var emptyState = container.querySelector('[data-blog-empty]');
    if (emptyState) {
      emptyState.classList.add('d-none');
    }
    var settings = container.dataset || {};
    var limit = parseLimit(settings.limit);
    var category = normalizeCategory(settings.category);
    var showExcerpt = String(settings.showExcerpt || '').toLowerCase();
    var showMeta = String(settings.showMeta || '').toLowerCase();
    var emptyMessage = settings.empty || 'No posts available.';
    fetchBlogPosts()
      .then(function (posts) {
        var filtered = posts.slice();
        if (category) {
          filtered = filtered.filter(function (post) {
            return normalizeCategory(post.category) === category;
          });
        }
        filtered.sort(function (a, b) {
          var aDate = a.publishDate || a.createdAt || '';
          var bDate = b.publishDate || b.createdAt || '';
          var aTime = Date.parse(aDate);
          var bTime = Date.parse(bDate);
          if (Number.isNaN(aTime)) {
            aTime = 0;
          }
          if (Number.isNaN(bTime)) {
            bTime = 0;
          }
          return bTime - aTime;
        });
        if (limit && filtered.length > limit) {
          filtered = filtered.slice(0, limit);
        }
        clearContainer(itemsHost);
        if (!filtered.length) {
          if (emptyState) {
            emptyState.textContent = emptyMessage;
            emptyState.classList.remove('d-none');
          } else {
            var notice = document.createElement('div');
            notice.className = 'blog-item blog-item--placeholder';
            notice.textContent = emptyMessage;
            itemsHost.appendChild(notice);
          }
          return;
        }
        if (emptyState) {
          emptyState.classList.add('d-none');
        }
        var excerptEnabled = showExcerpt !== 'no' && showExcerpt !== 'false';
        var metaEnabled = showMeta !== 'no' && showMeta !== 'false';
        filtered.forEach(function (post) {
          var article = document.createElement('article');
          article.className = 'blog-item';

          var title = document.createElement('h3');
          title.className = 'blog-title';
          var link = document.createElement('a');
          link.href = resolveDetailUrl(settings.base, post.slug);
          link.textContent = post.title || 'Untitled Post';
          title.appendChild(link);
          article.appendChild(title);

          if (metaEnabled) {
            var parts = [];
            if (post.author) {
              parts.push(post.author);
            }
            var formattedDate = formatDate(post.publishDate || post.createdAt);
            if (formattedDate) {
              parts.push(formattedDate);
            }
            if (parts.length) {
              var meta = document.createElement('div');
              meta.className = 'blog-meta';
              parts.forEach(function (value, index) {
                var span = document.createElement('span');
                span.textContent = value;
                meta.appendChild(span);
              });
              article.appendChild(meta);
            }
          }

          if (excerptEnabled && post.excerpt) {
            var excerpt = document.createElement('p');
            excerpt.className = 'blog-excerpt';
            excerpt.textContent = post.excerpt;
            article.appendChild(excerpt);
          }

          var readMore = document.createElement('a');
          readMore.className = 'blog-read-more';
          readMore.href = resolveDetailUrl(settings.base, post.slug);
          readMore.innerHTML = 'Read more <span aria-hidden="true">&rarr;</span>';
          article.appendChild(readMore);

          itemsHost.appendChild(article);
        });
      })
      .catch(function () {
        showError(container, 'Unable to load blog posts at this time.');
      });
  }

  function showBlogDetailLoading(container) {
    var loading = container.querySelector('[data-blog-loading]');
    var body = container.querySelector('[data-blog-body]');
    var emptyState = container.querySelector('[data-blog-empty]');
    if (loading) {
      loading.style.display = '';
    }
    if (body) {
      body.style.display = 'none';
    }
    if (emptyState) {
      emptyState.style.display = 'none';
    }
  }

  function showBlogDetailEmpty(container, message) {
    var loading = container.querySelector('[data-blog-loading]');
    var body = container.querySelector('[data-blog-body]');
    var emptyState = container.querySelector('[data-blog-empty]');
    if (loading) {
      loading.style.display = 'none';
    }
    if (body) {
      body.style.display = 'none';
    }
    if (emptyState) {
      emptyState.textContent = message || 'This blog post could not be found.';
      emptyState.style.display = '';
    }
  }

  function populateBlogDetail(container, post, options) {
    options = options || {};
    var loading = container.querySelector('[data-blog-loading]');
    var emptyState = container.querySelector('[data-blog-empty]');
    var body = container.querySelector('[data-blog-body]');
    if (loading) {
      loading.style.display = 'none';
    }
    if (emptyState) {
      emptyState.style.display = 'none';
    }
    if (body) {
      body.style.display = '';
    }

    var titleEl = container.querySelector('[data-blog-title]');
    if (titleEl) {
      titleEl.textContent = post.title || 'Untitled Post';
    }

    var categoryEl = container.querySelector('[data-blog-category]');
    if (categoryEl) {
      if (options.showCategory && post.category) {
        categoryEl.textContent = post.category;
        categoryEl.style.display = '';
      } else {
        categoryEl.textContent = '';
        categoryEl.style.display = 'none';
      }
    }

    var metaEl = container.querySelector('[data-blog-meta]');
    var authorEl = container.querySelector('[data-blog-author]');
    var dateEl = container.querySelector('[data-blog-date]');
    var metaVisible = false;
    if (options.showMeta) {
      if (authorEl) {
        authorEl.textContent = post.author || '';
        if (post.author) {
          authorEl.style.display = '';
          metaVisible = true;
        } else {
          authorEl.style.display = 'none';
        }
      }
      if (dateEl) {
        var formattedDate = formatDate(post.publishDate || post.createdAt);
        dateEl.textContent = formattedDate || '';
        if (formattedDate) {
          dateEl.style.display = '';
          metaVisible = true;
        } else {
          dateEl.style.display = 'none';
        }
      }
    } else {
      if (authorEl) {
        authorEl.textContent = '';
        authorEl.style.display = 'none';
      }
      if (dateEl) {
        dateEl.textContent = '';
        dateEl.style.display = 'none';
      }
    }
    if (metaEl) {
      if (options.showMeta && metaVisible) {
        metaEl.style.display = '';
      } else {
        metaEl.style.display = 'none';
      }
    }

    var imageWrapper = container.querySelector('[data-blog-image-wrapper]');
    var imageEl = container.querySelector('[data-blog-image]');
    if (imageWrapper) {
      if (options.showImage && post.image && imageEl) {
        imageEl.src = post.image;
        imageEl.alt = post.imageAlt || ('Featured image for ' + (post.title || 'blog post'));
        imageWrapper.style.display = '';
      } else {
        if (imageEl) {
          imageEl.removeAttribute('src');
          imageEl.alt = '';
        }
        imageWrapper.style.display = 'none';
      }
    }

    var contentEl = container.querySelector('[data-blog-content]');
    if (contentEl) {
      contentEl.innerHTML = post.content || '<p>This post does not have any content yet.</p>';
    }

    var tagsContainer = container.querySelector('[data-blog-tags]');
    var tagsList = container.querySelector('[data-blog-tag-list]');
    if (tagsContainer) {
      if (options.showTags && post.tags && tagsList) {
        var tags = String(post.tags)
          .split(',')
          .map(function (tag) {
            return tag.trim();
          })
          .filter(function (tag) {
            return tag.length > 0;
          });
        if (tags.length) {
          tagsList.innerHTML = '';
          tags.forEach(function (tag) {
            var item = document.createElement('li');
            item.className = 'blog-detail-tag';
            item.textContent = tag;
            tagsList.appendChild(item);
          });
          tagsContainer.style.display = '';
        } else {
          tagsList.innerHTML = '';
          tagsContainer.style.display = 'none';
        }
      } else if (tagsList) {
        tagsList.innerHTML = '';
        tagsContainer.style.display = 'none';
      }
    }
  }

  function renderBlogDetail(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    showBlogDetailLoading(container);
    var dataset = container.dataset || {};
    var emptyMessage = dataset.empty || 'This blog post could not be found.';
    var slug = dataset.slug || '';
    var autoSlug = String(dataset.autoSlug || 'yes').toLowerCase();
    if (autoSlug !== 'no' && autoSlug !== 'false') {
      var fromQuery = getSlugFromQuery(dataset.queryParam);
      if (fromQuery) {
        slug = fromQuery;
      } else {
        var derived = extractSlugFromLocation(dataset.base);
        if (derived) {
          slug = derived;
        }
      }
    }
    slug = (slug || '').trim();
    if (!slug) {
      showBlogDetailEmpty(container, emptyMessage);
      return;
    }
    container.dataset.blogSlug = slug;
    fetchBlogPosts()
      .then(function (posts) {
        var normalized = slug.toLowerCase();
        var match = posts.find(function (post) {
          if (!post) {
            return false;
          }
          return String(post.slug || '').toLowerCase() === normalized;
        });
        if (!match) {
          showBlogDetailEmpty(container, emptyMessage);
          return;
        }
        populateBlogDetail(container, match, {
          showImage: parseBooleanOption(dataset.showImage, true),
          showMeta: parseBooleanOption(dataset.showMeta, true),
          showCategory: parseBooleanOption(dataset.showCategory, true),
          showTags: parseBooleanOption(dataset.showTags, true)
        });
      })
      .catch(function () {
        showBlogDetailEmpty(container, emptyMessage);
      });
  }

  var eventsPromise = null;
  var eventCategoriesPromise = null;
  var htmlParser = null;
  var eventsDetailCache = Object.create(null);
  var EVENTS_CART_STORAGE_KEY = 'sparkcms.events.cart';
  var eventsCartState = loadEventsCartState();
  var activeEventsModalState = null;

  function fetchEvents() {
    if (eventsPromise) {
      return eventsPromise;
    }
    var base = normalizeBasePath();
    var url = base + '/CMS/data/events.json';
    eventsPromise = fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Failed to load events');
        }
        return response.json();
      })
      .then(function (events) {
        if (!Array.isArray(events)) {
          return [];
        }
        return events.filter(function (event) {
          if (!event || typeof event !== 'object') {
            return false;
          }
          var status = String(event.status || '').toLowerCase();
          return !status || status === 'published';
        });
      })
      .catch(function (error) {
        console.error('[SparkCMS] Events load error:', error);
        eventsPromise = null;
        throw error;
      });
    return eventsPromise;
  }

  function fetchEventCategories() {
    if (eventCategoriesPromise) {
      return eventCategoriesPromise;
    }
    var base = normalizeBasePath();
    var url = base + '/CMS/data/event_categories.json';
    eventCategoriesPromise = fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Failed to load event categories');
        }
        return response.json();
      })
      .then(function (records) {
        if (!Array.isArray(records)) {
          return {};
        }
        return records.reduce(function (map, record) {
          if (!record || typeof record !== 'object') {
            return map;
          }
          var id = record.id || record.slug || record.name;
          if (!id) {
            return map;
          }
          map[id] = {
            id: id,
            name: record.name || '',
            slug: record.slug || '',
            normalizedName: normalizeCategory(record.name),
            normalizedSlug: normalizeCategory(record.slug),
            normalizedId: normalizeCategory(id)
          };
          return map;
        }, {});
      })
      .catch(function (error) {
        console.error('[SparkCMS] Event categories load error:', error);
        eventCategoriesPromise = null;
        return {};
      });
    return eventCategoriesPromise;
  }

  function normalizeEventsLayout(value) {
    var layout = String(value || '').toLowerCase();
    if (['list', 'compact', 'cards'].indexOf(layout) === -1) {
      return 'cards';
    }
    return layout;
  }

  function eventMatchesCategoryFilter(event, filter, categoriesIndex) {
    if (!filter) {
      return true;
    }
    var ids = Array.isArray(event.categories) ? event.categories : [];
    if (!ids.length) {
      return false;
    }
    return ids.some(function (id) {
      var info = categoriesIndex[id];
      if (!info) {
        return normalizeCategory(id) === filter;
      }
      return info.normalizedId === filter || info.normalizedSlug === filter || info.normalizedName === filter;
    });
  }

  function stripHtml(html) {
    if (!html) {
      return '';
    }
    if (!htmlParser) {
      htmlParser = document.createElement('div');
    }
    htmlParser.innerHTML = html;
    return htmlParser.textContent || htmlParser.innerText || '';
  }

  function truncateText(value, maxLength) {
    if (!value) {
      return '';
    }
    var text = String(value).trim();
    if (!maxLength || text.length <= maxLength) {
      return text;
    }
    return text.slice(0, maxLength - 1).trimEnd() + '…';
  }

  function formatEventMonth(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return '';
    }
    try {
      return date.toLocaleString(undefined, { month: 'short' });
    } catch (error) {
      return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][date.getMonth()] || '';
    }
  }

  function formatEventDay(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return '';
    }
    return String(date.getDate()).padStart(2, '0');
  }

  function formatEventRange(start, end) {
    if (!(start instanceof Date) || Number.isNaN(start.getTime())) {
      return '';
    }
    var formatterOptions = { month: 'short', day: 'numeric', year: 'numeric' };
    var timeFormatterOptions = { hour: 'numeric', minute: '2-digit' };
    var dateLabel;
    try {
      dateLabel = start.toLocaleDateString(undefined, formatterOptions);
    } catch (error) {
      dateLabel = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0');
    }
    if (!(end instanceof Date) || Number.isNaN(end.getTime())) {
      try {
        var timeLabel = start.toLocaleTimeString(undefined, timeFormatterOptions);
        return timeLabel ? dateLabel + ' • ' + timeLabel : dateLabel;
      } catch (error) {
        return dateLabel;
      }
    }
    var sameDay = start.toDateString() === end.toDateString();
    try {
      if (sameDay) {
        var startTime = start.toLocaleTimeString(undefined, timeFormatterOptions);
        var endTime = end.toLocaleTimeString(undefined, timeFormatterOptions);
        if (startTime && endTime) {
          return dateLabel + ' • ' + startTime + ' – ' + endTime;
        }
        if (startTime) {
          return dateLabel + ' • ' + startTime;
        }
        return dateLabel;
      }
      var endLabel = end.toLocaleDateString(undefined, formatterOptions);
      return dateLabel + ' – ' + endLabel;
    } catch (error) {
      var endStamp = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0');
      return sameDay ? dateLabel : dateLabel + ' – ' + endStamp;
    }
  }

  function findLowestPrice(tickets) {
    if (!Array.isArray(tickets) || !tickets.length) {
      return null;
    }
    var values = tickets
      .filter(function (ticket) {
        return ticket && ticket.enabled !== false && Number.isFinite(Number(ticket.price));
      })
      .map(function (ticket) {
        return Number(ticket.price);
      });
    if (!values.length) {
      return null;
    }
    return Math.min.apply(Math, values);
  }

  function formatCurrency(amount) {
    if (!Number.isFinite(amount)) {
      return '';
    }
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amount);
    } catch (error) {
      return '$' + amount.toFixed(2);
    }
  }

  function createEventSlug(event) {
    if (!event || typeof event !== 'object') {
      return '';
    }
    if (event.slug) {
      return String(event.slug).trim();
    }
    if (event.title) {
      var generated = String(event.title)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
      if (generated) {
        return generated;
      }
    }
    if (event.id) {
      return String(event.id).trim();
    }
    return '';
  }

  function resolveEventDetailUrl(prefix, event) {
    var base = (prefix || '').toString().trim();
    if (!base) {
      return '';
    }
    var slug = createEventSlug(event);
    if (/^https?:\/\//i.test(base)) {
      return base.replace(/\/+$/, '') + (slug ? '/' + slug : '');
    }
    var normalized = base.replace(/\/+$/, '').replace(/^\/+/, '');
    var path = normalized;
    if (slug) {
      path = normalized ? normalized + '/' + slug : slug;
    }
    var start = normalizeBasePath();
    if (start && start.charAt(0) !== '/') {
      start = '/' + start;
    }
    if (!start) {
      return '/' + path;
    }
    return start.replace(/\/+$/, '') + '/' + path;
  }

  function showEventsEmpty(container, message) {
    var empty = container.querySelector('[data-events-empty]');
    if (empty) {
      empty.textContent = message || empty.textContent || '';
      empty.classList.remove('d-none');
    }
  }

  function hideEventsEmpty(container) {
    var empty = container.querySelector('[data-events-empty]');
    if (empty) {
      empty.classList.add('d-none');
    }
  }

  function rememberEventDetail(event) {
    if (!event || !event.raw) {
      return;
    }
    var id = event.raw.id || event.raw.slug || '';
    if (!id) {
      return;
    }
    var detail = {
      id: String(id),
      title: event.raw.title || 'Untitled Event',
      description: event.raw.description || '',
      location: event.raw.location || '',
      startDate: event.startDate instanceof Date && !Number.isNaN(event.startDate.getTime()) ? new Date(event.startDate.getTime()) : null,
      endDate: event.endDate instanceof Date && !Number.isNaN(event.endDate.getTime()) ? new Date(event.endDate.getTime()) : null,
      categoryNames: Array.isArray(event.categoryNames) ? event.categoryNames.slice() : [],
      tickets: Array.isArray(event.raw.tickets)
        ? event.raw.tickets.filter(function (ticket) {
            return ticket && ticket.enabled !== false;
          })
        : [],
      raw: event.raw
    };
    eventsDetailCache[detail.id] = detail;
  }

  function getEventDetailById(id) {
    if (!id) {
      return null;
    }
    return eventsDetailCache[id] || null;
  }

  function sanitizeEventHtml(html) {
    if (!html) {
      return '';
    }
    var wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    var forbidden = wrapper.querySelectorAll('script, style, iframe, object, embed, link, meta');
    forbidden.forEach(function (node) {
      if (node && node.parentNode) {
        node.parentNode.removeChild(node);
      }
    });
    var allowed = {
      P: true,
      BR: true,
      STRONG: true,
      EM: true,
      B: true,
      I: true,
      UL: true,
      OL: true,
      LI: true,
      A: true,
      H1: true,
      H2: true,
      H3: true,
      H4: true,
      H5: true,
      H6: true,
      BLOCKQUOTE: true,
      SPAN: true,
      DIV: true
    };
    var nodes = wrapper.querySelectorAll('*');
    nodes.forEach(function (node) {
      if (!allowed[node.tagName]) {
        var parent = node.parentNode;
        if (!parent) {
          return;
        }
        while (node.firstChild) {
          parent.insertBefore(node.firstChild, node);
        }
        parent.removeChild(node);
        return;
      }
      Array.prototype.slice
        .call(node.attributes || [])
        .forEach(function (attr) {
          var name = attr.name.toLowerCase();
          if (node.tagName === 'A' && (name === 'href' || name === 'title')) {
            if (name === 'href') {
              var href = node.getAttribute('href') || '';
              if (/^javascript:/i.test(href) || /^data:/i.test(href)) {
                node.removeAttribute('href');
              }
            }
            return;
          }
          if (name.indexOf('aria-') === 0) {
            return;
          }
          node.removeAttribute(attr.name);
        });
    });
    return wrapper.innerHTML;
  }

  function loadEventsCartState() {
    var fallback = { items: [] };
    if (typeof window === 'undefined' || !window.localStorage) {
      return fallback;
    }
    try {
      var stored = window.localStorage.getItem(EVENTS_CART_STORAGE_KEY);
      if (!stored) {
        return fallback;
      }
      var parsed = JSON.parse(stored);
      if (!parsed || typeof parsed !== 'object' || !Array.isArray(parsed.items)) {
        return fallback;
      }
      parsed.items = parsed.items.filter(function (item) {
        if (!item || typeof item !== 'object') {
          return false;
        }
        if (!item.eventId || !item.ticketId) {
          return false;
        }
        var quantity = parseInt(item.quantity, 10);
        if (!Number.isFinite(quantity) || quantity <= 0) {
          return false;
        }
        var price = Number(item.price);
        if (!Number.isFinite(price)) {
          price = 0;
        }
        item.quantity = quantity;
        item.price = price;
        if (item.eventStart) {
          var startDate = new Date(item.eventStart);
          if (Number.isNaN(startDate.getTime())) {
            item.eventStart = null;
          }
        }
        if (item.eventEnd) {
          var endDate = new Date(item.eventEnd);
          if (Number.isNaN(endDate.getTime())) {
            item.eventEnd = null;
          }
        }
        return true;
      });
      return parsed;
    } catch (error) {
      console.warn('[SparkCMS] Unable to parse events cart from storage:', error);
      return fallback;
    }
  }

  function saveEventsCartState() {
    if (typeof window === 'undefined' || !window.localStorage) {
      return;
    }
    try {
      window.localStorage.setItem(EVENTS_CART_STORAGE_KEY, JSON.stringify(eventsCartState));
    } catch (error) {
      console.warn('[SparkCMS] Unable to persist events cart:', error);
    }
  }

  function getCartItems() {
    if (!eventsCartState || !Array.isArray(eventsCartState.items)) {
      eventsCartState = { items: [] };
    }
    return eventsCartState.items;
  }

  function setCartItems(items) {
    eventsCartState.items = items;
    saveEventsCartState();
    updateEventsCartIndicators();
  }

  function getCartItemCount() {
    return getCartItems().reduce(function (total, item) {
      var quantity = parseInt(item.quantity, 10);
      if (!Number.isFinite(quantity) || quantity < 0) {
        return total;
      }
      return total + quantity;
    }, 0);
  }

  function getCartTotal() {
    return getCartItems().reduce(function (total, item) {
      var price = Number(item.price);
      var quantity = parseInt(item.quantity, 10);
      if (!Number.isFinite(price) || !Number.isFinite(quantity)) {
        return total;
      }
      return total + price * quantity;
    }, 0);
  }

  function updateEventsCartIndicators() {
    var count = getCartItemCount();
    var total = getCartTotal();
    var totalLabel = formatCurrency(total);
    if (!totalLabel) {
      totalLabel = '$0.00';
    }
    document.querySelectorAll('[data-events-cart-count]').forEach(function (node) {
      node.textContent = String(count);
    });
    document.querySelectorAll('[data-events-cart-total]').forEach(function (node) {
      node.textContent = totalLabel;
    });
    document.querySelectorAll('[data-events-cart-open]').forEach(function (button) {
      if (!(button instanceof HTMLElement)) {
        return;
      }
      if (count > 0) {
        button.classList.add('events-block__cart-button--active');
      } else {
        button.classList.remove('events-block__cart-button--active');
      }
    });
  }

  function updateCartButtonsExpanded(expanded) {
    document.querySelectorAll('[data-events-cart-open]').forEach(function (button) {
      if (button instanceof HTMLElement) {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      }
    });
  }

  function addTicketsToCart(detail, selections) {
    if (!detail || !Array.isArray(selections) || !selections.length) {
      return false;
    }
    var items = getCartItems().slice();
    var didAdd = false;
    selections.forEach(function (selection) {
      if (!selection || !selection.ticket || !selection.ticketId) {
        return;
      }
      var quantity = parseInt(selection.quantity, 10);
      if (!Number.isFinite(quantity) || quantity <= 0) {
        return;
      }
      var existing = items.find(function (item) {
        return item.eventId === detail.id && item.ticketId === selection.ticketId;
      });
      var available = Number(selection.ticket.quantity);
      if (existing) {
        var targetQuantity = existing.quantity + quantity;
        if (Number.isFinite(available) && available > 0) {
          targetQuantity = Math.min(targetQuantity, available);
        }
        if (targetQuantity > existing.quantity) {
          existing.quantity = targetQuantity;
          didAdd = true;
        }
        return;
      }
      if (Number.isFinite(available) && available > 0) {
        quantity = Math.min(quantity, available);
      }
      if (quantity <= 0) {
        return;
      }
      items.push({
        eventId: detail.id,
        eventTitle: detail.title,
        eventStart: detail.startDate instanceof Date ? detail.startDate.toISOString() : null,
        eventEnd: detail.endDate instanceof Date ? detail.endDate.toISOString() : null,
        ticketId: selection.ticketId,
        ticketName: selection.ticket.name || 'General Admission',
        price: Number(selection.ticket.price) || 0,
        quantity: quantity
      });
      didAdd = true;
    });
    if (!didAdd) {
      return false;
    }
    setCartItems(items);
    return true;
  }

  function removeCartItem(eventId, ticketId) {
    var items = getCartItems().filter(function (item) {
      return !(item.eventId === eventId && item.ticketId === ticketId);
    });
    setCartItems(items);
  }

  function clearCart() {
    setCartItems([]);
  }

  function getEventsModalState(container) {
    if (!(container instanceof HTMLElement)) {
      return null;
    }
    if (container._eventsModalState && container._eventsModalState.modal) {
      return container._eventsModalState;
    }
    var modal = container.querySelector('[data-events-modal]');
    if (!(modal instanceof HTMLElement)) {
      return null;
    }
    var dialog = modal.querySelector('.events-modal__dialog');
    var content = modal.querySelector('[data-events-modal-content]');
    if (!(dialog instanceof HTMLElement) || !(content instanceof HTMLElement)) {
      return null;
    }
    var state = {
      container: container,
      modal: modal,
      dialog: dialog,
      content: content,
      titleId: content.dataset.eventsModalTitleId || dialog.getAttribute('aria-labelledby') || '',
      lastTrigger: null,
      currentMode: null,
      notice: null
    };
    dialog.setAttribute('tabindex', '-1');
    if (!modal.dataset.eventsModalBound) {
      modal.dataset.eventsModalBound = 'true';
      modal.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        if (target.dataset && target.dataset.eventsModalClose !== undefined) {
          event.preventDefault();
          closeEventsModal();
        }
      });
    }
    container._eventsModalState = state;
    return state;
  }

  function trapModalFocus(dialog, event) {
    var selectors = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var focusable = Array.prototype.slice.call(dialog.querySelectorAll(selectors));
    if (!focusable.length) {
      event.preventDefault();
      dialog.focus();
      return;
    }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    var active = document.activeElement;
    if (event.shiftKey) {
      if (active === first || !dialog.contains(active)) {
        event.preventDefault();
        last.focus();
      }
    } else if (active === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function handleModalKeydown(event) {
    if (!activeEventsModalState || !(activeEventsModalState.dialog instanceof HTMLElement)) {
      return;
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      closeEventsModal();
      return;
    }
    if (event.key === 'Tab') {
      trapModalFocus(activeEventsModalState.dialog, event);
    }
  }

  function openEventsModal(state, trigger) {
    if (!state || !(state.modal instanceof HTMLElement) || !(state.dialog instanceof HTMLElement)) {
      return;
    }
    if (activeEventsModalState && activeEventsModalState !== state) {
      closeEventsModal();
    }
    state.lastTrigger = trigger || null;
    state.modal.hidden = false;
    activeEventsModalState = state;
    if (document && document.body) {
      document.body.classList.add('events-modal-open');
    }
    requestAnimationFrame(function () {
      try {
        state.dialog.focus();
      } catch (error) {}
    });
    document.addEventListener('keydown', handleModalKeydown);
  }

  function closeEventsModal() {
    if (!activeEventsModalState) {
      return;
    }
    var state = activeEventsModalState;
    activeEventsModalState = null;
    if (state.modal instanceof HTMLElement) {
      state.modal.hidden = true;
    }
    if (document && document.body) {
      document.body.classList.remove('events-modal-open');
    }
    document.removeEventListener('keydown', handleModalKeydown);
    if (state.currentMode === 'cart') {
      updateCartButtonsExpanded(false);
    }
    var trigger = state.lastTrigger;
    state.lastTrigger = null;
    if (trigger && typeof trigger.focus === 'function') {
      try {
        trigger.focus();
      } catch (error) {}
    }
  }

  function setEventsModalNotice(state, message, type) {
    if (!state || !(state.notice instanceof HTMLElement)) {
      return;
    }
    if (!message) {
      state.notice.textContent = '';
      state.notice.className = 'events-modal__notice';
      state.notice.hidden = true;
      return;
    }
    var className = 'events-modal__notice';
    if (type === 'success') {
      className += ' events-modal__notice--success';
    } else if (type === 'error') {
      className += ' events-modal__notice--error';
    }
    state.notice.className = className;
    state.notice.textContent = message;
    state.notice.hidden = false;
  }

  function renderEventDetailsModal(state, detail) {
    if (!state || !detail || !(state.content instanceof HTMLElement)) {
      return;
    }
    state.content.innerHTML = '';
    var titleId = state.titleId || 'events-modal-title';
    var header = document.createElement('header');
    header.className = 'events-modal__header';
    var title = document.createElement('h3');
    title.className = 'events-modal__title';
    title.id = titleId;
    title.textContent = detail.title;
    header.appendChild(title);

    var meta = document.createElement('div');
    meta.className = 'events-modal__meta';
    var hasMeta = false;
    if (detail.startDate instanceof Date) {
      var metaTime = document.createElement('time');
      metaTime.dateTime = detail.startDate.toISOString();
      metaTime.textContent = formatEventRange(detail.startDate, detail.endDate);
      meta.appendChild(metaTime);
      hasMeta = true;
    }
    if (detail.location) {
      var metaLocation = document.createElement('span');
      metaLocation.textContent = 'Location: ' + detail.location;
      meta.appendChild(metaLocation);
      hasMeta = true;
    }
    if (detail.categoryNames && detail.categoryNames.length) {
      var badgeWrap = document.createElement('span');
      badgeWrap.className = 'events-modal__meta-badges';
      detail.categoryNames.forEach(function (name) {
        var badge = document.createElement('span');
        badge.className = 'events-block__badge';
        badge.textContent = name;
        badgeWrap.appendChild(badge);
      });
      meta.appendChild(badgeWrap);
      hasMeta = true;
    }
    if (hasMeta) {
      header.appendChild(meta);
    }
    state.content.appendChild(header);

    state.dialog.setAttribute('aria-labelledby', title.id);

    var body = document.createElement('div');
    body.className = 'events-modal__body';
    if (detail.description) {
      var description = document.createElement('div');
      description.className = 'events-modal__description';
      description.innerHTML = sanitizeEventHtml(detail.description);
      body.appendChild(description);
    }

    var tickets = detail.tickets || [];
    var ticketEntries = [];
    if (tickets.length) {
      var ticketsContainer = document.createElement('div');
      ticketsContainer.className = 'events-modal__tickets';
      tickets.forEach(function (ticket, index) {
        if (!ticket) {
          return;
        }
        var ticketNode = document.createElement('div');
        ticketNode.className = 'events-modal__ticket';

        var info = document.createElement('div');
        var heading = document.createElement('h4');
        heading.textContent = ticket.name || 'Ticket ' + (index + 1);
        info.appendChild(heading);
        var price = document.createElement('div');
        price.className = 'events-modal__ticket-price';
        var priceValue = Number(ticket.price);
        price.textContent = Number.isFinite(priceValue) && priceValue > 0 ? formatCurrency(priceValue) : 'Free';
        info.appendChild(price);
        if (Number.isFinite(Number(ticket.quantity)) && Number(ticket.quantity) > 0) {
          var availability = document.createElement('div');
          availability.className = 'events-modal__ticket-availability';
          availability.textContent = 'Available: ' + Number(ticket.quantity);
          info.appendChild(availability);
        }
        ticketNode.appendChild(info);

        var quantityWrapper = document.createElement('label');
        var quantityId = titleId + '-qty-' + (ticket.id || index);
        quantityWrapper.setAttribute('for', quantityId);
        quantityWrapper.textContent = 'Quantity';
        var quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.min = '0';
        quantityInput.step = '1';
        quantityInput.id = quantityId;
        quantityInput.value = '0';
        if (Number.isFinite(Number(ticket.quantity)) && Number(ticket.quantity) > 0) {
          quantityInput.max = String(Number(ticket.quantity));
        }
        quantityInput.setAttribute('aria-label', 'Quantity for ' + (ticket.name || 'ticket'));
        quantityWrapper.appendChild(quantityInput);
        ticketNode.appendChild(quantityWrapper);

        ticketsContainer.appendChild(ticketNode);
        ticketEntries.push({
          input: quantityInput,
          ticket: ticket,
          ticketId: ticket.id || String(index)
        });
      });
      body.appendChild(ticketsContainer);
    } else {
      var noTickets = document.createElement('p');
      noTickets.className = 'events-modal__ticket-availability';
      noTickets.textContent = 'Tickets are not currently available for this event.';
      body.appendChild(noTickets);
    }

    state.content.appendChild(body);

    var notice = document.createElement('div');
    notice.className = 'events-modal__notice';
    notice.hidden = true;
    notice.setAttribute('role', 'status');
    state.content.appendChild(notice);
    state.notice = notice;

    var footer = document.createElement('div');
    footer.className = 'events-modal__footer';
    if (tickets.length) {
      var addButton = document.createElement('button');
      addButton.type = 'button';
      addButton.className = 'events-modal__primary';
      addButton.textContent = 'Add to cart';
      addButton.addEventListener('click', function () {
        var selections = ticketEntries
          .map(function (entry) {
            var quantity = parseInt(entry.input.value, 10);
            return {
              ticket: entry.ticket,
              ticketId: entry.ticketId,
              quantity: quantity
            };
          })
          .filter(function (entry) {
            return Number.isFinite(entry.quantity) && entry.quantity > 0;
          });
        if (!selections.length) {
          setEventsModalNotice(state, 'Please choose at least one ticket to add to your cart.', 'error');
          return;
        }
        var added = addTicketsToCart(detail, selections);
        if (!added) {
          setEventsModalNotice(state, 'Unable to add tickets to your cart. Please try a smaller quantity.', 'error');
          return;
        }
        ticketEntries.forEach(function (entry) {
          entry.input.value = '0';
        });
        updateEventsCartIndicators();
        setEventsModalNotice(state, 'Tickets added to your cart.', 'success');
      });
      footer.appendChild(addButton);
    }

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'events-modal__secondary';
    closeButton.textContent = 'Close';
    closeButton.addEventListener('click', function () {
      closeEventsModal();
    });
    footer.appendChild(closeButton);

    state.content.appendChild(footer);
    state.currentMode = 'details';
  }

  function openEventDetailsModal(container, eventId, trigger) {
    var state = getEventsModalState(container);
    if (!state) {
      return;
    }
    var detail = getEventDetailById(eventId);
    if (!detail) {
      return;
    }
    renderEventDetailsModal(state, detail);
    setEventsModalNotice(state, '', null);
    openEventsModal(state, trigger || null);
  }

  function renderCartModal(state) {
    if (!state || !(state.content instanceof HTMLElement)) {
      return;
    }
    state.content.innerHTML = '';
    var titleId = state.titleId || 'events-modal-title';
    var header = document.createElement('header');
    header.className = 'events-modal__header';
    var title = document.createElement('h3');
    title.className = 'events-modal__title';
    title.id = titleId;
    title.textContent = 'Your event cart';
    header.appendChild(title);
    state.content.appendChild(header);
    state.dialog.setAttribute('aria-labelledby', title.id);

    var body = document.createElement('div');
    body.className = 'events-modal__body events-modal__cart-summary';
    var items = getCartItems();
    if (!items.length) {
      var empty = document.createElement('div');
      empty.className = 'events-modal__empty-cart';
      empty.textContent = 'Your cart is empty. Add tickets from an event to get started.';
      body.appendChild(empty);
    } else {
      var list = document.createElement('div');
      list.className = 'events-modal__cart-list';
      items.forEach(function (item) {
        var entry = document.createElement('div');
        entry.className = 'events-modal__cart-item';
        var info = document.createElement('div');
        var heading = document.createElement('h4');
        heading.textContent = item.eventTitle || 'Event';
        info.appendChild(heading);
        var meta = document.createElement('div');
        meta.className = 'events-modal__cart-item-meta';
        if (item.eventStart) {
          var startDate = new Date(item.eventStart);
          var endDate = item.eventEnd ? new Date(item.eventEnd) : null;
          if (!Number.isNaN(startDate.getTime())) {
            var time = document.createElement('time');
            time.dateTime = startDate.toISOString();
            time.textContent = formatEventRange(startDate, endDate instanceof Date && !Number.isNaN(endDate.getTime()) ? endDate : null);
            meta.appendChild(time);
          }
        }
        var ticketLine = document.createElement('span');
        ticketLine.textContent = (item.ticketName || 'Ticket') + ' × ' + item.quantity;
        meta.appendChild(ticketLine);
        var priceLine = document.createElement('span');
        priceLine.textContent = formatCurrency(Number(item.price) * Number(item.quantity)) || '$0.00';
        meta.appendChild(priceLine);
        info.appendChild(meta);
        entry.appendChild(info);

        var removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'events-modal__cart-remove';
        removeButton.textContent = 'Remove';
        removeButton.addEventListener('click', function () {
          removeCartItem(item.eventId, item.ticketId);
          renderCartModal(state);
        });
        entry.appendChild(removeButton);
        list.appendChild(entry);
      });
      body.appendChild(list);

      var total = document.createElement('div');
      total.className = 'events-modal__cart-total';
      total.textContent = 'Total: ' + (formatCurrency(getCartTotal()) || '$0.00');
      body.appendChild(total);
    }

    state.content.appendChild(body);

    var notice = document.createElement('div');
    notice.className = 'events-modal__notice';
    notice.hidden = true;
    notice.setAttribute('role', 'status');
    state.content.appendChild(notice);
    state.notice = notice;

    var footer = document.createElement('div');
    footer.className = 'events-modal__footer';
    if (items.length) {
      var clearButton = document.createElement('button');
      clearButton.type = 'button';
      clearButton.className = 'events-modal__secondary';
      clearButton.textContent = 'Clear cart';
      clearButton.addEventListener('click', function () {
        clearCart();
        renderCartModal(state);
      });
      footer.appendChild(clearButton);

      var checkoutButton = document.createElement('button');
      checkoutButton.type = 'button';
      checkoutButton.className = 'events-modal__primary';
      checkoutButton.textContent = 'Proceed to checkout';
      checkoutButton.addEventListener('click', function () {
        setEventsModalNotice(state, 'Checkout is not available in this demo experience. Please contact our team to complete your registration.', 'error');
      });
      footer.appendChild(checkoutButton);
    } else {
      var closeButton = document.createElement('button');
      closeButton.type = 'button';
      closeButton.className = 'events-modal__primary';
      closeButton.textContent = 'Browse events';
      closeButton.addEventListener('click', function () {
        closeEventsModal();
      });
      footer.appendChild(closeButton);
    }

    state.content.appendChild(footer);
    state.currentMode = 'cart';
  }

  function openEventsCartModal(container, trigger) {
    var state = getEventsModalState(container);
    if (!state) {
      return;
    }
    renderCartModal(state);
    setEventsModalNotice(state, '', null);
    updateCartButtonsExpanded(true);
    openEventsModal(state, trigger || null);
  }

  function createEventsItem(event, options) {
    var item = document.createElement('article');
    item.className = 'events-block__item';
    rememberEventDetail(event);
    var eventId = event && event.raw && event.raw.id ? String(event.raw.id) : '';
    if (eventId) {
      item.setAttribute('data-events-id', eventId);
    }
    if (event.id) {
      item.setAttribute('data-events-id', String(event.id));
    }

    if (event.startDate instanceof Date && !Number.isNaN(event.startDate.getTime())) {
      var dateWrap = document.createElement('div');
      dateWrap.className = 'events-block__date';
      var monthSpan = document.createElement('span');
      monthSpan.className = 'events-block__date-month';
      monthSpan.textContent = formatEventMonth(event.startDate);
      dateWrap.appendChild(monthSpan);
      var daySpan = document.createElement('span');
      daySpan.className = 'events-block__date-day';
      daySpan.textContent = formatEventDay(event.startDate);
      dateWrap.appendChild(daySpan);
      item.appendChild(dateWrap);
    }

    var body = document.createElement('div');
    body.className = 'events-block__body';
    var title = document.createElement('h3');
    title.className = 'events-block__title';
    var linkUrl = resolveEventDetailUrl(options.detailBase, event.raw);
    if (linkUrl) {
      var link = document.createElement('a');
      link.className = 'events-block__title-link';
      link.href = linkUrl;
      link.textContent = event.raw.title || 'Untitled Event';
      title.appendChild(link);
    } else {
      title.textContent = event.raw.title || 'Untitled Event';
    }
    body.appendChild(title);

    var meta = document.createElement('div');
    meta.className = 'events-block__meta';

    if (event.startDate) {
      var metaLine = document.createElement('div');
      metaLine.className = 'events-block__meta-line';
      var timeEl = document.createElement('time');
      timeEl.className = 'events-block__time';
      timeEl.dateTime = event.startDate.toISOString();
      timeEl.textContent = formatEventRange(event.startDate, event.endDate);
      metaLine.appendChild(timeEl);
      meta.appendChild(metaLine);
    }

    if (options.showLocation && event.raw.location) {
      var locationLine = document.createElement('div');
      locationLine.className = 'events-block__meta-line';
      var locationSpan = document.createElement('span');
      locationSpan.className = 'events-block__location';
      locationSpan.textContent = event.raw.location;
      locationLine.appendChild(locationSpan);
      meta.appendChild(locationLine);
    }

    if (options.showCategories && event.categoryNames.length) {
      var categoriesLine = document.createElement('div');
      categoriesLine.className = 'events-block__meta-line events-block__meta-line--badges';
      event.categoryNames.forEach(function (name) {
        var badge = document.createElement('span');
        badge.className = 'events-block__badge';
        badge.textContent = name;
        categoriesLine.appendChild(badge);
      });
      meta.appendChild(categoriesLine);
    }

    if (options.showPrice && Number.isFinite(event.lowestPrice)) {
      var priceLine = document.createElement('div');
      priceLine.className = 'events-block__meta-line';
      var priceSpan = document.createElement('span');
      priceSpan.className = 'events-block__price';
      priceSpan.textContent = 'From ' + formatCurrency(event.lowestPrice);
      priceLine.appendChild(priceSpan);
      meta.appendChild(priceLine);
    }

    if (meta.childNodes.length) {
      body.appendChild(meta);
    }

    if (options.showDescription && event.description) {
      var description = document.createElement('p');
      description.className = 'events-block__description';
      description.textContent = event.description;
      body.appendChild(description);
    }

    var actions = document.createElement('div');
    actions.className = 'events-block__actions';

    if (eventId && options.container) {
      var registerButton = document.createElement('button');
      registerButton.type = 'button';
      registerButton.className = 'events-block__cta events-block__cta--register';
      registerButton.textContent = 'Details & Register';
      registerButton.addEventListener('click', function (evt) {
        evt.preventDefault();
        evt.stopPropagation();
        openEventDetailsModal(options.container, eventId, registerButton);
      });
      actions.appendChild(registerButton);
    }

    if (options.showButton && linkUrl) {
      var cta = document.createElement('a');
      cta.className = 'events-block__cta';
      cta.href = linkUrl;
      cta.textContent = options.buttonLabel || 'View event';
      actions.appendChild(cta);
    }

    if (actions.childNodes.length) {
      body.appendChild(actions);
    }

    item.appendChild(body);
    return item;
  }

  function renderEventsBlock(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    var itemsHost = container.querySelector('[data-events-items]');
    if (!itemsHost) {
      return;
    }

    updateEventsCartIndicators();

    if (!container.dataset.eventsControlsBound) {
      container.dataset.eventsControlsBound = 'true';
      var cartButton = container.querySelector('[data-events-cart-open]');
      if (cartButton) {
        cartButton.addEventListener('click', function (event) {
          event.preventDefault();
          openEventsCartModal(container, cartButton);
        });
      }
    }

    itemsHost.innerHTML = '';
    hideEventsEmpty(container);

    var loading = document.createElement('article');
    loading.className = 'events-block__item events-block__item--placeholder';
    var loadingBody = document.createElement('div');
    loadingBody.className = 'events-block__body';
    var loadingTitle = document.createElement('h3');
    loadingTitle.className = 'events-block__title';
    loadingTitle.textContent = 'Loading events…';
    loadingBody.appendChild(loadingTitle);
    loading.appendChild(loadingBody);
    itemsHost.appendChild(loading);

    var layout = normalizeEventsLayout(container.dataset.eventsLayout);
    container.dataset.eventsLayout = layout;
    container.classList.remove('events-block--layout-cards', 'events-block--layout-list', 'events-block--layout-compact');
    container.classList.add('events-block--layout-' + layout);

    var limit = parsePositiveInt(container.dataset.eventsLimit, 3);
    var categoryFilter = normalizeCategory(container.dataset.eventsCategory);
    var emptyMessage = container.dataset.eventsEmpty || 'No upcoming events found.';
    var descriptionLength = parsePositiveInt(container.dataset.eventsDescriptionLength, 160);
    var options = {
      detailBase: container.dataset.eventsDetailBase || '',
      buttonLabel: container.dataset.eventsButtonLabel || 'View event',
      showButton: parseBooleanOption(container.dataset.eventsShowButton, !!container.dataset.eventsDetailBase),
      showDescription: parseBooleanOption(container.dataset.eventsShowDescription, true),
      showLocation: parseBooleanOption(container.dataset.eventsShowLocation, true),
      showCategories: parseBooleanOption(container.dataset.eventsShowCategories, true),
      showPrice: parseBooleanOption(container.dataset.eventsShowPrice, true),
      container: container
    };

    Promise.all([fetchEvents(), fetchEventCategories()])
      .then(function (results) {
        var records = Array.isArray(results[0]) ? results[0] : [];
        var categoriesIndex = results[1] && typeof results[1] === 'object' ? results[1] : {};
        var now = new Date();
        var enriched = records
          .map(function (record) {
            var startDate = parseIsoDate(record.start);
            var endDate = parseIsoDate(record.end);
            var inFuture = false;
            if (startDate && !Number.isNaN(startDate.getTime())) {
              inFuture = startDate.getTime() >= now.getTime();
            }
            if (!inFuture && endDate && !Number.isNaN(endDate.getTime())) {
              inFuture = endDate.getTime() >= now.getTime();
            }
            var categoryNames = [];
            var ids = Array.isArray(record.categories) ? record.categories : [];
            ids.forEach(function (id) {
              var info = categoriesIndex[id];
              if (info && info.name) {
                categoryNames.push(info.name);
              } else if (id) {
                categoryNames.push(String(id));
              }
            });
            return {
              raw: record,
              id: record.id,
              startDate: startDate,
              endDate: endDate,
              upcoming: inFuture,
              description: truncateText(stripHtml(record.description), descriptionLength),
              categoryNames: categoryNames,
              lowestPrice: findLowestPrice(record.tickets)
            };
          })
          .filter(function (event) {
            if (!event.startDate) {
              return false;
            }
            if (!event.upcoming) {
              return false;
            }
            if (!categoryFilter) {
              return true;
            }
            return eventMatchesCategoryFilter(event.raw, categoryFilter, categoriesIndex);
          });

        enriched.sort(function (a, b) {
          var aTime = a.startDate instanceof Date ? a.startDate.getTime() : 0;
          var bTime = b.startDate instanceof Date ? b.startDate.getTime() : 0;
          return aTime - bTime;
        });

        if (limit && enriched.length > limit) {
          enriched = enriched.slice(0, limit);
        }

        itemsHost.innerHTML = '';

        if (!enriched.length) {
          showEventsEmpty(container, emptyMessage);
          return;
        }

        hideEventsEmpty(container);
        enriched.forEach(function (event) {
          var node = createEventsItem(event, options);
          itemsHost.appendChild(node);
        });
        updateEventsCartIndicators();
      })
      .catch(function () {
        itemsHost.innerHTML = '';
        showEventsEmpty(container, 'Unable to load events right now.');
      });
  }

  function initEventsBlocks() {
    var blocks = document.querySelectorAll('[data-events-block]');
    blocks.forEach(function (block) {
      renderEventsBlock(block);
    });
    updateEventsCartIndicators();
  }

  var calendarEventsPromise = null;

  function fetchCalendarEvents() {
    if (calendarEventsPromise) {
      return calendarEventsPromise;
    }
    var base = normalizeBasePath();
    var url = base + '/CMS/data/calendar_events.json';
    calendarEventsPromise = fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Failed to load calendar events');
        }
        return response.json();
      })
      .then(function (events) {
        if (!Array.isArray(events)) {
          return [];
        }
        return events.filter(function (event) {
          return event && typeof event === 'object';
        });
      })
      .catch(function (error) {
        console.error('[SparkCMS] Calendar load error:', error);
        calendarEventsPromise = null;
        throw error;
      });
    return calendarEventsPromise;
  }

  function parsePositiveInt(value, fallback) {
    var parsed = parseInt(value, 10);
    if (!Number.isFinite(parsed) || parsed < 1) {
      return fallback;
    }
    return parsed;
  }

  function parseIsoDate(value) {
    if (!value) {
      return null;
    }
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return date;
  }

  function addRecurringInterval(date, recurrence) {
    if (!(date instanceof Date)) {
      return null;
    }
    var next;
    switch (recurrence) {
      case 'daily':
        next = new Date(date.getTime());
        next.setDate(next.getDate() + 1);
        return next;
      case 'weekly':
        next = new Date(date.getTime());
        next.setDate(next.getDate() + 7);
        return next;
      case 'monthly':
        next = new Date(date.getTime());
        var day = next.getDate();
        next.setDate(1);
        next.setMonth(next.getMonth() + 1);
        var daysInMonth = new Date(next.getFullYear(), next.getMonth() + 1, 0).getDate();
        next.setDate(Math.min(day, daysInMonth));
        return next;
      case 'yearly':
        next = new Date(date.getTime());
        var month = next.getMonth();
        var dayOfMonth = next.getDate();
        next.setDate(1);
        next.setFullYear(next.getFullYear() + 1);
        next.setMonth(month);
        var maxDay = new Date(next.getFullYear(), month + 1, 0).getDate();
        next.setDate(Math.min(dayOfMonth, maxDay));
        return next;
      default:
        return null;
    }
  }

  function expandEventOccurrences(event, options) {
    var occurrences = [];
    if (!event.start) {
      return occurrences;
    }
    var duration = event.end ? Math.max(event.end.getTime() - event.start.getTime(), 0) : 0;
    var start = new Date(event.start.getTime());
    var recurrence = event.recurrence;
    var recurrenceEnd = event.recurrenceEnd;
    var perEventLimit = options.perEventLimit || 12;
    var maxIterations = options.maxIterations || 120;
    var windowStart = options.windowStart;
    var windowEnd = options.windowEnd;
    var sequence = 0;
    var iterations = 0;
    while (iterations < maxIterations) {
      if (recurrenceEnd && start > recurrenceEnd) {
        break;
      }
      var include = !windowStart || start >= windowStart;
      if (include && (!windowEnd || start <= windowEnd)) {
        var end = duration ? new Date(start.getTime() + duration) : null;
        occurrences.push({
          id: event.id,
          title: event.title,
          category: event.category,
          description: event.description,
          start: new Date(start.getTime()),
          end: end,
          recurrence: recurrence,
          occurrenceIndex: sequence
        });
        if (occurrences.length >= perEventLimit) {
          break;
        }
      }
      if (recurrence === 'none') {
        break;
      }
      var nextStart = addRecurringInterval(start, recurrence);
      if (!nextStart || nextStart.getTime() === start.getTime()) {
        break;
      }
      start = nextStart;
      sequence += 1;
      iterations += 1;
    }
    return occurrences;
  }

  function buildCalendarOccurrences(records, options) {
    var now = options.now || new Date();
    var windowStart = options.windowStart || now;
    var windowEnd = options.windowEnd || null;
    var perEventLimit = options.perEventLimit || 24;
    var maxIterations = options.maxIterations || 120;
    var normalizedCategory = options.category || '';
    var limit = options.limit || 6;
    var aggregated = [];
    records.forEach(function (raw) {
      if (!raw || typeof raw !== 'object') {
        return;
      }
      var start = parseIsoDate(raw.start_date);
      if (!start) {
        return;
      }
      var event = {
        id: raw.id,
        title: raw.title || 'Untitled Event',
        category: raw.category || '',
        description: raw.description || '',
        start: start,
        end: parseIsoDate(raw.end_date),
        recurrence: (raw.recurring_interval || 'none').toLowerCase(),
        recurrenceEnd: parseIsoDate(raw.recurring_end_date)
      };
      if (['daily', 'weekly', 'monthly', 'yearly'].indexOf(event.recurrence) === -1) {
        event.recurrence = 'none';
      }
      var occurrences = expandEventOccurrences(event, {
        windowStart: windowStart,
        windowEnd: windowEnd,
        perEventLimit: perEventLimit,
        maxIterations: maxIterations
      });
      occurrences.forEach(function (occurrence) {
        if (normalizedCategory && normalizeCategory(occurrence.category) !== normalizedCategory) {
          return;
        }
        aggregated.push(occurrence);
      });
    });
    aggregated.sort(function (a, b) {
      return a.start.getTime() - b.start.getTime();
    });
    if (limit && aggregated.length > limit) {
      aggregated = aggregated.slice(0, limit);
    }
    return aggregated;
  }

  var MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var WEEKDAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function formatMonthLabel(date) {
    if (!(date instanceof Date)) {
      return '--';
    }
    try {
      return date.toLocaleDateString(undefined, { month: 'short' });
    } catch (err) {
      return MONTH_NAMES[date.getMonth()] || '';
    }
  }

  function formatDayLabel(date) {
    if (!(date instanceof Date)) {
      return '--';
    }
    var day = date.getDate();
    if (!Number.isFinite(day)) {
      return '--';
    }
    return day < 10 ? '0' + day : String(day);
  }

  function formatWeekdayLabel(date) {
    if (!(date instanceof Date)) {
      return '';
    }
    try {
      return date.toLocaleDateString(undefined, { weekday: 'short' });
    } catch (err) {
      return WEEKDAY_NAMES[date.getDay()] || '';
    }
  }

  function formatTimeRangeDisplay(start, end) {
    if (!(start instanceof Date)) {
      return '';
    }
    var timeFormatter;
    try {
      timeFormatter = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });
    } catch (err) {
      timeFormatter = null;
    }
    var dateFormatter;
    try {
      dateFormatter = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' });
    } catch (err) {
      dateFormatter = null;
    }
    var startTime = timeFormatter ? timeFormatter.format(start) : start.toISOString().slice(11, 16);
    if (!(end instanceof Date) || end.getTime() <= start.getTime()) {
      return startTime;
    }
    var sameDay = start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth() && start.getDate() === end.getDate();
    var endTime = timeFormatter ? timeFormatter.format(end) : end.toISOString().slice(11, 16);
    if (sameDay) {
      return startTime + ' – ' + endTime;
    }
    var startLabel = dateFormatter ? dateFormatter.format(start) : start.toISOString().slice(0, 10);
    var endLabel = dateFormatter ? dateFormatter.format(end) : end.toISOString().slice(0, 10);
    return startLabel + ' ' + startTime + ' – ' + endLabel + ' ' + endTime;
  }

  function normalizeCalendarLayout(value) {
    var layout = (value || '').toString().toLowerCase();
    if (layout !== 'cards' && layout !== 'compact') {
      layout = 'list';
    }
    return layout;
  }

  function showCalendarEmpty(container, message) {
    var empty = container.querySelector('[data-calendar-empty]');
    if (!empty) {
      empty = document.createElement('div');
      empty.className = 'calendar-block__empty';
      empty.setAttribute('data-calendar-empty', '');
      container.appendChild(empty);
    }
    empty.textContent = message || '';
    empty.classList.remove('d-none');
  }

  function hideCalendarEmpty(container) {
    var empty = container.querySelector('[data-calendar-empty]');
    if (empty) {
      empty.classList.add('d-none');
    }
  }

  function createCalendarItem(occurrence, settings) {
    var item = document.createElement('article');
    item.className = 'calendar-block__item';
    item.setAttribute('data-calendar-event-id', String(occurrence.id));
    item.setAttribute('data-calendar-occurrence', String(occurrence.occurrenceIndex));

    var dateWrap = document.createElement('div');
    dateWrap.className = 'calendar-block__date';
    var monthSpan = document.createElement('span');
    monthSpan.className = 'calendar-block__date-month';
    monthSpan.textContent = formatMonthLabel(occurrence.start);
    dateWrap.appendChild(monthSpan);
    var daySpan = document.createElement('span');
    daySpan.className = 'calendar-block__date-day';
    daySpan.textContent = formatDayLabel(occurrence.start);
    dateWrap.appendChild(daySpan);
    item.appendChild(dateWrap);

    var body = document.createElement('div');
    body.className = 'calendar-block__body';

    var title = document.createElement('h3');
    title.className = 'calendar-block__title';
    title.textContent = occurrence.title || 'Untitled Event';
    body.appendChild(title);

    var meta = document.createElement('div');
    meta.className = 'calendar-block__meta';

    var metaLine = document.createElement('div');
    metaLine.className = 'calendar-block__meta-line';
    var parts = [];
    var weekday = formatWeekdayLabel(occurrence.start);
    if (weekday) {
      parts.push(weekday);
    }
    var timeRange = formatTimeRangeDisplay(occurrence.start, occurrence.end);
    if (timeRange) {
      parts.push(timeRange);
    }
    if (parts.length) {
      var timeEl = document.createElement('time');
      timeEl.className = 'calendar-block__time';
      timeEl.dateTime = occurrence.start.toISOString();
      timeEl.textContent = parts.join(' • ');
      metaLine.appendChild(timeEl);
      meta.appendChild(metaLine);
    }

    if (settings.showCategory && occurrence.category) {
      var badge = document.createElement('span');
      badge.className = 'calendar-block__category';
      badge.textContent = occurrence.category;
      meta.appendChild(badge);
    }

    if (meta.childNodes.length) {
      body.appendChild(meta);
    }

    if (settings.showDescription && occurrence.description) {
      var description = document.createElement('p');
      description.className = 'calendar-block__description';
      description.textContent = occurrence.description;
      body.appendChild(description);
    }

    item.appendChild(body);
    return item;
  }

  function renderCalendarBlock(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    var itemsHost = container.querySelector('[data-calendar-items]');
    if (!itemsHost) {
      return;
    }
    itemsHost.innerHTML = '';
    hideCalendarEmpty(container);
    var loading = document.createElement('article');
    loading.className = 'calendar-block__item calendar-block__item--placeholder';
    var loadingBody = document.createElement('div');
    loadingBody.className = 'calendar-block__body';
    var loadingTitle = document.createElement('h3');
    loadingTitle.className = 'calendar-block__title';
    loadingTitle.textContent = 'Loading events…';
    loadingBody.appendChild(loadingTitle);
    loading.appendChild(loadingBody);
    itemsHost.appendChild(loading);

    var layout = normalizeCalendarLayout(container.dataset.calendarLayout);
    container.dataset.calendarLayout = layout;
    container.classList.remove('calendar-block--layout-list', 'calendar-block--layout-cards', 'calendar-block--layout-compact');
    container.classList.add('calendar-block--layout-' + layout);

    var limit = parsePositiveInt(container.dataset.calendarLimit, 6);
    var category = normalizeCategory(container.dataset.calendarCategory);
    var emptyMessage = container.dataset.calendarEmptyMessage || 'No upcoming events found.';
    var showDescription = String(container.dataset.calendarDescription || '').toLowerCase();
    var showCategory = String(container.dataset.calendarShowCategory || '').toLowerCase();
    var showDescriptionFlag = showDescription !== 'no' && showDescription !== 'false' && showDescription !== 'hide';
    var showCategoryFlag = showCategory !== 'no' && showCategory !== 'false' && showCategory !== 'hide';

    fetchCalendarEvents()
      .then(function (records) {
        var now = new Date();
        var occurrences = buildCalendarOccurrences(records, {
          now: now,
          windowStart: now,
          windowEnd: null,
          limit: limit,
          category: category,
          perEventLimit: Math.max(limit * 3, 18),
          maxIterations: Math.max(limit * 6, 120)
        });
        itemsHost.innerHTML = '';
        if (!occurrences.length) {
          showCalendarEmpty(container, emptyMessage);
          return;
        }
        hideCalendarEmpty(container);
        occurrences.forEach(function (occurrence) {
          var node = createCalendarItem(occurrence, {
            showDescription: showDescriptionFlag,
            showCategory: showCategoryFlag
          });
          itemsHost.appendChild(node);
        });
      })
      .catch(function () {
        itemsHost.innerHTML = '';
        showCalendarEmpty(container, 'Unable to load events right now.');
      });
  }

  function initCalendarBlocks() {
    var blocks = document.querySelectorAll('[data-calendar-block]');
    blocks.forEach(function (block) {
      renderCalendarBlock(block);
    });
  }

  function initBlogLists() {
    var lists = document.querySelectorAll('[data-blog-list]');
    lists.forEach(function (container) {
      hydrate(container);
    });
  }

  function initBlogDetails() {
    var details = document.querySelectorAll('[data-blog-detail]');
    details.forEach(function (container) {
      renderBlogDetail(container);
    });
  }

  function observe() {
    if (typeof MutationObserver === 'undefined') {
      return;
    }
    var observer = new MutationObserver(function (mutations) {
      var shouldRefreshBlogs = false;
      var shouldRefreshBlogDetails = false;
      var shouldRefreshCalendars = false;
      var shouldRefreshEvents = false;
      mutations.forEach(function (mutation) {
        if (mutation.type !== 'childList') {
          return;
        }
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof HTMLElement)) {
            return;
          }
          if (node.matches('[data-blog-list]') || node.querySelector('[data-blog-list]')) {
            shouldRefreshBlogs = true;
          }
          if (node.matches('[data-blog-detail]') || node.querySelector('[data-blog-detail]')) {
            shouldRefreshBlogDetails = true;
          }
          if (node.matches('[data-calendar-block]') || node.querySelector('[data-calendar-block]')) {
            shouldRefreshCalendars = true;
          }
          if (node.matches('[data-events-block]') || node.querySelector('[data-events-block]')) {
            shouldRefreshEvents = true;
          }
        });
      });
      if (shouldRefreshBlogDetails) {
        initBlogDetails();
      }
      if (shouldRefreshBlogs) {
        initBlogLists();
      }
      if (shouldRefreshCalendars) {
        initCalendarBlocks();
      }
      if (shouldRefreshEvents) {
        initEventsBlocks();
      }
    });
    observer.observe(document.body || document.documentElement, {
      childList: true,
      subtree: true
    });
  }

  ready(function () {
    initBlogDetails();
    initBlogLists();
    initEventsBlocks();
    updateEventsCartIndicators();
    initCalendarBlocks();
    observe();
  });

  window.SparkCMSBlogLists = {
    refresh: initBlogLists
  };

  window.SparkCMSBlogDetails = {
    refresh: initBlogDetails
  };

  window.SparkCMSEvents = {
    refresh: initEventsBlocks
  };

  window.SparkCMSCalendars = {
    refresh: initCalendarBlocks
  };
})();
/* File: script.js */
// File: script.js
(function () {
  var formCache = {};
  var formRequests = {};

  function basePath() {
    var base = typeof window.cmsBase === 'string' ? window.cmsBase : '';
    base = base.trim();
    if (!base) return '';
    if (base.charAt(0) !== '/') {
      base = '/' + base;
    }
    return base.replace(/\/$/, '');
  }

  function fetchFormDefinition(id) {
    var key = String(id);
    if (formCache[key]) return Promise.resolve(formCache[key]);
    if (formRequests[key]) return formRequests[key];
    var prefix = basePath();
    var url = (prefix || '') + '/forms/get.php?id=' + encodeURIComponent(id);
    formRequests[key] = fetch(url, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) throw new Error('Failed to load form');
        return response.json();
      })
      .then(function (data) {
        formCache[key] = data;
        return data;
      })
      .catch(function (err) {
        delete formRequests[key];
        throw err;
      });
    return formRequests[key];
  }

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }
    return value.replace(/([\.\#\[\]:,])/g, '\\$1');
  }

  function prepareContainer(container) {
    if (!container) return;
    if (!container.dataset.successMessage) {
      var successTemplate = container.querySelector('template[data-success-template]');
      if (successTemplate) {
        container.dataset.successMessage = successTemplate.textContent.trim();
        successTemplate.remove();
      } else {
        container.dataset.successMessage = 'Thank you!';
      }
    }
    if (!container.dataset.placeholderMessage) {
      var placeholderEl = container.querySelector('.spark-form-placeholder');
      if (placeholderEl) {
        container.dataset.placeholderMessage = placeholderEl.textContent.trim();
      } else if (container.dataset.placeholder) {
        container.dataset.placeholderMessage = container.dataset.placeholder;
      } else {
        container.dataset.placeholderMessage = 'Select a form to display.';
      }
    }
  }

  function showPlaceholder(container, message) {
    prepareContainer(container);
    container.innerHTML = '';
    var placeholder = document.createElement('div');
    placeholder.className = 'spark-form-placeholder text-muted';
    placeholder.textContent = message || container.dataset.placeholderMessage || '';
    container.appendChild(placeholder);
    container.removeAttribute('data-rendered-form-id');
  }

  function showLoading(container) {
    prepareContainer(container);
    container.innerHTML = '';
    var loading = document.createElement('div');
    loading.className = 'spark-form-loading text-muted';
    loading.textContent = 'Loading form…';
    container.appendChild(loading);
  }

  function showError(container, message) {
    prepareContainer(container);
    container.innerHTML = '';
    var error = document.createElement('div');
    error.className = 'spark-form-error text-danger';
    error.textContent = message || 'This form is currently unavailable.';
    container.appendChild(error);
    container.removeAttribute('data-rendered-form-id');
  }

  function buildField(field, index) {
    var type = (field.type || 'text').toLowerCase();
    var name = field.name || ('field_' + index);
    var labelText = field.label || name;
    var required = !!field.required;
    var options = Array.isArray(field.options) ? field.options : [];
    var fieldId = 'spark-form-' + name + '-' + index;
    var wrapper = document.createElement('div');
    wrapper.className = 'mb-3 spark-form-field';
    wrapper.setAttribute('data-field-name', name);

    if (type === 'submit') {
      var submitBtn = document.createElement('button');
      submitBtn.type = 'submit';
      submitBtn.className = 'btn btn-primary';
      submitBtn.textContent = labelText || 'Submit';
      wrapper.classList.add('spark-form-actions');
      wrapper.appendChild(submitBtn);
      return wrapper;
    }

    function appendFeedback(target, blockDisplay) {
      var feedback = document.createElement('div');
      feedback.className = 'invalid-feedback' + (blockDisplay ? ' d-block' : '');
      target.appendChild(feedback);
      return feedback;
    }

    if (type === 'checkbox' && !options.length) {
      var checkboxWrap = document.createElement('div');
      checkboxWrap.className = 'form-check';
      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'form-check-input';
      checkbox.id = fieldId;
      checkbox.name = name;
      checkbox.value = '1';
      if (required) checkbox.required = true;
      var checkboxLabel = document.createElement('label');
      checkboxLabel.className = 'form-check-label';
      checkboxLabel.setAttribute('for', fieldId);
      checkboxLabel.textContent = labelText + (required ? ' *' : '');
      checkboxWrap.appendChild(checkbox);
      checkboxWrap.appendChild(checkboxLabel);
      wrapper.appendChild(checkboxWrap);
      appendFeedback(wrapper, true);
      return wrapper;
    }

    if ((type === 'checkbox' && options.length) || type === 'radio') {
      var groupLabel = document.createElement('span');
      groupLabel.className = 'form-label d-block';
      groupLabel.textContent = labelText + (required ? ' *' : '');
      wrapper.appendChild(groupLabel);
      var choices = document.createElement('div');
      choices.className = 'spark-choice-group';
      options.forEach(function (option, optionIndex) {
        var checkWrap = document.createElement('div');
        checkWrap.className = 'form-check';
        var input = document.createElement('input');
        input.type = type;
        input.className = 'form-check-input';
        var optionName = type === 'checkbox' ? name + '[]' : name;
        input.name = optionName;
        var optionId = fieldId + '-' + optionIndex;
        input.id = optionId;
        input.value = option;
        if (required) {
          if (type === 'radio') {
            input.required = true;
          } else if (type === 'checkbox' && optionIndex === 0) {
            input.required = true;
          }
        }
        var optLabel = document.createElement('label');
        optLabel.className = 'form-check-label';
        optLabel.setAttribute('for', optionId);
        optLabel.textContent = option;
        checkWrap.appendChild(input);
        checkWrap.appendChild(optLabel);
        choices.appendChild(checkWrap);
      });
      wrapper.appendChild(choices);
      appendFeedback(wrapper, true);
      return wrapper;
    }

    var label = document.createElement('label');
    label.className = 'form-label';
    label.setAttribute('for', fieldId);
    label.textContent = labelText + (required ? ' *' : '');
    wrapper.appendChild(label);

    if (type === 'textarea') {
      var textarea = document.createElement('textarea');
      textarea.className = 'form-control';
      textarea.id = fieldId;
      textarea.name = name;
      textarea.rows = 4;
      if (required) textarea.required = true;
      wrapper.appendChild(textarea);
      appendFeedback(wrapper);
      return wrapper;
    }

    if (type === 'select') {
      var select = document.createElement('select');
      select.className = 'form-select';
      select.id = fieldId;
      select.name = name;
      if (required) select.required = true;
      var placeholderOption = document.createElement('option');
      placeholderOption.value = '';
      placeholderOption.textContent = 'Please select';
      select.appendChild(placeholderOption);
      options.forEach(function (option) {
        var opt = document.createElement('option');
        opt.value = option;
        opt.textContent = option;
        select.appendChild(opt);
      });
      wrapper.appendChild(select);
      appendFeedback(wrapper);
      return wrapper;
    }

    var input = document.createElement('input');
    input.id = fieldId;
    input.name = name;
    if (required) input.required = true;
    switch (type) {
      case 'date':
        input.type = 'date';
        break;
      case 'number':
        input.type = 'number';
        break;
      case 'password':
        input.type = 'password';
        break;
      case 'file':
        input.type = 'file';
        break;
      case 'email':
        input.type = 'email';
        break;
      default:
        input.type = 'text';
        break;
    }
    input.className = input.type === 'file' ? 'form-control' : 'form-control';
    wrapper.appendChild(input);
    appendFeedback(wrapper);
    return wrapper;
  }

  function clearFieldErrors(formEl) {
    formEl.querySelectorAll('.spark-form-field').forEach(function (wrapper) {
      wrapper.classList.remove('has-error');
      wrapper.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
      });
      var feedback = wrapper.querySelector('.invalid-feedback');
      if (feedback) {
        feedback.textContent = '';
        feedback.style.display = '';
      }
    });
  }

  function applyFieldErrors(formEl, errors) {
    if (!Array.isArray(errors)) return;
    errors.forEach(function (error) {
      if (!error || !error.field) return;
      var selector = '[data-field-name="' + escapeSelector(error.field) + '"]';
      var wrapper = formEl.querySelector(selector);
      if (!wrapper) return;
      wrapper.classList.add('has-error');
      var inputs = wrapper.querySelectorAll('input, textarea, select');
      inputs.forEach(function (input) {
        input.classList.add('is-invalid');
      });
      var feedback = wrapper.querySelector('.invalid-feedback');
      if (feedback) {
        feedback.textContent = error.message || 'Please correct this field.';
        feedback.style.display = 'block';
      }
    });
  }

  function attachSubmitHandler(formEl, statusEl, successMessage) {
    if (!formEl) return;
    formEl.addEventListener('submit', function (event) {
      event.preventDefault();
      if (formEl.dataset.submitting === 'true') return;
      clearFieldErrors(formEl);
      if (statusEl) {
        statusEl.textContent = 'Submitting…';
        statusEl.classList.remove('text-success', 'text-danger');
      }
      formEl.dataset.submitting = 'true';
      var submitButtons = formEl.querySelectorAll('button[type="submit"], input[type="submit"]');
      submitButtons.forEach(function (btn) {
        btn.disabled = true;
      });
      var prefix = basePath();
      var url = (prefix || '') + '/forms/submit.php';
      var formData = new FormData(formEl);
      fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.data || result.data.success === false) {
            var errors = result.data && result.data.errors ? result.data.errors : null;
            applyFieldErrors(formEl, errors);
            if (statusEl) {
              statusEl.textContent = (result.data && result.data.message) || 'We were unable to submit the form.';
              statusEl.classList.add('text-danger');
            }
            return;
          }
          formEl.reset();
          if (statusEl) {
            statusEl.textContent = successMessage || 'Thank you!';
            statusEl.classList.add('text-success');
          }
        })
        .catch(function () {
          if (statusEl) {
            statusEl.textContent = 'We were unable to submit the form.';
            statusEl.classList.add('text-danger');
          }
        })
        .finally(function () {
          formEl.dataset.submitting = 'false';
          submitButtons.forEach(function (btn) {
            btn.disabled = false;
          });
        });
    });
  }

  function renderSparkForm(container, form) {
    prepareContainer(container);
    container.innerHTML = '';
    if (!form || !Array.isArray(form.fields) || !form.fields.length) {
      showError(container, 'This form has no fields yet.');
      return;
    }
    var formEl = document.createElement('form');
    formEl.className = 'spark-form needs-validation';
    formEl.setAttribute('novalidate', 'novalidate');
    formEl.setAttribute('enctype', 'multipart/form-data');
    formEl.dataset.formId = form.id;

    var hiddenId = document.createElement('input');
    hiddenId.type = 'hidden';
    hiddenId.name = 'form_id';
    hiddenId.value = form.id;
    formEl.appendChild(hiddenId);

    form.fields.forEach(function (field, index) {
      formEl.appendChild(buildField(field, index));
    });

    var status = document.createElement('div');
    status.className = 'spark-form-status mt-3';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');

    container.appendChild(formEl);
    container.appendChild(status);

    var successMessage = container.dataset.successMessage || 'Thank you!';
    attachSubmitHandler(formEl, status, successMessage);
  }

  function initializeSparkForms() {
    var containers = document.querySelectorAll('.spark-form-embed[data-form-id]');
    containers.forEach(function (container) {
      if (!container) return;
      prepareContainer(container);
      var rawId = container.getAttribute('data-form-id') || '';
      var formId = parseInt(rawId, 10);
      if (!formId) {
        showPlaceholder(container, container.dataset.placeholderMessage || 'Select a form to display.');
        return;
      }
      var renderedId = parseInt(container.getAttribute('data-rendered-form-id') || '0', 10);
      if (renderedId === formId && container.querySelector('form.spark-form')) {
        return;
      }
      showLoading(container);
      var token = Date.now().toString(36) + Math.random().toString(36).slice(2);
      container.setAttribute('data-render-token', token);
      fetchFormDefinition(formId)
        .then(function (form) {
          if (container.getAttribute('data-render-token') !== token) return;
          renderSparkForm(container, form);
          container.setAttribute('data-rendered-form-id', String(formId));
          container.removeAttribute('data-render-token');
        })
        .catch(function () {
          if (container.getAttribute('data-render-token') !== token) return;
          showError(container, 'We were unable to load this form.');
          container.removeAttribute('data-render-token');
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('main-nav');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        nav.classList.toggle('active');
      });
    }

    var accordions = document.querySelectorAll('.accordion');
    accordions.forEach(function (acc) {
      var btn = acc.querySelector('.accordion-button');
      var panel = acc.querySelector('.accordion-panel');
      if (!btn || !panel) return;

      if (acc.classList.contains('open')) {
        btn.setAttribute('aria-expanded', 'true');
        panel.style.display = 'block';
      } else {
        btn.setAttribute('aria-expanded', 'false');
        panel.style.display = 'none';
      }

      btn.addEventListener('click', function () {
        if (acc.classList.contains('open')) {
          acc.classList.remove('open');
          btn.setAttribute('aria-expanded', 'false');
          panel.style.display = 'none';
        } else {
          acc.classList.add('open');
          btn.setAttribute('aria-expanded', 'true');
          panel.style.display = 'block';
        }
      });
    });

    initializeSparkForms();
    document.addEventListener('canvasUpdated', initializeSparkForms);

    if (window.MutationObserver) {
      var observer = new MutationObserver(function (mutations) {
        var needsRefresh = false;
        mutations.forEach(function (mutation) {
          if (mutation.type === 'attributes' && mutation.attributeName === 'data-form-id') {
            var target = mutation.target;
            if (target && target.classList && target.classList.contains('spark-form-embed')) {
              needsRefresh = true;
            }
          }
          if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(function (node) {
              if (node.nodeType !== 1) return;
              if (node.classList && node.classList.contains('spark-form-embed')) {
                needsRefresh = true;
              } else if (node.querySelector && node.querySelector('.spark-form-embed')) {
                needsRefresh = true;
              }
            });
          }
        });
        if (needsRefresh) {
          initializeSparkForms();
        }
      });
      observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: [
          'data-form-id',
        ],
      });
    }
  });
})();

