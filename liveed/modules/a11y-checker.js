// File: a11y-checker.js
(function ($) {
  // Create hidden checker button
  const checkerBtn = $(
    '<div class="accessibility-checker" role="button" aria-label="Check Accessibility">Check Accessibility</div>'
  );
  $('body').append(checkerBtn);

  function toggleAccessibilityChecker(){
    if (checkerBtn.is(':visible')){
      checkerBtn.animate({ right: '-150px' }, 500, function(){ checkerBtn.hide(); });
    } else {
      checkerBtn.show().animate({ right: '20px' }, 500);
    }
  }

  $(document).on('mousedown', function(e){
    if(e.button === 0){
      $(document).on('keydown.accessibilityCheck', function(ev){
        if(ev.key.toLowerCase() === 'a'){ toggleAccessibilityChecker(); }
      });
    }
  });
  $(document).on('mouseup', (e) => {
    if (e.button === 0) {
      $(document).off('keydown.accessibilityCheck');
    }
  });

  function addError($el, message, cls = 'highlight-issue') {
    $el.addClass(cls).attr('aria-label', message);
    $('<span>')
      .addClass(`error-message ${cls}`.trim())
      .text(message)
      .insertAfter($el);
  }

  function checkIssues($elements, issue) {
    addError($elements, issue);
  }

  function checkHeadings($root) {
    if ($root.find('h1').length === 0) {
      $('<h1 class="error-message">Missing h1 tag</h1>').prependTo($root);
    }
    const headingLevels = { H1: 1, H2: 2, H3: 3, H4: 4, H5: 5, H6: 6 };
    let last = 0;
    $root.find('h1, h2, h3, h4, h5, h6').each(function () {
      const $t = $(this);
      const cur = headingLevels[$t.prop('tagName')];
      const tag = $t.prop('tagName');
      $t.addClass('highlight-htag');
      if (cur > last + 1) {
        addError(
          $t,
          'Heading is out of order. Ensure proper heading hierarchy.',
          'highlight-htag'
        );
        $t.append('<span class="accessibility-bubble red">' + tag + '</span>');
      } else {
        $t.append('<span class="accessibility-bubble green">' + tag + '</span>');
      }
      last = cur;
    });
  }

  function checkLinks($root){
    $root.find('a').each(function () {
      const txt = $(this).text().trim();
      const href = $(this).attr('href');
      if (
        href &&
        href !== '#' &&
        href !== '/' &&
        !href.includes('#') &&
        !href.includes('javascript:')
      ) {
        if (!txt || txt === 'click here') {
          addError(
            $(this),
            'Non-descriptive link text. Use descriptive text that provides context for the link.'
          );
        }
      }
    });
  }

  function parseColor(color) {
    let match;
    if ((match = color.match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i))) {
      return [
        parseInt(match[1], 16),
        parseInt(match[2], 16),
        parseInt(match[3], 16),
      ];
    } else if (
      (match = color.match(
        /^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/
      ))
    ) {
      return [parseInt(match[1]), parseInt(match[2]), parseInt(match[3])];
    }
    return null;
  }
  function adjustColor(val) {
    val = val / 255;
    return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
  }
  function getRelativeLuminance(color) {
    const rgb = parseColor(color);
    if (!rgb) return 1;
    const r = adjustColor(rgb[0]);
    const g = adjustColor(rgb[1]);
    const b = adjustColor(rgb[2]);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  }
  function calculateContrastRatio(c1, c2) {
    const l1 = getRelativeLuminance(c1);
    const l2 = getRelativeLuminance(c2);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function checkColorContrast($root) {
    $root.find('*').each(function () {
      const bg = $(this).css('background-color');
      const col = $(this).css('color');
      if (bg && col) {
        const ratio = calculateContrastRatio(bg, col);
        if (ratio < 4.5) {
          addError(
            $(this),
            'Insufficient color contrast. Ensure text is readable against the background color.'
          );
        }
      }
    });
  }

  function checkFormLabels($root){
    $root.find('input, select, textarea').each(function () {
      const label = $('label[for="' + $(this).attr('id') + '"]');
      if (!label.length) {
        addError(
          $(this),
          'Missing associated label. Each form control must have a corresponding label.'
        );
      }
    });
  }

  function checkLangAttribute() {
    if (!$('html').attr('lang')) {
      addError(
        $('html'),
        'Missing language attribute on HTML element. Use the "lang" attribute to specify the language of the page content.'
      );
    }
  }

  function checkSemanticHTML($root){
    $root.find('*').each(function () {
      const tag = $(this).prop('tagName').toLowerCase();
      if (
        ['div', 'span'].includes(tag) &&
        !['header', 'nav', 'main', 'footer'].includes(tag)
      ) {
        addError(
          $(this),
          'Use of non-semantic HTML. Consider using semantic elements like <header>, <nav>, <main>, <footer>, etc.'
        );
      }
    });
  }

  function checkMediaAccessibility($root){
    $root.find('video, audio').each(function () {
      if (!$(this).find('track').length) {
        addError(
          $(this),
          'Missing captions or transcripts for multimedia content. Ensure accessibility for users with visual or hearing impairments.'
        );
      }
    });
  }

  function checkTextResizing() {
    $('body').append(
      '<div class="text-resize-test" style="font-size:100%;position:absolute;left:-9999px;">Text resizing test</div>'
    );
    const originalSize = $('.text-resize-test').css('font-size');
    $('.text-resize-test').css('font-size', '200%');
    if ($('.text-resize-test').css('font-size') !== originalSize) {
      addError(
        $('.text-resize-test'),
        'Text resizing issue. Ensure text can be resized up to 200% without loss of content or functionality.'
      );
    }
    $('.text-resize-test').remove();
  }

  function checkResponsiveDesign() {
    if (!$('meta[name="viewport"]').length) {
      $('head').append(
        '<meta name="viewport" content="width=device-width, initial-scale=1">'
      );
    }
    const viewportMeta = $('meta[name="viewport"]').attr('content');
    if (!viewportMeta.includes('width=device-width')) {
      addError(
        $('meta[name="viewport"]'),
        'Missing viewport settings. Ensure content is accessible on different screen sizes and orientations.'
      );
    }
  }

  let keyboardBound = false;
  function checkKeyboardNavigation($root) {
    if (keyboardBound) return;
    $root.on('keydown.a11y', '*', function (e) {
      if (e.which === 9) {
        addError(
          $(this),
          'Keyboard navigation issue. Ensure all interactive elements are accessible via keyboard navigation.'
        );
      }
    });
    keyboardBound = true;
  }

  function checkFocusManagement() {
    $(':focus').each(function () {
      addError(
        $(this),
        'Focus management issue. Ensure focus is managed correctly and visually noticeable.'
      );
    });
  }

  function checkNotifications($root) {
    $root.find('.alert, .notification').each(function () {
      if (!$(this).attr('role')) {
        $(this).attr('role', 'alert');
        addError(
          $(this),
          'Missing role attribute for notifications. Ensure alerts and notifications are accessible and announced by screen readers.'
        );
      }
    });
  }

  let dynamicObserver;
  function checkDynamicContentUpdates() {
    if (dynamicObserver) return;
    dynamicObserver = new MutationObserver((mutations) => {
      mutations.forEach((m) => {
        if (m.addedNodes.length > 0) {
          $(m.addedNodes).each(function () {
            addError(
              $(this),
              'Dynamic content update. Ensure updates to the content are announced to screen reader users.'
            );
          });
        }
      });
    });
    dynamicObserver.observe(document.body, { childList: true, subtree: true });
  }

  function addSkipLink() {
    if (!$('a.skip-main').length) {
      $('<a class="skip-main" href="#main-content">Skip to main content</a>').prependTo('body');
    }
  }

  function runChecks() {
    const $root = $('#canvas');
    $('.error-message').remove();
    checkIssues($root.find('img:not([alt])'),'Missing alt attribute');
    checkIssues($root.find('img[alt=""]'),'Empty alt attribute');
    checkIssues($root.find(':button:not([aria-label]), :input:not([aria-label])'),'Missing aria-label attribute');
    checkIssues($root.find('input:not([id])'),'Missing id attribute');
    checkHeadings($root);
    checkLinks($root);
    checkColorContrast($root);
    checkFormLabels($root);
    checkLangAttribute();
    checkSemanticHTML($root);
    checkMediaAccessibility($root);
    checkTextResizing();
    checkResponsiveDesign();
    checkKeyboardNavigation($root);
    checkFocusManagement();
    checkNotifications($root);
    checkDynamicContentUpdates();
    addSkipLink();
  }

  window.runAccessibilityCheck = runChecks;

  checkerBtn.on('click', runChecks);
  $('.a11y-check-btn').on('click', runChecks);
})(jQuery);
