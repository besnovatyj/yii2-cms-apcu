<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [
    [
        'label' => 'APCu',
        'iconClass' => 'bi bi-memory me-1',
        'url' => ['/Apcu/backend/apcu/index'],
        'active' => static function () {
            return str_contains(\Yii::$app->request->url, 'Apcu/backend/apcu');
        },
        '_meta' => [
            'placements' => [
                [
                    'location' => 'right-sidebar',
                    'group' => 'Service',
                    'groupIcon' => 'bi bi-sliders',
                    'priority' => 120,
                    'groupPriority' => 100,
                ],
            ],
        ],
    ],
];
