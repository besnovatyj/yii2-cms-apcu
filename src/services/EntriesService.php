<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\services;

use Besnovatyj\Apcu\entities\CacheEntry;
use Besnovatyj\Apcu\entities\EntriesPage;
use Besnovatyj\Apcu\entities\EntryValue;
use Besnovatyj\Apcu\forms\backend\EntriesFilterForm;
use Besnovatyj\Apcu\repositories\ApcuRepository;
use yii\data\Pagination;
use yii\helpers\VarDumper;

/**
 * Список записей пользовательского кэша — порт раздела `OB_USER_CACHE` из apc.php.
 *
 * Источник — `apcu_cache_info(false)`: и активные (`cache_list`), и удалённые (`deleted_list`)
 * записи. Фильтр по регулярному выражению, сортировка по любому полю, срез страницы и — по
 * запросу — чтение значения одной записи. На больших кэшах полный `cache_info` дорог, но это
 * отладочный экран, а не рантайм-путь приложения.
 */
final class EntriesService
{
    public function __construct(
        private readonly ApcuRepository $repo,
        /** Предел показа сырого значения, байт. */
        private readonly int $valueMaxBytes,
    ) {
    }

    public function getPage(EntriesFilterForm $filter): EntriesPage
    {
        $info = $this->repo->cacheInfo(false);
        $listKey = $filter->scope === EntriesFilterForm::SCOPE_DELETED ? 'deleted_list' : 'cache_list';
        $rawList = is_array($info[$listKey] ?? null) ? $info[$listKey] : [];

        $entries = array_map(static fn(array $raw): CacheEntry => CacheEntry::fromRaw($raw), $rawList);
        $totalInScope = count($entries);

        $pattern = $filter->searchPattern();
        if ($pattern !== null) {
            $entries = array_values(array_filter(
                $entries,
                static fn(CacheEntry $e): bool => @preg_match($pattern, $e->key) === 1,
            ));
        }

        $this->sort($entries, $filter->sort, $filter->dir);
        $total = count($entries);

        $pagination = null;
        if ($filter->count > 0) {
            $pagination = new Pagination([
                'totalCount' => $total,
                'pageSize' => $filter->count,
                'pageSizeParam' => false,   // размер страницы — из формы (count), не из per-page
            ]);
            $entries = array_slice($entries, $pagination->offset, $pagination->limit);
        }

        $expanded = null;
        if ($filter->key !== null && $filter->key !== '') {
            $expanded = $this->fetchValue($filter->key);
        }

        return new EntriesPage($entries, $total, $totalInScope, $pagination, $expanded);
    }

    /**
     * Значение записи: сырое + разбор PHP-serialized строки без инстанцирования классов.
     *
     * `Cache::set()` в Yii хранит `serialize([$value, $dependency])`; `unserialize` с
     * `allowed_classes => false` показывает объекты как `__PHP_Incomplete_Class` со всеми полями —
     * ничего не скрыто и никакой код кэшированных классов не исполняется.
     */
    public function fetchValue(string $key): EntryValue
    {
        [$value, $exists] = $this->repo->fetch($key);

        $raw = is_string($value) ? $value : VarDumper::dumpAsString($value, 10);
        $rawLength = strlen($raw);
        $truncated = $rawLength > $this->valueMaxBytes;
        if ($truncated) {
            $raw = substr($raw, 0, $this->valueMaxBytes);
        }

        $decoded = null;
        if (is_string($value) && $this->looksSerialized($value)) {
            $decodedValue = @unserialize($value, ['allowed_classes' => false]);
            if ($decodedValue !== false || $value === 'b:0;') {
                $decoded = VarDumper::dumpAsString($decodedValue, 10);
                if (strlen($decoded) > $this->valueMaxBytes) {
                    $decoded = substr($decoded, 0, $this->valueMaxBytes) . "\n… [обрезано]";
                }
            }
        }

        return new EntryValue(
            key: $key,
            exists: $exists,
            type: get_debug_type($value),
            raw: $raw,
            rawLength: $rawLength,
            truncated: $truncated,
            decoded: $decoded,
            keyInfo: $exists ? $this->repo->keyInfo($key) : null,
        );
    }

    /** Удалить запись по ключу; false — ключа нет. */
    public function delete(string $key): bool
    {
        return $this->repo->delete($key);
    }

    /** Очистить весь пользовательский кэш. */
    public function clear(): bool
    {
        return $this->repo->clear();
    }

    /**
     * @param CacheEntry[] $entries
     */
    private function sort(array &$entries, string $sort, string $dir): void
    {
        $field = match ($sort) {
            EntriesFilterForm::SORT_SIZE => 'memSize',
            EntriesFilterForm::SORT_KEY => 'key',
            EntriesFilterForm::SORT_ACCESS => 'accessTime',
            EntriesFilterForm::SORT_MTIME => 'mtime',
            EntriesFilterForm::SORT_CREATED => 'creationTime',
            EntriesFilterForm::SORT_TTL => 'ttl',
            EntriesFilterForm::SORT_DELETED => 'deletionTime',
            default => 'hits',
        };
        $sign = $dir === EntriesFilterForm::DIR_ASC ? 1 : -1;

        usort($entries, static function (CacheEntry $a, CacheEntry $b) use ($field, $sign): int {
            $cmp = $a->$field <=> $b->$field;
            if ($cmp === 0) {
                $cmp = strcmp($a->key, $b->key);   // стабильный порядок при равных значениях
            }
            return $cmp * $sign;
        });
    }

    /** Быстрая проверка формы PHP-serialized строки: `тип:` в начале и `;`/`}` в конце. */
    private function looksSerialized(string $value): bool
    {
        if ($value === 'N;' || $value === 'b:0;') {
            return true;
        }
        return strlen($value) >= 4
            && preg_match('/^[abdiOsCE]:/', $value) === 1
            && in_array($value[strlen($value) - 1], [';', '}'], true);
    }
}
