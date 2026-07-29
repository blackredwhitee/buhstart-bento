(function() {
  var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // 1. Scroll-reveal — только для элементов НИЖЕ первого экрана
  var io = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (e.isIntersecting) {
        e.target.classList.add('anim-visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

  function isBelowFold(el) {
    var rect = el.getBoundingClientRect();
    return rect.top > window.innerHeight * 0.85;
  }

  function initReveal() {
    if (prefersReduced) return;
    var cards = document.querySelectorAll(
      'section div[style*="border-radius:16px"], ' +
      'section div[style*="border-radius:10px"], ' +
      'section a[style*="border-radius:10px"], ' +
      'section a[style*="border-radius:16px"]'
    );
    cards.forEach(function(el, i) {
      if (!isBelowFold(el)) return; // не трогаем видимые элементы
      el.classList.add('anim-reveal');
      el.style.setProperty('--anim-delay', (i % 4) * 90 + 'ms');
      io.observe(el);
    });
  }

  // 2. Counter animation
  function animateCounter(el) {
    var text = el.textContent.trim();
    var match = text.match(/^(\d+)/);
    if (!match) return;
    var target = parseInt(match[1]);
    var suffix = text.replace(/^\d+/, '');
    var duration = 1400;
    var start = null;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      var ease = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(ease * target) + suffix;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  var counterIo = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (e.isIntersecting) {
        animateCounter(e.target);
        counterIo.unobserve(e.target);
      }
    });
  }, { threshold: 0.6 });

  function initCounters() {
    if (prefersReduced) return;
    var all = document.querySelectorAll('*');
    all.forEach(function(el) {
      if (el.children.length > 0) return;
      var t = el.textContent.trim();
      if (/^\d{2,}[+₽%]?$/.test(t) && el.offsetHeight < 80 && el.offsetWidth < 300) {
        counterIo.observe(el);
      }
    });
  }

  function init() {
    initReveal();
    initCounters();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    setTimeout(init, 50);
  }
})();
