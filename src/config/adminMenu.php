<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;
use Besnovatyj\Contracts\adminMenu\AdminMenuPlacement;

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
                new AdminMenuPlacement(
                    location: AdminMenuLocation::RightSidebar,
                    group: 'Service',
                    groupIcon: 'bi bi-sliders',
                    groupPriority: 100,
                    priority: 120,
                ),
            ],
        ],
    ],
];
