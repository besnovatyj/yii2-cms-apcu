<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\entities;

/**
 * Итог расчёта фрагментации свободной памяти — порт блока «Detailed Memory Usage and Fragmentation»
 * из apc.php.
 *
 * Оригинальная метрика: доля «мелких» (< 5 МБ) свободных блоков в общем объёме свободной памяти.
 * Если свободный блок один — фрагментации нет (0 %).
 */
final readonly class Fragmentation
{
    public function __construct(
        /** Процент фрагментации, 0..100. */
        public float $percent,
        /** Суммарный размер свободных блоков меньше 5 МБ, байт. */
        public int $fragmentedBytes,
        /** Вся свободная память, байт. */
        public int $freeBytes,
        /** Количество свободных блоков (фрагментов) во всех сегментах. */
        public int $fragments,
    ) {
    }

    public function isFragmented(): bool
    {
        return $this->fragments > 1;
    }
}
