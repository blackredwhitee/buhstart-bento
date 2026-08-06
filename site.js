// общий скрипт сайта «Доверительная Бухгалтерия»
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
  document.querySelectorAll('[data-ask]').forEach(function(b){ b.addEventListener('click', function(){ openModal('ask'); }); });
  document.querySelectorAll('[data-lead]').forEach(function(b){ b.addEventListener('click', function(){ openModal('lead', b.dataset.lead || ''); }); });
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
    ct.addEventListener('pointerenter', function(){ clearInterval(timer); });
  }
});
