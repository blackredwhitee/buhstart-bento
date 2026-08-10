<?php
/**
 * Редактор сайта «Доверительная Бухгалтерия» — общая часть.
 *
 * Данные и настройки лежат ВНЕ папки сайта, чтобы их нельзя было скачать из браузера:
 *   .../data/bento-data/config.php   — логин и хеш пароля
 *   .../data/bento-data/backups/     — копии файлов перед каждой правкой
 *   .../data/bento-data/throttle/    — счётчик неудачных входов
 */

declare(strict_types=1);

const SITE_DIR = __DIR__ . '/..';                 // корень сайта

/**
 * Папку с данными ищем вверх по дереву: она должна лежать выше корня сайта,
 * иначе настройки и резервные копии можно было бы скачать из браузера.
 */
function data_dir(): string
{
    static $found = null;
    if ($found !== null) {
        return $found;
    }
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $dir = dirname($dir);
        if (is_dir($dir . '/bento-data')) {
            return $found = $dir . '/bento-data';
        }
    }
    return $found = dirname(__DIR__, 3) . '/bento-data';   // ../../../bento-data
}

function data_path(string $rel = ''): string
{
    $base = realpath(data_dir()) ?: data_dir();
    return $rel === '' ? $base : $base . '/' . $rel;
}

function ensure_dirs(): void
{
    foreach (['', 'backups', 'throttle'] as $d) {
        $p = data_path($d);
        if (!is_dir($p)) {
            @mkdir($p, 0750, true);
        }
    }
}

function config(): array
{
    $f = data_path('config.php');
    if (!is_file($f)) {
        return [];
    }
    /** @var array $cfg */
    $cfg = require $f;
    return is_array($cfg) ? $cfg : [];
}

function config_save(array $cfg): void
{
    ensure_dirs();
    $code = "<?php\n// настройки редактора, файл создаётся программой\nreturn " . var_export($cfg, true) . ";\n";
    write_atomic(data_path('config.php'), $code);
    @chmod(data_path('config.php'), 0640);
}

/** Запись через временный файл: при обрыве не останется половины файла. */
function write_atomic(string $path, string $content): bool
{
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $content) === false) {
        return false;
    }
    return rename($tmp, $path);
}

/** Копия файла перед правкой: откатиться можно всегда. */
function backup_file(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }
    ensure_dirs();
    $name = date('Y-m-d_H-i-s') . '__' . str_replace(['/', '\\'], '-', ltrim(substr($path, strlen(realpath(SITE_DIR) ?: SITE_DIR)), '/'));
    $dest = data_path('backups/' . $name);
    return copy($path, $dest) ? $dest : null;
}

/* ----------------------------------------------------------------- сессия */

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => $https,
    ]);
    session_name('bentopanel');
    session_start();
}

function is_logged_in(): bool
{
    session_start_safe();
    return !empty($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function csrf_token(): string
{
    session_start_safe();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $t): bool
{
    session_start_safe();
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

/* -------------------------------------------------- защита от перебора */

function throttle_file(): string
{
    ensure_dirs();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return data_path('throttle/' . sha1($ip) . '.json');
}

/** Сколько секунд ждать до следующей попытки; 0 — можно пробовать. */
function throttle_wait(): int
{
    $f = throttle_file();
    if (!is_file($f)) {
        return 0;
    }
    $d = json_decode((string)file_get_contents($f), true) ?: [];
    $fails = (int)($d['fails'] ?? 0);
    $last  = (int)($d['last'] ?? 0);
    if ($fails < 5) {
        return 0;
    }
    $wait = 900 - (time() - $last);          // после пяти промахов пауза 15 минут
    return $wait > 0 ? $wait : 0;
}

function throttle_fail(): void
{
    $f = throttle_file();
    $d = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $fails = (int)($d['fails'] ?? 0);
    if (time() - (int)($d['last'] ?? 0) > 900) {
        $fails = 0;                          // пауза прошла — счёт заново
    }
    write_atomic($f, json_encode(['fails' => $fails + 1, 'last' => time()]));
}

function throttle_reset(): void
{
    $f = throttle_file();
    if (is_file($f)) {
        @unlink($f);
    }
}

/* ------------------------------------------------------------------ вход */

function try_login(string $login, string $pass): bool
{
    $cfg = config();
    $u = $cfg['user'] ?? null;
    if (!$u || !hash_equals((string)$u['login'], $login)) {
        throttle_fail();
        return false;
    }
    if (!password_verify($pass, (string)$u['hash'])) {
        throttle_fail();
        return false;
    }
    throttle_reset();
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user'] = $login;
    $_SESSION['must_change'] = !empty($u['must_change']);
    return true;
}

function set_password(string $new): void
{
    $cfg = config();
    $cfg['user']['hash'] = password_hash($new, PASSWORD_DEFAULT);
    $cfg['user']['must_change'] = false;
    config_save($cfg);
    session_start_safe();
    $_SESSION['must_change'] = false;
}

function logout(): void
{
    session_start_safe();
    $_SESSION = [];
    session_destroy();
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* --------------------------------------------------------------- журнал */

function log_action(string $what): void
{
    ensure_dirs();
    $line = date('Y-m-d H:i:s') . "\t" . ($_SESSION['user'] ?? '-') . "\t"
          . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\t" . $what . "\n";
    file_put_contents(data_path('actions.log'), $line, FILE_APPEND);
}
