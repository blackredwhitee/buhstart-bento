"""Безопасное редактирование DC-страниц.

Шаблон страницы лежит внутри <script type="__bundler/template"> как JSON-строка,
в которой все </  экранированы как <\/ (safe_json). Если это экранирование
потерять, браузер закроет внешний <script> раньше времени и страница упадёт с
"Unterminated string in JSON".

Блобы шапки/подвала — gzip+base64 в __bundler/manifest, у каждой страницы свой UUID.
"""
import base64
import gzip
import json
import re

TPL_RE = re.compile(r'(<script type="__bundler/template">)(.*?)(</script>)', re.DOTALL)


def _encode_tpl(html: str) -> str:
    """JSON-строка с safe_json экранированием."""
    return json.dumps(html, ensure_ascii=False).replace('</', '<\\/')


def read_template(path: str) -> str:
    raw = open(path, encoding='utf-8').read()
    m = TPL_RE.search(raw)
    if not m:
        raise ValueError(f'{path}: не найден __bundler/template')
    return json.loads(m.group(2))


def write_template(path: str, html: str) -> None:
    raw = open(path, encoding='utf-8').read()
    m = TPL_RE.search(raw)
    if not m:
        raise ValueError(f'{path}: не найден __bundler/template')
    new = raw[:m.start(2)] + _encode_tpl(html) + raw[m.end(2):]
    # контроль: шаблон обязан снова распарситься
    m2 = TPL_RE.search(new)
    json.loads(m2.group(2))
    open(path, 'w', encoding='utf-8').write(new)


def edit_template(path: str, *pairs, count: int = 1, required: bool = True) -> int:
    """pairs = (old, new), ... Возвращает число применённых замен."""
    html = read_template(path)
    applied = 0
    for old, new in pairs:
        n = html.count(old)
        if n == 0:
            if required:
                raise ValueError(f'{path}: не найдено:\n  {old[:200]}')
            continue
        html = html.replace(old, new) if count is None else html.replace(old, new, count)
        applied += 1
    write_template(path, html)
    return applied


# ---------- блобы шапки/подвала ----------

BLOB_RE_T = '"{uuid}":{{"mime":"text/html","compressed":true,"data":"'


def read_blob(path: str, uuid: str) -> str:
    raw = open(path, 'rb').read()
    i = raw.find(uuid.encode())
    if i < 0:
        raise ValueError(f'{path}: нет блоба {uuid}')
    m = re.search(rb'"data":"([A-Za-z0-9+/=]+)"', raw[i:i + 6000])
    return gzip.decompress(base64.b64decode(m.group(1) + b'==')).decode('utf-8')


def write_blob(path: str, uuid: str, html: str) -> None:
    raw = open(path, 'rb').read()
    i = raw.find(uuid.encode())
    if i < 0:
        raise ValueError(f'{path}: нет блоба {uuid}')
    m = re.search(rb'"data":"([A-Za-z0-9+/=]+)"', raw[i:i + 6000])
    old = m.group(1)
    new = base64.b64encode(gzip.compress(html.encode('utf-8'), compresslevel=9))
    open(path, 'wb').write(raw.replace(old, new, 1))


def edit_blob(path: str, uuid: str, *pairs, required: bool = True) -> int:
    html = read_blob(path, uuid)
    applied = 0
    for old, new in pairs:
        if old not in html:
            if required:
                raise ValueError(f'{path}/{uuid[:8]}: не найдено:\n  {old[:160]}')
            continue
        html = html.replace(old, new)
        applied += 1
    write_blob(path, uuid, html)
    return applied


def check(path: str) -> None:
    """Проверить, что страница снова корректна."""
    raw = open(path, encoding='utf-8').read()
    for m in re.finditer(r'<script type="(__bundler/[a-z_]+)">(.*?)</script>', raw, re.DOTALL):
        if m.group(1) == '__bundler/manifest':
            continue  # манифест обрезан по первому </script>, парсить нельзя
        json.loads(m.group(2))


HEADERS = {
    'index.html': '9855394d-2378-4731-8b45-c21cb178f000',
    'vacancy.html': '7606dc11-981a-4a72-998e-938269dd708f',
    'contacts.html': '417ba8d3-e206-42f7-8fbc-2bd878b11ea9',
    'team.html': '3394c2c1-6673-4058-94cf-e468336fdfb2',
    'blog.html': 'b86ea7da-5415-4b8d-bb52-518040cf1ac0',
    'article.html': '50b0f89e-f247-4a7b-99e9-33a646fe1a7d',
    'uslugi.html': '62b56043-4512-4874-8706-4320b47241d5',
    'novosti.html': '54014c09-8158-4f0e-9d43-c97c9a67f063',
    'usluga.html': 'da738306-0284-4524-b84c-bc13a733096c',
    'privacy.html': 'b8a182a3-66aa-4b01-8a3e-2e0ec51e6a32',
    'soglasie.html': '5ec08807-54dd-4a9b-a933-714bc4839666',
    '404.html': '9142db59-b316-4b46-ba16-5d51b5545e12',
}
