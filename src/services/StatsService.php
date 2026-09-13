<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\services;

use Besnovatyj\Apcu\entities\Fragmentation;
use Besnovatyj\Apcu\entities\HostStats;
use Besnovatyj\Apcu\entities\MemoryBlock;
use Besnovatyj\Apcu\repositories\ApcuRepository;

/**
 * Сбор статистики хоста — порт вычислений раздела `OB_HOST_STATS` из apc.php.
 *
 * Здесь только арифметика над массивами расширения: скорости запросов, проценты, восстановление
 * карты памяти из списка свободных блоков и расчёт фрагментации. Форматирование — во вьюхе.
 */
final class StatsService
{
    /** Порог «мелкого» свободного блока для метрики фрагментации (как в apc.php: < 5 МБ). */
    private const int FRAGMENT_THRESHOLD = 5 * 1024 * 1024;

    public function __construct(
        private readonly ApcuRepository $repo,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->repo->isAvailable();
    }

    public function collect(): HostStats
    {
        $cache = $this->repo->cacheInfo(true);
        $mem = $this->repo->smaInfo();
        $now = time();

        $numSeg = (int)($mem['num_seg'] ?? 0);
        $segSize = (int)($mem['seg_size'] ?? 0);
        $memTotal = $numSeg * $segSize;
        $memAvail = (int)($mem['avail_mem'] ?? 0);

        $startTime = (int)($cache['start_time'] ?? $now);
        $elapsed = max($now - $startTime, 1);

        $hits = (int)($cache['num_hits'] ?? 0);
        $misses = (int)($cache['num_misses'] ?? 0);
        $inserts = (int)($cache['num_inserts'] ?? 0);

        $blockLists = is_array($mem['block_lists'] ?? null) ? $mem['block_lists'] : [];

        return new HostStats(
            apcuVersion: $this->repo->version(),
            phpVersion: PHP_VERSION,
            hostName: (string)php_uname('n'),
            serverName: (string)($_SERVER['SERVER_NAME'] ?? ''),
            serverAddr: (string)($_SERVER['SERVER_ADDR'] ?? ''),
            serverSoftware: (string)($_SERVER['SERVER_SOFTWARE'] ?? ''),
            numSegments: $numSeg,
            segmentSize: $segSize,
            memoryType: (string)($cache['memory_type'] ?? ''),
            startTime: $startTime,
            uptimeSeconds: $now - $startTime,
            numEntries: (int)($cache['num_entries'] ?? 0),
            entriesBytes: (int)($cache['mem_size'] ?? 0),
            hits: $hits,
            misses: $misses,
            inserts: $inserts,
            requestRate: ($hits + $misses) / $elapsed,
            hitRate: $hits / $elapsed,
            missRate: $misses / $elapsed,
            insertRate: $inserts / $elapsed,
            cleanups: isset($cache['cleanups']) ? (int)$cache['cleanups'] : null,
            defragmentations: isset($cache['defragmentations']) ? (int)$cache['defragmentations'] : null,
            expunges: (int)($cache['expunges'] ?? 0),
            iniSettings: $this->repo->iniSettings(),
            memoryTotal: $memTotal,
            memoryAvailable: $memAvail,
            memoryUsed: $memTotal - $memAvail,
            memoryMap: $this->buildMemoryMap($blockLists, $segSize),
            fragmentation: $this->calculateFragmentation($blockLists),
            allocationDistribution: is_array($mem['adist'] ?? null) ? $mem['adist'] : null,
        );
    }

    /**
     * Полная карта блоков: `block_lists` содержит только свободные блоки, занятые восстанавливаются
     * как промежутки между ними (и хвост сегмента). Логика та же, что у диаграмм IMG=1/IMG=3 в apc.php.
     *
     * @param array<int, array<int, array{offset: int, size: int}>> $blockLists
     * @return MemoryBlock[]
     */
    private function buildMemoryMap(array $blockLists, int $segSize): array
    {
        $map = [];
        foreach ($blockLists as $segment => $free) {
            $free = is_array($free) ? $free : [];
            usort($free, static fn(array $a, array $b): int => ($a['offset'] ?? 0) <=> ($b['offset'] ?? 0));

            $ptr = 0;
            foreach ($free as $block) {
                $offset = (int)($block['offset'] ?? 0);
                $size = (int)($block['size'] ?? 0);
                if ($offset !== $ptr) {                          // занятый участок до свободного блока
                    $map[] = new MemoryBlock((int)$segment, $ptr, $offset - $ptr, false);
                }
                $map[] = new MemoryBlock((int)$segment, $offset, $size, true);
                $ptr = $offset + $size;
            }
            if ($ptr < $segSize) {                               // занятая память в конце сегмента
                $map[] = new MemoryBlock((int)$segment, $ptr, $segSize - $ptr, false);
            }
        }
        return $map;
    }

    /**
     * Fragmentation: (freeseg - 1) / total_seg — как в apc.php. В процент попадают только свободные
     * блоки меньше 5 МБ; один свободный блок = фрагментации нет.
     *
     * @param array<int, array<int, array{offset: int, size: int}>> $blockLists
     */
    private function calculateFragmentation(array $blockLists): Fragmentation
    {
        $freeSegments = 0;
        $fragSize = 0;
        $freeTotal = 0;

        foreach ($blockLists as $free) {
            $free = is_array($free) ? $free : [];
            foreach ($free as $block) {
                $size = (int)($block['size'] ?? 0);
                if ($size < self::FRAGMENT_THRESHOLD) {
                    $fragSize += $size;
                }
                $freeTotal += $size;
            }
            $freeSegments += count($free);
        }

        $percent = ($freeSegments > 1 && $freeTotal > 0) ? $fragSize / $freeTotal * 100 : 0.0;

        return new Fragmentation($percent, $fragSize, $freeTotal, $freeSegments);
    }
}
