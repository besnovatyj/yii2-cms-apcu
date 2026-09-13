<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\entities;

/**
 * Снимок состояния APCu для экрана «Статистика хоста» (порт `OB_HOST_STATS` из apc.php).
 *
 * Собирается {@see \Besnovatyj\Apcu\services\StatsService} из `apcu_cache_info(true)`,
 * `apcu_sma_info()`, `ini_get_all('apcu')` и окружения. Все производные величины (скорости,
 * проценты, карта памяти, фрагментация) посчитаны заранее — вьюха только форматирует.
 */
final readonly class HostStats
{
    /**
     * @param array<string, string> $iniSettings ключ → local_value из ini_get_all('apcu')
     * @param MemoryBlock[] $memoryMap полная карта блоков всех сегментов, по порядку
     * @param array<int, int>|null $allocationDistribution `adist` из sma_info (в APCu 5 отсутствует)
     */
    public function __construct(
        // --- General Cache Information ---
        public string $apcuVersion,
        public string $phpVersion,
        public string $hostName,
        public string $serverName,
        public string $serverAddr,
        public string $serverSoftware,
        public int $numSegments,
        public int $segmentSize,
        public string $memoryType,
        public int $startTime,
        public int $uptimeSeconds,

        // --- Cache Information ---
        public int $numEntries,
        public int $entriesBytes,
        public int $hits,
        public int $misses,
        public int $inserts,
        public float $requestRate,
        public float $hitRate,
        public float $missRate,
        public float $insertRate,
        public ?int $cleanups,
        public ?int $defragmentations,
        public int $expunges,

        // --- Runtime Settings ---
        public array $iniSettings,

        // --- Memory ---
        public int $memoryTotal,
        public int $memoryAvailable,
        public int $memoryUsed,
        public array $memoryMap,
        public Fragmentation $fragmentation,
        public ?array $allocationDistribution,
    ) {
    }

    public function hitPercent(): float
    {
        $total = $this->hits + $this->misses;
        return $total > 0 ? $this->hits * 100 / $total : 0.0;
    }

    public function missPercent(): float
    {
        $total = $this->hits + $this->misses;
        return $total > 0 ? $this->misses * 100 / $total : 0.0;
    }

    public function freePercent(): float
    {
        return $this->memoryTotal > 0 ? $this->memoryAvailable * 100 / $this->memoryTotal : 0.0;
    }

    public function usedPercent(): float
    {
        return $this->memoryTotal > 0 ? $this->memoryUsed * 100 / $this->memoryTotal : 0.0;
    }

    /** В apc.php: «multiple slices indicate fragments» — подпись у диаграммы, если блоков больше одного. */
    public function hasMultipleSlices(): bool
    {
        return count($this->memoryMap) > 1;
    }
}
