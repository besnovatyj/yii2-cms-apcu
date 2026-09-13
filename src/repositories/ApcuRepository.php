<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\repositories;

use RuntimeException;

/**
 * Единственная точка обращения к функциям расширения `apcu_*`.
 *
 * Изолирует остальной модуль от глобальных функций: сервисы получают массивы/скаляры и не знают,
 * загружено ли расширение. Все методы чтения бросают {@see RuntimeException}, если APCu недоступен —
 * контроллёр переводит это в понятную страницу.
 *
 * Важно: APCu живёт в памяти SAPI-процесса. Из php-fpm виден кэш пула php-fpm, из CLI — свой
 * (и только при `apc.enable_cli=1`). Модуль работает в бэкенде, т.е. показывает кэш web-пула.
 */
final class ApcuRepository
{
    public function isAvailable(): bool
    {
        return function_exists('apcu_cache_info') && function_exists('apcu_sma_info')
            && (bool)ini_get('apc.enabled');
    }

    /**
     * @param bool $limited true — без списков записей (дёшево); false — с `cache_list` и `deleted_list`
     * @return array<string, mixed>
     */
    public function cacheInfo(bool $limited): array
    {
        $this->assertAvailable();
        $info = apcu_cache_info($limited);
        if ($info === false) {
            throw new RuntimeException('apcu_cache_info() вернула false — кэш недоступен.');
        }
        return $info;
    }

    /**
     * @return array<string, mixed> `num_seg`, `seg_size`, `avail_mem`, `block_lists`
     */
    public function smaInfo(): array
    {
        $this->assertAvailable();
        $info = apcu_sma_info(false);
        if ($info === false) {
            throw new RuntimeException('apcu_sma_info() вернула false — общая память недоступна.');
        }
        return $info;
    }

    /**
     * @return array<string, string> имя директивы → текущее значение (local_value)
     */
    public function iniSettings(): array
    {
        $all = ini_get_all('apcu') ?: [];
        $settings = [];
        foreach ($all as $name => $spec) {
            $settings[(string)$name] = (string)($spec['local_value'] ?? '');
        }
        return $settings;
    }

    public function version(): string
    {
        return (string)(phpversion('apcu') ?: '');
    }

    /**
     * @return array{0: mixed, 1: bool} значение и признак успеха (как $success у apcu_fetch)
     */
    public function fetch(string $key): array
    {
        $this->assertAvailable();
        $success = false;
        $value = apcu_fetch($key, $success);
        return [$value, (bool)$success];
    }

    /**
     * @return array<string, mixed>|null null — функции нет (APCu < 5.1.16) либо ключа нет
     */
    public function keyInfo(string $key): ?array
    {
        if (!function_exists('apcu_key_info')) {
            return null;
        }
        $info = apcu_key_info($key);
        return is_array($info) ? $info : null;
    }

    public function delete(string $key): bool
    {
        $this->assertAvailable();
        return (bool)apcu_delete($key);
    }

    public function clear(): bool
    {
        $this->assertAvailable();
        return apcu_clear_cache();
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(
                'Расширение APCu не загружено или выключено (apc.enabled=0). Кэш недоступен.'
            );
        }
    }
}
