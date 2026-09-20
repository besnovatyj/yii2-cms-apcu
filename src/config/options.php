<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

// Опции модуля настроек yii2-cms-config. Значения применяются в Yii::$app->getModule('Apcu')->params.*
return [
    'apcu_page_size' => [
        'path' => 'modules.Apcu.params.pageSize',
        'label' => 'Записей на странице',
        'description' => 'Размер страницы списка записей по умолчанию (1–1000). В форме списка можно выбрать другой.',
        'category' => 'Apcu',
        'rules' => [
            ['required'],
            ['integer', 'min' => 1, 'max' => 1000],
        ],
        'inputOptions' => [
            'type' => 'number',
        ],
    ],
    'apcu_value_max_bytes' => [
        'path' => 'modules.Apcu.params.valueMaxBytes',
        'label' => 'Предел вывода значения, байт',
        'description' => 'Сырое значение записи длиннее предела обрезается при показе (1 КБ – 16 МБ).',
        'category' => 'Apcu',
        'rules' => [
            ['required'],
            ['integer', 'min' => 1024, 'max' => 16777216],
        ],
        'inputOptions' => [
            'type' => 'number',
        ],
    ],
    'apcu_pecl_proxy' => [
        'path' => 'modules.Apcu.params.peclProxy',
        'label' => 'Прокси для проверки версии',
        'description' => 'Адрес прокси для запроса к pecl.php.net, например tcp://127.0.0.1:8080. Пусто — без прокси.',
        'category' => 'Apcu',
        'rules' => [
            ['string'],
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
    'apcu_date_format' => [
        'path' => 'modules.Apcu.params.dateFormat',
        'label' => 'Формат дат',
        'description' => 'PHP date-формат для колонок времени в таблицах (по умолчанию Y-m-d H:i:s).',
        'category' => 'Apcu',
        'rules' => [
            ['required'],
            ['string', 'max' => 50],
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
];
