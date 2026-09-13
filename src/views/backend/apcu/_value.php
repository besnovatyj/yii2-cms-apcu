<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Apcu\entities\EntryValue;
use yii\helpers\Html;
use yii\web\View;

/**
 * Значение записи (порт `print_r(apcu_fetch(...))` из apc.php): сырое, разобранное и apcu_key_info().
 *
 * @var View $this
 * @var EntryValue $value
 * @var string $dateFormat формат для Formatter::asDatetime (`php:...`)
 */

$formatter = Yii::$app->formatter;
$preStyle = 'max-height:480px;overflow:auto;white-space:pre-wrap;word-break:break-all;';
?>
<div class="small">
    <?php if (!$value->exists): ?>
        <div class="alert alert-warning mb-2">
            Значение по ключу <code><?= Html::encode($value->key) ?></code> не получено — запись истекла или удалена.
        </div>
    <?php else: ?>
        <div class="mb-2">
            <span class="badge bg-secondary">тип: <?= Html::encode($value->type) ?></span>
            <span class="badge bg-secondary">длина: <?= Html::encode($formatter->asShortSize($value->rawLength, 1)) ?>
                (<?= $value->rawLength ?> байт)</span>
            <?php if ($value->truncated): ?>
                <span class="badge bg-warning text-dark">вывод обрезан по пределу модуля</span>
            <?php endif; ?>
        </div>

        <?php if ($value->keyInfo !== null): ?>
            <div class="mb-2 text-muted">
                apcu_key_info:
                <?php foreach ($value->keyInfo as $k => $v): ?>
                    <?php
                    $shown = is_int($v) && in_array($k, ['mtime', 'creation_time', 'access_time', 'deletion_time'], true) && $v > 0
                        ? $formatter->asDatetime($v, $dateFormat)
                        : (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
                    ?>
                    <code><?= Html::encode((string)$k) ?>=<?= Html::encode((string)$shown) ?></code>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($value->decoded !== null): ?>
            <div class="fw-semibold mb-1">Разобранное значение (unserialize)</div>
            <pre class="border rounded p-2 bg-white mb-2" style="<?= $preStyle ?>"><?= Html::encode($value->decoded) ?></pre>
            <details>
                <summary class="fw-semibold mb-1">Сырое значение</summary>
                <pre class="border rounded p-2 bg-white mb-0" style="<?= $preStyle ?>"><?= Html::encode($value->raw) ?></pre>
            </details>
        <?php else: ?>
            <div class="fw-semibold mb-1">Значение</div>
            <pre class="border rounded p-2 bg-white mb-0" style="<?= $preStyle ?>"><?= Html::encode($value->raw) ?></pre>
        <?php endif; ?>
    <?php endif; ?>
</div>
