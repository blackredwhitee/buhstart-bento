(function () {
  var HDR_H = 73; // px — реальная высота шапки

  // ── 1. CSS: переопределяем inline position:sticky на шапке ──────────────
  var s = document.createElement('style');
  s.textContent =
    '#dc-root header{' +
      'position:fixed!important;top:0!important;left:0!important;' +
      'right:0!important;width:100%!important;z-index:100!important;' +
    '}' +
    // Кнопка «Наверх»
    '#btt-btn{' +
      'position:fixed;right:20px;bottom:24px;z-index:160;' +
      'width:52px;height:52px;border-radius:50%;' +
      'background:#fff;border:1px solid #E8E8E8;' +
      'box-shadow:0 8px 28px rgba(0,0,0,.14);' +
      'cursor:pointer;display:flex;align-items:center;justify-content:center;' +
      'opacity:0;pointer-events:none;' +
      'transition:opacity .25s ease,transform .25s ease,background .15s ease;' +
      'transform:translateY(10px);' +
    '}' +
    '#btt-btn.btt-visible{opacity:1;pointer-events:auto;transform:translateY(0);}' +
    '#btt-btn:hover{background:#F07828;}' +
    '#btt-btn:hover svg path{stroke:#fff;}' +
    '@media(max-width:767px){#btt-btn{width:48px;height:48px;bottom:72px;}}';
  (document.head || document.documentElement).appendChild(s);

  // ── 2. JS: вставляем распорку сразу после <header> ──────────────────────
  var spacerDone = false;
  function insertSpacer() {
    if (spacerDone) return;
    var hdr = document.querySelector('#dc-root header');
    if (!hdr || !hdr.nextSibling) return;
    // Не вставлять если распорка уже есть
    var next = hdr.nextElementSibling;
    if (next && next.dataset && next.dataset.spacer) return;
    var div = document.createElement('div');
    div.dataset.spacer = '1';
    div.style.cssText = 'height:' + HDR_H + 'px;flex-shrink:0;';
    hdr.parentNode.insertBefore(div, hdr.nextSibling);
    spacerDone = true;
  }

  var obs = new MutationObserver(function () {
    insertSpacer();
    if (spacerDone) obs.disconnect();
  });
  obs.observe(document.documentElement, { childList: true, subtree: true });
  insertSpacer(); // на случай если уже отрендерено

  // ── 3. Кнопка «Наверх» ──────────────────────────────────────────────────
  function initBackToTop() {
    if (document.getElementById('btt-btn')) return;
    var btn = document.createElement('button');
    btn.id = 'btt-btn';
    btn.setAttribute('aria-label', 'Наверх');
    btn.innerHTML =
      '<svg width="20" height="20" viewBox="0 0 20 20" fill="none">' +
      '<path d="M10 15V5M10 5L5 10M10 5L15 10"' +
      ' stroke="#F07828" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';
    document.body.appendChild(btn);

    btn.addEventListener('click', function () {
      var de = document.scrollingElement || document.documentElement;
      var sc = document.body.scrollHeight > de.scrollHeight ? document.body : de;
      sc.scrollTo ? sc.scrollTo({ top: 0, behavior: 'smooth' }) : (sc.scrollTop = 0);
    });

    document.addEventListener('scroll', function () {
      var de = document.scrollingElement || document.documentElement;
      var sc = document.body.scrollHeight > de.scrollHeight ? document.body : de;
      var scrolled = sc.scrollTop;
      var total = sc.scrollHeight - sc.clientHeight;
      var show = total > 0 && scrolled / total > 0.72;
      if (show) btn.classList.add('btt-visible');
      else btn.classList.remove('btt-visible');
    }, { passive: true, capture: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBackToTop);
  } else {
    initBackToTop();
  }
})();
