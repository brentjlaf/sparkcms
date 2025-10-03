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

  var eventsPromise = null;
  var eventCategoriesPromise = null;
  var htmlParser = null;

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

  function parseBooleanOption(value, defaultValue) {
    if (value == null) {
      return defaultValue;
    }
    var normalized = String(value).toLowerCase().trim();
    if (['no', 'false', '0', 'off', 'hide'].indexOf(normalized) !== -1) {
      return false;
    }
    if (['yes', 'true', '1', 'show', 'on'].indexOf(normalized) !== -1) {
      return true;
    }
    return defaultValue;
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

  function createEventsItem(event, options) {
    var item = document.createElement('article');
    item.className = 'events-block__item';
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

    if (options.showButton && linkUrl) {
      var cta = document.createElement('a');
      cta.className = 'events-block__cta';
      cta.href = linkUrl;
      cta.textContent = options.buttonLabel || 'View event';
      body.appendChild(cta);
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
      showPrice: parseBooleanOption(container.dataset.eventsShowPrice, true)
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

  function observe() {
    if (typeof MutationObserver === 'undefined') {
      return;
    }
    var observer = new MutationObserver(function (mutations) {
      var shouldRefreshBlogs = false;
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
          if (node.matches('[data-calendar-block]') || node.querySelector('[data-calendar-block]')) {
            shouldRefreshCalendars = true;
          }
          if (node.matches('[data-events-block]') || node.querySelector('[data-events-block]')) {
            shouldRefreshEvents = true;
          }
        });
      });
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
    initBlogLists();
    initEventsBlocks();
    initCalendarBlocks();
    observe();
  });

  window.SparkCMSBlogLists = {
    refresh: initBlogLists
  };

  window.SparkCMSEvents = {
    refresh: initEventsBlocks
  };

  window.SparkCMSCalendars = {
    refresh: initCalendarBlocks
  };
})();
