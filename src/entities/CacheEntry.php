<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\entities;

/**
 * Одна запись пользовательского кэша — элемент `cache_list`/`deleted_list` из `apcu_cache_info()`.
 *
 * Поля один в один соответствуют массиву расширения (`info`, `num_hits`, `mem_size`, ...),
 * только с типами и нормальными именами. Значение записи сюда НЕ входит — оно читается
 * отдельно по требованию ({@see EntryValue}).
 */
final readonly class CacheEntry
{
    public function __construct(
        /** Ключ записи (в apc.php — «User Entry Label», поле `info`). */
        public string $key,
        public int $hits,
        public int $memSize,
        public int $accessTime,
        public int $mtime,
        public int $creationTime,
        /** TTL в секундах; 0 — бессрочная. */
        public int $ttl,
        /** Момент удаления (для `deleted_list`); 0 — запись активна. */
        public int $deletionTime,
        public int $refCount,
    ) {
    }

    /**
     * @param array<string, mixed> $raw элемент `cache_list` из apcu_cache_info(false)
     */
    public static function fromRaw(array $raw): self
    {
        return new self(
            key: (string)($raw['info'] ?? ''),
            hits: (int)($raw['num_hits'] ?? 0),
            memSize: (int)($raw['mem_size'] ?? 0),
            accessTime: (int)($raw['access_time'] ?? 0),
            mtime: (int)($raw['mtime'] ?? 0),
            creationTime: (int)($raw['creation_time'] ?? 0),
            ttl: (int)($raw['ttl'] ?? 0),
            deletionTime: (int)($raw['deletion_time'] ?? 0),
            refCount: (int)($raw['ref_count'] ?? 0),
        );
    }

    /** Стабильный id для якорей/DOM (ключ может быть любой строкой). */
    public function anchorId(): string
    {
        return 'key-' . md5($this->key);
    }

    public function isDeleted(): bool
    {
        return $this->deletionTime > 0;
    }
}
