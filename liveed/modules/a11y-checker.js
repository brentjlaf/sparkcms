// File: a11y-checker.js
(function($){
  // Create hidden checker button
  var checkerBtn = $('<div class="accessibility-checker" role="button" aria-label="Check Accessibility">Check Accessibility</div>');
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
  $(document).on('mouseup', function(e){ if(e.button === 0){ $(document).off('keydown.accessibilityCheck'); } });

  function checkIssues($elements, issue){
    $elements.addClass('highlight-issue').attr('aria-label', issue).each(function(){
      $(this).after('<span class="error-message">'+issue+'.</span>');
    });
  }

  function checkHeadings($root){
    if($root.find('h1').length === 0){ $('<h1 class="error-message">Missing h1 tag</h1>').prependTo($root); }
    var headingLevels = {H1:1,H2:2,H3:3,H4:4,H5:5,H6:6};
    var last = 0;
    $root.find('h1, h2, h3, h4, h5, h6').each(function(){
      var $t = $(this); var cur = headingLevels[$t.prop('tagName')]; var tag = $t.prop('tagName');
      $t.addClass('highlight-htag');
      if(cur > last + 1){
        $t.attr('aria-label','Heading out of order').after('<span class="error-message highlight-htag">Heading is out of order. Ensure proper heading hierarchy.</span>');
        $t.append('<span class="accessibility-bubble red">'+tag+'</span>');
      } else {
        $t.append('<span class="accessibility-bubble green">'+tag+'</span>');
      }
      last = cur;
    });
  }

  function checkLinks($root){
    $root.find('a').each(function(){
      var txt = $(this).text().trim();
      var href = $(this).attr('href');
      if(href && href !== '#' && href !== '/' && !href.includes('#') && !href.includes('javascript:')){
        if(!txt || txt === 'click here'){
          $(this).addClass('highlight-issue').attr('aria-label','Non-descriptive link text').each(function(){
            $(this).after('<span class="error-message">Non-descriptive link text. Use descriptive text that provides context for the link.</span>');
          });
        }
      }
    });
  }

  function parseColor(color){
    var match;
    if((match = color.match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i))){
      return [parseInt(match[1],16),parseInt(match[2],16),parseInt(match[3],16)];
    } else if((match = color.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/))){
      return [parseInt(match[1]),parseInt(match[2]),parseInt(match[3])];
    }
    return null;
  }
  function adjustColor(val){ val = val/255; return val <= 0.03928 ? val/12.92 : Math.pow((val+0.055)/1.055,2.4); }
  function getRelativeLuminance(color){ var rgb = parseColor(color); if(!rgb) return 1; var r=adjustColor(rgb[0]),g=adjustColor(rgb[1]),b=adjustColor(rgb[2]); return 0.2126*r + 0.7152*g + 0.0722*b; }
  function calculateContrastRatio(c1,c2){ var l1=getRelativeLuminance(c1); var l2=getRelativeLuminance(c2); var lighter=Math.max(l1,l2); var darker=Math.min(l1,l2); return (lighter+0.05)/(darker+0.05); }

  function checkColorContrast($root){
    $root.find('*').each(function(){
      var bg = $(this).css('background-color');
      var col = $(this).css('color');
      if(bg && col){
        var ratio = calculateContrastRatio(bg,col);
        if(ratio < 4.5){
          $(this).addClass('highlight-issue').attr('aria-label','Insufficient color contrast').each(function(){
            $(this).after('<span class="error-message">Insufficient color contrast. Ensure text is readable against the background color.</span>');
          });
        }
      }
    });
  }

  function checkFormLabels($root){
    $root.find('input, select, textarea').each(function(){
      var label = $('label[for="'+$(this).attr('id')+'"]');
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
        $(this).after('<span class="error-message">Missing language attribute on HTML element. Use the \"lang\" attribute to specify the language of the page content.</span>');
      });
    }
  }

  function checkSemanticHTML($root){
    $root.find('*').each(function(){
      var tag = $(this).prop('tagName').toLowerCase();
      if(['div','span'].includes(tag) && !['header','nav','main','footer'].includes(tag)){
        $(this).addClass('highlight-issue').attr('aria-label','Use of non-semantic HTML').each(function(){
          $(this).after('<span class="error-message">Use of non-semantic HTML. Consider using semantic elements like <header>, <nav>, <main>, <footer>, etc.</span>');
        });
      }
    });
  }

  function checkMediaAccessibility($root){
    $root.find('video, audio').each(function(){
      if(!$(this).find('track').length){
        $(this).addClass('highlight-issue').attr('aria-label','Missing captions or transcripts').each(function(){
          $(this).after('<span class="error-message">Missing captions or transcripts for multimedia content. Ensure accessibility for users with visual or hearing impairments.</span>');
        });
      }
    });
  }

  function checkTextResizing(){
    $('body').append('<div class="text-resize-test" style="font-size:100%;position:absolute;left:-9999px;">Text resizing test</div>');
    var originalSize = $('.text-resize-test').css('font-size');
    $('.text-resize-test').css('font-size','200%');
    if($('.text-resize-test').css('font-size') !== originalSize){
      $('.text-resize-test').addClass('highlight-issue').attr('aria-label','Text resizing issue').each(function(){
        $(this).after('<span class="error-message">Text resizing issue. Ensure text can be resized up to 200% without loss of content or functionality.</span>');
      });
    }
    $('.text-resize-test').remove();
  }

  function checkResponsiveDesign(){
    if(!$('meta[name="viewport"]').length){ $('head').append('<meta name="viewport" content="width=device-width, initial-scale=1">'); }
    var viewportMeta = $('meta[name="viewport"]').attr('content');
    if(!viewportMeta.includes('width=device-width')){
      $('meta[name="viewport"]').addClass('highlight-issue').attr('aria-label','Missing viewport settings').each(function(){
        $(this).after('<span class="error-message">Missing viewport settings. Ensure content is accessible on different screen sizes and orientations.</span>');
      });
    }
  }

  function checkKeyboardNavigation($root){
    $root.find('*').on('keydown', function(e){
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

  function checkNotifications($root){
    $root.find('.alert, .notification').each(function(){
      if(!$(this).attr('role')){
        $(this).attr('role','alert').addClass('highlight-issue').attr('aria-label','Missing role attribute for notifications').each(function(){
          $(this).after('<span class="error-message">Missing role attribute for notifications. Ensure alerts and notifications are accessible and announced by screen readers.</span>');
        });
      }
    });
  }

  function checkDynamicContentUpdates(){
    var observer = new MutationObserver(function(mutations){
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
    observer.observe(document.body,{childList:true,subtree:true});
  }

  function addSkipLink(){
    if(!$('a.skip-main').length){
      $('<a class="skip-main" href="#main-content">Skip to main content</a>').prependTo('body');
    }
  }

  function runChecks(){
    var $root = $('#canvas');
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
