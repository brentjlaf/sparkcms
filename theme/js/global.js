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

  function parseLimit(value) {
    var limit = parseInt(value, 10);
    if (!Number.isFinite(limit) || limit < 1) {
      return 6;
    }
    return limit;
  }

  function normalizeCategory(value) {
    return (value || '').toString().toLowerCase().trim();
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

  function parseBoolean(value) {
    return String(value || '').trim().toLowerCase() === 'true';
  }

  function parseAutoplayDelay(value) {
    var seconds = parseInt(value, 10);
    if (!Number.isFinite(seconds) || seconds <= 0) {
      return 0;
    }
    return seconds * 1000;
  }

  function normalizeTestimonialSlide(slide) {
    if (!(slide instanceof HTMLElement)) {
      return false;
    }
    var quote = slide.querySelector('[data-quote]');
    var author = slide.querySelector('[data-author]');
    var role = slide.querySelector('[data-role]');
    var rating = slide.querySelector('[data-rating]');
    var avatarWrap = slide.querySelector('[data-avatar]');
    var defaultQuote = slide.getAttribute('data-default-quote') || 'SparkCMS customer success story.';
    var defaultAuthor = slide.getAttribute('data-default-author') || 'Happy Customer';
    var defaultRole = slide.getAttribute('data-default-role') || '';
    var defaultRating = parseInt(slide.getAttribute('data-default-rating') || '', 10);
    if (quote) {
      var quoteText = quote.textContent || '';
      if (!quoteText.trim()) {
        quote.textContent = defaultQuote;
      }
    }
    if (author) {
      var authorText = author.textContent || '';
      if (!authorText.trim()) {
        author.textContent = defaultAuthor;
      }
    }
    if (role) {
      var roleText = role.textContent || '';
      if (!roleText.trim()) {
        role.textContent = defaultRole;
      }
    }
    var resolvedAuthor = author ? author.textContent.trim() : '';
    if (avatarWrap) {
      var avatarImage = avatarWrap.querySelector('img');
      var avatarSrc = avatarImage && avatarImage.getAttribute('src') ? avatarImage.getAttribute('src').trim() : '';
      var fallback = avatarWrap.querySelector('.testimonial-card__avatar-fallback');
      if (!fallback) {
        fallback = document.createElement('span');
        fallback.className = 'testimonial-card__avatar-fallback';
        avatarWrap.appendChild(fallback);
      }
      if (!avatarSrc) {
        if (avatarImage) {
          avatarImage.style.display = 'none';
        }
        var initials = resolvedAuthor ? resolvedAuthor.charAt(0).toUpperCase() : 'S';
        fallback.textContent = initials;
        fallback.style.display = 'inline-flex';
      } else {
        if (avatarImage) {
          avatarImage.style.display = '';
          if (resolvedAuthor && (!avatarImage.getAttribute('alt') || !avatarImage.getAttribute('alt').trim())) {
            avatarImage.setAttribute('alt', resolvedAuthor);
          }
        }
        fallback.textContent = '';
        fallback.style.display = 'none';
      }
    }
    if (rating) {
      var ratingValue = rating.getAttribute('data-rating') || rating.dataset.rating || '';
      var parsedRating = parseInt(ratingValue, 10);
      if (!Number.isFinite(parsedRating) || parsedRating < 1) {
        parsedRating = Number.isFinite(defaultRating) && defaultRating > 0 ? defaultRating : 0;
      }
      rating.innerHTML = '';
      if (parsedRating > 0) {
        var cappedRating = Math.min(parsedRating, 5);
        rating.style.display = 'flex';
        rating.setAttribute('aria-label', cappedRating + ' out of 5 stars');
        for (var i = 0; i < cappedRating; i += 1) {
          var star = document.createElement('span');
          star.className = 'testimonial-card__star';
          star.setAttribute('aria-hidden', 'true');
          star.textContent = '★';
          rating.appendChild(star);
        }
      } else {
        rating.style.display = 'none';
        rating.removeAttribute('aria-label');
      }
    }
    var finalQuote = quote ? quote.textContent.trim() : '';
    var finalAuthor = author ? author.textContent.trim() : '';
    var finalRole = role ? role.textContent.trim() : '';
    var hasContent = Boolean(finalQuote || finalAuthor || finalRole);
    slide.classList.toggle('testimonial-slide--empty', !hasContent);
    return hasContent;
  }

  function initCarouselInstance(carousel) {
    if (!(carousel instanceof HTMLElement) || carousel.dataset.carouselInitialized === 'true') {
      return;
    }
    var wrapper = carousel.querySelector('.swiper-wrapper');
    if (!wrapper) {
      carousel.classList.add('is-empty');
      return;
    }
    var slides = Array.prototype.slice.call(wrapper.querySelectorAll('.swiper-slide'));
    if (!slides.length) {
      carousel.classList.add('is-empty');
      return;
    }
    var filteredSlides = [];
    slides.forEach(function (slide) {
      if (normalizeTestimonialSlide(slide)) {
        filteredSlides.push(slide);
      } else if (slide.parentNode) {
        slide.parentNode.removeChild(slide);
      }
    });
    if (!filteredSlides.length) {
      carousel.classList.add('is-empty');
      return;
    }
    carousel.classList.remove('is-empty');
    var prevBtn = carousel.querySelector('[data-carousel-prev]');
    var nextBtn = carousel.querySelector('[data-carousel-next]');
    var paginationHost = carousel.querySelector('[data-carousel-pagination]');
    var autoplayDelay = parseAutoplayDelay(carousel.getAttribute('data-autoplay'));
    var showPagination = parseBoolean(carousel.getAttribute('data-show-pagination'));
    var state = {
      current: 0,
      autoplayTimer: null
    };

    function slidesPerView() {
      if (window.innerWidth >= 768) {
        return Math.min(2, filteredSlides.length);
      }
      return 1;
    }

    function maxIndex() {
      var perView = slidesPerView();
      return Math.max(0, filteredSlides.length - perView);
    }

    function clearAutoplay() {
      if (state.autoplayTimer) {
        window.clearTimeout(state.autoplayTimer);
        state.autoplayTimer = null;
      }
    }

    function scheduleAutoplay() {
      clearAutoplay();
      if (!autoplayDelay) {
        return;
      }
      state.autoplayTimer = window.setTimeout(function () {
        var perView = slidesPerView();
        var max = Math.max(0, filteredSlides.length - perView);
        if (state.current >= max) {
          goTo(0);
        } else {
          goTo(state.current + 1);
        }
      }, autoplayDelay);
    }

    function updateNavControls(max) {
      if (prevBtn) {
        prevBtn.disabled = state.current <= 0;
      }
      if (nextBtn) {
        nextBtn.disabled = state.current >= max;
      }
    }

    function renderPagination(max, currentIndex) {
      if (!paginationHost) {
        return;
      }
      var pageCount = max + 1;
      if (!showPagination || pageCount <= 1) {
        paginationHost.innerHTML = '';
        paginationHost.style.display = 'none';
        return;
      }
      paginationHost.style.display = '';
      paginationHost.innerHTML = '';
      for (var i = 0; i < pageCount; i += 1) {
        (function (index) {
          var dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'testimonial-carousel__dot' + (index === currentIndex ? ' is-active' : '');
          dot.setAttribute('aria-label', 'Go to testimonial ' + (index + 1));
          dot.addEventListener('click', function () {
            goTo(index);
          });
          paginationHost.appendChild(dot);
        })(i);
      }
    }

    function updateTransform(options) {
      var perView = slidesPerView();
      wrapper.style.setProperty('--slides-per-view', perView);
      var max = Math.max(0, filteredSlides.length - perView);
      if (state.current > max) {
        state.current = max;
      }
      var translate = perView > 0 ? (state.current * 100) / perView : 0;
      wrapper.style.transform = 'translateX(-' + translate + '%)';
      updateNavControls(max);
      renderPagination(max, state.current);
      if (!options || options.autoplay !== false) {
        scheduleAutoplay();
      }
    }

    function goTo(index, options) {
      var perView = slidesPerView();
      var max = Math.max(0, filteredSlides.length - perView);
      var nextIndex = Math.min(Math.max(index, 0), max);
      state.current = nextIndex;
      updateTransform(options);
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goTo(state.current - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        goTo(state.current + 1);
      });
    }

    carousel.addEventListener('mouseenter', clearAutoplay);
    carousel.addEventListener('mouseleave', scheduleAutoplay);

    window.addEventListener('resize', function () {
      updateTransform({ autoplay: false });
    });

    carousel.dataset.carouselInitialized = 'true';
    updateTransform({ autoplay: true });
  }

  function initTestimonialCarousels() {
    var carousels = document.querySelectorAll('[data-testimonial-carousel]');
    carousels.forEach(function (carousel) {
      initCarouselInstance(carousel);
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
      var shouldRefreshCarousels = false;
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
          if (node.matches('[data-testimonial-carousel]') || node.querySelector('[data-testimonial-carousel]')) {
            shouldRefreshCarousels = true;
          }
        });
      });
      if (shouldRefreshBlogs) {
        initBlogLists();
      }
      if (shouldRefreshCalendars) {
        initCalendarBlocks();
      }
      if (shouldRefreshCarousels) {
        initTestimonialCarousels();
      }
    });
    observer.observe(document.body || document.documentElement, {
      childList: true,
      subtree: true
    });
  }

  ready(function () {
    initBlogLists();
    initCalendarBlocks();
    initTestimonialCarousels();
    observe();
  });

  window.SparkCMSBlogLists = {
    refresh: initBlogLists
  };

  window.SparkCMSCalendars = {
    refresh: initCalendarBlocks
  };

  window.SparkCMSTestimonialCarousels = {
    refresh: initTestimonialCarousels
  };
})();
