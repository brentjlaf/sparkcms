/* File: combined.js - merged from global.js, script.js */
/* File: global.js */

/* File: script.js */
// File: script.js
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
});

