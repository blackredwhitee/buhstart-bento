<?php
/**
 * Приём заявки с сайта и создание лида в Битрикс24.
 *
 * Ссылка-вебхук хранится в настройках редактора, вне папки сайта: если вставить
 * ключ прямо в site.js, любой желающий сможет засыпать чужую CRM мусором.
 *
 * В Google-таблицу заявка уходит отдельно, самим сайтом. Здесь только дубль
 * в CRM — если Битрикс недоступен, заявка всё равно не теряется.
 */

declare(strict_types=1);
require __DIR__ . '/bitrix.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

function out(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Ограничение от спама. Считаем только новые заявки: ответы в блоке обратной
    связи — это несколько нажатий подряд, и упираться в лимит из-за них нельзя. */
function rate_ok(): bool
{
    ensure_dirs();
    $f = data_path('throttle/lead_' . sha1($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json');
    $d = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $hits = array_values(array_filter((array)($d['hits'] ?? []), fn($t) => $t > time() - 3600));
    if (count($hits) >= 60) { return false; }
    $hits[] = time();
    write_atomic($f, json_encode(['hits' => $hits]));
    return true;
}

$hook = bitrix_hook();
if ($hook === '') { out(['ok' => false, 'error' => 'Битрикс не подключён']); }

$raw = file_get_contents('php://input');
// потолок с запасом: резюме до 10 МБ после кодирования весит около 13,5 МБ
if ($raw === false || $raw === '' || strlen($raw) > 20000000) { out(['ok' => false, 'error' => 'Пустой запрос'], 400); }
$data = json_decode((string)$raw, true);
if (!is_array($data)) { out(['ok' => false, 'error' => 'Ждём JSON'], 400); }

// клиент вернулся к уже отправленной заявке — дописываем в ту же карточку
$leadId = (int)($data['leadId'] ?? 0);
if ($leadId > 0) {
    $note = trim((string)($data['message'] ?? ''));
    $head = trim((string)($data['kind'] ?? 'Ответ клиента'));
    [$okC, $resC] = bitrix_comment($leadId, $head . ':' . "\n" . $note);
    out($okC ? ['ok' => true, 'id' => $leadId, 'comment' => true] : ['ok' => false, 'error' => $resC]);
}

// тот же человек в том же заходе — дописываем в его карточку, а не создаём копию
$phone = trim((string)($data['phone'] ?? $data['contact'] ?? ''));
$recent = bitrix_recent_lead($phone);
if ($recent > 0) {
    $head = trim((string)($data['kind'] ?? 'Ещё одно обращение с сайта'));
    $note = trim((string)($data['message'] ?? ''));
    $extra = trim((string)($data['extra'] ?? ''));
    bitrix_comment($recent, trim($head . "\n" . $note . ($extra !== '' ? "\n" . $extra : '')));
    $kpNew = (string)($data['kpBase64'] ?? '');
    if ($kpNew !== '') { bitrix_attach($recent, (string)($data['kpName'] ?? 'КП.pdf'), $kpNew); }
    $cvNew = (string)($data['resumeBase64'] ?? '');
    if ($cvNew !== '') { bitrix_attach($recent, (string)($data['resumeName'] ?? 'Резюме.pdf'), $cvNew, 'Резюме кандидата'); }
    out(['ok' => true, 'id' => $recent, 'comment' => true]);
}

if (!rate_ok()) { out(['ok' => false, 'error' => 'Слишком много заявок с одного адреса'], 429); }

[$ok, $res] = bx($hook, 'crm.lead.add', [
    'fields' => lead_fields($data),
    'params' => ['REGISTER_SONET_EVENT' => 'Y'],
]);

if (!$ok) {
    // в журнал, чтобы потом понять, почему заявка не дошла до CRM
    @file_put_contents(data_path('bitrix-errors.log'),
        date('Y-m-d H:i:s') . "\t" . $res . "\t" . substr($raw, 0, 500) . "\n", FILE_APPEND);
    out(['ok' => false, 'error' => $res]);
}
$leadId = (int)$res;

// КП из калькулятора кладём прямо в карточку — файлом в ленте
$kp = (string)($data['kpBase64'] ?? '');
if ($kp !== '') {
    [$okF, $errF] = bitrix_attach($leadId, (string)($data['kpName'] ?? 'КП.pdf'), $kp);
    if (!$okF) {
        @file_put_contents(data_path('bitrix-errors.log'),
            date('Y-m-d H:i:s') . "\tфайл КП не прикрепился: " . $errF . "\n", FILE_APPEND);
    }
}

// резюме кандидата — туда же, чтобы кадровик открывал его прямо из карточки
$cv = (string)($data['resumeBase64'] ?? '');
if ($cv !== '') {
    [$okR, $errR] = bitrix_attach($leadId, (string)($data['resumeName'] ?? 'Резюме.pdf'), $cv, 'Резюме кандидата');
    if (!$okR) {
        @file_put_contents(data_path('bitrix-errors.log'),
            date('Y-m-d H:i:s') . "\tрезюме не прикрепилось: " . $errR . "\n", FILE_APPEND);
    }
}

out(['ok' => true, 'id' => $leadId]);
