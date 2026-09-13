<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\helpers;

use Besnovatyj\Apcu\entities\MemoryBlock;
use yii\helpers\Html;
use yii\i18n\Formatter;

/**
 * Диаграммы экрана статистики inline-SVG — замена GD-картинок `?IMG=1|2|3` из apc.php.
 *
 * Цвета и смысл сохранены: зелёный — свободно / хиты, красный — занято / промахи. Вместо
 * подписей-выносок оригинала используются `<title>` (tooltip браузера) и подписи внутри
 * достаточно крупных долей. Без GD, без отдельных запросов за картинками.
 */
final class SvgCharts
{
    private const string COLOR_FREE = '#60f060';
    private const string COLOR_USED = '#d06030';
    private const string COLOR_EDGE = '#000';

    /** Доля (0..1), начиная с которой на секторе/блоке рисуется подпись размера. */
    private const float LABEL_MIN_SHARE = 0.05;

    public function __construct(
        private readonly Formatter $formatter,
    ) {
    }

    /**
     * Круговая диаграмма памяти (IMG=1): каждый блок карты — отдельный сектор, поэтому
     * фрагментация видна как «нарезка» круга.
     *
     * @param MemoryBlock[] $memoryMap
     */
    public function memoryPie(array $memoryMap, int $totalBytes, int $size = 200): string
    {
        $r = $size / 2;
        $cx = $r;
        $cy = $r;
        $out = [];

        if ($totalBytes <= 0 || $memoryMap === []) {
            $out[] = sprintf('<circle cx="%s" cy="%s" r="%s" fill="#ddd"/>', $cx, $cy, $r - 1);
            return $this->wrap($out, $size, $size);
        }

        // Сектор рисуется только если он не меньше 1° — как в оригинале (иначе SVG забит мусором).
        $angleFrom = 0.0;
        $labels = [];
        foreach ($memoryMap as $block) {
            $share = $block->size / $totalBytes;
            $angleTo = min($angleFrom + $share * 360, 360.0);
            if ($angleTo - $angleFrom >= 1) {
                $color = $block->isFree ? self::COLOR_FREE : self::COLOR_USED;
                $title = ($block->isFree ? 'Свободно ' : 'Занято ') . $this->size($block->size)
                    . ' (сегмент ' . $block->segment . ', смещение ' . $block->offset . ')';
                $out[] = '<path d="' . $this->arcPath($cx, $cy, $r - 1, $angleFrom, $angleTo) . '" fill="' . $color
                    . '" stroke="' . self::COLOR_EDGE . '" stroke-width="1"><title>' . Html::encode($title) . '</title></path>';
                if ($share >= self::LABEL_MIN_SHARE) {
                    $labels[] = [$angleFrom, $angleTo, $this->size($block->size)];
                }
            }
            $angleFrom = $angleTo;
        }

        foreach ($labels as [$a0, $a1, $text]) {
            $mid = deg2rad(($a0 + $a1) / 2);
            $x = $cx + cos($mid) * $r / 2;
            $y = $cy + sin($mid) * $r / 2;
            $out[] = sprintf(
                '<text x="%.1f" y="%.1f" font-size="10" text-anchor="middle" dominant-baseline="middle">%s</text>',
                $x,
                $y,
                Html::encode($text)
            );
        }

        return $this->wrap($out, $size, $size);
    }

    /**
     * Столбики «хиты / промахи» (IMG=2).
     */
    public function hitsBars(int $hits, int $misses, int $size = 200): string
    {
        $total = $hits + $misses;
        $hitPct = $total > 0 ? $hits * 100 / $total : 0.0;
        $missPct = $total > 0 ? $misses * 100 / $total : 0.0;
        $maxH = $size - 21;

        $out = [];
        $out[] = $this->bar(30, $size, 50, $total > 0 ? $hitPct / 100 * $maxH : 0, self::COLOR_FREE,
            sprintf('%.1f%%', $hitPct), 'Хиты: ' . $hits);
        $out[] = $this->bar(130, $size, 50, $total > 0 ? max(4, $missPct / 100 * $maxH) : 0, self::COLOR_USED,
            sprintf('%.1f%%', $missPct), 'Промахи: ' . $misses);

        return $this->wrap($out, $size + 50, $size + 10);
    }

    /**
     * Карта памяти (IMG=3): по горизонтальной полосе на сегмент, блоки по порядку смещений.
     *
     * @param MemoryBlock[] $memoryMap
     */
    public function memoryMap(array $memoryMap, int $segmentSize, int $numSegments): string
    {
        $width = 1000;
        $rowH = 28;
        $gap = 6;
        $height = max(1, $numSegments) * ($rowH + $gap) - $gap;
        $out = [];

        if ($segmentSize <= 0) {
            return $this->wrap($out, $width, $rowH, true);
        }

        foreach ($memoryMap as $block) {
            $x = $block->offset / $segmentSize * $width;
            $w = $block->size / $segmentSize * $width;
            $y = $block->segment * ($rowH + $gap);
            $color = $block->isFree ? self::COLOR_FREE : self::COLOR_USED;
            $title = ($block->isFree ? 'Свободно ' : 'Занято ') . $this->size($block->size)
                . ' @ ' . $block->offset;
            $out[] = sprintf(
                '<rect x="%.2f" y="%d" width="%.2f" height="%d" fill="%s" stroke="%s" stroke-width="0.5"><title>%s</title></rect>',
                $x,
                $y,
                max($w, 0.5),
                $rowH,
                $color,
                self::COLOR_EDGE,
                Html::encode($title)
            );
            if ($block->size / $segmentSize >= self::LABEL_MIN_SHARE) {
                $out[] = sprintf(
                    '<text x="%.2f" y="%d" font-size="11" text-anchor="middle" dominant-baseline="middle">%s</text>',
                    $x + $w / 2,
                    $y + $rowH / 2,
                    Html::encode($this->size($block->size))
                );
            }
        }

        return $this->wrap($out, $width, $height, true);
    }

    /** Цветной квадратик легенды (span.box в apc.php). */
    public function legendBox(bool $free): string
    {
        return '<span class="d-inline-block border border-dark align-middle me-1" style="width:1em;height:1em;background:'
            . ($free ? self::COLOR_FREE : self::COLOR_USED) . '"></span>';
    }

    private function bar(int $x, int $bottom, int $w, float $h, string $color, string $label, string $title): string
    {
        $h = max(0.0, $h);
        $y = $bottom - $h;
        return sprintf(
            '<rect x="%d" y="%.1f" width="%d" height="%.1f" fill="%s" stroke="%s"><title>%s</title></rect>'
            . '<text x="%d" y="%.1f" font-size="12" text-anchor="middle">%s</text>',
            $x,
            $y,
            $w,
            $h,
            $color,
            self::COLOR_EDGE,
            Html::encode($title),
            $x + intdiv($w, 2),
            $y - 4,
            Html::encode($label)
        );
    }

    /**
     * Путь сектора от угла $from до $to (градусы, 0° = «3 часа», по часовой — как у GD).
     * Полный круг (360°) SVG-дугой не выразить — рисуется двумя полуокружностями.
     */
    private function arcPath(float $cx, float $cy, float $r, float $from, float $to): string
    {
        if ($to - $from >= 359.999) {
            return sprintf(
                'M %.3f %.3f A %.3f %.3f 0 1 1 %.3f %.3f A %.3f %.3f 0 1 1 %.3f %.3f Z',
                $cx + $r, $cy, $r, $r, $cx - $r, $cy, $r, $r, $cx + $r, $cy
            );
        }
        $x1 = $cx + $r * cos(deg2rad($from));
        $y1 = $cy + $r * sin(deg2rad($from));
        $x2 = $cx + $r * cos(deg2rad($to));
        $y2 = $cy + $r * sin(deg2rad($to));
        $large = ($to - $from) > 180 ? 1 : 0;

        return sprintf(
            'M %.3f %.3f L %.3f %.3f A %.3f %.3f 0 %d 1 %.3f %.3f Z',
            $cx, $cy, $x1, $y1, $r, $r, $large, $x2, $y2
        );
    }

    /**
     * @param string[] $elements
     */
    private function wrap(array $elements, int $width, int $height, bool $fluid = false): string
    {
        // fluid: ширина 100 % контейнера, высота — по пропорции viewBox (текст не искажается)
        $attrs = $fluid
            ? sprintf('width="100%%" viewBox="0 0 %d %d"', $width, $height)
            : sprintf('width="%d" height="%d" viewBox="0 0 %d %d"', $width, $height, $width, $height);

        return '<svg xmlns="http://www.w3.org/2000/svg" ' . $attrs . ' role="img" style="max-width:100%">'
            . implode('', $elements) . '</svg>';
    }

    private function size(int $bytes): string
    {
        return (string)$this->formatter->asShortSize($bytes, 1);
    }
}
