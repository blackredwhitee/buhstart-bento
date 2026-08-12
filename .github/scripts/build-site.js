/* Сборка страниц из данных.
   Источники:
     _articles/*.json — статьи и новости законодательства
     _cases/*.json    — кейсы
   Что собирается:
     article-<slug>.html, blog.html, novosti.html, keysy.html,
     articles.js (тизеры для главной), sitemap.xml
   Шаблон берётся из существующей страницы, поэтому оформление не расходится. */

const fs = require('fs');
const path = require('path');

const BASE = 'https://buhstart.ru/';
const COVERS = {
  'Налоги': 'uploads/cover-nalogi.svg',
  'Налоговая': 'uploads/cover-nalogovaya.svg',
  'Финансы': 'uploads/cover-finansy.svg',
  'Бизнес': 'uploads/cover-biznes.svg',
  'ИП и ООО': 'uploads/cover-ip-ooo.svg',
  'Новости законодательства': 'uploads/cover-nalogovaya.svg'
};
const esc = s => String(s == null ? '' : s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

function read(dir) {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir).filter(f => f.endsWith('.json'))
    .map(f => JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8')))
    .sort((a, b) => (b.isoDate || '').localeCompare(a.isoDate || ''));
}

const articles = read('_articles');
const cases = read('_cases');
const isNews = a => (a.tag === 'Новости законодательства') || /^novosti-/.test(a.slug || '');

/* ---------- страницы статей ---------- */

// шаблон: берём готовую страницу и вырезаем её «начинку»
const sample = fs.readFileSync('article-ooo-ili-ip.html', 'utf8');
const headTpl = sample.slice(0, sample.indexOf('<main>'));
const footTpl = sample.slice(sample.lastIndexOf('</main>'));

function blocksToHtml(blocks) {
  return (blocks || []).map(b => {
    if (b.t === 'h') return `<h2>${esc(b.text)}</h2>`;
    if (b.t === 'li') return `<p>• ${esc(b.text)}</p>`;
    if (b.t === 'quote') return `<blockquote style="margin:0;border-left:3px solid var(--orange);padding-left:18px;font-size:18px;font-weight:500;line-height:1.5">${esc(b.text)}</blockquote>`;
    return `<p>${esc(b.text)}</p>`;
  }).join('\n');
}

function relatedCards(a, all) {
  const same = all.filter(x => x.slug !== a.slug && x.tag === a.tag);
  const pool = same.length >= 3 ? same : all.filter(x => x.slug !== a.slug);
  return pool.slice(0, 3).map(x => {
    const cover = x.coverImage || COVERS[x.tag] || 'uploads/cover-biznes.svg';
    return `<a class="post" href="article-${x.slug}.html"><img src="${cover}" alt="" loading="lazy" style="height:140px"><div class="b"><h3>${esc(x.title)}</h3><div class="small" style="margin-top:10px">${esc(x.date || '')}</div></div></a>`;
  }).join('');
}

function articlePage(a, all) {
  const news = isNews(a);
  const section = news ? 'Новости законодательства' : 'Статьи';
  const sectionUrl = news ? 'novosti.html' : 'blog.html';
  const firstP = (a.blocks || []).find(b => !b.t || b.t === 'p');
  const desc = a.preview || (firstP ? firstP.text.slice(0, 300) : a.title);

  let head = headTpl
    .replace(/<title>.*?<\/title>/s, `<title>${esc(a.title)} — Доверительная Бухгалтерия</title>`)
    .replace(/(name="description" content=")[^"]*(")/, `$1${esc(desc)}$2`)
    .replace(/(rel="canonical" href=")[^"]*(")/, `$1${BASE}article-${a.slug}.html$2`)
    .replace(/(property="og:title" content=")[^"]*(")/, `$1${esc(a.title)} — Доверительная Бухгалтерия$2`)
    .replace(/(property="og:description" content=")[^"]*(")/, `$1${esc(desc)}$2`)
    .replace(/(property="og:url" content=")[^"]*(")/, `$1${BASE}article-${a.slug}.html$2`);

  // микроразметка статьи и крошек
  const ld = [
    {'@context':'https://schema.org','@type':'Article','headline':a.title,
     'articleSection':a.tag || section,'description':desc,
     'author':{'@type':'Organization','name':'Доверительная Бухгалтерия'},
     'publisher':{'@type':'Organization','name':'Доверительная Бухгалтерия',
       'logo':{'@type':'ImageObject','url':BASE+'uploads/logo-transparent.png'}},
     'mainEntityOfPage':BASE+'article-'+a.slug+'.html','inLanguage':'ru-RU'},
    {'@context':'https://schema.org','@type':'BreadcrumbList','itemListElement':[
      {'@type':'ListItem','position':1,'name':'Главная','item':BASE+'index.html'},
      {'@type':'ListItem','position':2,'name':section,'item':BASE+sectionUrl},
      {'@type':'ListItem','position':3,'name':a.title,'item':BASE+'article-'+a.slug+'.html'}]}
  ];
  head = head.replace(/<script type="application\/ld\+json">.*?<\/script>/gs, '')
             .replace('</head>', ld.map(x => `<script type="application/ld+json">${JSON.stringify(x)}</script>`).join('') + '\n</head>');

  const body = `<main>
<section class="wrap" style="padding-top:14px"><div class="tile article">
<nav class="crumbs"><a href="index.html">Главная</a> / <a href="${sectionUrl}">${section}</a></nav>
<span class="pill">${esc(a.tag || section)}</span>
<h1 style="font-size:40px;margin-top:16px">${esc(a.title)}</h1>
<div class="small" style="margin-top:14px">${esc(a.date || '')}</div>
<div class="hr"></div>
${blocksToHtml(a.blocks)}
<div class="next-steps">
<div class="ns-title">Что с этим делать</div>
<p class="ns-text">Если вопрос про ваш бизнес, а не «вообще» — разберём вашу ситуацию и скажем, что делать. Без обязательств.</p>
<div class="row" style="margin-top:18px"><button class="btn btn-p" data-lead="Статья: ${esc(a.title)}">Разобрать мою ситуацию</button></div>
</div>
</div></section>
<section class="wrap"><div class="tile-dark" style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;justify-content:space-between">
<div><h3 style="font-size:22px;font-weight:700;color:#fff">Остались вопросы по вашей ситуации?</h3><p style="margin-top:8px">Разберём на консультации и скажем, что делать дальше</p></div>
<button class="btn btn-p" data-lead="${esc(a.title)}">Записаться</button>
</div></section>
<section class="wrap">
<div style="padding:12px 4px 20px"><h2>Читайте <span class="mark">также</span></h2></div>
<div class="grid g3">${relatedCards(a, articles)}</div>
</section>
`;
  return head + body + footTpl;
}

let built = 0;
for (const a of articles) {
  if (!a.slug) continue;
  fs.writeFileSync(`article-${a.slug}.html`, articlePage(a, articles));
  built++;
}

/* ---------- списки: блог и новости ---------- */

function cardHtml(a) {
  const cover = a.coverImage || COVERS[a.tag] || 'uploads/cover-biznes.svg';
  return `<a class="post" href="article-${a.slug}.html" data-tag="${esc(a.tag || '')}"><img src="${cover}" alt="" loading="lazy" style="height:140px"><div class="b"><span class="pill">${esc(a.tag || '')}</span><h3 style="margin-top:12px">${esc(a.title)}</h3><div class="small" style="margin-top:10px">${esc(a.date || '')}</div><div class="more">Читать →</div></div></a>`;
}
function rowHtml(a) {
  const label = a.range || a.date || '';
  const note = a.preview ? `<span class="cardtext" style="display:block;margin-top:6px">${esc(a.preview)}</span>` : '';
  return `<a class="nrow" href="article-${a.slug}.html"><span class="d">${esc(label)}</span><span><span class="t">${esc(a.title)}</span>${note}</span><span class="x">→</span></a>`;
}
function replaceInside(file, id, html) {
  if (!fs.existsSync(file)) return false;
  let t = fs.readFileSync(file, 'utf8');
  const open = t.indexOf(`id="${id}"`);
  if (open < 0) return false;
  const start = t.indexOf('>', open) + 1;
  // ищем закрывающий тег того же уровня
  let depth = 1, i = start;
  while (i < t.length && depth > 0) {
    const nextOpen = t.indexOf('<div', i), nextClose = t.indexOf('</div>', i);
    if (nextClose < 0) break;
    if (nextOpen > -1 && nextOpen < nextClose) { depth++; i = nextOpen + 4; }
    else { depth--; i = nextClose + 6; }
  }
  const end = i - 6;
  fs.writeFileSync(file, t.slice(0, start) + html + t.slice(end));
  return true;
}

const posts = articles.filter(a => !isNews(a));
const news = articles.filter(isNews);
replaceInside('blog.html', 'posts', posts.map(cardHtml).join(''));
replaceInside('novosti.html', 'news', news.map(rowHtml).join(''));

/* ---------- кейсы ---------- */

function caseHtml(c, i) {
  const metric = c.metric
    ? `<div class="case-metric">${esc(c.metric)}${c.metricNote ? `<small>${esc(c.metricNote)}</small>` : ''}</div>`
    : '';
  const row = (label, text) => text
    ? `<div class="case-row"><div class="case-label">${label}</div><p class="case-text">${esc(text)}</p></div>`
    : '';
  return `<article class="case ${i % 2 ? 'tilt-r' : 'tilt-l'}">
${c.tag ? `<span class="pill case-tag">${esc(c.tag)}</span>` : ''}
<h3 class="case-title">${esc(c.title || '')}</h3>
${metric}
${row('Что было', c.was)}
${row('Что сделали', c.did)}
${row('Результат', c.result)}
</article>`;
}

if (cases.length) replaceInside('keysy.html', 'cases', cases.map(caseHtml).join('\n'));


/* ---------- кейсы на главной: три компактные плитки ---------- */
function caseTeaser(c, i) {
  const metric = c.metric
    ? `<div class="ct-metric">${esc(c.metric)}</div><div class="ct-note">${esc(c.metricNote || '')}</div>`
    : '';
  return `<a class="case-teaser${i === 0 ? ' teaser-out' : ''}" href="keysy.html">
${c.tag ? `<span class="pill">${esc(c.tag)}</span>` : ''}
${metric}
<div class="ct-title">${esc(c.title || '')}</div>
<div class="more">Смотреть кейс →</div>
</a>`;
}
if (cases.length) replaceInside('index.html', 'cases-home', cases.slice(0, 3).map(caseTeaser).join(''));

/* ---------- тизеры для главной ---------- */

const lines = ['window.BUHSTART_ARTICLES = {};'];
for (const a of articles) {
  const { slug, isoDate, ...rest } = a;
  lines.push(`window.BUHSTART_ARTICLES[${JSON.stringify(slug)}] = ${JSON.stringify(rest)};`);
}
fs.writeFileSync('articles.js', lines.join('\n') + '\n');

/* ---------- карта сайта ---------- */

const staticPages = ['index.html','uslugi.html','usluga-bukhgalterskie-uslugi.html','usluga-nadzor.html',
  'usluga-audit.html','usluga-upravlencheskii-uchet.html','keysy.html','calculator.html','blog.html',
  'novosti.html','team.html','vacancy.html','contacts.html','privacy.html','soglasie.html'];
const urls = staticPages.map(p => `  <url><loc>${BASE}${p}</loc><changefreq>weekly</changefreq><priority>${p === 'index.html' ? '1.0' : '0.8'}</priority></url>`)
  .concat(articles.map(a => `  <url><loc>${BASE}article-${a.slug}.html</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>`));
fs.writeFileSync('sitemap.xml',
  `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join('\n')}\n</urlset>\n`);

console.log(`Собрано: страниц статей ${built}, статей в блоге ${posts.length}, новостей ${news.length}, кейсов ${cases.length}`);
