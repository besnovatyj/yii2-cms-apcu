<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\entities;

/**
 * Непрерывный участок сегмента общей памяти APCu.
 *
 * `apcu_sma_info()` отдаёт только СВОБОДНЫЕ блоки (`block_lists`); занятые участки — это промежутки
 * между ними и хвост сегмента. Карта памяти ({@see \Besnovatyj\Apcu\services\StatsService})
 * восстанавливает полную последовательность блоков сегмента: занятый/свободный по порядку смещений.
 */
final readonly class MemoryBlock
{
    public function __construct(
        public int $segment,
        public int $offset,
        public int $size,
        public bool $isFree,
    ) {
    }
}
