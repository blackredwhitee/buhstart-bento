(function () {
  var HDR_H = 73;

  // ── 1. Фиксируем шапку ──────────────────────────────────────────────────
  // DC перерендеривает header после того как observer отработал — сбрасывает
  // стиль обратно на sticky. Решение: observer не отключаем, применяем fix
  // при каждом изменении DOM с дебаунсом.

  function fixHeader() {
    var hdr = document.querySelector('#dc-root header');
    if (!hdr) return;
    if (window.getComputedStyle(hdr).position === 'fixed') return; // уже ок
    hdr.style.setProperty('position', 'fixed', 'important');
    hdr.style.setProperty('top', '0', 'important');
    hdr.style.setProperty('left', '0', 'important');
    hdr.style.setProperty('right', '0', 'important');
    hdr.style.setProperty('width', '100%', 'important');
    hdr.style.setProperty('z-index', '100', 'important');
    // Распорка
    if (!document.querySelector('[data-hdr-spacer]')) {
      var sp = document.createElement('div');
      sp.setAttribute('data-hdr-spacer', '1');
      sp.style.cssText = 'height:' + HDR_H + 'px;flex-shrink:0;';
      hdr.parentNode.insertBefore(sp, hdr.nextSibling);
    }
  }

  var timer = null;
  var obs = new MutationObserver(function () {
    clearTimeout(timer);
    timer = setTimeout(fixHeader, 50);
  });
  obs.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
  fixHeader();

  // ── 2. Кнопка «Наверх» ──────────────────────────────────────────────────
  var bs = document.createElement('style');
  bs.textContent =
    '#btt-btn{position:fixed;right:20px;bottom:24px;z-index:160;' +
    'width:52px;height:52px;border-radius:50%;background:#fff;' +
    'border:1px solid #E8E8E8;box-shadow:0 8px 28px rgba(0,0,0,.14);' +
    'cursor:pointer;display:flex;align-items:center;justify-content:center;' +
    'opacity:0;pointer-events:none;' +
    'transition:opacity .25s ease,transform .25s ease,background .15s ease;' +
    'transform:translateY(10px)}' +
    '#btt-btn.btt-on{opacity:1;pointer-events:auto;transform:translateY(0)}' +
    '#btt-btn:hover{background:#F07828}' +
    '#btt-btn:hover svg path{stroke:#fff}' +
    '@media(max-width:767px){#btt-btn{width:48px;height:48px;bottom:72px;right:16px}}';

  function initBackToTop() {
    if (document.getElementById('btt-btn')) return;
    document.body.appendChild(bs);
    var btn = document.createElement('button');
    btn.id = 'btt-btn';
    btn.setAttribute('aria-label', 'Наверх');
    btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none">' +
      '<path d="M10 15V5M10 5L5 10M10 5L15 10" stroke="#F07828" stroke-width="2"' +
      ' stroke-linecap="round" stroke-linejoin="round"/></svg>';
    document.body.appendChild(btn);
    btn.addEventListener('click', function () {
      var de = document.scrollingElement || document.documentElement;
      var sc = document.body.scrollHeight > de.scrollHeight ? document.body : de;
      sc.scrollTo ? sc.scrollTo({ top: 0, behavior: 'smooth' }) : (sc.scrollTop = 0);
    });
    document.addEventListener('scroll', function () {
      var de = document.scrollingElement || document.documentElement;
      var sc = document.body.scrollHeight > de.scrollHeight ? document.body : de;
      var total = sc.scrollHeight - sc.clientHeight;
      btn.classList.toggle('btt-on', total > 0 && sc.scrollTop / total > 0.72);
    }, { passive: true, capture: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBackToTop);
  } else {
    initBackToTop();
  }
})();
