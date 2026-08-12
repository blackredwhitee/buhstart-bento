<?php
/**
 * Сборка страниц из данных — то же, что делает build-site.js в репозитории,
 * только на сервере, чтобы редактор работал без GitHub.
 *
 *   _articles/*.json → article-<slug>.html, blog.html, novosti.html, articles.js
 *   _cases/*.json    → keysy.html, блок кейсов на главной
 *   плюс sitemap.xml
 *
 * Шаблон берётся из готовой страницы статьи, поэтому оформление не расходится.
 */

declare(strict_types=1);

const BASE_URL = 'https://buhstart.ru/';
const COVERS = [
    'Налоги'                   => 'uploads/cover-nalogi.svg',
    'Налоговая'                => 'uploads/cover-nalogovaya.svg',
    'Финансы'                  => 'uploads/cover-finansy.svg',
    'Бизнес'                   => 'uploads/cover-biznes.svg',
    'ИП и ООО'                 => 'uploads/cover-ip-ooo.svg',
    'Новости законодательства' => 'uploads/cover-nalogovaya.svg',
];
const TAGS = ['Налоги', 'Налоговая', 'Финансы', 'Бизнес', 'ИП и ООО', 'Новости законодательства'];

function e(?string $s): string
{
    return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], (string)$s);
}

function read_json_dir(string $dir): array
{
    $out = [];
    foreach (glob(SITE_DIR . '/' . $dir . '/*.json') ?: [] as $f) {
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d)) {
            $d['_file'] = basename($f);
            $out[] = $d;
        }
    }
    usort($out, fn($a, $b) => strcmp((string)($b['isoDate'] ?? ''), (string)($a['isoDate'] ?? '')));
    return $out;
}

function articles_all(): array { return read_json_dir('_articles'); }
function cases_all(): array    { return read_json_dir('_cases'); }

/** Обложка: своя, иначе по разделу, иначе общая. Пустая строка считается отсутствием. */
function cover_of(array $a): string
{
    $own = trim((string)($a['coverImage'] ?? ''));
    if ($own !== '') { return $own; }
    $tag = (string)($a['tag'] ?? '');
    return COVERS[$tag] ?? 'uploads/cover-biznes.svg';
}

function is_news(array $a): bool
{
    return ($a['tag'] ?? '') === 'Новости законодательства' || preg_match('~^novosti-~', (string)($a['slug'] ?? ''));
}

/* ------------------------------------------------------- страница статьи */

function blocks_to_html(array $blocks): string
{
    $out = [];
    foreach ($blocks as $b) {
        $t = $b['t'] ?? 'p';
        $text = (string)($b['text'] ?? '');
        if ($text === '') { continue; }
        if ($t === 'h') {
            $out[] = '<h2>' . e($text) . '</h2>';
        } elseif ($t === 'li') {
            $out[] = '<p>• ' . e($text) . '</p>';
        } elseif ($t === 'quote') {
            $out[] = '<blockquote style="margin:0;border-left:3px solid var(--orange);padding-left:18px;font-size:18px;font-weight:500;line-height:1.5">' . e($text) . '</blockquote>';
        } else {
            $out[] = '<p>' . e($text) . '</p>';
        }
    }
    return implode("\n", $out);
}

function related_cards(array $a, array $all): string
{
    $same = array_values(array_filter($all, fn($x) => ($x['slug'] ?? '') !== ($a['slug'] ?? '') && ($x['tag'] ?? '') === ($a['tag'] ?? '')));
    $pool = count($same) >= 3 ? $same : array_values(array_filter($all, fn($x) => ($x['slug'] ?? '') !== ($a['slug'] ?? '')));
    $out = '';
    foreach (array_slice($pool, 0, 3) as $x) {
        $cover = cover_of($x);
        $out .= '<a class="post" href="article-' . e($x['slug'] ?? '') . '.html"><img src="' . e($cover)
             . '" alt="" loading="lazy" style="height:140px"><div class="b"><h3>' . e($x['title'] ?? '')
             . '</h3><div class="small" style="margin-top:10px">' . e($x['date'] ?? '') . '</div></div></a>';
    }
    return $out;
}

function article_page(array $a, array $all): ?string
{
    $tplFile = null;
    foreach (glob(SITE_DIR . '/article-*.html') ?: [] as $f) {
        $tplFile = $f;                                        // любая готовая страница статьи
        break;
    }
    if (!$tplFile) { return null; }
    $sample  = (string)file_get_contents($tplFile);
    $headTpl = substr($sample, 0, (int)strpos($sample, '<main>'));
    $footTpl = substr($sample, (int)strrpos($sample, '</main>'));

    $news    = is_news($a);
    $section = $news ? 'Новости законодательства' : 'Статьи';
    $secUrl  = $news ? 'novosti.html' : 'blog.html';
    $slug    = (string)($a['slug'] ?? '');
    $title   = (string)($a['title'] ?? '');

    $firstP = '';
    foreach ((array)($a['blocks'] ?? []) as $b) {
        if (($b['t'] ?? 'p') === 'p') { $firstP = (string)($b['text'] ?? ''); break; }
    }
    $desc = (string)($a['preview'] ?? '') ?: (mb_substr($firstP, 0, 300) ?: $title);

    $head = $headTpl;
    $head = preg_replace('~<title>.*?</title>~s', '<title>' . e($title) . ' — Доверительная Бухгалтерия</title>', $head, 1) ?? $head;
    $head = preg_replace('~(name="description" content=")[^"]*(")~', '${1}' . e($desc) . '${2}', $head, 1) ?? $head;
    $head = preg_replace('~(rel="canonical" href=")[^"]*(")~', '${1}' . BASE_URL . 'article-' . $slug . '.html${2}', $head, 1) ?? $head;
    $head = preg_replace('~(property="og:title" content=")[^"]*(")~', '${1}' . e($title) . ' — Доверительная Бухгалтерия${2}', $head, 1) ?? $head;
    $head = preg_replace('~(property="og:description" content=")[^"]*(")~', '${1}' . e($desc) . '${2}', $head, 1) ?? $head;
    $head = preg_replace('~(property="og:url" content=")[^"]*(")~', '${1}' . BASE_URL . 'article-' . $slug . '.html${2}', $head, 1) ?? $head;

    $ld = [
        ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $title,
         'articleSection' => $a['tag'] ?? $section, 'description' => $desc,
         'author' => ['@type' => 'Organization', 'name' => 'Доверительная Бухгалтерия'],
         'publisher' => ['@type' => 'Organization', 'name' => 'Доверительная Бухгалтерия',
             'logo' => ['@type' => 'ImageObject', 'url' => BASE_URL . 'uploads/logo-transparent.png']],
         'mainEntityOfPage' => BASE_URL . 'article-' . $slug . '.html', 'inLanguage' => 'ru-RU'],
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => BASE_URL . 'index.html'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $section, 'item' => BASE_URL . $secUrl],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => BASE_URL . 'article-' . $slug . '.html'],
        ]],
    ];
    $head = preg_replace('~<script type="application/ld\+json">.*?</script>~s', '', $head) ?? $head;
    $head = preg_replace('~\n{3,}~', "\n", $head) ?? $head;
    $json = '';
    foreach ($ld as $x) {
        $json .= '<script type="application/ld+json">' . json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
    $head = str_replace('</head>', $json . "\n</head>", $head);

    $body = '<main>' . "\n"
        . '<section class="wrap" style="padding-top:14px"><div class="tile article">' . "\n"
        . '<nav class="crumbs"><a href="index.html">Главная</a> / <a href="' . $secUrl . '">' . $section . '</a></nav>' . "\n"
        . '<span class="pill">' . e($a['tag'] ?? $section) . '</span>' . "\n"
        . '<h1 style="font-size:40px;margin-top:16px">' . e($title) . '</h1>' . "\n"
        . '<div class="small" style="margin-top:14px">' . e($a['date'] ?? '') . '</div>' . "\n"
        . '<div class="hr"></div>' . "\n"
        . blocks_to_html((array)($a['blocks'] ?? [])) . "\n"
        . '<div class="next-steps">' . "\n"
        . '<div class="ns-title">Что с этим делать</div>' . "\n"
        . '<p class="ns-text">Если вопрос про ваш бизнес, а не «вообще» — разберём вашу ситуацию и скажем, что делать. Без обязательств.</p>' . "\n"
        . '<div class="row" style="margin-top:18px"><button class="btn btn-p" data-lead="Статья: ' . e($title) . '">Разобрать мою ситуацию</button></div>' . "\n"
        . '</div>' . "\n"
        . '</div></section>' . "\n"
        . '<section class="wrap"><div class="tile-dark" style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;justify-content:space-between">' . "\n"
        . '<div><h3 style="font-size:22px;font-weight:700;color:#fff">Остались вопросы по вашей ситуации?</h3><p style="margin-top:8px">Разберём на консультации и скажем, что делать дальше</p></div>' . "\n"
        . '<button class="btn btn-p" data-lead="' . e($title) . '">Записаться</button>' . "\n"
        . '</div></section>' . "\n"
        . '<section class="wrap">' . "\n"
        . '<div style="padding:12px 4px 20px"><h2>Читайте <span class="mark">также</span></h2></div>' . "\n"
        . '<div class="grid g3">' . related_cards($a, $all) . '</div>' . "\n"
        . '</section>' . "\n";

    return $head . $body . $footTpl;
}

/* ------------------------------------------------ списки внутри страниц */

/** Меняем содержимое <div id="..."> …тут… </div>, считая вложенность. */
function replace_inside(string $file, string $id, string $html): bool
{
    $path = SITE_DIR . '/' . $file;
    if (!is_file($path)) { return false; }
    $t = (string)file_get_contents($path);
    $open = strpos($t, 'id="' . $id . '"');
    if ($open === false) { return false; }
    $start = strpos($t, '>', $open);
    if ($start === false) { return false; }
    $start++;
    $depth = 1; $i = $start;
    while ($i < strlen($t) && $depth > 0) {
        $nextOpen  = strpos($t, '<div', $i);
        $nextClose = strpos($t, '</div>', $i);
        if ($nextClose === false) { break; }
        if ($nextOpen !== false && $nextOpen < $nextClose) { $depth++; $i = $nextOpen + 4; }
        else { $depth--; $i = $nextClose + 6; }
    }
    $end = $i - 6;
    backup_file($path);
    return write_atomic($path, substr($t, 0, $start) . $html . substr($t, $end));
}

function card_html(array $a): string
{
    $cover = cover_of($a);
    return '<a class="post" href="article-' . e($a['slug'] ?? '') . '.html" data-tag="' . e($a['tag'] ?? '') . '">'
        . '<img src="' . e($cover) . '" alt="" loading="lazy" style="height:140px"><div class="b">'
        . '<span class="pill">' . e($a['tag'] ?? '') . '</span>'
        . '<h3 style="margin-top:12px">' . e($a['title'] ?? '') . '</h3>'
        . '<div class="small" style="margin-top:10px">' . e($a['date'] ?? '') . '</div>'
        . '<div class="more">Читать →</div></div></a>';
}

function row_html(array $a): string
{
    $label = (string)($a['range'] ?? ($a['date'] ?? ''));
    $note = !empty($a['preview'])
        ? '<span class="cardtext" style="display:block;margin-top:6px">' . e($a['preview']) . '</span>' : '';
    return '<a class="nrow" href="article-' . e($a['slug'] ?? '') . '.html"><span class="d">' . e($label)
        . '</span><span><span class="t">' . e($a['title'] ?? '') . '</span>' . $note . '</span><span class="x">→</span></a>';
}

function case_html(array $c, int $i): string
{
    $metric = !empty($c['metric'])
        ? '<div class="case-metric">' . e($c['metric']) . (!empty($c['metricNote']) ? '<small>' . e($c['metricNote']) . '</small>' : '') . '</div>'
        : '';
    $row = fn(string $label, ?string $text) => $text
        ? '<div class="case-row"><div class="case-label">' . $label . '</div><p class="case-text">' . e($text) . '</p></div>' : '';
    return '<article class="case ' . ($i % 2 ? 'tilt-r' : 'tilt-l') . '">' . "\n"
        . (!empty($c['tag']) ? '<span class="pill case-tag">' . e($c['tag']) . '</span>' : '') . "\n"
        . '<h3 class="case-title">' . e($c['title'] ?? '') . '</h3>' . "\n"
        . $metric . "\n"
        . $row('Что было', $c['was'] ?? null) . "\n"
        . $row('Что сделали', $c['did'] ?? null) . "\n"
        . $row('Результат', $c['result'] ?? null) . "\n"
        . '</article>';
}

function case_teaser(array $c, int $i): string
{
    $metric = !empty($c['metric'])
        ? '<div class="ct-metric">' . e($c['metric']) . '</div><div class="ct-note">' . e($c['metricNote'] ?? '') . '</div>' : '';
    return '<a class="case-teaser' . ($i === 0 ? ' teaser-out' : '') . '" href="keysy.html">' . "\n"
        . (!empty($c['tag']) ? '<span class="pill">' . e($c['tag']) . '</span>' : '') . "\n"
        . $metric . "\n"
        . '<div class="ct-title">' . e($c['title'] ?? '') . '</div>' . "\n"
        . '<div class="more">Смотреть кейс →</div>' . "\n"
        . '</a>';
}

/* ---------------------------------------------------------- вся сборка */

function build_all(): array
{
    $articles = articles_all();
    $cases    = cases_all();
    $report   = [];

    $built = 0;
    foreach ($articles as $a) {
        if (empty($a['slug'])) { continue; }
        $html = article_page($a, $articles);
        if ($html === null) { continue; }
        write_atomic(SITE_DIR . '/article-' . $a['slug'] . '.html', $html);
        $built++;
    }
    $report[] = 'страниц статей: ' . $built;

    $posts = array_values(array_filter($articles, fn($a) => !is_news($a)));
    $news  = array_values(array_filter($articles, 'is_news'));
    replace_inside('blog.html', 'posts', implode('', array_map('card_html', $posts)));
    replace_inside('novosti.html', 'news', implode('', array_map('row_html', $news)));
    $report[] = 'в блоге: ' . count($posts) . ', новостей: ' . count($news);

    if ($cases) {
        $list = '';
        foreach ($cases as $i => $c) { $list .= case_html($c, $i) . "\n"; }
        replace_inside('keysy.html', 'cases', $list);
        $teasers = '';
        foreach (array_slice($cases, 0, 3) as $i => $c) { $teasers .= case_teaser($c, $i); }
        replace_inside('index.html', 'cases-home', $teasers);
        $report[] = 'кейсов: ' . count($cases);
    }

    // тизеры для главной
    $lines = ['window.BUHSTART_ARTICLES = {};'];
    foreach ($articles as $a) {
        $slug = (string)($a['slug'] ?? '');
        $rest = $a;
        unset($rest['slug'], $rest['isoDate'], $rest['_file']);
        $lines[] = 'window.BUHSTART_ARTICLES[' . json_encode($slug, JSON_UNESCAPED_UNICODE) . '] = '
                 . json_encode($rest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    }
    write_atomic(SITE_DIR . '/articles.js', implode("\n", $lines) . "\n");

    // карта сайта
    $static = ['index.html','uslugi.html','usluga-bukhgalterskie-uslugi.html','usluga-nadzor.html',
        'usluga-audit.html','usluga-upravlencheskii-uchet.html','usluga-marketplace.html','keysy.html',
        'calculator.html','blog.html','novosti.html','team.html','vacancy.html','contacts.html',
        'privacy.html','soglasie.html'];
    $urls = [];
    foreach ($static as $p) {
        if (!is_file(SITE_DIR . '/' . $p)) { continue; }
        $urls[] = '  <url><loc>' . BASE_URL . $p . '</loc><changefreq>weekly</changefreq><priority>'
                . ($p === 'index.html' ? '1.0' : '0.8') . '</priority></url>';
    }
    foreach ($articles as $a) {
        $urls[] = '  <url><loc>' . BASE_URL . 'article-' . $a['slug'] . '.html</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>';
    }
    write_atomic(SITE_DIR . '/sitemap.xml',
        '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
        . implode("\n", $urls) . "\n" . '</urlset>' . "\n");

    return $report;
}

/* ------------------------------------------------- запись самих данных */

function slugify(string $s): string
{
    return translit_slug($s);
}

function article_save(array $in, ?string $origFile): array
{
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') { return [false, 'Заголовок не может быть пустым.']; }

    $slug = trim((string)($in['slug'] ?? '')) ?: slugify($title);
    $slug = preg_replace('~[^a-z0-9\-]~', '', mb_strtolower($slug)) ?? '';
    if ($slug === '') { return [false, 'Не удалось составить адрес страницы — впишите его вручную латиницей.']; }

    $tag = (string)($in['tag'] ?? 'Бизнес');
    if (!in_array($tag, TAGS, true)) { $tag = 'Бизнес'; }

    $iso = (string)($in['isoDate'] ?? '');
    if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $iso)) { $iso = date('Y-m-d'); }

    $blocks = [];
    foreach (preg_split('~\R{2,}~u', (string)($in['body'] ?? '')) ?: [] as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') { continue; }
        if (str_starts_with($chunk, '## ')) {
            $blocks[] = ['t' => 'h', 'text' => trim(substr($chunk, 3))];
        } elseif (str_starts_with($chunk, '> ')) {
            $blocks[] = ['t' => 'quote', 'text' => trim(substr($chunk, 2))];
        } elseif (preg_match('~^[-•*]\s~u', $chunk)) {
            foreach (preg_split('~\R~u', $chunk) ?: [] as $li) {
                $li = preg_replace('~^[-•*]\s*~u', '', trim($li)) ?? '';
                if ($li !== '') { $blocks[] = ['t' => 'li', 'text' => $li]; }
            }
        } else {
            $blocks[] = ['t' => 'p', 'text' => $chunk];
        }
    }
    if (!$blocks) { return [false, 'Текст статьи пустой.']; }

    $data = [
        'slug'    => $slug,
        'title'   => $title,
        'tag'     => $tag,
        'date'    => ru_date($iso),
        'isoDate' => $iso,
        'preview' => trim((string)($in['preview'] ?? '')),
        'blocks'  => $blocks,
    ];
    if (!empty($in['range'])) { $data['range'] = trim((string)$in['range']); }
    if (!empty($in['coverImage'])) { $data['coverImage'] = trim((string)$in['coverImage']); }

    $dir = SITE_DIR . '/_articles';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $file = $dir . '/' . $slug . '.json';

    // переименовали адрес — старый файл и старую страницу убираем
    if ($origFile && $origFile !== $slug . '.json') {
        $old = $dir . '/' . basename($origFile);
        if (is_file($old)) { backup_file($old); @unlink($old); }
        $oldPage = SITE_DIR . '/article-' . basename($origFile, '.json') . '.html';
        if (is_file($oldPage)) { backup_file($oldPage); @unlink($oldPage); }
    }

    if (is_file($file)) { backup_file($file); }
    if (!write_atomic($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n")) {
        return [false, 'Не удалось записать файл статьи.'];
    }
    $rep = build_all();
    return [true, 'Сохранено и опубликовано. ' . implode(', ', $rep)];
}

function article_delete(string $file): array
{
    $p = SITE_DIR . '/_articles/' . basename($file);
    if (!is_file($p)) { return [false, 'Статья не найдена.']; }
    $slug = basename($file, '.json');
    backup_file($p);
    @unlink($p);
    $page = SITE_DIR . '/article-' . $slug . '.html';
    if (is_file($page)) { backup_file($page); @unlink($page); }
    $rep = build_all();
    return [true, 'Статья снята с публикации, копия сохранена. ' . implode(', ', $rep)];
}

function case_save(array $in, ?string $origFile): array
{
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') { return [false, 'Заголовок кейса не может быть пустым.']; }
    $slug = trim((string)($in['slug'] ?? '')) ?: slugify($title);
    $slug = preg_replace('~[^a-z0-9\-]~', '', mb_strtolower($slug)) ?? '';
    if ($slug === '') { $slug = 'case-' . date('Ymd-His'); }
    $iso = (string)($in['isoDate'] ?? '');
    if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $iso)) { $iso = date('Y-m-d'); }

    $data = [
        'slug'       => $slug,
        'isoDate'    => $iso,
        'tag'        => trim((string)($in['tag'] ?? '')),
        'title'      => $title,
        'metric'     => trim((string)($in['metric'] ?? '')),
        'metricNote' => trim((string)($in['metricNote'] ?? '')),
        'was'        => trim((string)($in['was'] ?? '')),
        'did'        => trim((string)($in['did'] ?? '')),
        'result'     => trim((string)($in['result'] ?? '')),
    ];
    $dir = SITE_DIR . '/_cases';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if ($origFile && $origFile !== $slug . '.json') {
        $old = $dir . '/' . basename($origFile);
        if (is_file($old)) { backup_file($old); @unlink($old); }
    }
    $file = $dir . '/' . $slug . '.json';
    if (is_file($file)) { backup_file($file); }
    if (!write_atomic($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n")) {
        return [false, 'Не удалось записать файл кейса.'];
    }
    $rep = build_all();
    return [true, 'Кейс сохранён и опубликован. ' . implode(', ', $rep)];
}

function case_delete(string $file): array
{
    $p = SITE_DIR . '/_cases/' . basename($file);
    if (!is_file($p)) { return [false, 'Кейс не найден.']; }
    backup_file($p);
    @unlink($p);
    $rep = build_all();
    return [true, 'Кейс убран с сайта, копия сохранена. ' . implode(', ', $rep)];
}

/** Дата по-русски: 5 августа 2026 */
function ru_date(string $iso): string
{
    $m = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
    [$y, $mo, $d] = array_map('intval', explode('-', $iso));
    return $d . ' ' . ($m[$mo - 1] ?? '') . ' ' . $y;
}

/** Текст статьи обратно в простой вид для правки. */
function blocks_to_text(array $blocks): string
{
    $out = [];
    foreach ($blocks as $b) {
        $t = $b['t'] ?? 'p';
        $text = (string)($b['text'] ?? '');
        if ($t === 'h')      { $out[] = '## ' . $text; }
        elseif ($t === 'quote') { $out[] = '> ' . $text; }
        elseif ($t === 'li') { $out[] = '- ' . $text; }
        else                 { $out[] = $text; }
    }
    return implode("\n\n", $out);
}
