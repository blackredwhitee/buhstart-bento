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
    modal.querySelector('.m-kicker').textContent = isAsk ? 'Вопрос бухгалтеру' : 'Заявка';
    modal.querySelector('.m-title').textContent = isAsk ? 'Спросите — ответим' : (situation ? 'Уточнить цену' : 'Записаться на консультацию');
    modal.querySelector('.m-sub').textContent = situation ? ('Ситуация: ' + situation) : (isAsk ? 'Короткий вопрос по учёту, налогам или отчётности. Отвечаем в рабочее время.' : 'Оставьте контакты — перезвоним в течение 2 часов в рабочее время.');
    modal.querySelector('.m-question').style.display = isAsk ? 'block' : 'none';
    modal.dataset.source = situation ? ('Уточнить цену: ' + situation) : (isAsk ? 'Вопрос бухгалтеру' : 'Записаться на консультацию');
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
