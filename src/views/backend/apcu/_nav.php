<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/**
 * Меню экранов APCu (порт `ol.menu` из apc.php): вкладки разделов, «Обновить» и «Очистить кэш».
 *
 * @var View $this
 * @var string $active id активного экрана: index | entries | version
 */

$tabs = [
    'index' => ['Статистика хоста', 'bi-speedometer2'],
    'entries' => ['Записи кэша', 'bi-list-ul'],
    'version' => ['Проверка версии', 'bi-cloud-check'],
];
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <ul class="nav nav-pills">
        <?php foreach ($tabs as $route => [$label, $icon]): ?>
            <li class="nav-item">
                <?= Html::a(
                    '<i class="bi ' . $icon . ' me-1"></i>' . Html::encode($label),
                    [$route],
                    ['class' => 'nav-link' . ($route === $active ? ' active' : '')]
                ) ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="ms-auto d-flex gap-2">
        <?= Html::a(
            '<i class="bi bi-arrow-clockwise me-1"></i>Обновить',
            Url::current(),
            ['class' => 'btn btn-outline-secondary']
        ) ?>
        <?= Html::a(
            '<i class="bi bi-trash me-1"></i>Очистить кэш',
            ['clear'],
            [
                'class' => 'btn btn-outline-danger',
                'data-method' => 'post',
                'data-confirm' => 'Очистить ВЕСЬ пользовательский кэш APCu? Приложение пересоберёт его заново.',
            ]
        ) ?>
    </div>
</div>
