<?php
/**
 * Правка текстов на страницах.
 *
 * Файл не пересобирается: находим границы текста внутри разрешённых тегов и
 * подменяем только эти куски. Всё остальное — вёрстка, атрибуты, отступы —
 * остаётся байт в байт как было. Поэтому сломать дизайн правкой текста нельзя.
 */

declare(strict_types=1);

// какие теги считаем текстовыми
const EDIT_TAGS = ['h1', 'h2', 'h3', 'h4', 'p', 'li', 'blockquote', 'summary'];

// внутри текста разрешаем только оформление, не блоки
const KEEP_TAGS = ['a', 'b', 'strong', 'i', 'em', 'br', 'span', 'sup', 'sub', 'small', 'u'];

// страницы, которые собираются из данных, править напрямую нельзя — их перезапишет сборка
function editable_pages(): array
{
    $titles = [
        'index.html'    => 'Главная',
        'uslugi.html'   => 'Услуги — список',
        'usluga-bukhgalterskie-uslugi.html' => 'Услуга: Бухгалтерские услуги',
        'usluga-nadzor.html'                => 'Услуга: Бухгалтерский надзор',
        'usluga-audit.html'                 => 'Услуга: Аудит',
        'usluga-upravlencheskii-uchet.html' => 'Услуга: Управленческий учёт',
        'usluga-marketplace.html'           => 'Услуга: Маркетплейсы',
        'keysy.html'    => 'Кейсы',
        'team.html'     => 'Команда',
        'contacts.html' => 'Контакты',
        'calculator.html' => 'Калькулятор',
        'blog.html'     => 'Блог — список',
        'novosti.html'  => 'Новости — список',
        'vacancy.html'  => 'Работа у нас',
        'privacy.html'  => 'Политика конфиденциальности',
        'soglasie.html' => 'Согласие на обработку данных',
        '404.html'      => 'Страница не найдена',
    ];
    $out = [];
    foreach ($titles as $file => $name) {
        if (is_file(SITE_DIR . '/' . $file)) {
            $out[$file] = $name;
        }
    }
    return $out;
}

function page_path(string $file): ?string
{
    if (!array_key_exists($file, editable_pages())) {
        return null;                                  // только из белого списка
    }
    $p = SITE_DIR . '/' . $file;
    return is_file($p) ? $p : null;
}

/** Границы <main>…</main>: правим только содержимое страницы, не шапку и не подвал. */
function main_range(string $html): array
{
    $s = stripos($html, '<main');
    if ($s === false) {
        return [0, strlen($html)];
    }
    $s = strpos($html, '>', $s);
    $e = stripos($html, '</main>');
    return [$s === false ? 0 : $s + 1, $e === false ? strlen($html) : $e];
}

/**
 * Список текстовых кусков страницы: тег, текст и границы в файле.
 * Внутрь берём только «листья» — если в теге есть блочная разметка, не трогаем.
 */
function find_blocks(string $html): array
{
    [$from, $to] = main_range($html);
    $blocks = [];
    $pos = $from;
    $tagsRe = implode('|', EDIT_TAGS);

    while ($pos < $to && preg_match('~<(' . $tagsRe . ')(\s[^>]*)?>~i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $tag      = strtolower($m[1][0]);
        $openAt   = $m[0][1];
        $innerAt  = $openAt + strlen($m[0][0]);
        if ($openAt >= $to) {
            break;
        }

        // ищем свой закрывающий тег с учётом вложенности таких же
        $depth = 1;
        $scan  = $innerAt;
        $closeAt = null;
        while (preg_match('~<(/?)' . $tag . '(\s[^>]*)?>~i', $html, $mm, PREG_OFFSET_CAPTURE, $scan)) {
            $isClose = $mm[1][0] === '/';
            $at      = $mm[0][1];
            $depth  += $isClose ? -1 : 1;
            $scan    = $at + strlen($mm[0][0]);
            if ($depth === 0) {
                $closeAt = $at;
                break;
            }
        }
        if ($closeAt === null) {                        // тег не закрыт — пропускаем
            $pos = $innerAt;
            continue;
        }

        $inner = substr($html, $innerAt, $closeAt - $innerAt);

        // внутри блочная разметка или скрипт — это контейнер, а не текст
        $isLeaf = !preg_match('~<\s*/?\s*(div|section|article|ul|ol|table|tr|td|form|script|style|h[1-6]|p|button|img|svg|input|textarea|details|figure|iframe|main|nav|header|footer)\b~i', $inner);

        if ($isLeaf && trim(strip_tags($inner)) !== '') {
            $blocks[] = [
                'tag'   => $tag,
                'start' => $innerAt,
                'end'   => $closeAt,
                'html'  => $inner,
                'text'  => trim(preg_replace('~\s+~u', ' ', strip_tags($inner)) ?? ''),
            ];
            $pos = $closeAt;                            // внутрь листа не заходим
        } else {
            $pos = $innerAt;                            // это контейнер — идём внутрь
        }
    }
    return $blocks;
}

/** Оставляем только оформление: ссылки, выделение, перенос строки. */
function sanitize_inline(string $html): string
{
    $keep = implode('|', KEEP_TAGS);

    // выкидываем всё опасное вместе с содержимым
    $html = preg_replace('~<\s*(script|style|iframe|object|embed)\b.*?<\s*/\s*\1\s*>~is', '', $html) ?? '';

    // остальные неразрешённые теги убираем, текст внутри оставляем
    $html = preg_replace_callback('~<\s*/?\s*([a-z0-9]+)\b([^>]*)>~i', function ($m) use ($keep) {
        $tag = strtolower($m[1]);
        if (!in_array($tag, KEEP_TAGS, true)) {
            return '';
        }
        // чистим атрибуты: у ссылок оставляем адрес и цель, у остального — класс
        $attrs = '';
        if ($tag === 'a' && preg_match('~href\s*=\s*("|\')(.*?)\1~i', $m[2], $h)) {
            $href = trim($h[2]);
            if (preg_match('~^(https?:|mailto:|tel:|/|[a-z0-9._\-]+\.html|#)~i', $href)) {
                $attrs .= ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
                if (preg_match('~^https?://~i', $href)) {
                    $attrs .= ' target="_blank" rel="noopener"';
                }
            }
        }
        if (preg_match('~class\s*=\s*("|\')(.*?)\1~i', $m[2], $c)) {
            $attrs .= ' class="' . htmlspecialchars(trim($c[2]), ENT_QUOTES, 'UTF-8') . '"';
        }
        $slash = str_starts_with(ltrim($m[0], '<'), '/') ? '/' : '';
        if ($slash === '/') {
            return '</' . $tag . '>';
        }
        return '<' . $tag . $attrs . ($tag === 'br' ? '/' : '') . '>';
    }, $html) ?? '';

    // недопустимые обработчики на всякий случай
    return preg_replace('~\son[a-z]+\s*=\s*("|\').*?\1~i', '', $html) ?? '';
}

/**
 * Применяем правки. Ключ — номер куска, значение — новый текст.
 * Пишем с конца, чтобы не сбить границы предыдущих кусков.
 */
function apply_blocks(string $file, array $values, string $expectHash): array
{
    $html = (string)file_get_contents($file);
    if ($expectHash !== '' && !hash_equals(md5($html), $expectHash)) {
        return [false, 'Страница изменилась, пока вы её правили. Откройте её заново.'];
    }
    $blocks = find_blocks($html);
    $changed = 0;

    foreach (array_reverse(array_keys($blocks)) as $i) {
        if (!array_key_exists((string)$i, $values) && !array_key_exists($i, $values)) {
            continue;
        }
        $new = (string)($values[$i] ?? $values[(string)$i]);
        $new = sanitize_inline(trim($new));
        if ($new === '' || $new === $blocks[$i]['html']) {
            continue;                                  // пусто не пишем: так текст не потеряется
        }
        $html = substr($html, 0, $blocks[$i]['start']) . $new . substr($html, $blocks[$i]['end']);
        $changed++;
    }

    if ($changed === 0) {
        return [true, 'Изменений не было.'];
    }
    backup_file($file);
    if (!write_atomic($file, $html)) {
        return [false, 'Не удалось записать файл страницы.'];
    }
    return [true, 'Сохранено. Изменено фрагментов: ' . $changed . '. Копия старой версии сохранена.'];
}
