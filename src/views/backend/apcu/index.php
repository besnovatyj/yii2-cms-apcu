<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Apcu\entities\HostStats;
use Besnovatyj\Apcu\helpers\Duration;
use Besnovatyj\Apcu\helpers\SvgCharts;
use yii\helpers\Html;
use yii\web\View;

/**
 * Статистика хоста — порт раздела `OB_HOST_STATS` из apc.php.
 *
 * @var View $this
 * @var HostStats $stats
 * @var string $dateFormat формат для Formatter::asDatetime (`php:...`)
 */

$this->title = 'APCu — статистика хоста';
$this->params['breadcrumbs'][] = 'APCu';

$formatter = Yii::$app->formatter;
$charts = new SvgCharts($formatter);

$size = static fn(int $bytes): string => (string)$formatter->asShortSize($bytes, 1);
$rate = static fn(float $v): string => sprintf('%.2f запросов/с', $v);

/**
 * Строки таблицы «ключ → значение» одной карточки.
 * @param array<string, string> $rows значения уже HTML-безопасны
 */
$card = static function (string $title, array $rows): string {
    $body = '';
    foreach ($rows as $label => $value) {
        $body .= '<tr><th class="text-nowrap fw-normal text-muted" style="width:45%">' . Html::encode($label) . '</th>'
            . '<td>' . $value . '</td></tr>';
    }
    return '<div class="card mb-3"><div class="card-header">' . Html::encode($title) . '</div>'
        . '<table class="table table-sm table-striped mb-0"><tbody>' . $body . '</tbody></table></div>';
};

$host = $stats->serverName;
if ($stats->hostName !== '') {
    $host .= ' (' . $stats->hostName . ')';
}
if ($stats->serverAddr !== '') {
    $host .= ' (' . $stats->serverAddr . ')';
}
?>
<h1><?= Html::encode($this->title) ?></h1>

<?= $this->render('_nav', ['active' => 'index']) ?>

<div class="row">
    <div class="col-lg-5">
        <?= $card('Общая информация', [
            'Версия APCu' => Html::encode($stats->apcuVersion),
            'Версия PHP' => Html::encode($stats->phpVersion),
            'Хост' => Html::encode(trim($host)),
            'Сервер' => Html::encode($stats->serverSoftware),
            'Общая память' => Html::encode(
                $stats->numSegments . ' сегм. × ' . $size($stats->segmentSize) . ' (' . $stats->memoryType . ' memory)'
            ),
            'Запущен' => Html::encode($formatter->asDatetime($stats->startTime, $dateFormat)),
            'Uptime' => Html::encode(Duration::format($stats->uptimeSeconds)),
        ]) ?>

        <?= $card('Кэш', [
            'Записей' => Html::encode($formatter->asInteger($stats->numEntries) . ' (' . $size($stats->entriesBytes) . ')'),
            'Хиты' => Html::encode($formatter->asInteger($stats->hits)),
            'Промахи' => Html::encode($formatter->asInteger($stats->misses)),
            'Вставки' => Html::encode($formatter->asInteger($stats->inserts)),
            'Скорость запросов (хиты + промахи)' => Html::encode($rate($stats->requestRate)),
            'Скорость хитов' => Html::encode($rate($stats->hitRate)),
            'Скорость промахов' => Html::encode($rate($stats->missRate)),
            'Скорость вставок' => Html::encode($rate($stats->insertRate)),
            'Очисток по TTL (cleanups)' => Html::encode($stats->cleanups === null ? '—' : (string)$stats->cleanups),
            'Дефрагментаций' => Html::encode($stats->defragmentations === null ? '—' : (string)$stats->defragmentations),
            'Переполнений (cache full)' => Html::encode((string)$stats->expunges),
        ]) ?>

        <?php
        $ini = [];
        foreach ($stats->iniSettings as $name => $value) {
            // как в оригинале: списки через запятую переносятся построчно
            $ini[$name] = nl2br(Html::encode(str_replace(',', ",\n", $value)));
        }
        ?>
        <?= $card('Настройки (ini)', $ini) ?>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Диаграммы</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-sm-6 mb-3">
                        <div class="fw-semibold">
                            Использование памяти
                            <?php if ($stats->hasMultipleSlices()): ?>
                                <div class="text-muted small">(несколько долей = фрагменты)</div>
                            <?php endif; ?>
                        </div>
                        <?= $charts->memoryPie($stats->memoryMap, $stats->memoryTotal) ?>
                        <div class="text-start mt-2">
                            <div><?= $charts->legendBox(true) ?>Свободно:
                                <?= Html::encode($size($stats->memoryAvailable)) ?>
                                (<?= sprintf('%.1f%%', $stats->freePercent()) ?>)</div>
                            <div><?= $charts->legendBox(false) ?>Занято:
                                <?= Html::encode($size($stats->memoryUsed)) ?>
                                (<?= sprintf('%.1f%%', $stats->usedPercent()) ?>)</div>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="fw-semibold">Хиты и промахи</div>
                        <?= $charts->hitsBars($stats->hits, $stats->misses) ?>
                        <div class="text-start mt-2">
                            <div><?= $charts->legendBox(true) ?>Хиты:
                                <?= Html::encode($formatter->asInteger($stats->hits)) ?>
                                (<?= sprintf('%.1f%%', $stats->hitPercent()) ?>)</div>
                            <div><?= $charts->legendBox(false) ?>Промахи:
                                <?= Html::encode($formatter->asInteger($stats->misses)) ?>
                                (<?= sprintf('%.1f%%', $stats->missPercent()) ?>)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Детальное использование памяти и фрагментация</div>
            <div class="card-body">
                <?= $charts->memoryMap($stats->memoryMap, $stats->segmentSize, $stats->numSegments) ?>

                <?php $frag = $stats->fragmentation; ?>
                <p class="mt-3 mb-0">
                    <strong>Фрагментация:</strong>
                    <?php if ($frag->isFragmented()): ?>
                        <?= sprintf('%.2f%%', $frag->percent) ?>
                        (<?= Html::encode($size($frag->fragmentedBytes)) ?> из
                        <?= Html::encode($size($frag->freeBytes)) ?> в <?= $frag->fragments ?> фрагментах)
                    <?php else: ?>
                        0%
                    <?php endif; ?>
                </p>
                <p class="text-muted small mb-0">
                    Свободных блоков: <?= $frag->fragments ?>; всего блоков на карте: <?= count($stats->memoryMap) ?>.
                    В процент входят только свободные блоки меньше 5 МБ (как в apc.php).
                </p>

                <?php if ($stats->allocationDistribution !== null): ?>
                    <table class="table table-sm mt-3 mb-0" style="max-width:320px">
                        <thead><tr><th>Размер блока</th><th class="text-end">Аллокаций</th></tr></thead>
                        <tbody>
                        <?php foreach ($stats->allocationDistribution as $i => $count): ?>
                            <?php $cur = 2 ** $i; $nxt = 2 ** ($i + 1) - 1; ?>
                            <tr>
                                <th class="fw-normal"><?= $i === 0 ? '1' : $cur . ' – ' . $nxt ?></th>
                                <td class="text-end"><?= (int)$count ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
