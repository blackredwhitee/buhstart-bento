<?php
/**
 * Замена и загрузка картинок.
 *
 * Замена идёт «в то же имя файла» — тогда страницы менять не нужно,
 * новое изображение подхватывается само. Старое уходит в архив копий.
 */

declare(strict_types=1);

const IMG_DIR = 'uploads';
const IMG_MAX = 8 * 1024 * 1024;                       // 8 МБ на файл
const IMG_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                   'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'ico' => 'image/x-icon'];

function images_list(): array
{
    $dir = SITE_DIR . '/' . IMG_DIR;
    $out = [];
    foreach (glob($dir . '/*') ?: [] as $p) {
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        if (!isset(IMG_TYPES[$ext]) || !is_file($p)) {
            continue;
        }
        $out[] = [
            'name' => basename($p),
            'size' => filesize($p) ?: 0,
            'time' => filemtime($p) ?: 0,
            'dim'  => $ext === 'svg' ? '' : (function ($p) {
                $s = @getimagesize($p);
                return $s ? $s[0] . '×' . $s[1] : '';
            })($p),
        ];
    }
    usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $out;
}

function image_path(string $name): ?string
{
    $name = basename($name);                            // никаких путей наружу
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!isset(IMG_TYPES[$ext])) {
        return null;
    }
    $p = SITE_DIR . '/' . IMG_DIR . '/' . $name;
    return is_file($p) ? $p : null;
}

/** Проверка загруженного файла: тип, размер и то, что это действительно картинка. */
function check_upload(array $f, ?string $forceExt = null): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Файл не загрузился. Возможно, он слишком большой.'];
    }
    if (($f['size'] ?? 0) > IMG_MAX) {
        return [false, 'Файл больше 8 МБ. Сожмите его и попробуйте снова.'];
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!isset(IMG_TYPES[$ext])) {
        return [false, 'Принимаем JPG, PNG, SVG, WEBP.'];
    }
    if ($forceExt !== null && $ext !== $forceExt
        && !($forceExt === 'jpg' && $ext === 'jpeg') && !($forceExt === 'jpeg' && $ext === 'jpg')) {
        return [false, 'Нужен файл того же типа: .' . $forceExt];
    }
    if ($ext === 'svg') {
        $svg = (string)file_get_contents((string)$f['tmp_name']);
        if (preg_match('~<\s*script|javascript:|\son[a-z]+\s*=~i', $svg)) {
            return [false, 'В этом SVG есть скрипты — такой файл не принимаем.'];
        }
    } else {
        $info = @getimagesize((string)$f['tmp_name']);
        if (!$info) {
            return [false, 'Это не похоже на изображение.'];
        }
    }
    return [true, $ext];
}

/** Замена существующей картинки под тем же именем. */
function image_replace(string $name, array $f): array
{
    $path = image_path($name);
    if (!$path) {
        return [false, 'Такой картинки нет.'];
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    [$ok, $res] = check_upload($f, $ext);
    if (!$ok) {
        return [false, $res];
    }
    backup_file($path);
    if (!move_uploaded_file((string)$f['tmp_name'], $path)) {
        return [false, 'Не удалось записать файл.'];
    }
    @chmod($path, 0644);
    return [true, 'Картинка «' . basename($path) . '» заменена. Если на сайте видно старую — обновите страницу с Ctrl+R.'];
}

/** Загрузка новой картинки под своим именем. */
function image_add(string $wanted, array $f): array
{
    [$ok, $ext] = check_upload($f);
    if (!$ok) {
        return [false, $ext];
    }
    $base = mb_strtolower(trim($wanted) !== '' ? $wanted : pathinfo((string)$f['name'], PATHINFO_FILENAME));
    $base = translit_slug($base);
    if ($base === '') {
        $base = 'file-' . date('Ymd-His');
    }
    $path = SITE_DIR . '/' . IMG_DIR . '/' . $base . '.' . $ext;
    if (is_file($path)) {
        return [false, 'Файл с таким именем уже есть — выберите другое имя или замените существующую картинку.'];
    }
    if (!move_uploaded_file((string)$f['tmp_name'], $path)) {
        return [false, 'Не удалось записать файл.'];
    }
    @chmod($path, 0644);
    return [true, 'Загружено: uploads/' . basename($path) . '. Этот адрес можно вставить в текст страницы.'];
}

function translit_slug(string $s): string
{
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i',
            'й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t',
            'у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'',
            'э'=>'e','ю'=>'yu','я'=>'ya',' '=>'-','_'=>'-'];
    $s = strtr(mb_strtolower($s), $map);
    $s = preg_replace('~[^a-z0-9\-]+~', '-', $s) ?? '';
    return trim(preg_replace('~-+~', '-', $s) ?? '', '-');
}

function human_size(int $b): string
{
    return $b >= 1048576 ? round($b / 1048576, 1) . ' МБ' : max(1, (int)round($b / 1024)) . ' КБ';
}
