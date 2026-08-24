<?php
/**
 * Общая часть работы с Битрикс24: вызов методов, сборка лида и проверка связи.
 * Используется приёмом заявок (api/lead.php) и кнопкой проверки в редакторе.
 */

declare(strict_types=1);
require_once __DIR__ . '/../panel/lib.php';

/** Кто становится ответственным за заявку. Пусто — Битрикс поставит владельца вебхука. */
function bitrix_assigned(): int
{
    return (int)(config()['bitrix_assigned'] ?? 0);
}

/** Ссылка-вебхук из настроек редактора. Пусто — значит CRM не подключена. */
function bitrix_hook(): string
{
    $cfg = config();
    return trim((string)($cfg['bitrix_hook'] ?? ''));
}

/**
 * Вызов метода Битрикса. Возвращает [успех, результат либо текст ошибки].
 */
function bx(string $hook, string $method, array $params = []): array
{
    $url = rtrim($hook, '/') . '/' . $method . '.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false)      { return [false, 'Битрикс не ответил: ' . $err]; }
    $d = json_decode((string)$body, true);
    if (!is_array($d))        { return [false, 'Битрикс вернул неожиданный ответ']; }
    if (isset($d['error']))   { return [false, (string)($d['error_description'] ?: $d['error'])]; }
    return [true, $d['result'] ?? null];
}

/** Собираем лид из полей заявки — теми же словами, что видит менеджер в таблице. */
function lead_fields(array $data): array
{
    $kind   = trim((string)($data['kind'] ?? 'Заявка с сайта'));
    $name   = trim((string)($data['name'] ?? ''));
    $phone  = trim((string)($data['phone'] ?? $data['contact'] ?? ''));
    $email  = trim((string)($data['email'] ?? ''));
    $page   = trim((string)($data['page'] ?? ''));
    $source = trim((string)($data['source'] ?? ''));
    $text   = trim((string)($data['message'] ?? $data['comment'] ?? ''));

    // откуда пришла заявка — человеческой фразой, а не набором полей
    $where = $source !== '' && $page !== '' ? 'форма «' . $source . '», страница «' . $page . '»'
           : ($source !== '' ? 'форма «' . $source . '»' : ($page !== '' ? 'страница «' . $page . '»' : ''));

    // описание собираем подробно: менеджер должен понять заявку, не открывая сайт
    $lines = [];
    $lines[] = 'ЗАЯВКА С САЙТА buhstart.ru';
    $lines[] = 'Получена: ' . date('d.m.Y H:i');
    $lines[] = '';
    $lines[] = 'Что нужно: ' . ($kind !== '' ? $kind : 'заявка с сайта');
    if ($where !== '') { $lines[] = 'Откуда: ' . $where; }
    $lines[] = '';
    $lines[] = 'Контакты';
    $lines[] = '  Имя: ' . ($name !== '' ? $name : 'не указано');
    $lines[] = '  Телефон: ' . ($phone !== '' ? $phone : 'не указан');
    if ($email !== '') { $lines[] = '  Почта: ' . $email; }
    if ($text !== '') {
        $lines[] = '';
        $lines[] = 'Сообщение клиента:';
        $lines[] = $text;
    }
    if (!empty($data['extra'])) {
        $lines[] = '';
        $lines[] = 'Дополнительно:';
        $lines[] = (string)$data['extra'];
    }
    // расчёт из калькулятора: тарифы и номер КП
    if (!empty($data['tariff']) || !empty($data['kpNum'])) {
        $lines[] = '';
        $lines[] = 'Расчёт из калькулятора';
        if (!empty($data['kpNum']))    { $lines[] = '  Коммерческое предложение: ' . $data['kpNum']; }
        if (!empty($data['system']))   { $lines[] = '  Налогообложение: ' . $data['system']; }
        if (!empty($data['company']))  { $lines[] = '  Форма бизнеса: ' . $data['company']; }
        if (!empty($data['employees'])){ $lines[] = '  Сотрудники: ' . $data['employees']; }
        if (!empty($data['priceBase'])){ $lines[] = '  Базовая: ' . number_format((int)$data['priceBase'], 0, ',', ' ') . ' ₽/мес'; }
        if (!empty($data['priceStd'])) { $lines[] = '  Стандарт: ' . number_format((int)$data['priceStd'], 0, ',', ' ') . ' ₽/мес'; }
        if (!empty($data['priceOpt'])) { $lines[] = '  Оптима: ' . number_format((int)$data['priceOpt'], 0, ',', ' ') . ' ₽/мес'; }
        $lines[] = '  Файл КП лежит в Google-таблице заявок';
    }
    if (!empty($data['subscribe'])) {
        $lines[] = '';
        $lines[] = 'Согласие на рассылку: ' . $data['subscribe'];
    }

    // имя выносим в заголовок: в списке лидов видно только его
    $title = ($name !== '' ? $name : 'Без имени') . ' — ' . ($kind !== '' ? $kind : 'заявка с сайта');

    $fields = [
        'TITLE'              => $title,
        'NAME'               => $name !== '' ? $name : 'С сайта',
        'SOURCE_ID'          => 'WEB',
        'SOURCE_DESCRIPTION' => $where !== '' ? ucfirst($where) : 'Сайт buhstart.ru',
        'COMMENTS'           => implode("\n", $lines),
        'OPENED'             => 'Y',
    ];
    if (bitrix_assigned() > 0) { $fields['ASSIGNED_BY_ID'] = bitrix_assigned(); }
    if ($phone !== '') { $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']]; }
    if ($email !== '') { $fields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']]; }
    return $fields;
}

/**
 * Проверка связи: заводим тестовый лид и тут же удаляем его,
 * чтобы в чужой CRM не оставалось мусора.
 */
function bitrix_selftest(bool $keep = false): array
{
    $hook = bitrix_hook();
    if ($hook === '') { return [false, 'Сначала сохраните ссылку-вебхук.']; }

    [$ok, $who] = bx($hook, 'profile');
    if (!$ok) { return [false, 'Связи нет: ' . $who]; }

    [$ok, $id] = bx($hook, 'crm.lead.add', ['fields' => lead_fields([
        'kind' => 'ТЕСТ — проверка связи', 'name' => 'Проверка связи',
        'phone' => '+7 000 000-00-00', 'message' => 'Тестовая заявка из редактора сайта. Удаляется автоматически.',
        'source' => 'Проверка подключения', 'page' => 'Редактор сайта',
    ])]);
    if (!$ok) { return [false, 'Лид не создался: ' . $id]; }

    if ($keep) {
        $portal = preg_replace('~/rest/.*$~', '', $hook);
        return [true, 'Тестовый лид № ' . (int)$id . ' создан и оставлен в CRM. '
            . 'Открыть карточку: ' . $portal . '/crm/lead/details/' . (int)$id . '/ — посмотрите и удалите её сами.'];
    }
    [$okDel, $delErr] = bx($hook, 'crm.lead.delete', ['id' => (int)$id]);
    $tail = $okDel
        ? 'Тестовый лид № ' . (int)$id . ' создан и сразу удалён — в CRM чисто.'
        : 'Тестовый лид № ' . (int)$id . ' создан, но удалить его не вышло: ' . $delErr . ' Удалите вручную.';

    $user = trim((string)(($who['NAME'] ?? '') . ' ' . ($who['LAST_NAME'] ?? '')));
    return [true, 'Связь есть. Работаем от имени: ' . ($user !== '' ? $user : 'пользователь #' . ($who['ID'] ?? '?')) . '. ' . $tail];
}

/**
 * Дописать событие в уже созданный лид: клиент вернулся, передумал, уточнил.
 * Так вся история заявки лежит в одной карточке, а не расползается по копиям.
 */
function bitrix_comment(int $leadId, string $text): array
{
    $hook = bitrix_hook();
    if ($hook === '' || $leadId <= 0 || trim($text) === '') { return [false, 'Нечего добавлять']; }
    return bx($hook, 'crm.timeline.comment.add', ['fields' => [
        'ENTITY_ID'   => $leadId,
        'ENTITY_TYPE' => 'lead',
        'COMMENT'     => $text,
    ]]);
}

/**
 * Прикладываем файл к карточке: КП попадает в ленту лида, менеджеру
 * не нужно искать его в Google-таблице. Настройки CRM при этом не меняем —
 * файл живёт в комментарии, а не в отдельном поле.
 */
function bitrix_attach(int $leadId, string $name, string $base64, string $note = ''): array
{
    $hook = bitrix_hook();
    if ($hook === '' || $leadId <= 0 || $base64 === '') { return [false, 'Нечего прикладывать']; }
    return bx($hook, 'crm.timeline.comment.add', ['fields' => [
        'ENTITY_ID'   => $leadId,
        'ENTITY_TYPE' => 'lead',
        'COMMENT'     => $note !== '' ? $note : 'Коммерческое предложение по расчёту',
        'FILES'       => [[$name !== '' ? $name : 'КП.pdf', $base64]],
    ]]);
}
