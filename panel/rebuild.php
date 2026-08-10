<?php
/**
 * Пересборка сайта из командной строки — вызывается после публикации,
 * чтобы страницы соответствовали данным на сервере, а не тому,
 * что лежало у разработчика.
 *
 * Через браузер не работает: только из консоли.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/lib.php';
require __DIR__ . '/images.php';
require __DIR__ . '/build.php';

echo 'Пересборка: ', implode(', ', build_all()), PHP_EOL;
