<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\helpers;

/**
 * Человекочитаемая длительность — порт функции `duration()` из apc.php (uptime кэша).
 *
 * Разбивка та же: годы (52.177457 недели), недели, дни, часы, минуты; нулевые единицы
 * опускаются, минуты выводятся всегда.
 */
final class Duration
{
    private const float WEEKS_PER_YEAR = 52.177457;

    public static function format(int $seconds): string
    {
        $seconds = max(0, $seconds);

        $years = (int)(($seconds / (7 * 86400)) / self::WEEKS_PER_YEAR);
        $rem = (int)($seconds - $years * self::WEEKS_PER_YEAR * 7 * 86400);
        $weeks = intdiv($rem, 7 * 86400);
        $days = intdiv($rem, 86400) - $weeks * 7;
        $hours = intdiv($rem, 3600) - $days * 24 - $weeks * 7 * 24;
        $mins = intdiv($rem, 60) - $hours * 60 - $days * 24 * 60 - $weeks * 7 * 24 * 60;

        $parts = [];
        if ($years > 0) {
            $parts[] = self::plural($years, 'год', 'года', 'лет');
        }
        if ($weeks > 0) {
            $parts[] = self::plural($weeks, 'неделя', 'недели', 'недель');
        }
        if ($days > 0) {
            $parts[] = self::plural($days, 'день', 'дня', 'дней');
        }
        if ($hours > 0) {
            $parts[] = self::plural($hours, 'час', 'часа', 'часов');
        }
        $parts[] = self::plural($mins, 'минута', 'минуты', 'минут');

        return implode(', ', $parts);
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        $word = match (true) {
            $mod10 === 1 && $mod100 !== 11 => $one,
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => $few,
            default => $many,
        };
        return $n . ' ' . $word;
    }
}
