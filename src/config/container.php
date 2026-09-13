<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Apcu\Module;
use Besnovatyj\Apcu\repositories\ApcuRepository;
use Besnovatyj\Apcu\services\EntriesService;
use Besnovatyj\Apcu\services\StatsService;
use Besnovatyj\Apcu\services\VersionCheckService;
use yii\di\Container;

/**
 * DI-конфигурация модуля (способ A: только для самого модуля, ленивая проводка при init).
 *
 * Скалярные параметры сервисов (предел вывода значения, прокси) берутся из `params` модуля —
 * их переопределяет модуль настроек yii2-cms-config (см. config/options.php). Сервисы о
 * конфигурации не знают, получают готовые значения.
 */
return static function (Container $container): void {

    $container->setSingleton(ApcuRepository::class, ApcuRepository::class);
    $container->setSingleton(StatsService::class, StatsService::class);

    $container->setSingleton(EntriesService::class, static function (Container $c): EntriesService {
        $params = Yii::$app->getModule(Module::moduleId())?->params ?? [];
        return new EntriesService(
            repo: $c->get(ApcuRepository::class),
            valueMaxBytes: max(1024, (int)($params['valueMaxBytes'] ?? 262144)),
        );
    });

    $container->setSingleton(VersionCheckService::class, static function (Container $c): VersionCheckService {
        $params = Yii::$app->getModule(Module::moduleId())?->params ?? [];
        $proxy = trim((string)($params['peclProxy'] ?? ''));
        return new VersionCheckService(
            repo: $c->get(ApcuRepository::class),
            proxy: $proxy === '' ? null : $proxy,
        );
    });
};
