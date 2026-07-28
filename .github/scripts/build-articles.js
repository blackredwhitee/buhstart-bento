const fs = require('fs');
const path = require('path');

const dir = '_articles';
const out = 'articles.js';

const files = fs.readdirSync(dir).filter(f => f.endsWith('.json'));

const articles = files
  .map(f => JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8')))
  .sort((a, b) => (b.isoDate || '').localeCompare(a.isoDate || ''));

let lines = ['window.BUHSTART_ARTICLES = {};'];
for (const a of articles) {
  const { slug, isoDate, preview, coverImage, ...rest } = a;
  const entry = { ...rest };
  if (preview) entry.preview = preview;
  if (coverImage) entry.coverImage = coverImage;
  lines.push(`window.BUHSTART_ARTICLES[${JSON.stringify(slug)}] = ${JSON.stringify(entry)};`);
}

fs.writeFileSync(out, lines.join('\n') + '\n');
console.log(`Built ${articles.length} articles → ${out}`);
