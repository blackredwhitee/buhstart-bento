(function() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  // Counter animation for stat numbers
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
  }, { threshold: 0.8 });

  function initCounters() {
    document.querySelectorAll('*').forEach(function(el) {
      if (el.children.length > 0) return;
      var t = el.textContent.trim();
      if (/^\d{2,}[+₽%]?$/.test(t) && el.offsetHeight < 80 && el.offsetWidth < 300) {
        counterIo.observe(el);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCounters);
  } else {
    setTimeout(initCounters, 100);
  }
})();
