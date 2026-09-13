<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\entities;

use yii\data\Pagination;

/**
 * Страница списка записей: отфильтрованный/отсортированный срез плюс постраничка.
 *
 * @param CacheEntry[] $entries записи текущей страницы
 * @param int $total записей после фильтра (по ним строится постраничка)
 * @param int $totalInScope всего записей в выбранной области до фильтра
 * @param Pagination|null $pagination null — режим «Все» (count = 0)
 */
final readonly class EntriesPage
{
    public function __construct(
        public array $entries,
        public int $total,
        public int $totalInScope,
        public ?Pagination $pagination,
        /** Раскрытое значение записи (если запрошено ключом), иначе null. */
        public ?EntryValue $expanded,
    ) {
    }
}
