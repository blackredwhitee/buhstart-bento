(function() {
  // 1. Scroll-reveal for sections and cards not already tagged
  var revealSelectors = [
    'section > div > h2',
    'section > div > h3',
    '.article-card',
    '[data-rv]'
  ];

  var io = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (e.isIntersecting) {
        e.target.classList.add('anim-visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  function initReveal() {
    // Auto-reveal: cards, service blocks, team cards, article cards
    var autoTargets = document.querySelectorAll(
      'a[data-rv], div[data-rv], ' +
      'section div[style*="border-radius:16px"], ' +
      'section div[style*="border-radius:10px"], ' +
      'section div[style*="border-radius:12px"]'
    );
    autoTargets.forEach(function(el, i) {
      if (!el.closest('[data-no-anim]')) {
        el.classList.add('anim-reveal');
        el.style.setProperty('--anim-delay', (i % 6) * 80 + 'ms');
        io.observe(el);
      }
    });

    // Headings inside sections
    var headings = document.querySelectorAll('section h1, section h2');
    headings.forEach(function(el) {
      if (!el.dataset.rv) {
        el.classList.add('anim-heading');
        io.observe(el);
      }
    });
  }

  // 2. Counter animation for stat numbers
  function animateCounter(el) {
    var text = el.textContent.trim();
    var match = text.match(/^(\d+)/);
    if (!match) return;
    var target = parseInt(match[1]);
    var suffix = text.replace(/^\d+/, '');
    var duration = 1200;
    var start = null;
    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(ease * target) + suffix;
      if (progress < 1) requestAnimationFrame(step);
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
  }, { threshold: 0.5 });

  function initCounters() {
    // Find elements that look like stat numbers (digits followed by + or лет etc)
    var all = document.querySelectorAll('*');
    all.forEach(function(el) {
      if (el.children.length > 0) return;
      var t = el.textContent.trim();
      if (/^\d{2,}[+₽%]?/.test(t) && el.offsetHeight < 80) {
        counterIo.observe(el);
      }
    });
  }

  // 3. Subtle parallax on hero section
  function initParallax() {
    var hero = document.querySelector('section:first-of-type');
    if (!hero) return;
    var img = hero.querySelector('img[style*="object-fit"]') || hero.querySelector('img');
    if (!img) return;
    window.addEventListener('scroll', function() {
      var offset = window.scrollY;
      if (offset < window.innerHeight) {
        img.style.transform = 'translateY(' + offset * 0.12 + 'px)';
      }
    }, { passive: true });
  }

  // Init on DOM ready
  function init() {
    initReveal();
    initCounters();
    initParallax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
