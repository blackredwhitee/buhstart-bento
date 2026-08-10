<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_login();

$page = $_GET['p'] ?? 'home';
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

            case 'calendar':
                $rules = [];
                $days   = (array)($_POST['day'] ?? []);
                $months = (array)($_POST['months'] ?? []);
                $whats  = (array)($_POST['what'] ?? []);
                $whos   = (array)($_POST['who'] ?? []);
                foreach ($whats as $i => $what) {
                    $what = trim((string)$what);
                    if ($what === '') {
                        continue;                     // пустая строка — значит удалили
                    }
                    $day = max(1, min(31, (int)($days[$i] ?? 1)));
                    $mm = [];
                    foreach (preg_split('/\D+/', (string)($months[$i] ?? '')) ?: [] as $m) {
                        if ($m === '') { continue; }
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
    'calendar' => ['Календарь отчётности', 'Сроки, которые видны на главной'],
    'texts'    => ['Тексты страниц', 'Заголовки и абзацы на страницах сайта'],
    'articles' => ['Статьи и новости', 'Добавить, изменить, снять с публикации'],
    'cases'    => ['Кейсы', 'Истории клиентов с цифрами'],
    'prices'   => ['Цены калькулятора', 'Стоимость услуг в расчёте'],
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
  <?php if ($page !== 'home'): ?><a href="app.php">← Все разделы</a><?php endif; ?>
  <span class="sp"></span>
  <a href="../index.html" target="_blank">Открыть сайт ↗</a>
  <a href="app.php?p=password">Пароль</a>
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
          <td data-l="Месяцы"><input type="text" name="months[<?= $i ?>]" value="<?= h(implode(',', (array)$r['months'])) ?>"></td>
          <td data-l="Что"><input type="text" name="what[<?= $i ?>]" value="<?= h($r['what']) ?>"></td>
          <td data-l="Кого касается"><input type="text" name="who[<?= $i ?>]" value="<?= h($r['who'] ?? '') ?>"></td>
        </tr>
      <?php $i++; endforeach; ?>
      </tbody>
    </table>
    <p class="hint">Месяцы — через запятую: <b>1,4,7,10</b>. Каждый месяц — просто <b>0</b>.
       Чтобы удалить строку, очистите поле «Что сдавать».</p>
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
  function addRow(){
    var tb = document.getElementById('rows'), tr = document.createElement('tr');
    tr.innerHTML = '<td data-l="Число"><input type="number" name="day['+next+']" min="1" max="31" value="25"></td>'
      + '<td data-l="Месяцы"><input type="text" name="months['+next+']" value="0"></td>'
      + '<td data-l="Что"><input type="text" name="what['+next+']" placeholder="Например: Декларация по НДС"></td>'
      + '<td data-l="Кого касается"><input type="text" name="who['+next+']" placeholder="Например: ОСНО"></td>';
    tb.appendChild(tr); next++;
    tr.querySelector('input[name^=what]').focus();
  }
  </script>

<?php elseif (isset($SECTIONS[$page])): ?>
  <h1><?= h($SECTIONS[$page][0]) ?></h1>
  <p class="lead"><?= h($SECTIONS[$page][1]) ?></p>
  <div class="panel">Этот раздел ещё готовится.</div>

<?php else: ?>
  <h1>Редактор сайта</h1>
  <p class="lead">Что нужно изменить?</p>
  <div class="cards">
    <?php foreach ($SECTIONS as $key => [$title, $sub]): ?>
      <a class="card" href="app.php?p=<?= h($key) ?>"><b><?= h($title) ?></b><span><?= h($sub) ?></span></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</div>
</body>
</html>
