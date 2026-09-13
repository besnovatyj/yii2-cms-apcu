<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Apcu\entities\VersionCheck;
use yii\helpers\Html;
use yii\web\View;

/**
 * Проверка версии — порт раздела `OB_VERSION_CHECK` из apc.php. Запрос к PECL — только по кнопке.
 *
 * @var View $this
 * @var VersionCheck|null $result null — проверка ещё не запускалась
 */

$this->title = 'APCu — проверка версии';
$this->params['breadcrumbs'][] = ['label' => 'APCu', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Версия';
?>
<h1><?= Html::encode($this->title) ?></h1>

<?= $this->render('_nav', ['active' => 'version']) ?>

<div class="card">
    <div class="card-header">Версия APCu</div>
    <div class="card-body">
        <?= Html::beginForm(['version'], 'post', ['class' => 'mb-3']) ?>
            <?= Html::submitButton(
                '<i class="bi bi-cloud-download me-1"></i>Проверить на pecl.php.net',
                ['class' => 'btn btn-primary']
            ) ?>
            <span class="text-muted ms-2">Выполняет исходящий HTTP-запрос с сервера к ленте релизов PECL.</span>
        <?= Html::endForm() ?>

        <?php if ($result !== null): ?>
            <?php if ($result->error !== null): ?>
                <div class="alert alert-danger mb-0">
                    <strong>Не удалось получить информацию о версии.</strong>
                    <?= Html::encode($result->error) ?>
                    <div class="text-muted small mt-1">Установлена: <?= Html::encode($result->installed) ?></div>
                </div>
            <?php elseif ($result->isLatest): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Установлена актуальная версия APCu (<?= Html::encode($result->installed) ?>).
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Установлена устаревшая версия APCu (<?= Html::encode($result->installed) ?>);
                    доступна <?= Html::a(
                        Html::encode((string)$result->latest),
                        $result->releaseUrl((string)$result->latest),
                        ['target' => '_blank', 'rel' => 'noopener']
                    ) ?>.
                </div>
            <?php endif; ?>

            <?php if ($result->changelog !== []): ?>
                <h5>Изменения</h5>
                <?php foreach ($result->changelog as $release): ?>
                    <div class="mb-3">
                        <strong><?= Html::a(
                            'APCu ' . Html::encode($release['version']),
                            $result->releaseUrl($release['version']),
                            ['target' => '_blank', 'rel' => 'noopener']
                        ) ?></strong>
                        <blockquote class="border-start ps-3 mt-1 mb-0 text-muted">
                            <?= nl2br(Html::encode($release['changes'])) ?>
                        </blockquote>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
