(function($){
  function toggleAccessibilityChecker(){
    const $checker = $('.accessibility-checker');
    if($checker.is(':visible')){
      $checker.animate({ right: '-150px' }, 500, function(){ $checker.hide(); });
    } else {
      $checker.show().animate({ right: '20px' }, 500);
    }
  }

  function runChecks(){
    $('.error-message').remove();
    checkIssues($('img:not([alt])'), 'Missing alt attribute');
    checkIssues($('img[alt=""]'), 'Empty alt attribute');
    checkIssues($(':button:not([aria-label]), :input:not([aria-label])'), 'Missing aria-label attribute');
    checkIssues($('input:not([id])'), 'Missing id attribute');
    checkHeadings();
    checkLinks();
    checkColorContrast();
    checkFormLabels();
    checkLangAttribute();
    checkSemanticHTML();
    checkMediaAccessibility();
    checkTextResizing();
    checkResponsiveDesign();
    checkKeyboardNavigation();
    checkFocusManagement();
    checkNotifications();
    checkDynamicContentUpdates();
    addSkipLink();
  }

  $(document).on('mousedown', function(e){
    if(e.button===0){
      $(document).on('keydown.accessibilityCheck', function(ev){
        if(ev.key.toLowerCase() === 'a'){
          toggleAccessibilityChecker();
        }
      });
    }
  });
  $(document).on('mouseup', function(e){
    if(e.button===0){
      $(document).off('keydown.accessibilityCheck');
    }
  });

  $(function(){
    $('.accessibility-checker').on('click', runChecks);
    $('.a11y-check-btn').on('click', runChecks);
  });

  function checkIssues(elements, issue){
    elements.addClass('highlight-issue').attr('aria-label', issue).each(function(){
      $(this).after('<span class="error-message">'+issue+'.</span>');
    });
  }

  function checkHeadings(){
    if($('h1').length === 0){
      $('<h1 class="error-message">').text('Missing h1 tag').prependTo('body');
    }
    const headingLevels = { H1:1, H2:2, H3:3, H4:4, H5:5, H6:6 };
    let last = 0;
    $('h1, h2, h3, h4, h5, h6').each(function(){
      const $el = $(this);
      const lvl = headingLevels[$el.prop('tagName')];
      const tagName = $el.prop('tagName');
      $el.addClass('highlight-htag');
      if(lvl > last + 1){
        $el.attr('aria-label','Heading out of order')
          .after('<span class="error-message highlight-htag">Heading is out of order. Ensure proper heading hierarchy.</span>');
        $el.append('<span class="accessibility-bubble red">'+tagName+'</span>');
      }else{
        $el.append('<span class="accessibility-bubble green">'+tagName+'</span>');
      }
      last = lvl;
    });
  }

  function checkLinks(){
    $('a').each(function(){
      const text = $(this).text().trim();
      const href = $(this).attr('href');
      if(href && href !== '#' && href !== '/' && !href.includes('#') && !href.includes('javascript:')){
        if(!text || text === 'click here'){
          $(this).addClass('highlight-issue').attr('aria-label','Non-descriptive link text').each(function(){
            $(this).after('<span class="error-message">Non-descriptive link text. Use descriptive text that provides context for the link.</span>');
          });
        }
      }
    });
  }

  function checkColorContrast(){
    $('*').each(function(){
      const bg = $(this).css('background-color');
      const fg = $(this).css('color');
      if(bg && fg){
        const ratio = calculateContrastRatio(bg, fg);
        if(ratio < 4.5){
          $(this).addClass('highlight-issue').attr('aria-label','Insufficient color contrast').each(function(){
            $(this).after('<span class="error-message">Insufficient color contrast. Ensure text is readable against the background color.</span>');
          });
        }
      }
    });
  }

  function calculateContrastRatio(color1, color2){
    const lum1 = getRelativeLuminance(color1);
    const lum2 = getRelativeLuminance(color2);
    const lighter = Math.max(lum1, lum2);
    const darker = Math.min(lum1, lum2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function getRelativeLuminance(color){
    const rgb = parseColor(color);
    const r = adjustColor(rgb[0]);
    const g = adjustColor(rgb[1]);
    const b = adjustColor(rgb[2]);
    return 0.2126*r + 0.7152*g + 0.0722*b;
  }

  function adjustColor(value){
    value = value / 255;
    return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
  }

  function parseColor(color){
    let match;
    if(match = color.match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i)){
      return [parseInt(match[1],16), parseInt(match[2],16), parseInt(match[3],16)];
    } else if(match = color.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/)){
      return [parseInt(match[1]), parseInt(match[2]), parseInt(match[3])];
    }
    return [0,0,0];
  }

  function checkFormLabels(){
    $('input, select, textarea').each(function(){
      const label = $("label[for='"+$(this).attr('id')+"']");
      if(!label.length){
        $(this).addClass('highlight-issue').attr('aria-label','Missing associated label').each(function(){
          $(this).after('<span class="error-message">Missing associated label. Each form control must have a corresponding label.</span>');
        });
      }
    });
  }

  function checkLangAttribute(){
    if(!$('html').attr('lang')){
      $('html').addClass('highlight-issue').attr('aria-label','Missing language attribute').each(function(){
        $(this).after('<span class="error-message">Missing language attribute on HTML element. Use the "lang" attribute to specify the language of the page content.</span>');
      });
    }
  }

  function checkSemanticHTML(){
    $('*').each(function(){
      const tagName = $(this).prop('tagName').toLowerCase();
      if(['div','span'].includes(tagName) && !['header','nav','main','footer'].includes(tagName)){
        $(this).addClass('highlight-issue').attr('aria-label','Use of non-semantic HTML').each(function(){
          $(this).after('<span class="error-message">Use of non-semantic HTML. Consider using semantic elements like <header>, <nav>, <main>, <footer>, etc.</span>');
        });
      }
    });
  }

  function checkMediaAccessibility(){
    $('video, audio').each(function(){
      if(!$(this).find('track').length){
        $(this).addClass('highlight-issue').attr('aria-label','Missing captions or transcripts').each(function(){
          $(this).after('<span class="error-message">Missing captions or transcripts for multimedia content. Ensure accessibility for users with visual or hearing impairments.</span>');
        });
      }
    });
  }

  function checkTextResizing(){
    $('body').append('<div class="text-resize-test" style="font-size: 100%; position: absolute; left: -9999px;">Text resizing test</div>');
    const originalSize = $('.text-resize-test').css('font-size');
    $('.text-resize-test').css('font-size','200%');
    if($('.text-resize-test').css('font-size') !== originalSize){
      $('.text-resize-test').addClass('highlight-issue').attr('aria-label','Text resizing issue').each(function(){
        $(this).after('<span class="error-message">Text resizing issue. Ensure text can be resized up to 200% without loss of content or functionality.</span>');
      });
    }
    $('.text-resize-test').remove();
  }

  function checkResponsiveDesign(){
    if(!$("meta[name='viewport']").length){
      $('head').append('<meta name="viewport" content="width=device-width, initial-scale=1">');
    }
    const viewportMeta = $("meta[name='viewport']").attr('content');
    if(!viewportMeta.includes('width=device-width')){
      $("meta[name='viewport']").addClass('highlight-issue').attr('aria-label','Missing viewport settings').each(function(){
        $(this).after('<span class="error-message">Missing viewport settings. Ensure content is accessible on different screen sizes and orientations.</span>');
      });
    }
  }

  function checkKeyboardNavigation(){
    $('*').on('keydown', function(e){
      if(e.which === 9){
        $(this).addClass('highlight-issue').attr('aria-label','Keyboard navigation issue').each(function(){
          $(this).after('<span class="error-message">Keyboard navigation issue. Ensure all interactive elements are accessible via keyboard navigation.</span>');
        });
      }
    });
  }

  function checkFocusManagement(){
    $(':focus').each(function(){
      $(this).addClass('highlight-issue').attr('aria-label','Focus management issue').each(function(){
        $(this).after('<span class="error-message">Focus management issue. Ensure focus is managed correctly and visually noticeable.</span>');
      });
    });
  }

  function checkNotifications(){
    $('.alert, .notification').each(function(){
      if(!$(this).attr('role')){
        $(this).attr('role','alert').addClass('highlight-issue').attr('aria-label','Missing role attribute for notifications').each(function(){
          $(this).after('<span class="error-message">Missing role attribute for notifications. Ensure alerts and notifications are accessible and announced by screen readers.</span>');
        });
      }
    });
  }

  function checkDynamicContentUpdates(){
    const observer = new MutationObserver(function(mutations){
      mutations.forEach(function(m){
        if(m.addedNodes.length > 0){
          $(m.addedNodes).each(function(){
            $(this).addClass('highlight-issue').attr('aria-label','Dynamic content update').each(function(){
              $(this).after('<span class="error-message">Dynamic content update. Ensure updates to the content are announced to screen reader users.</span>');
            });
          });
        }
      });
    });
    observer.observe(document.body,{ childList:true, subtree:true });
  }

  function addSkipLink(){
    $('<a>').attr('href','#main-content').text('Skip to main content').prependTo('body');
  }
})(jQuery);
