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

  function parseLimit(value) {
    var limit = parseInt(value, 10);
    if (!Number.isFinite(limit) || limit < 1) {
      return 3;
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

  function initAll() {
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
      var shouldRefresh = false;
      mutations.forEach(function (mutation) {
        if (mutation.type !== 'childList') {
          return;
        }
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof HTMLElement)) {
            return;
          }
          if (node.matches('[data-blog-list]') || node.querySelector('[data-blog-list]')) {
            shouldRefresh = true;
          }
        });
      });
      if (shouldRefresh) {
        initAll();
      }
    });
    observer.observe(document.body || document.documentElement, {
      childList: true,
      subtree: true
    });
  }

  ready(function () {
    initAll();
    observe();
  });

  window.SparkCMSBlogLists = {
    refresh: initAll
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

  var calendarStates = new WeakMap();

  function debounce(fn, wait) {
    var timeoutId;
    return function () {
      var context = this;
      var args = arguments;
      clearTimeout(timeoutId);
      timeoutId = setTimeout(function () {
        fn.apply(context, args);
      }, wait);
    };
  }

  function resolvedTimeZone() {
    try {
      var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      return typeof tz === 'string' && tz ? tz : 'UTC';
    } catch (err) {
      return 'UTC';
    }
  }

  function normalizeTimezone(value) {
    if (typeof value !== 'string') {
      return resolvedTimeZone();
    }
    var trimmed = value.trim();
    if (!trimmed) {
      return resolvedTimeZone();
    }
    try {
      new Intl.DateTimeFormat(undefined, { timeZone: trimmed });
      return trimmed;
    } catch (err) {
      return resolvedTimeZone();
    }
  }

  function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
  }

  function addMonths(date, amount) {
    return new Date(date.getFullYear(), date.getMonth() + amount, 1);
  }

  function dateKey(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
  }

  function formatMonthLabel(date, timezone) {
    try {
      return new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
        timeZone: timezone,
      }).format(date);
    } catch (err) {
      return date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    }
  }

  function normalizeEvents(list) {
    if (!Array.isArray(list)) {
      return [];
    }
    return list
      .map(function (item) {
        if (!item || !item.start) {
          return null;
        }
        var start = new Date(item.start);
        if (isNaN(start.getTime())) {
          return null;
        }
        var end = item.end ? new Date(item.end) : new Date(start.getTime());
        if (isNaN(end.getTime())) {
          end = new Date(start.getTime());
        }
        var category = null;
        if (item.category && typeof item.category === 'object') {
          category = {
            id: item.category.id ? String(item.category.id) : '',
            name: item.category.name || '',
            color: item.category.color || '',
          };
        }
        var occurrence = {
          id: item.id || item.sourceId || String(start.getTime()),
          title: item.title || 'Untitled event',
          description: item.description || '',
          start: start,
          end: end,
          allDay: !!item.allDay,
          category: category,
          raw: item,
        };
        occurrence.dateKey = dateKey(start);
        return occurrence;
      })
      .filter(function (item) {
        return !!item;
      })
      .sort(function (a, b) {
        return a.start.getTime() - b.start.getTime();
      });
  }

  function formatEventTimeRange(event, timezone) {
    var dateOptions = { year: 'numeric', month: 'short', day: 'numeric' };
    var timeOptions = { hour: 'numeric', minute: '2-digit' };
    try {
      if (timezone) {
        dateOptions.timeZone = timezone;
        timeOptions.timeZone = timezone;
      }
      var dateFormatter = new Intl.DateTimeFormat(undefined, dateOptions);
      var timeFormatter = new Intl.DateTimeFormat(undefined, timeOptions);
      var startDate = event.start;
      var endDate = event.end;
      var sameDay = dateKey(startDate) === dateKey(endDate);
      if (event.allDay) {
        if (sameDay) {
          return dateFormatter.format(startDate) + ' · All day';
        }
        return dateFormatter.format(startDate) + ' – ' + dateFormatter.format(endDate) + ' · All day';
      }
      if (sameDay) {
        return (
          dateFormatter.format(startDate) +
          ' · ' +
          timeFormatter.format(startDate) +
          ' – ' +
          timeFormatter.format(endDate)
        );
      }
      return (
        dateFormatter.format(startDate) +
        ' ' +
        timeFormatter.format(startDate) +
        ' – ' +
        dateFormatter.format(endDate) +
        ' ' +
        timeFormatter.format(endDate)
      );
    } catch (err) {
      return event.start.toString();
    }
  }

  function setCurrentView(widget, state, view) {
    state.view = view === 'list' ? 'list' : 'grid';
    var panels = widget.querySelectorAll('[data-calendar-view-panel]');
    panels.forEach(function (panel) {
      var panelView = panel.getAttribute('data-calendar-view-panel');
      panel.hidden = panelView !== state.view;
    });
    var buttons = widget.querySelectorAll('[data-calendar-view]');
    buttons.forEach(function (button) {
      var matches = button.getAttribute('data-calendar-view') === state.view;
      button.classList.toggle('active', matches);
      button.setAttribute('aria-pressed', matches ? 'true' : 'false');
    });
  }

  function updateMonthLabel(widget, state) {
    if (!state.elements.monthLabel) {
      return;
    }
    state.elements.monthLabel.textContent = formatMonthLabel(state.currentMonth, state.timezone);
  }

  function updateCategoryFilter(widget, state) {
    if (!state.elements.categoryFilter) {
      return;
    }
    var select = state.elements.categoryFilter;
    while (select.firstChild) {
      select.removeChild(select.firstChild);
    }
    var allOption = document.createElement('option');
    allOption.value = '';
    allOption.textContent = 'All categories';
    select.appendChild(allOption);
    var current = state.filters.category;
    var found = false;
    state.categories.forEach(function (category) {
      var option = document.createElement('option');
      var value = category.id != null ? String(category.id) : '';
      option.value = value;
      option.textContent = category.name || 'Category';
      if (value === current) {
        found = true;
      }
      select.appendChild(option);
    });
    if (current && !found) {
      state.filters.category = '';
    }
    select.value = state.filters.category;
  }

  function renderCalendar(widget, state) {
    var grid = state.elements.grid;
    if (!grid) {
      return;
    }
    grid.innerHTML = '';
    var monthStart = startOfMonth(state.currentMonth);
    var daysInMonth = new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 0).getDate();
    var firstWeekday = monthStart.getDay();
    for (var offset = 0; offset < firstWeekday; offset++) {
      var spacer = document.createElement('div');
      spacer.className = 'calendar-cell calendar-cell--empty';
      spacer.setAttribute('role', 'presentation');
      grid.appendChild(spacer);
    }
    var todayKey = dateKey(state.today);
    for (var day = 1; day <= daysInMonth; day++) {
      var date = new Date(monthStart.getFullYear(), monthStart.getMonth(), day);
      var cell = document.createElement('div');
      cell.className = 'calendar-cell';
      cell.setAttribute('data-date', dateKey(date));
      cell.setAttribute('role', 'gridcell');
      if (dateKey(date) === todayKey) {
        cell.classList.add('calendar-cell--today');
      }
      var header = document.createElement('div');
      header.className = 'calendar-cell__header';
      header.textContent = String(day);
      cell.appendChild(header);
      var eventsForDay = state.events.filter(function (event) {
        return event.dateKey === dateKey(date);
      });
      if (eventsForDay.length) {
        var list = document.createElement('div');
        list.className = 'calendar-cell__events';
        eventsForDay.forEach(function (event) {
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'calendar-event-chip';
          button.textContent = event.title;
          if (event.category && event.category.color) {
            button.style.backgroundColor = event.category.color;
          }
          button.addEventListener('click', function () {
            openCalendarModal(state, event);
          });
          list.appendChild(button);
        });
        cell.appendChild(list);
      }
      grid.appendChild(cell);
    }
  }

  function renderList(widget, state) {
    var list = state.elements.list;
    if (!list) {
      return;
    }
    list.innerHTML = '';
    if (state.elements.listEmpty) {
      state.elements.listEmpty.classList.add('d-none');
    }
    if (!state.events.length) {
      if (state.elements.listEmpty) {
        state.elements.listEmpty.textContent = state.emptyMessage;
        state.elements.listEmpty.classList.remove('d-none');
      }
      return;
    }
    state.events.forEach(function (event) {
      var item = document.createElement('article');
      item.className = 'calendar-list__item';
      item.setAttribute('tabindex', '0');
      item.setAttribute('role', 'button');
      var header = document.createElement('div');
      header.className = 'calendar-list__item-header';
      var title = document.createElement('h4');
      title.textContent = event.title;
      header.appendChild(title);
      if (event.category && (event.category.name || event.category.color)) {
        var badge = document.createElement('span');
        badge.className = 'calendar-category-badge';
        if (event.category.color) {
          badge.style.backgroundColor = event.category.color;
          badge.style.color = '#fff';
        }
        badge.textContent = event.category.name || 'Category';
        header.appendChild(badge);
      }
      item.appendChild(header);
      var meta = document.createElement('p');
      meta.textContent = formatEventTimeRange(event, state.timezone);
      item.appendChild(meta);
      if (event.description) {
        var description = document.createElement('p');
        description.textContent = event.description;
        item.appendChild(description);
      }
      item.addEventListener('click', function (evt) {
        if (evt.target && (evt.target.closest('button') || evt.target.closest('a'))) {
          return;
        }
        openCalendarModal(state, event);
      });
      item.addEventListener('keydown', function (evt) {
        if (evt.key === 'Enter' || evt.key === ' ') {
          evt.preventDefault();
          openCalendarModal(state, event);
        }
      });
      list.appendChild(item);
    });
  }

  function renderUpcoming(widget, state) {
    if (!state.elements.upcoming) {
      return;
    }
    var list = state.elements.upcoming;
    list.innerHTML = '';
    if (!state.upcoming.length) {
      var empty = document.createElement('li');
      empty.className = 'calendar-empty';
      empty.textContent = 'No upcoming events in the next 30 days.';
      list.appendChild(empty);
      return;
    }
    state.upcoming.forEach(function (event) {
      var item = document.createElement('li');
      var marker = document.createElement('span');
      marker.className = 'calendar-upcoming__dot';
      marker.style.backgroundColor = event.category && event.category.color ? event.category.color : '#2563eb';
      item.appendChild(marker);
      var content = document.createElement('div');
      var title = document.createElement('span');
      title.className = 'calendar-upcoming__title';
      title.textContent = event.title;
      var time = document.createElement('span');
      time.className = 'd-block';
      time.textContent = formatEventTimeRange(event, state.timezone);
      content.appendChild(title);
      content.appendChild(time);
      item.appendChild(content);
      item.addEventListener('click', function () {
        openCalendarModal(state, event);
      });
      list.appendChild(item);
    });
  }

  function renderCategories(widget, state) {
    if (!state.elements.categories) {
      return;
    }
    var list = state.elements.categories;
    list.innerHTML = '';
    if (!state.categories.length) {
      var empty = document.createElement('li');
      empty.className = 'calendar-empty';
      empty.textContent = 'No categories defined yet.';
      list.appendChild(empty);
      return;
    }
    state.categories.forEach(function (category) {
      var item = document.createElement('li');
      var marker = document.createElement('span');
      marker.className = 'calendar-category__marker';
      marker.style.backgroundColor = category.color || '#2563eb';
      item.appendChild(marker);
      var name = document.createElement('span');
      name.textContent = category.name || 'Category';
      item.appendChild(name);
      list.appendChild(item);
    });
  }

  function updateMetrics(widget, meta, state) {
    if (!state.elements.metrics) {
      return;
    }
    var metrics = state.elements.metrics;
    metrics.month.textContent = meta && typeof meta.eventsThisMonth === 'number' ? meta.eventsThisMonth : state.events.length;
    metrics.recurring.textContent = meta && typeof meta.recurringSeries === 'number' ? meta.recurringSeries : '0';
    metrics.categories.textContent = meta && typeof meta.categories === 'number' ? meta.categories : state.categories.length;
    metrics.updated.textContent = meta && meta.lastUpdated ? meta.lastUpdated : 'Just now';
  }

  function showLoading(widget, state) {
    if (state.elements.grid) {
      state.elements.grid.innerHTML = '<p class="calendar-empty">Loading events…</p>';
    }
    if (state.elements.list) {
      state.elements.list.innerHTML = '';
    }
    if (state.elements.listEmpty) {
      state.elements.listEmpty.textContent = 'Loading events…';
      state.elements.listEmpty.classList.remove('d-none');
    }
    if (state.elements.upcoming) {
      state.elements.upcoming.innerHTML = '';
      var loadingUpcoming = document.createElement('li');
      loadingUpcoming.className = 'calendar-empty';
      loadingUpcoming.textContent = 'Loading upcoming events…';
      state.elements.upcoming.appendChild(loadingUpcoming);
    }
    if (state.elements.categories) {
      state.elements.categories.innerHTML = '';
      var loadingCategories = document.createElement('li');
      loadingCategories.className = 'calendar-empty';
      loadingCategories.textContent = 'Loading categories…';
      state.elements.categories.appendChild(loadingCategories);
    }
  }

  function showError(widget, state, message) {
    var text = message || 'We were unable to load events right now.';
    if (state.elements.grid) {
      state.elements.grid.innerHTML = '<p class="calendar-empty">' + text + '</p>';
    }
    if (state.elements.list) {
      state.elements.list.innerHTML = '';
    }
    if (state.elements.listEmpty) {
      state.elements.listEmpty.textContent = text;
      state.elements.listEmpty.classList.remove('d-none');
    }
    if (state.elements.upcoming) {
      state.elements.upcoming.innerHTML = '';
      var li = document.createElement('li');
      li.className = 'calendar-empty';
      li.textContent = text;
      state.elements.upcoming.appendChild(li);
    }
    if (state.elements.categories) {
      state.elements.categories.innerHTML = '';
      var catLi = document.createElement('li');
      catLi.className = 'calendar-empty';
      catLi.textContent = text;
      state.elements.categories.appendChild(catLi);
    }
  }

  function closeCalendarModal(state) {
    if (!state.modal) {
      return;
    }
    state.modal.classList.remove('open');
    state.modal.setAttribute('aria-hidden', 'true');
    if (state.modalElements && state.modalElements.content) {
      state.modalElements.content.blur();
    }
    if (state.modalKeyHandler) {
      document.removeEventListener('keydown', state.modalKeyHandler, true);
      state.modalKeyHandler = null;
    }
  }

  function openCalendarModal(state, event) {
    if (!state.modal) {
      return;
    }
    if (state.modalElements && state.modalElements.title) {
      state.modalElements.title.textContent = event.title;
    }
    if (state.modalElements && state.modalElements.time) {
      state.modalElements.time.textContent = formatEventTimeRange(event, state.timezone);
    }
    if (state.modalElements && state.modalElements.category) {
      if (event.category && (event.category.name || event.category.color)) {
        state.modalElements.category.textContent = event.category.name || 'Category';
        state.modalElements.category.style.display = '';
        state.modalElements.category.style.color = event.category.color || '';
      } else {
        state.modalElements.category.textContent = '';
        state.modalElements.category.style.display = 'none';
      }
    }
    if (state.modalElements && state.modalElements.description) {
      if (event.description) {
        state.modalElements.description.textContent = event.description;
        state.modalElements.description.style.display = '';
      } else {
        state.modalElements.description.textContent = '';
        state.modalElements.description.style.display = 'none';
      }
    }
    state.modal.classList.add('open');
    state.modal.setAttribute('aria-hidden', 'false');
    if (state.modalElements && state.modalElements.content) {
      state.modalElements.content.focus({ preventScroll: true });
    }
    state.modalKeyHandler = function (evt) {
      if (evt.key === 'Escape') {
        evt.preventDefault();
        closeCalendarModal(state);
      }
    };
    document.addEventListener('keydown', state.modalKeyHandler, true);
  }

  function setupCalendarWidget(widget, state) {
    state.elements = {
      monthLabel: widget.querySelector('[data-calendar-month-label]'),
      grid: widget.querySelector('[data-calendar-grid]'),
      list: widget.querySelector('[data-calendar-list]'),
      listEmpty: widget.querySelector('[data-calendar-list-empty]'),
      upcoming: widget.querySelector('[data-calendar-upcoming]'),
      categories: widget.querySelector('[data-calendar-category-list]'),
      categoryFilter: widget.querySelector('[data-calendar-category-filter]'),
      metrics: {
        month: widget.querySelector('[data-calendar-metric="month"]') || document.createElement('span'),
        recurring: widget.querySelector('[data-calendar-metric="recurring"]') || document.createElement('span'),
        categories: widget.querySelector('[data-calendar-metric="categories"]') || document.createElement('span'),
        updated: widget.querySelector('[data-calendar-metric="updated"]') || document.createElement('span'),
      },
    };
    var container = widget.closest('.calendar-block') || widget;
    state.modal = container.querySelector('[data-calendar-modal="detail"]');
    if (state.modal) {
      state.modalElements = {
        container: state.modal,
        content: state.modal.querySelector('.calendar-modal__content'),
        title: state.modal.querySelector('[data-calendar-modal-title]'),
        time: state.modal.querySelector('[data-calendar-modal-time]'),
        category: state.modal.querySelector('[data-calendar-modal-category]'),
        description: state.modal.querySelector('[data-calendar-modal-description]'),
      };
      state.modal.setAttribute('aria-hidden', 'true');
      state.modal.addEventListener('click', function (evt) {
        if (evt.target === state.modal) {
          closeCalendarModal(state);
        }
      });
      var closeButton = state.modal.querySelector('[data-calendar-modal-close]');
      if (closeButton) {
        closeButton.addEventListener('click', function () {
          closeCalendarModal(state);
        });
      }
    }
    var viewButtons = widget.querySelectorAll('[data-calendar-view]');
    viewButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        setCurrentView(widget, state, button.getAttribute('data-calendar-view'));
      });
    });
    var prev = widget.querySelector('[data-calendar-nav="prev"]');
    if (prev) {
      prev.addEventListener('click', function () {
        state.currentMonth = addMonths(state.currentMonth, -1);
        refreshCalendarWidget(widget, state);
      });
    }
    var next = widget.querySelector('[data-calendar-nav="next"]');
    if (next) {
      next.addEventListener('click', function () {
        state.currentMonth = addMonths(state.currentMonth, 1);
        refreshCalendarWidget(widget, state);
      });
    }
    if (state.elements.categoryFilter) {
      state.elements.categoryFilter.addEventListener('change', function (evt) {
        state.filters.category = evt.target.value || '';
        refreshCalendarWidget(widget, state);
      });
    }
    var searchInput = widget.querySelector('[data-calendar-search]');
    if (searchInput) {
      var handleSearch = debounce(function (evt) {
        state.filters.search = evt.target.value.trim();
        refreshCalendarWidget(widget, state);
      }, 250);
      searchInput.addEventListener('input', handleSearch);
    }
    setCurrentView(widget, state, state.view);
    updateMonthLabel(widget, state);
  }

  function fetchCalendarData(widget, state) {
    if (state.abortController) {
      state.abortController.abort();
    }
    var controller = new AbortController();
    state.abortController = controller;
    var base = basePath();
    var endpoint = (base || '') + '/CMS/modules/calendar/public_feed.php';
    var params = new URLSearchParams();
    params.set('month', String(state.currentMonth.getMonth() + 1));
    params.set('year', String(state.currentMonth.getFullYear()));
    if (state.filters.search) {
      params.set('search', state.filters.search);
    }
    if (state.filters.category) {
      params.set('category', state.filters.category);
    }
    if (state.timezone) {
      params.set('timezone', state.timezone);
    }
    var url = endpoint + '?' + params.toString();
    var request = fetch(url, { credentials: 'same-origin', signal: controller.signal })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Failed to load events');
        }
        return response.json();
      })
      .then(function (data) {
        if (!data || data.status !== 'success') {
          throw new Error((data && data.message) || 'Unable to load events');
        }
        return data;
      });
    return request.finally(function () {
      if (state.abortController === controller) {
        state.abortController = null;
      }
    });
  }

  function refreshCalendarWidget(widget, state) {
    state.today = startOfDay(new Date());
    updateMonthLabel(widget, state);
    updateCategoryFilter(widget, state);
    showLoading(widget, state);
    fetchCalendarData(widget, state)
      .then(function (data) {
        state.events = normalizeEvents(data.events);
        state.upcoming = normalizeEvents(data.upcoming);
        state.categories = Array.isArray(data.categories)
          ? data.categories.map(function (category) {
              return {
                id: category.id != null ? String(category.id) : '',
                name: category.name || 'Category',
                color: category.color || '',
              };
            })
          : [];
        updateCategoryFilter(widget, state);
        renderCalendar(widget, state);
        renderList(widget, state);
        renderUpcoming(widget, state);
        renderCategories(widget, state);
        updateMetrics(widget, data.meta || null, state);
      })
      .catch(function (error) {
        if (error && error.name === 'AbortError') {
          return;
        }
        var message = error && error.message ? error.message : null;
        showError(widget, state, message);
      });
  }

  function initCalendarWidgets() {
    var widgets = document.querySelectorAll('[data-calendar-widget]');
    widgets.forEach(function (widget) {
      if (calendarStates.has(widget)) {
        return;
      }
      var state = {
        timezone: normalizeTimezone(widget.getAttribute('data-calendar-timezone')),
        currentMonth: startOfMonth(new Date()),
        today: startOfDay(new Date()),
        events: [],
        upcoming: [],
        categories: [],
        filters: { search: '', category: '' },
        view: widget.getAttribute('data-calendar-initial-view') === 'list' ? 'list' : 'grid',
        emptyMessage: widget.getAttribute('data-calendar-empty') || 'No events scheduled for this period.',
        abortController: null,
      };
      calendarStates.set(widget, state);
      setupCalendarWidget(widget, state);
      refreshCalendarWidget(widget, state);
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

    initCalendarWidgets();
    document.addEventListener('canvasUpdated', initCalendarWidgets);

    if (window.MutationObserver) {
      var observer = new MutationObserver(function (mutations) {
        var needsRefresh = false;
        var needsCalendarInit = false;
        mutations.forEach(function (mutation) {
          if (mutation.type === 'attributes' && mutation.attributeName === 'data-form-id') {
            var target = mutation.target;
            if (target && target.classList && target.classList.contains('spark-form-embed')) {
              needsRefresh = true;
            }
            if (target && target.hasAttribute && target.hasAttribute('data-calendar-widget')) {
              needsCalendarInit = true;
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
              if (node.hasAttribute && node.hasAttribute('data-calendar-widget')) {
                needsCalendarInit = true;
              } else if (node.querySelector && node.querySelector('[data-calendar-widget]')) {
                needsCalendarInit = true;
              }
            });
          }
        });
        if (needsRefresh) {
          initializeSparkForms();
        }
        if (needsCalendarInit) {
          initCalendarWidgets();
        }
      });
      observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: [
          'data-form-id',
          'data-calendar-widget',
          'data-calendar-timezone',
          'data-calendar-initial-view',
          'data-calendar-empty',
        ],
      });
    }
  });
})();

