<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [
    'id' => 'Apcu',
    'params' => [
        'iconClass' => 'bi bi-memory',

        'directories' => false, // Если для работы модуля необходимы директории для статики

        // Записей на странице списка по умолчанию (аналог COUNT в apc.php; в форме можно выбрать «Все»).
        // Переопределяется через модуль настроек yii2-cms-config, см. config/options.php.
        'pageSize' => 50,

        // Предел вывода значения записи (сырой сериализованной строки), байт. Больше — обрезается с пометкой.
        'valueMaxBytes' => 262144,

        // Прокси для проверки версии на PECL (аналог PROXY в apc.php), например 'tcp://127.0.0.1:8080'.
        // null — без прокси.
        'peclProxy' => null,

        // Формат дат в таблицах (PHP date-формат, аналог DATE_FORMAT в apc.php).
        'dateFormat' => 'Y-m-d H:i:s',
    ],
];
