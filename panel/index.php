<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

$err = '';
$cfg = config();

// первый запуск: настроек ещё нет
if (!isset($cfg['user'])) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Редактор не настроен</title>'
       . '<p style="font:16px/1.5 system-ui;padding:40px">Редактор ещё не настроен. Сообщите разработчику.</p>';
    exit;
}

if (($_GET['do'] ?? '') === 'logout') {
    logout();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $wait = throttle_wait();
    if ($wait > 0) {
        $err = 'Слишком много попыток. Подождите ' . ceil($wait / 60) . ' мин.';
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $err = 'Страница устарела, обновите её и попробуйте снова.';
    } elseif (try_login(trim((string)($_POST['login'] ?? '')), (string)($_POST['pass'] ?? ''))) {
        log_action('вход');
        header('Location: app.php');
        exit;
    } else {
        $err = 'Неверный логин или пароль.';
    }
}

if (is_logged_in()) {
    header('Location: app.php');
    exit;
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
:root{--orange:#F07828;--ink:#18181B;--g500:#71717A;--g200:#E4E4E7;--bg:#F4F4F5}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);
  font:400 15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:var(--ink);padding:24px}
.card{width:100%;max-width:380px;background:#fff;border-radius:24px;padding:36px 32px;box-shadow:0 12px 40px rgba(24,24,27,.08)}
img.logo{height:40px;margin-bottom:26px}
h1{font-size:20px;font-weight:700;letter-spacing:-.02em;margin:0 0 4px}
p.sub{margin:0 0 24px;color:var(--g500);font-size:14px}
label{display:block;font-size:13px;font-weight:600;margin:0 0 6px}
input{width:100%;height:48px;padding:0 16px;border:1px solid var(--g200);border-radius:14px;font:inherit;background:#fff}
input:focus{outline:2px solid var(--orange);outline-offset:-1px;border-color:var(--orange)}
.row{margin-bottom:16px}
button{width:100%;height:50px;border:0;border-radius:14px;background:var(--orange);color:#fff;
  font:600 15px/1 inherit;cursor:pointer;transition:background .15s}
button:hover{background:#E06818}
.err{background:#FEE;color:#B3261E;border-radius:12px;padding:11px 14px;font-size:14px;margin-bottom:16px}
</style>
</head>
<body>
<form class="card" method="post" autocomplete="on">
  <img class="logo" src="../uploads/logo-transparent.png" alt="Доверительная Бухгалтерия">
  <h1>Редактор сайта</h1>
  <p class="sub">Вход для сотрудников компании</p>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <input type="hidden" name="form" value="login">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="row">
    <label for="login">Логин</label>
    <input id="login" name="login" autocomplete="username" required autofocus>
  </div>
  <div class="row">
    <label for="pass">Пароль</label>
    <input id="pass" name="pass" type="password" autocomplete="current-password" required>
  </div>
  <button type="submit">Войти</button>
</form>
</body>
</html>
