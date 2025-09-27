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
        attributeFilter: ['data-form-id'],
      });
    }
  });
})();

