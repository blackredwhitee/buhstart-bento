<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/text.php';
require __DIR__ . '/images.php';
require __DIR__ . '/build.php';
require_login();

$page = $_GET['p'] ?? 'home';
// «Команда» — это страница team.html в текстовом редакторе; уводим сразу, до вывода
if ($page === 'team') { header('Location: texts?f=team.html'); exit; }
$msg = $err = '';

// пока пароль не сменён — дальше не пускаем
if (!empty($_SESSION['must_change']) && $page !== 'password') {
    $page = 'password';
}

/* ------------------------------------------------------------- обработка */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $err = 'Страница устарела, обновите её и попробуйте снова.';
    } else {
        switch ($_POST['form'] ?? '') {

            case 'password':
                $new = (string)($_POST['new'] ?? '');
                $rep = (string)($_POST['repeat'] ?? '');
                if (mb_strlen($new) < 10) {
                    $err = 'Пароль должен быть не короче 10 символов.';
                } elseif ($new !== $rep) {
                    $err = 'Пароли не совпадают.';
                } else {
                    set_password($new);
                    log_action('смена пароля');
                    $msg = 'Пароль изменён.';
                    $page = 'home';
                }
                break;

            case 'texts':
                $file = (string)($_POST['file'] ?? '');
                $path = page_path($file);
                if (!$path) {
                    $err = 'Такой страницы нет.';
                    break;
                }
                [$ok, $note] = apply_blocks($path, (array)($_POST['b'] ?? []), (string)($_POST['hash'] ?? ''));
                if ($ok) {
                    $msg = $note;
                    log_action('тексты: ' . $file . ' — ' . $note);
                } else {
                    $err = $note;
                }
                $page = 'texts';
                $_GET['f'] = $file;
                break;

            case 'image-replace':
                [$ok, $note] = image_replace((string)($_POST['name'] ?? ''), (array)($_FILES['file'] ?? []));
                $ok ? ($msg = $note) : ($err = $note);
                if ($ok) { log_action('картинка заменена: ' . (string)($_POST['name'] ?? '')); }
                $page = 'images';
                break;

            case 'image-add':
                [$ok, $note] = image_add((string)($_POST['newname'] ?? ''), (array)($_FILES['file'] ?? []));
                $ok ? ($msg = $note) : ($err = $note);
                if ($ok) { log_action('картинка добавлена: ' . $note); }
                $page = 'images';
                break;

            case 'prices':
                $file = SITE_DIR . '/content/prices.json';
                $cur = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
                $in = (array)($_POST['p'] ?? []);
                $set = function (array &$node, array $in) use (&$set) {
                    foreach ($node as $k => $v) {
                        if (is_array($v) && isset($in[$k]) && is_array($in[$k])) {
                            $set($node[$k], $in[$k]);
                        } elseif (isset($in[$k]) && $in[$k] !== '') {
                            $node[$k] = max(0, (int)preg_replace('~\D~', '', (string)$in[$k]));
                        }
                    }
                };
                $set($cur, $in);
                backup_file($file);
                if (write_atomic($file, json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n")) {
                    log_action('цены калькулятора сохранены');
                    $msg = 'Цены сохранены. В калькуляторе применятся сразу.';
                } else {
                    $err = 'Не удалось записать файл цен.';
                }
                $page = 'prices';
                break;

            case 'article':
                if (($_POST['act'] ?? '') === 'delete') {
                    [$ok, $note] = article_delete((string)($_POST['orig'] ?? ''));
                } else {
                    [$ok, $note] = article_save($_POST, ($_POST['orig'] ?? '') !== '' ? (string)$_POST['orig'] : null);
                }
                $ok ? ($msg = $note) : ($err = $note);
                if ($ok) { log_action('статья: ' . (string)($_POST['title'] ?? $_POST['orig'] ?? '') . ' — ' . $note); }
                $page = 'articles';
                break;

            case 'case':
                if (($_POST['act'] ?? '') === 'delete') {
                    [$ok, $note] = case_delete((string)($_POST['orig'] ?? ''));
                } else {
                    [$ok, $note] = case_save($_POST, ($_POST['orig'] ?? '') !== '' ? (string)$_POST['orig'] : null);
                }
                $ok ? ($msg = $note) : ($err = $note);
                if ($ok) { log_action('кейс: ' . (string)($_POST['title'] ?? '') . ' — ' . $note); }
                $page = 'cases';
                break;

            case 'restore':
                [$ok, $note] = backup_restore((string)($_POST['file'] ?? ''));
                $ok ? ($msg = $note) : ($err = $note);
                if ($ok) { log_action('откат: ' . (string)($_POST['file'] ?? '')); }
                $page = 'history';
                break;

            case 'rebuild':
                $rep = build_all();
                log_action('пересборка сайта');
                $msg = 'Сайт пересобран: ' . implode(', ', $rep);
                $page = 'home';
                break;

            case 'calendar':
                $rules = [];
                $days   = (array)($_POST['day'] ?? []);
                $months = (array)($_POST['m'] ?? []);
                $whats  = (array)($_POST['what'] ?? []);
                $whos   = (array)($_POST['who'] ?? []);
                foreach ($whats as $i => $what) {
                    $what = trim((string)$what);
                    if ($what === '') {
                        continue;                     // пустая строка — значит удалили
                    }
                    $day = max(1, min(31, (int)($days[$i] ?? 1)));
                    $mm = [];
                    foreach ((array)($months[$i] ?? []) as $m) {
                        $m = (int)$m;
                        if ($m >= 0 && $m <= 12) { $mm[] = $m; }
                    }
                    if (!$mm) { $mm = [0]; }
                    if (in_array(0, $mm, true)) { $mm = [0]; }   // 0 = каждый месяц
                    $rules[] = [
                        'day'    => $day,
                        'months' => array_values(array_unique($mm)),
                        'what'   => $what,
                        'who'    => trim((string)($whos[$i] ?? '')),
                    ];
                }
                $file = SITE_DIR . '/content/calendar.json';
                $cur = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
                $cur['rules'] = $rules;
                if (!isset($cur['holidays'])) {
                    $cur['holidays'] = ['01-01','01-02','01-03','01-04','01-05','01-06','01-07','01-08',
                                        '02-23','03-08','05-01','05-09','06-12','11-04'];
                }
                $cur['horizonDays'] = max(7, min(365, (int)($_POST['horizon'] ?? 60)));
                $cur['maxRows']     = max(1, min(20, (int)($_POST['maxrows'] ?? 5)));
                backup_file($file);
                if (write_atomic($file, json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n")) {
                    log_action('календарь: сохранено правил ' . count($rules));
                    $msg = 'Календарь сохранён. Изменения видны на сайте сразу.';
                } else {
                    $err = 'Не удалось записать файл календаря.';
                }
                $page = 'calendar';
                break;
        }
    }
}

/* ------------------------------------------------------------------ вывод */

$SECTIONS = [
    'articles' => ['Статьи', 'Полезные материалы для клиентов'],
    'news'     => ['Новости законодательства', 'Короткие заметки об изменениях'],
    'cases'    => ['Кейсы', 'Истории клиентов с цифрами'],
    'prices'   => ['Цены калькулятора', 'Стоимость услуг в расчёте'],
    'team'     => ['Команда', 'Имена, должности, стаж и образование сотрудников'],
    'texts'    => ['Тексты страниц', 'Заголовки и абзацы на страницах сайта'],
    'calendar' => ['Календарь отчётности', 'Сроки, которые видны на главной'],
    'images'   => ['Фотографии и картинки', 'Заменить фото сотрудника, логотип, обложку'],
    'history'  => ['История правок', 'Вернуть страницу к прежней версии'],
];

function calendar_data(): array
{
    $f = SITE_DIR . '/content/calendar.json';
    $d = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    return $d + ['rules' => [], 'horizonDays' => 60, 'maxRows' => 5];
}
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Редактор сайта — Доверительная Бухгалтерия</title>
<link rel="icon" href="../uploads/favicon-32.png?v=4" type="image/png">
<style>
:root{--orange:#F07828;--ink:#18181B;--g500:#71717A;--g400:#A1A1AA;--g200:#E4E4E7;--bg:#F4F4F5}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);
  font:400 15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
header{background:#fff;border-bottom:1px solid var(--g200);padding:14px 24px;
  display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:5}
header img{height:32px}
header .sp{flex:1}
a{color:var(--orange)}
.wrap{max-width:960px;margin:0 auto;padding:28px 24px 60px}
h1{font-size:26px;font-weight:800;letter-spacing:-.02em;margin:0 0 6px}
p.lead{color:var(--g500);margin:0 0 26px}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.card{display:block;background:#fff;border-radius:20px;padding:22px 24px;text-decoration:none;color:inherit;
  border:1px solid var(--g200);transition:transform .18s,box-shadow .18s}
.card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(24,24,27,.07)}
.card b{display:block;font-size:16px;margin-bottom:4px}
.card span{color:var(--g500);font-size:13.5px}
.panel{background:#fff;border:1px solid var(--g200);border-radius:20px;padding:24px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--g500);
  padding:0 8px 10px;font-weight:600}
td{padding:5px 8px 5px 0;vertical-align:top}
input[type=text],input[type=number],input[type=password]{width:100%;height:42px;padding:0 12px;
  border:1px solid var(--g200);border-radius:11px;font:inherit;background:#fff}
input:focus{outline:2px solid var(--orange);outline-offset:-1px}
.btn{height:46px;padding:0 22px;border:0;border-radius:13px;background:var(--orange);color:#fff;
  font:600 15px/1 inherit;cursor:pointer}
.btn:hover{background:#E06818}
.btn-g{background:#fff;color:var(--ink);border:1px solid var(--g200)}
.btn-g:hover{background:var(--bg)}
.msg{border-radius:13px;padding:12px 16px;margin-bottom:20px;font-size:14.5px}
.ok{background:#E7F6EC;color:#1E6B3A}
.bad{background:#FEE;color:#B3261E}
.hint{color:var(--g500);font-size:13px;margin-top:6px}
.blk{padding:12px 0;border-bottom:1px solid var(--g200)}
.blk:last-of-type{border-bottom:0}
.blk-tag{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--g400);margin-bottom:5px}
.sec-title{margin:22px 0 6px;padding-top:14px;border-top:2px solid var(--orange);
  font:700 14px/1.3 inherit;color:var(--ink)}
.sec-title:first-child{margin-top:0;padding-top:0;border-top:0}
.blk.found{background:#FFF6EC;border-radius:12px;padding:12px;margin:6px -12px}
code{background:var(--bg);padding:2px 6px;border-radius:6px;font-size:12.5px}
mark{background:#FFE7C7;padding:0 2px}
textarea{width:100%;padding:10px 12px;border:1px solid var(--g200);border-radius:11px;font:inherit;
  background:#fff;resize:vertical;line-height:1.5}
textarea:focus{outline:2px solid var(--orange);outline-offset:-1px}
.imgs{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px}
.img{background:#fff;border:1px solid var(--g200);border-radius:16px;padding:12px}
.img-prev{height:110px;display:grid;place-items:center;background:var(--bg);border-radius:11px;overflow:hidden;margin-bottom:9px}
.img-prev img{max-width:100%;max-height:110px;display:block}
.img-name{font-size:12.5px;font-weight:600;word-break:break-all}
.img-meta{font-size:11.5px;color:var(--g400);margin:2px 0 9px}
.img-btn{display:block;text-align:center;font-size:13px;font-weight:600;color:var(--orange);cursor:pointer;
  border:1px dashed var(--g200);border-radius:10px;padding:7px}
.img-btn:hover{background:#FFF3EE;border-color:var(--orange)}
.img-btn input{display:none}
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;margin-top:10px}
.pitem{display:flex;align-items:center;gap:10px;justify-content:space-between}
.pitem span{font-size:13.5px;color:var(--g500)}
.pitem input{width:104px;text-align:right}
.fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:14px}
.fgrid label,label.full{display:block;font-size:13px;font-weight:600}
label.full{margin-bottom:14px}
.fgrid input,.fgrid select,label.full textarea{margin-top:5px;font-weight:400}
select{width:100%;height:42px;padding:0 10px;border:1px solid var(--g200);border-radius:11px;font:inherit;background:#fff}
input[type=date]{width:100%;height:42px;padding:0 12px;border:1px solid var(--g200);border-radius:11px;font:inherit}
.lrow{display:flex;gap:14px;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--g200)}
.lrow:last-child{border-bottom:0}
.lrow a{font-weight:600;text-decoration:none}
.lrow a:hover{text-decoration:underline}
.lmeta{font-size:12.5px;color:var(--g400);margin-top:2px}
.lopen{font-size:13px;white-space:nowrap}
.months{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px 10px;margin-top:8px}
.mchk{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:400;color:var(--g600);cursor:pointer;white-space:nowrap}
.mchk input{width:15px;height:15px;flex:none;accent-color:var(--orange);margin:0}
.mevery{font-weight:600;color:var(--ink);margin-bottom:2px}
td[data-l="Месяцы"]{min-width:230px}
.tools{display:flex;gap:8px;align-items:center;margin:6px 0 8px;flex-wrap:wrap}
.tools button{height:32px;padding:0 12px;border:1px solid var(--g200);border-radius:9px;background:#fff;
  font:600 13px/1 inherit;color:var(--ink);cursor:pointer}
.tools button:hover{border-color:var(--orange);color:var(--orange)}
.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:20px}
.small{width:90px}
@media(max-width:700px){
  table,thead,tbody,tr,td,th{display:block}
  thead{display:none}
  tr{border:1px solid var(--g200);border-radius:14px;padding:12px;margin-bottom:12px}
  td{padding:4px 0}
  td::before{content:attr(data-l);display:block;font-size:12px;color:var(--g500);margin-bottom:3px}
}
</style>
</head>
<body>
<header>
  <img src="../uploads/logo-transparent.png" alt="">
<?php
/* Кнопка «назад»: из карточки материала — к списку, из списка — ко всем разделам.
   Раньше вернуться можно было только через «Все разделы» наверху. */
$inItem = ($_GET['f'] ?? '') !== '' || ($_GET['new'] ?? '') !== '';
$backMap = [
  'texts'    => ['texts',    'К списку страниц'],
  'articles' => ['articles', 'К списку статей'],
  'news'     => ['news',     'К списку новостей'],
  'cases'    => ['cases',    'К списку кейсов'],
];
if ($page !== 'home' && isset($backMap[$page]) && $inItem) {
    [$bHref, $bText] = $backMap[$page];
} else {
    [$bHref, $bText] = ['./', 'Все разделы'];
}
?>
  <?php if ($page !== 'home'): ?><a href="<?= h($bHref) ?>">← <?= h($bText) ?></a><?php endif; ?>
  <span class="sp"></span>
  <a href="../" target="_blank">Открыть сайт ↗</a>
  <a href="password">Пароль</a>
  <a href="index.php?do=logout">Выйти</a>
</header>
<div class="wrap">

<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg bad"><?= h($err) ?></div><?php endif; ?>

<?php if ($page === 'password'): ?>
  <h1>Пароль</h1>
  <p class="lead"><?= !empty($_SESSION['must_change'])
      ? 'Придумайте свой пароль — временный работать перестанет.'
      : 'Смена пароля для входа в редактор.' ?></p>
  <form class="panel" method="post" style="max-width:420px">
    <input type="hidden" name="form" value="password">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <p><label>Новый пароль<br><input type="password" name="new" minlength="10" required autocomplete="new-password"></label></p>
    <p><label>Повторите<br><input type="password" name="repeat" minlength="10" required autocomplete="new-password"></label></p>
    <p class="hint">Не короче 10 символов. Не используйте пароль от почты или банка.</p>
    <div class="row"><button class="btn" type="submit">Сохранить</button></div>
  </form>

<?php elseif ($page === 'calendar'):
  $cal = calendar_data(); ?>
  <h1>Календарь отчётности</h1>
  <p class="lead">Сайт сам считает даты по этим правилам и показывает ближайшие сроки. Даты нигде не хранятся —
     достаточно указать число месяца и месяцы.</p>
  <form class="panel" method="post">
    <input type="hidden" name="form" value="calendar">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <table>
      <thead><tr>
        <th style="width:80px">Число</th><th style="width:150px">Месяцы</th>
        <th>Что сдавать или платить</th><th style="width:200px">Кого касается</th>
      </tr></thead>
      <tbody id="rows">
      <?php $i = 0; foreach ($cal['rules'] as $r): ?>
        <tr>
          <td data-l="Число"><input type="number" name="day[<?= $i ?>]" min="1" max="31" value="<?= (int)$r['day'] ?>"></td>
          <td data-l="Месяцы"><?php $mm = (array)$r['months']; $every = in_array(0, $mm, true); ?>
            <label class="mchk mevery"><input type="checkbox" data-every value="0" name="m[<?= $i ?>][]"<?= $every ? ' checked' : '' ?>> каждый месяц</label>
            <div class="months"<?= $every ? ' hidden' : '' ?>>
              <?php foreach (['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'] as $k => $mn): ?>
                <label class="mchk"><input type="checkbox" name="m[<?= $i ?>][]" value="<?= $k + 1 ?>"<?= in_array($k + 1, $mm, true) ? ' checked' : '' ?>><?= $mn ?></label>
              <?php endforeach; ?>
            </div>
          </td>
          <td data-l="Что"><input type="text" name="what[<?= $i ?>]" value="<?= h($r['what']) ?>"></td>
          <td data-l="Кого касается"><input type="text" name="who[<?= $i ?>]" value="<?= h($r['who'] ?? '') ?>"></td>
        </tr>
      <?php $i++; endforeach; ?>
      </tbody>
    </table>
    <p class="hint">Отметьте месяцы, в которых наступает срок. Чтобы удалить строку — очистите поле «Что сдавать».</p>
    <div class="row">
      <label>Показывать на <input class="small" type="number" name="horizon" min="7" max="365" value="<?= (int)$cal['horizonDays'] ?>"> дней вперёд</label>
      <label>не больше <input class="small" type="number" name="maxrows" min="1" max="20" value="<?= (int)$cal['maxRows'] ?>"> строк</label>
    </div>
    <div class="row">
      <button class="btn" type="submit">Сохранить</button>
      <button class="btn btn-g" type="button" onclick="addRow()">Добавить строку</button>
    </div>
  </form>
  <script>
  var next = <?= (int)$i ?>;
  document.addEventListener('change', function(e){
    var el = e.target;
    if(!el.matches('input[data-every]')) return;
    var box = el.closest('td').querySelector('.months');
    box.hidden = el.checked;
    if(el.checked) box.querySelectorAll('input').forEach(function(i){ i.checked=false });
  });
  function addRow(){
    var tb = document.getElementById('rows'), tr = document.createElement('tr');
    var MN=['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'];
    var boxes=MN.map(function(m,k){return '<label class="mchk"><input type="checkbox" name="m['+next+'][]" value="'+(k+1)+'">'+m+'</label>'}).join('');
    tr.innerHTML = '<td data-l="Число"><input type="number" name="day['+next+']" min="1" max="31" value="25"></td>'
      + '<td data-l="Месяцы"><label class="mchk mevery"><input type="checkbox" data-every name="m['+next+'][]" value="0" checked> каждый месяц</label>'
      + '<div class="months" hidden>'+boxes+'</div></td>'
      + '<td data-l="Что"><input type="text" name="what['+next+']" placeholder="Например: Декларация по НДС"></td>'
      + '<td data-l="Кого касается"><input type="text" name="who['+next+']" placeholder="Например: ОСНО"></td>';
    tb.appendChild(tr); next++;
    tr.querySelector('input[name^=what]').focus();
  }
  </script>

<?php elseif ($page === 'texts'):
  $file = (string)($_GET['f'] ?? '');
  $path = page_path($file);
  if (!$path): ?>
    <h1>Тексты страниц</h1>
    <p class="lead">Выберите страницу или найдите нужную фразу по всему сайту.
       Правится только текст — вёрстку и оформление изменить нельзя.</p>
    <form class="panel" method="get" action="texts" style="margin-bottom:18px">
      <div class="row" style="margin:0">
        <input type="text" name="q" value="<?= h((string)($_GET['q'] ?? '')) ?>"
               placeholder="Например: 15 минут" style="flex:1;min-width:220px">
        <button class="btn" type="submit">Найти</button>
      </div>
    </form>
    <?php $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== ''):
      $found = search_pages($q); ?>
      <div class="panel" style="margin-bottom:18px">
        <b>Нашли совпадений: <?= count($found) ?></b>
        <?php foreach ($found as $r): ?>
          <div class="lrow">
            <div><a href="texts?f=<?= h(rawurlencode($r['file'])) ?>&amp;q=<?= h(rawurlencode($q)) ?>#b<?= (int)$r['i'] ?>"><?= h($r['name']) ?></a>
              <div class="lmeta"><?= h($r['section']) ?></div>
              <div style="font-size:13.5px;margin-top:4px"><?= $r['snippet'] ?></div></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$found): ?><p class="hint">Ничего не нашли. Попробуйте другое слово.</p><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="cards">
      <?php foreach (editable_pages() as $f => $name): ?>
        <a class="card" href="texts?f=<?= h($f) ?>"><b><?= h($name) ?></b><span><?= h($f) ?></span></a>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin-top:20px">Статьи, новости и кейсы правятся в своих разделах — они собираются из данных,
       и правка прямо в странице потерялась бы при следующей сборке.</p>
  <?php else:
    $html = (string)file_get_contents($path);
    $blocks = find_blocks($html);
    $names = editable_pages(); ?>
    <h1><?= h($names[$file]) ?></h1>
    <p class="lead">Фрагментов на странице: <?= count($blocks) ?>. Они идут в том же порядке, что и на сайте,
       и сгруппированы по разделам. Пустое поле не сохраняется — потерять текст нельзя.
       <a href="../<?= h($file) ?>" target="_blank">Открыть страницу ↗</a></p>
    <div class="panel" style="margin-bottom:16px;font-size:13.5px;color:var(--g500)">
      <b style="color:var(--ink)">Как оформить текст</b><br>
      <code>==текст==</code> — выделить оранжевым · <code>**текст**</code> — жирным ·
      <code>[текст](contacts.html)</code> — ссылка. Тегов писать не нужно.
    </div>
    <form class="panel" method="post">
      <input type="hidden" name="form" value="texts">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="file" value="<?= h($file) ?>">
      <input type="hidden" name="hash" value="<?= h(md5($html)) ?>">
      <?php $lastSection = null;
      foreach ($blocks as $i => $b):
        if ($b['section'] !== $lastSection): $lastSection = $b['section']; ?>
          <div class="sec-title"><?= h($lastSection) ?></div>
        <?php endif;
        $isHead = in_array($b['tag'], ['h1','h2','h3','h4'], true);
        $val = html_to_simple($b['html']);
        $long = mb_strlen($val) > 90; ?>
        <div class="blk" id="b<?= $i ?>">
          <div class="blk-tag"><?= h($b['tag'] === 'li' ? 'пункт списка'
              : ($isHead ? 'заголовок' : ($b['tag'] === 'blockquote' ? 'цитата'
              : ($b['tag'] === 'summary' ? 'подпись' : 'абзац')))) ?></div>
          <?php if ($isHead && !$long): ?>
            <input type="text" name="b[<?= $i ?>]" value="<?= h($val) ?>">
          <?php else: ?>
            <textarea name="b[<?= $i ?>]" rows="<?= $long ? 3 : 2 ?>"><?= h($val) ?></textarea>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="row"><button class="btn" type="submit">Сохранить страницу</button>
        <a class="btn btn-g" href="texts" style="display:inline-flex;align-items:center;text-decoration:none">К списку страниц</a></div>
    </form>
    <script>
    // подсвечиваем найденный фрагмент, если пришли из поиска
    (function(){ var h=location.hash; if(!h) return; var el=document.querySelector(h);
      if(el){ el.classList.add('found'); el.scrollIntoView({block:'center'}); } })();
    </script>
  <?php endif; ?>

<?php elseif ($page === 'articles' || $page === 'news'):
  $isNewsPage = ($page === 'news');
  // новость узнаём по разделу или по адресу — так же, как это делает сборщик сайта
  $isNewsItem = function (array $a): bool {
      return ($a['tag'] ?? '') === 'Новости законодательства' || str_starts_with((string)($a['slug'] ?? ''), 'novosti-');
  };
  $items = array_values(array_filter(articles_all(), fn($a) => $isNewsItem($a) === $isNewsPage));
  $edit = null;
  if (($_GET['f'] ?? '') !== '') {
    foreach ($items as $it) { if ($it['_file'] === basename((string)$_GET['f'])) { $edit = $it; break; } }
  }
  $isNew = ($_GET['new'] ?? '') !== '';
  if ($edit || $isNew): ?>
    <h1><?= $edit ? ($isNewsPage ? 'Правка новости' : 'Правка статьи') : ($isNewsPage ? 'Новая новость' : 'Новая статья') ?></h1>
    <p class="lead">Абзацы разделяются пустой строкой. Заголовок внутри текста — строка,
       начинающаяся с <b>##</b>. Пункт списка — с <b>-</b>. Цитата — с <b>&gt;</b>.</p>
    <form class="panel" method="post">
      <input type="hidden" name="form" value="article">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="orig" value="<?= h($edit['_file'] ?? '') ?>">
      <div class="fgrid">
        <label>Заголовок<input type="text" name="title" required value="<?= h($edit['title'] ?? '') ?>"></label>
        <?php if ($isNewsPage): ?>
          <!-- в разделе новостей выбирать нечего: раздел всегда «Новости законодательства» -->
          <input type="hidden" name="tag" value="Новости законодательства">
          <label>Раздел<input type="text" value="Новости законодательства" disabled></label>
        <?php else: ?>
          <label>Раздел<select name="tag">
            <?php foreach (TAGS as $t): if ($t === 'Новости законодательства') continue; ?>
              <option<?= ($edit['tag'] ?? '') === $t ? ' selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select></label>
        <?php endif; ?>
        <label>Дата<input type="date" name="isoDate" value="<?= h($edit['isoDate'] ?? date('Y-m-d')) ?>"></label>
        <label>Адрес страницы (латиницей, можно оставить пустым)
          <input type="text" name="slug" value="<?= h($edit['slug'] ?? '') ?>" placeholder="составится из заголовка"></label>
      </div>
      <label class="full">Короткое описание для списков и поиска
        <textarea name="preview" rows="2"><?= h($edit['preview'] ?? '') ?></textarea></label>
      <label class="full">Текст
        <div class="tools">
          <button type="button" onclick="ins('## ','','заголовок внутри текста')">Заголовок</button>
          <button type="button" onclick="ins('- ','','пункт списка')">Список</button>
          <button type="button" onclick="ins('&gt; ','','цитата')">Цитата</button>
          <span class="hint" style="margin:0 0 0 auto">Абзацы разделяются пустой строкой</span>
        </div>
        <textarea id="body" name="body" rows="18" required><?= h($edit ? blocks_to_text((array)$edit['blocks']) : '') ?></textarea></label>
      <script>
      function ins(pre, post, hintText){
        var t = document.getElementById('body');
        var s = t.selectionStart, e = t.selectionEnd, v = t.value;
        // работаем со всей строкой, а не с куском слова
        var lineStart = v.lastIndexOf('\n', s - 1) + 1;
        var sel = v.slice(s, e) || hintText;
        t.value = v.slice(0, lineStart) + pre + v.slice(lineStart, s) + sel + post + v.slice(e);
        t.focus();
        t.selectionStart = lineStart + pre.length + (s - lineStart);
        t.selectionEnd = t.selectionStart + sel.length;
      }
      </script>
      <div class="row">
        <button class="btn" type="submit">Сохранить и опубликовать</button>
        <a class="btn btn-g" href="<?= $page ?>" style="display:inline-flex;align-items:center;text-decoration:none">Отмена</a>
        <?php if ($edit): ?>
          <button class="btn btn-g" type="submit" name="act" value="delete" style="margin-left:auto;color:#B3261E"
            onclick="return confirm('Снять статью с публикации? Копия останется в архиве.')">Снять с публикации</button>
        <?php endif; ?>
      </div>
    </form>
  <?php else: ?>
    <h1><?= $isNewsPage ? 'Новости законодательства' : 'Статьи' ?></h1>
    <p class="lead">Всего материалов: <?= count($items) ?>. Страница, списки и карта сайта обновляются сами при сохранении.</p>
    <div class="row" style="margin:0 0 18px">
      <a class="btn" href="<?= $page ?>?new=1" style="display:inline-flex;align-items:center;text-decoration:none;color:#fff"><?= $isNewsPage ? "Добавить новость" : "Добавить статью" ?></a>
      <form method="post" style="display:inline">
        <input type="hidden" name="form" value="rebuild">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button class="btn btn-g" type="submit">Пересобрать сайт</button>
      </form>
    </div>
    <div class="panel">
      <?php foreach ($items as $it): ?>
        <div class="lrow">
          <div><a href="<?= $page ?>?f=<?= h(rawurlencode($it['_file'])) ?>"><?= h($it['title'] ?? '') ?></a>
            <div class="lmeta"><?= h($it['tag'] ?? '') ?> · <?= h($it['date'] ?? '') ?></div></div>
          <a class="lopen" href="../article-<?= h($it['slug'] ?? '') ?>.html" target="_blank">на сайте ↗</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($page === 'cases'):
  $items = cases_all();
  $edit = null;
  if (($_GET['f'] ?? '') !== '') {
    foreach ($items as $it) { if ($it['_file'] === basename((string)$_GET['f'])) { $edit = $it; break; } }
  }
  $isNew = ($_GET['new'] ?? '') !== '';
  if ($edit || $isNew): ?>
    <h1><?= $edit ? 'Правка кейса' : 'Новый кейс' ?></h1>
    <p class="lead">Кейс из трёх частей: что было, что сделали, результат. Цифра результата выносится крупно.</p>
    <form class="panel" method="post">
      <input type="hidden" name="form" value="case">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="orig" value="<?= h($edit['_file'] ?? '') ?>">
      <div class="fgrid">
        <label>Заголовок<input type="text" name="title" required value="<?= h($edit['title'] ?? '') ?>"></label>
        <label>Отрасль или тема<input type="text" name="tag" value="<?= h($edit['tag'] ?? '') ?>" placeholder="Например: Общепит"></label>
        <label>Цифра результата<input type="text" name="metric" value="<?= h($edit['metric'] ?? '') ?>" placeholder="Например: 11,7 млн ₽"></label>
        <label>Подпись к цифре<input type="text" name="metricNote" value="<?= h($edit['metricNote'] ?? '') ?>" placeholder="сэкономлено за год"></label>
        <label>Дата<input type="date" name="isoDate" value="<?= h($edit['isoDate'] ?? date('Y-m-d')) ?>"></label>
        <label>Адрес (латиницей, необязательно)<input type="text" name="slug" value="<?= h($edit['slug'] ?? '') ?>"></label>
      </div>
      <label class="full">Что было<textarea name="was" rows="3"><?= h($edit['was'] ?? '') ?></textarea></label>
      <label class="full">Что сделали<textarea name="did" rows="4"><?= h($edit['did'] ?? '') ?></textarea></label>
      <label class="full">Результат<textarea name="result" rows="3"><?= h($edit['result'] ?? '') ?></textarea></label>
      <div class="row">
        <button class="btn" type="submit">Сохранить и опубликовать</button>
        <a class="btn btn-g" href="cases" style="display:inline-flex;align-items:center;text-decoration:none">Отмена</a>
        <?php if ($edit): ?>
          <button class="btn btn-g" type="submit" name="act" value="delete" style="margin-left:auto;color:#B3261E"
            onclick="return confirm('Убрать кейс с сайта? Копия останется в архиве.')">Убрать с сайта</button>
        <?php endif; ?>
      </div>
    </form>
  <?php else: ?>
    <h1>Кейсы</h1>
    <p class="lead">Всего кейсов: <?= count($items) ?>. Первые три показываются на главной, все — на странице «Кейсы».</p>
    <div class="row" style="margin:0 0 18px">
      <a class="btn" href="cases?new=1" style="display:inline-flex;align-items:center;text-decoration:none;color:#fff">Добавить кейс</a>
    </div>
    <div class="panel">
      <?php foreach ($items as $it): ?>
        <div class="lrow">
          <div><a href="cases?f=<?= h(rawurlencode($it['_file'])) ?>"><?= h($it['title'] ?? '') ?></a>
            <div class="lmeta"><?= h($it['tag'] ?? '') ?><?= !empty($it['metric']) ? ' · ' . h($it['metric']) : '' ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($page === 'history'): ?>
  <h1>История правок</h1>
  <p class="lead">Перед каждым изменением сохраняется копия страницы. Здесь можно вернуть любую —
     текущая версия при этом тоже сохранится, так что откат всегда обратим.</p>
  <div class="panel">
    <?php $bk = backups_list(); if (!$bk): ?>
      <p class="hint">Пока ничего не менялось — копий нет.</p>
    <?php endif; ?>
    <?php foreach ($bk as $b): ?>
      <div class="lrow">
        <div><b><?= h($b['target']) ?></b>
          <div class="lmeta">версия от <?= h($b['when']) ?> · <?= h(human_size((int)$b['size'])) ?></div></div>
        <form method="post" style="margin:0">
          <input type="hidden" name="form" value="restore">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="file" value="<?= h($b['file']) ?>">
          <button class="btn btn-g" type="submit"
            onclick="return confirm('Вернуть версию от <?= h($b['when']) ?>?')">Вернуть</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($page === 'images'): ?>
  <h1>Фотографии и картинки</h1>
  <p class="lead">Замена идёт под тем же именем файла — страницы менять не нужно, новое фото встанет на место старого.
     Старое сохраняется в архиве.</p>

  <form class="panel" method="post" enctype="multipart/form-data" style="margin-bottom:18px">
    <input type="hidden" name="form" value="image-add">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <b>Загрузить новую картинку</b>
    <div class="row">
      <input type="file" name="file" accept=".jpg,.jpeg,.png,.svg,.webp" required style="flex:1;min-width:220px">
      <input type="text" name="newname" placeholder="имя файла, необязательно" style="flex:1;min-width:180px">
      <button class="btn" type="submit">Загрузить</button>
    </div>
  </form>

  <div class="imgs">
    <?php foreach (images_list() as $im): ?>
      <form class="img" method="post" enctype="multipart/form-data">
        <input type="hidden" name="form" value="image-replace">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="name" value="<?= h($im['name']) ?>">
        <div class="img-prev"><img src="../uploads/<?= h(rawurlencode($im['name'])) ?>?t=<?= (int)$im['time'] ?>" alt="" loading="lazy"></div>
        <div class="img-name"><?= h($im['name']) ?></div>
        <div class="img-meta"><?= h(human_size((int)$im['size'])) ?><?= $im['dim'] ? ' · ' . h($im['dim']) : '' ?></div>
        <label class="img-btn">Заменить<input type="file" name="file" accept=".jpg,.jpeg,.png,.svg,.webp" onchange="this.form.submit()"></label>
      </form>
    <?php endforeach; ?>
  </div>

<?php elseif ($page === 'prices'):
  $pf = SITE_DIR . '/content/prices.json';
  $pr = is_file($pf) ? (json_decode((string)file_get_contents($pf), true) ?: []) : [];
  $LABEL = [
    'null_ip' => 'Нулевая отчётность, ИП', 'null_ooo' => 'Нулевая отчётность, ООО',
    'entity_ooo' => 'Надбавка за ООО', 'optima_base' => 'Тариф «Оптима», базовая часть',
    'visit_price' => 'Один выезд бухгалтера в офис', 'ved' => 'ВЭД и валютные расчёты',
    'reconcile' => 'Сверка с налоговой', 'tax_mgmt' => 'Налоговый менеджмент',
    'military_per' => 'Воинский учёт, за сотрудника', 'licenses' => 'Лицензии', 'spot' => 'Обособленное подразделение',
    'tax' => 'Система налогообложения', 'vat' => 'НДС', 'niche' => 'Вид деятельности',
    'staff' => 'Сотрудники', 'cash' => 'Наличные расчёты', 'invoice' => 'Выставление счетов',
  ];
  $NAME = [
    'patent'=>'Патент','ausn_d'=>'АУСН Доходы','ausn_dr'=>'АУСН Доходы-Расходы','usn6'=>'УСН 6%',
    'usn15'=>'УСН 15%','osno'=>'ОСНО','не_облагается'=>'Не облагается','освобождение'=>'Освобождение (ст. 145)',
    'nds0'=>'НДС 0%','nds5'=>'НДС 5%','nds7'=>'НДС 7%','nds10'=>'НДС 10%','nds22'=>'НДС 22%',
    'marketplace'=>'Маркетплейсы','wb'=>'Wildberries','ozon'=>'Ozon','ya'=>'Яндекс Маркет',
    'mp_inventory'=>'Учёт товара на маркетплейсе','wholesale'=>'Опт','wh_inventory'=>'Складской учёт',
    'retail'=>'Розница','rt_inventory'=>'Учёт товара в рознице','production'=>'Производство',
    'construction'=>'Строительство','catering'=>'Общепит','medicine'=>'Медицина','services'=>'Услуги',
    'rf_1_3'=>'1–3 сотрудника','rf_per'=>'Каждый следующий','foreign'=>'Иностранный сотрудник',
    'kassa'=>'Касса (ККМ)','avans'=>'Авансовые отчёты','base'=>'Базовая','std'=>'Стандарт','opt'=>'Оптима',
  ]; ?>
  <h1>Цены калькулятора</h1>
  <p class="lead">Стоимость в рублях за месяц. Калькулятор берёт эти цифры сразу, пересобирать ничего не нужно.</p>
  <form class="panel" method="post">
    <input type="hidden" name="form" value="prices">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <?php
    $simple = array_filter($pr, fn($v) => !is_array($v));
    $groups = array_filter($pr, 'is_array'); ?>
    <b>Основное</b>
    <div class="pgrid">
      <?php foreach ($simple as $k => $v): ?>
        <label class="pitem"><span><?= h($LABEL[$k] ?? $k) ?></span>
          <input type="text" inputmode="numeric" name="p[<?= h($k) ?>]" value="<?= (int)$v ?>"></label>
      <?php endforeach; ?>
    </div>
    <?php foreach ($groups as $g => $items): ?>
      <b style="display:block;margin-top:22px"><?= h($LABEL[$g] ?? $g) ?></b>
      <div class="pgrid">
        <?php foreach ($items as $k => $v): ?>
          <label class="pitem"><span><?= h($NAME[$k] ?? $k) ?></span>
            <input type="text" inputmode="numeric" name="p[<?= h($g) ?>][<?= h($k) ?>]" value="<?= (int)$v ?>"></label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <div class="row"><button class="btn" type="submit">Сохранить цены</button></div>
  </form>

<?php elseif (isset($SECTIONS[$page])): ?>
  <h1><?= h($SECTIONS[$page][0]) ?></h1>
  <p class="lead"><?= h($SECTIONS[$page][1]) ?></p>
  <div class="panel">Этот раздел ещё готовится.</div>

<?php else: ?>
  <h1>Редактор сайта</h1>
  <p class="lead">Что нужно изменить?</p>
  <div class="cards">
    <?php foreach ($SECTIONS as $key => [$title, $sub]): ?>
      <a class="card" href="<?= h($key) ?>"><b><?= h($title) ?></b><span><?= h($sub) ?></span></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</div>
</body>
</html>
