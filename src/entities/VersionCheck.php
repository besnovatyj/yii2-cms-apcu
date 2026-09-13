<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\entities;

/**
 * Результат сравнения установленной версии APCu с последним релизом на PECL
 * (порт `OB_VERSION_CHECK` из apc.php).
 *
 * @param list<array{version: string, changes: string}> $changelog записи релизов новее текущей
 *   версии (для актуальной — три последних, как в оригинале)
 */
final readonly class VersionCheck
{
    public function __construct(
        public string $installed,
        /** Последняя версия с PECL; null — фид недоступен. */
        public ?string $latest,
        public bool $isLatest,
        public array $changelog,
        /** Текст ошибки получения фида, если была. */
        public ?string $error,
    ) {
    }

    public function releaseUrl(string $version): string
    {
        return 'https://pecl.php.net/package/APCu/' . rawurlencode($version);
    }
}
