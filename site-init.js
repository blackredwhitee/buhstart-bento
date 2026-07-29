// Sticky header + back-to-top button for all pages
(function () {
  // Make SiteHeader iframe sticky after DC renders it
  function stickyHeader() {
    var done = false;
    var obs = new MutationObserver(function () {
      if (done) return;
      var frames = document.querySelectorAll('body iframe');
      if (frames.length > 0) {
        var hdr = frames[0];
        hdr.style.cssText += ';position:sticky!important;top:0!important;z-index:200!important;width:100%!important;display:block!important;';
        done = true;
        obs.disconnect();
      }
    });
    obs.observe(document.body || document.documentElement, { childList: true, subtree: true });
  }

  // Back-to-top button
  function backToTop() {
    var btn = document.createElement('button');
    btn.id = 'btt-btn';
    btn.innerHTML = '&#8679;';
    btn.title = 'Наверх';
    btn.setAttribute('aria-label', 'Наверх');
    btn.style.cssText = [
      'position:fixed', 'bottom:28px', 'right:28px', 'z-index:9999',
      'width:48px', 'height:48px', 'border-radius:50%',
      'background:#F07828', 'color:#fff', 'border:none',
      'font-size:26px', 'line-height:1', 'cursor:pointer',
      'box-shadow:0 4px 16px rgba(240,120,40,0.35)',
      'opacity:0', 'pointer-events:none',
      'transition:opacity 0.25s ease, transform 0.25s ease',
      'transform:translateY(12px)',
      'display:flex', 'align-items:center', 'justify-content:center',
      'font-family:sans-serif'
    ].join(';');
    document.body.appendChild(btn);

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    var visible = false;
    function onScroll() {
      var scrolled = window.scrollY || document.documentElement.scrollTop;
      var shouldShow = scrolled > 400;
      if (shouldShow !== visible) {
        visible = shouldShow;
        btn.style.opacity = visible ? '1' : '0';
        btn.style.transform = visible ? 'translateY(0)' : 'translateY(12px)';
        btn.style.pointerEvents = visible ? 'auto' : 'none';
      }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      stickyHeader();
      backToTop();
    });
  } else {
    stickyHeader();
    backToTop();
  }
})();
