<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\entities;

/**
 * Значение записи кэша для показа (порт `apcu_fetch` + `print_r` из apc.php, расширенный).
 *
 * Yii `Cache::set()` кладёт в APCu `serialize([$value, $dependency])`, поэтому кроме сырого
 * значения показывается и его разбор (`unserialize` без инстанцирования классов — объекты
 * приходят как `__PHP_Incomplete_Class` со всеми полями, ничего не скрывается и не исполняется).
 *
 * @param array<string, mixed>|null $keyInfo результат apcu_key_info() (APCu ≥ 5.1.16), если доступен
 */
final readonly class EntryValue
{
    public function __construct(
        public string $key,
        /** Значение найдено (`apcu_fetch` вернул success). */
        public bool $exists,
        /** Тип значения по `get_debug_type()`. */
        public string $type,
        /** Сырое представление (сама строка либо дамп не-строкового значения). */
        public string $raw,
        /** Полная длина сырого представления до обрезки, байт. */
        public int $rawLength,
        /** Сырое представление обрезано по пределу модуля. */
        public bool $truncated,
        /** Дамп разобранного значения, если строка оказалась PHP-serialized; иначе null. */
        public ?string $decoded,
        public ?array $keyInfo,
    ) {
    }
}
