// общий скрипт сайта «Доверительная Бухгалтерия»

/* Аналитика. Когда появится номер счётчика Яндекс.Метрики:
   1. вписать его в YM_ID ниже;
   2. вставить код счётчика на страницы (одной строкой перед </body>).
   Цели уже расставлены — заработают сразу, дополнительно настраивать в коде ничего не нужно.
   Список целей: lead_open, lead_sent, question_sent, vacancy_sent, calc_start,
   calc_done, calc_kp, risk_done, calendar_click, phone_click, messenger_click. */
var YM_ID = null;

function track(goal, params){
  try{
    if(YM_ID && typeof ym === 'function') ym(YM_ID, 'reachGoal', goal, params || {});
    if(typeof gtag === 'function') gtag('event', goal, params || {});
    if(window.dataLayer) window.dataLayer.push(Object.assign({event: goal}, params || {}));
  }catch(e){}
}
var ENDPOINT = 'https://script.google.com/macros/s/AKfycbxLWOlaftsjU3tG0r3q95i5zaj20uvtmTru9ZIHisSUIj5FTvn2oYAJKz3e4N2SDQa7jQ/exec';

function send(payload){
  try{
    fetch(ENDPOINT,{method:'POST',mode:'no-cors',headers:{'Content-Type':'text/plain;charset=utf-8'},body:JSON.stringify(payload)});
  }catch(e){}
}

document.addEventListener('DOMContentLoaded', function(){
  // мобильное меню
  var burger = document.querySelector('.burger'), menu = document.querySelector('.mobile-menu');
  if(burger && menu) burger.addEventListener('click', function(){ menu.classList.toggle('open'); });

  // наверх
  var top = document.querySelector('.fab .top');
  if(top) top.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });

  // аккордеоны
  document.querySelectorAll('.faq').forEach(function(faq){
    faq.querySelectorAll('.item').forEach(function(item){
      var q = item.querySelector('.q');
      q.addEventListener('click', function(){
        var open = item.classList.contains('open');
        faq.querySelectorAll('.item').forEach(function(i){ i.classList.remove('open'); i.querySelector('.q i').textContent = '+'; });
        if(!open){ item.classList.add('open'); q.querySelector('i').textContent = '\u2212'; }
      });
    });
  });

  // модальное окно «Задать вопрос» / «Записаться»
  var modal = document.querySelector('.modal');
  function openModal(kind, situation){
    if(!modal) return;
    modal.classList.add('open');
    modal.querySelector('.done').classList.remove('show');
    modal.querySelector('form').style.display = 'flex';
    var isAsk = kind === 'ask';
    var isPersonal = /^Личное обращение/.test(situation || '');
    modal.querySelector('.m-kicker').textContent = isAsk ? 'Вопрос бухгалтеру' : (isPersonal ? 'Руководителю лично' : 'Заявка');
    modal.querySelector('.m-title').textContent = isAsk ? 'Спросите — ответим'
      : isPersonal ? 'Написать руководителю'
      : (situation ? 'Уточнить цену' : 'Записаться на консультацию');
    modal.querySelector('.m-sub').textContent = isPersonal
      ? 'Обращение читает лично Горелкина Галина Викторовна, руководитель компании.'
      : situation ? ('Ситуация: ' + situation)
      : (isAsk ? 'Короткий вопрос по учёту, налогам или отчётности. Отвечаем в рабочее время.'
               : 'Оставьте контакты — перезвоним в течение 2 часов в рабочее время.');
    modal.querySelector('.m-question').style.display = (isAsk || isPersonal) ? 'block' : 'none';
    modal.dataset.source = isPersonal ? 'Личное обращение к руководителю'
      : situation ? ('Уточнить цену: ' + situation)
      : (isAsk ? 'Вопрос бухгалтеру' : 'Записаться на консультацию');
  }
  document.querySelectorAll('[data-ask]').forEach(function(b){ b.addEventListener('click', function(){ openModal('ask'); track('lead_open', {kind:'question'}); }); });
  document.querySelectorAll('[data-lead]').forEach(function(b){ b.addEventListener('click', function(){ openModal('lead', b.dataset.lead || ''); track('lead_open', {kind: b.dataset.lead || 'lead'}); }); });
  if(modal){
    modal.addEventListener('click', function(e){ if(e.target === modal) modal.classList.remove('open'); });
    modal.querySelectorAll('[data-close]').forEach(function(b){ b.addEventListener('click', function(){ modal.classList.remove('open'); }); });
  }

  // формы: согласие обязательно
  document.querySelectorAll('form[data-form]').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var agree = form.querySelector('input[type=checkbox][required], input[name=agree]');
      var err = form.querySelector('.formerr');
      if(agree && !agree.checked){ if(err) err.classList.add('show'); return; }
      if(err) err.classList.remove('show');
      var data = {type: form.dataset.form || 'lead'};
      var fileEl = null;
      form.querySelectorAll('input,textarea,select').forEach(function(el){
        if(el.type === 'checkbox' || !el.name) return;
        if(el.type === 'file'){ fileEl = el; return; }
        data[el.name] = el.value;
      });
      // ссылка на резюме уходит в поле, которое читает скрипт таблицы
      if(data.resume){ data.resumeLink = data.resume; delete data.resume; }
      // контакт с собакой — это почта, а не телефон
      if(data.contact && data.contact.indexOf('@') > -1 && !data.email){ data.email = data.contact; }
      var box = form.closest('.formbox') || form.closest('.box') || form.parentNode;
      data.comment = [data.comment, 'Источник: ' + (form.dataset.source || (modal && modal.dataset.source) || document.title)].filter(Boolean).join(' | ');

      function finish(){
        send(data);
        var goal = data.type === 'vacancy' ? 'vacancy_sent'
                 : (data.comment || '').indexOf('Вопрос бухгалтеру') > -1 ? 'question_sent' : 'lead_sent';
        track(goal, {source: form.dataset.source || (modal && modal.dataset.source) || document.title});
        form.style.display = 'none';
        var done = box.querySelector('.done');
        if(done) done.classList.add('show');
        form.reset();
      }

      var file = fileEl && fileEl.files && fileEl.files[0];
      if(file){
        var okType = /\.(pdf|doc|docx)$/i.test(file.name);
        if(!okType){ if(err){ err.textContent = 'Резюме принимаем в PDF, DOC или DOCX'; err.classList.add('show'); } return; }
        if(file.size > 10 * 1024 * 1024){ if(err){ err.textContent = 'Файл больше 10 МБ — пришлите ссылку'; err.classList.add('show'); } return; }
        var r = new FileReader();
        r.onload = function(){
          data.resumeBase64 = String(r.result).split(',')[1] || '';
          data.resumeName = file.name;
          data.resumeMime = file.type || 'application/octet-stream';
          finish();
        };
        r.onerror = function(){ finish(); };
        r.readAsDataURL(file);
        return;
      }
      finish();
    });
    form.querySelectorAll('input[type=checkbox]').forEach(function(cb){
      cb.addEventListener('change', function(){ var err = form.querySelector('.formerr'); if(err && cb.checked) err.classList.remove('show'); });
    });
  });
});

/* ---------- оживление: появление блоков, счётчики, наклон, магнитная кнопка ---------- */
document.addEventListener('DOMContentLoaded', function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = window.matchMedia('(hover:hover) and (pointer:fine)').matches;

  // --- появление блоков при прокрутке, друг за другом ---
  if(!reduce && 'IntersectionObserver' in window){
    var groups = [];
    document.querySelectorAll('main>section').forEach(function(s){
      var kids = s.querySelectorAll(':scope>*, :scope>.grid>*, :scope>.stack>*, :scope>.tile>.grid>*');
      groups.push(kids.length ? kids : [s]);
    });
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if(!e.isIntersecting) return;
        e.target.classList.add('rv-in');
        io.unobserve(e.target);
      });
    }, {rootMargin:'0px 0px -8% 0px', threshold:0.08});
    var watched = [];
    groups.forEach(function(kids){
      Array.prototype.forEach.call(kids, function(el, i){
        if(el.classList.contains('rv')) return;
        // то, что уже видно при загрузке, не скрываем — первый экран должен быть на месте
        if(el.getBoundingClientRect().top < window.innerHeight * 0.9){ el.classList.add('rv','rv-in'); return; }
        el.classList.add('rv');
        el.style.transitionDelay = Math.min(i, 6) * 60 + 'ms';
        io.observe(el);
        watched.push(el);
      });
    });
    // страховка: если наблюдатель не сработал, показываем всё, что попало в экран
    function rescue(){
      var h = window.innerHeight || 800;
      watched = watched.filter(function(el){
        if(el.classList.contains('rv-in')) return false;
        if(el.getBoundingClientRect().top < h){ el.classList.add('rv-in'); return false; }
        return true;
      });
      if(!watched.length) window.removeEventListener('scroll', onScroll);
    }
    var onScroll = function(){ clearTimeout(onScroll.t); onScroll.t = setTimeout(rescue, 400); };
    window.addEventListener('scroll', onScroll, {passive:true});
    setTimeout(rescue, 1500);
  }

  // --- счётчики цифр ---
  function countUp(el){
    var raw = el.textContent.trim();
    var m = raw.match(/^(\D*)(\d+(?:[.,]\d+)?)(.*)$/);
    if(!m) return;
    var pre = m[1], target = m[2], post = m[3];
    if(/[А-Яа-яA-Za-z]/.test(post)) return;      // «2 часа» крутить не надо
    if(/^(19|20)\d{2}$/.test(raw)) return;       // год основания — тоже не крутим
    var frac = target.indexOf(',') > -1 ? 1 : 0;
    var end = parseFloat(target.replace(',', '.'));
    if(!(end > 0)) return;
    var t0 = null, dur = 900;
    // в скрытой вкладке requestAnimationFrame не идёт: цифра застыла бы на «1+» вместо «20+»
    if(document.hidden) return;
    document.addEventListener('visibilitychange', function(){ if(document.hidden) el.textContent = raw; });
    function frame(ts){
      if(t0 === null) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur);
      var v = end * (1 - Math.pow(1 - p, 3));
      if(p < 1){
        el.textContent = pre + (frac ? v.toFixed(1).replace('.', ',') : Math.round(v)) + post;
        requestAnimationFrame(frame);
      } else {
        el.textContent = raw;   // в конце всегда точное исходное значение
      }
    }
    requestAnimationFrame(frame);
  }
  if(!reduce && 'IntersectionObserver' in window){
    var cio = new IntersectionObserver(function(entries){
      entries.forEach(function(e){ if(e.isIntersecting){ countUp(e.target); cio.unobserve(e.target); } });
    }, {threshold:0.6});
    document.querySelectorAll('.num').forEach(function(el){ cio.observe(el); });
  }

  // --- наклон и подсветка: только кликабельные карточки ---
  if(!reduce && fine){
    document.querySelectorAll('a.post, a.card, a.person, .nrow').forEach(function(card){
      card.classList.add('tiltable');
      card.addEventListener('pointermove', function(e){
        var r = card.getBoundingClientRect();
        var dx = (e.clientX - r.left) / r.width - .5, dy = (e.clientY - r.top) / r.height - .5;
        card.style.transform = 'perspective(900px) rotateX(' + (-dy * 4).toFixed(2) + 'deg) rotateY(' + (dx * 4).toFixed(2) + 'deg) translateY(-3px)';
        card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
        card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
      });
      card.addEventListener('pointerleave', function(){ card.style.transform = ''; });
    });

    // --- магнитная кнопка ---
    document.querySelectorAll('.btn-p').forEach(function(b){
      b.addEventListener('pointermove', function(e){
        var r = b.getBoundingClientRect();
        b.style.transform = 'translate(' + ((e.clientX - r.left - r.width / 2) * .12).toFixed(1) + 'px,' + ((e.clientY - r.top - r.height / 2) * .18).toFixed(1) + 'px)';
      });
      b.addEventListener('pointerleave', function(){ b.style.transform = ''; });
    });
  }

  // --- живой тизер калькулятора на главной ---
  var ct = document.getElementById('calc-teaser');
  if(ct && !reduce){
    var STEPS = [
      {n:1, q:'Вы ИП или ООО?', opts:['ИП','ООО','Пока выбираю'], pick:1},
      {n:2, q:'Ведёте деятельность?', opts:['Да, есть обороты','Нулевая отчётность','Только открылись'], pick:0},
      {n:3, q:'Система налогообложения?', opts:['УСН 6%','УСН 15%','ОСНО'], pick:0},
      {n:4, q:'Вид деятельности?', opts:['Услуги','Торговля','Производство'], pick:1},
      {n:5, q:'Есть сотрудники в штате?', opts:['Нет','Да, есть'], pick:1},
      {n:6, q:'Нужен бухгалтер в офисе?', opts:['Да, нужен','Нет, не нужен'], pick:1}
    ];
    var TOTAL = 8;
    var i = 0;
    function draw(){
      var s = STEPS[i];
      ct.querySelector('.ct-bar span').style.width = (s.n / TOTAL * 100).toFixed(0) + '%';
      var q = ct.querySelector('.ct-q'), st = ct.querySelector('.ct-step');
      q.style.opacity = 0;
      setTimeout(function(){
        st.textContent = 'Шаг ' + s.n + ' из ' + TOTAL;
        q.textContent = s.q;
        q.style.opacity = 1;
      }, 180);
      ct.querySelectorAll('.ct-opt').forEach(function(o, k){
        o.style.opacity = 0;
        setTimeout(function(){
          if(k >= s.opts.length){ o.style.display = 'none'; return }
          o.style.display = '';
          o.textContent = s.opts[k];
          o.classList.toggle('on', k === s.pick);
          o.style.opacity = 1;
        }, 180 + k * 70);
      });
      i = (i + 1) % STEPS.length;
    }
    draw();
    var timer = setInterval(draw, 2600);
    // пауза только пока курсор над тизером — раньше анимация умирала после первого наведения
    ct.addEventListener('pointerenter', function(){ clearInterval(timer); });
    ct.addEventListener('pointerleave', function(){ clearInterval(timer); timer = setInterval(draw, 2600); });
  }
});

/* ---------- календарь отчётности: ближайшие сроки ---------- */
document.addEventListener('DOMContentLoaded', function(){
  var box = document.getElementById('cal');
  if(!box) return;

  // повторяющиеся сроки: день месяца → что сдавать. Месяцы: 1–12, 0 = каждый месяц
  var RULES = [
    {day:25, months:[1,4,7,10], what:'НДС — уплата 1/3 за квартал',                who:'ОСНО'},
    {day:25, months:[1,4,7,10], what:'Декларация по НДС за квартал',               who:'ОСНО'},
    {day:25, months:[4,7,10],   what:'Аванс по УСН за квартал',                    who:'УСН'},
    {day:25, months:[4,7,10],   what:'Расчёт по страховым взносам (РСВ)',          who:'с сотрудниками'},
    {day:25, months:[4,7,10],   what:'6-НДФЛ за квартал',                          who:'с сотрудниками'},
    {day:25, months:[0],        what:'Уведомление по ЕНП',                         who:'все режимы'},
    {day:28, months:[0],        what:'Уплата налогов по ЕНП',                      who:'все режимы'},
    {day:25, months:[0],        what:'Персонифицированные сведения о физлицах',    who:'с сотрудниками'},
    {day:25, months:[1],        what:'Декларация по УСН за прошлый год: ООО до 25.03, ИП до 25.04', who:'УСН'},
    {day:25, months:[3],        what:'Декларация по УСН за год — ООО',             who:'УСН, ООО'},
    {day:25, months:[4],        what:'Декларация по УСН за год — ИП',              who:'УСН, ИП'},
    {day:31, months:[3],        what:'Бухгалтерская отчётность за прошлый год',    who:'ООО'},
    {day:15, months:[0],        what:'Взносы на травматизм и ЕФС-1 (при событиях)',who:'с сотрудниками'},
    {day:31, months:[12],       what:'Фиксированные взносы ИП за себя',           who:'ИП'}
  ];

  var MONTHS = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
  var now = new Date(), today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  function nextDate(rule){
    for(var add = 0; add < 14; add++){
      var d = new Date(today.getFullYear(), today.getMonth() + add, 1);
      var m = d.getMonth() + 1;
      if(rule.months[0] !== 0 && rule.months.indexOf(m) < 0) continue;
      var last = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
      var day = Math.min(rule.day, last);
      var when = new Date(d.getFullYear(), d.getMonth(), day);
      // срок переносится с выходного на ближайший рабочий день
      while(when.getDay() === 0 || when.getDay() === 6) when.setDate(when.getDate() + 1);
      if(when >= today) return when;
    }
    return null;
  }

  var all = RULES.map(function(r){ return {when: nextDate(r), r: r} })
                 .filter(function(x){ return x.when })
                 .sort(function(a, b){ return a.when - b.when });
  // показываем ближайшие 60 дней, но не меньше трёх строк
  var near = all.filter(function(x){ return (x.when - today) / 86400000 <= 60 });
  var items = (near.length >= 3 ? near : all).slice(0, 5);

  function plural(n){
    var a = n % 10, b = n % 100;
    if(b > 10 && b < 20) return 'дней';
    if(a === 1) return 'день';
    if(a > 1 && a < 5) return 'дня';
    return 'дней';
  }

  box.innerHTML = items.map(function(x){
    var left = Math.round((x.when - today) / 86400000);
    var urgent = left <= 7;
    return '<div class="cal-row' + (urgent ? ' cal-hot' : '') + '">'
      + '<div class="cal-date"><b>' + x.when.getDate() + '</b><span>' + MONTHS[x.when.getMonth()] + '</span></div>'
      + '<div class="cal-what"><div class="cal-title">' + x.r.what + '</div><div class="cal-who">' + x.r.who + '</div></div>'
      + '<div class="cal-left">' + (left === 0 ? 'сегодня' : 'через ' + left + ' ' + plural(left)) + '</div>'
      + '</div>';
  }).join('');
});

/* ---------- тест «риск налоговой проверки» по открытым критериям ФНС ---------- */
document.addEventListener('DOMContentLoaded', function(){
  var wrap = document.getElementById('risk');
  if(!wrap) return;

  var QS = [
    'Зарплата у сотрудников ниже средней по вашей отрасли в регионе?',
    'Компания показывала убыток два года подряд или дольше?',
    'Доля вычетов по НДС держится выше 89% от начисленного налога?',
    'Расходы растут быстрее доходов?',
    'Показатели вплотную подошли к лимитам вашего спецрежима — по выручке или сотрудникам?',
    'Есть контрагенты, о которых почти ничего не известно: нет сайта, массовый адрес, нет договора на руках?'
  ];

  var i = 0, score = 0;
  var qEl = document.getElementById('risk-q'), body = document.getElementById('risk-body'),
      res = document.getElementById('risk-res'), fill = document.getElementById('risk-fill'),
      cnt = document.getElementById('risk-count');

  function draw(){
    qEl.style.opacity = 0;
    setTimeout(function(){ qEl.textContent = QS[i]; qEl.style.opacity = 1; }, 140);
    fill.style.width = (i / QS.length * 100) + '%';
    cnt.textContent = 'Вопрос ' + (i + 1) + ' из ' + QS.length;
  }

  function answer(points){
    score += points;
    i++;
    if(i < QS.length){ draw(); return; }
    body.style.display = 'none';
    res.classList.add('show');
    var light = document.getElementById('risk-light'),
        verdict = document.getElementById('risk-verdict'),
        advice = document.getElementById('risk-advice');
    var level, text, tip;
    if(score <= 1){
      level = 'low';  text = 'Риск низкий';
      tip = 'По открытым критериям вы не выделяетесь. Это не гарантия — инспекция смотрит и на контрагентов, — но поводов для выездной проверки в ваших цифрах не видно.';
    } else if(score <= 3){
      level = 'mid';  text = 'Риск средний';
      tip = 'Вы попадаете под часть критериев. Обычно это лечится за один аудит: находим, что именно настораживает налоговую, и закрываем до того, как придёт требование.';
    } else {
      level = 'high'; text = 'Риск высокий';
      tip = 'Совпадений много — по таким признакам инспекция как раз и отбирает компании для выездной проверки. Стоит разобраться в отчётности не откладывая.';
    }
    light.className = 'risk-light ' + level;
    light.textContent = score + ' из ' + QS.length;
    verdict.textContent = text;
    advice.textContent = tip;
    // фиксируем результат, чтобы менеджер видел контекст обращения
    var m = document.querySelector('.modal');
    if(m) m.dataset.riskScore = score;
    track('risk_done', {score: score, level: level});
  }

  document.getElementById('risk-yes').addEventListener('click', function(){ answer(1) });
  document.getElementById('risk-no').addEventListener('click', function(){ answer(0) });
  document.getElementById('risk-idk').addEventListener('click', function(){ answer(0.5) });
  document.getElementById('risk-again').addEventListener('click', function(){
    i = 0; score = 0; res.classList.remove('show'); body.style.display = ''; draw();
  });
  draw();
});

/* ---------- цели на звонки, мессенджеры и календарь ---------- */
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('a[href^="tel:"]').forEach(function(a){
    a.addEventListener('click', function(){ track('phone_click', {where: document.title}); });
  });
  document.querySelectorAll('a[href*="t.me"], a[href*="max.ru"]').forEach(function(a){
    a.addEventListener('click', function(){ track('messenger_click', {href: a.getAttribute('href')}); });
  });
  var calBtn = document.querySelector('[data-lead*="Календарь"]');
  if(calBtn) calBtn.addEventListener('click', function(){ track('calendar_click'); });
});
