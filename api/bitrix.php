<?php
/**
 * Общая часть работы с Битрикс24: вызов методов, сборка лида и проверка связи.
 * Используется приёмом заявок (api/lead.php) и кнопкой проверки в редакторе.
 */

declare(strict_types=1);
require_once __DIR__ . '/../panel/lib.php';

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

    $comment = array_filter([
        $text !== ''   ? $text : null,
        $source !== '' ? 'Форма: ' . $source : null,
        $page !== ''   ? 'Страница: ' . $page : null,
        !empty($data['extra'])     ? (string)$data['extra'] : null,
        !empty($data['subscribe']) ? 'Согласие на рассылку: ' . $data['subscribe'] : null,
    ]);

    $fields = [
        'TITLE'              => 'Сайт: ' . ($kind !== '' ? $kind : 'заявка'),
        'NAME'               => $name !== '' ? $name : 'С сайта',
        'SOURCE_ID'          => 'WEB',
        'SOURCE_DESCRIPTION' => $source !== '' ? $source : 'Сайт buhstart.ru',
        'COMMENTS'           => implode("\n", $comment),
        'OPENED'             => 'Y',
    ];
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
