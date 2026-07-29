(function () {
  // Sticky header: CSS injection survives DC framework iframe recreation
  var s = document.createElement('style');
  s.textContent = [
    'body>iframe:first-of-type{',
    'position:fixed!important;',
    'top:0!important;left:0!important;',
    'width:100vw!important;height:72px!important;',
    'z-index:200!important;',
    '}'
  ].join('');
  (document.head || document.documentElement).appendChild(s);

  function applyPadding() {
    if (document.body) document.body.style.paddingTop = '72px';
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyPadding);
  } else {
    applyPadding();
  }

  // Back-to-top button
  function backToTop() {
    if (document.getElementById('btt-btn')) return;
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
      'transition:opacity 0.25s ease,transform 0.25s ease',
      'transform:translateY(12px)',
      'display:flex', 'align-items:center', 'justify-content:center',
      'font-family:sans-serif'
    ].join(';');
    document.body.appendChild(btn);

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    var visible = false;
    window.addEventListener('scroll', function () {
      var should = (window.scrollY || document.documentElement.scrollTop) > 400;
      if (should !== visible) {
        visible = should;
        btn.style.opacity = visible ? '1' : '0';
        btn.style.transform = visible ? 'translateY(0)' : 'translateY(12px)';
        btn.style.pointerEvents = visible ? 'auto' : 'none';
      }
    }, { passive: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', backToTop);
  } else {
    backToTop();
  }
})();
