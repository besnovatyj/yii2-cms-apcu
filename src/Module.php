<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu;

use Besnovatyj\Contracts\module\DeclaresModule;
use Besnovatyj\Contracts\module\ProvidesOptions;
use Besnovatyj\Kernel\module\CmsModule;

/**
 * Модуль просмотра и управления пользовательским кэшем APCu — порт `apc.php` из репозитория
 * расширения (krakjoe/apcu) на стек Yii2-cms.
 *
 * Отладочный инструмент: показывает ВСЁ содержимое кэша без сокрытия чувствительных данных.
 * Доступ ограничивается только RBAC-гейтом закрытого бэкенда — выдавать права на маршруты модуля
 * следует одному администратору. На продакшен модуль не предназначен.
 *
 * Экраны (по образцу оригинала):
 *  - «Статистика хоста» — версии, общая память, uptime, счётчики, скорость запросов, ini-настройки,
 *    диаграммы памяти/хитов, карта памяти и фрагментация;
 *  - «Записи кэша» — список ключей (активных и удалённых) с сортировкой, поиском по регулярному
 *    выражению, постраничкой, просмотром значения и удалением;
 *  - «Проверка версии» — сравнение с последним релизом на PECL (по кнопке, исходящий запрос).
 *
 * Отличия от оригинала: destructive-действия (очистка, удаление) — только `POST` с CSRF; графики
 * рендерятся inline-SVG без GD; встроенная Basic-аутентификация выброшена (её роль играет RBAC).
 */
class Module extends CmsModule implements
    DeclaresModule, ProvidesOptions
{
    public const bool EDITABLE = true;
    public const string VERSION = '1.0.0';
    public const string MODULE_ID = 'Apcu';

    public static function moduleId(): string { return self::MODULE_ID; }
    public static function moduleVersion(): string { return self::VERSION; }
    public static function isEditable(): bool { return self::EDITABLE; }
    public static function moduleConfig(): array { return require __DIR__ . '/config/config.php'; }
    public static function options(): array { return require __DIR__ . '/config/options.php'; }
}
