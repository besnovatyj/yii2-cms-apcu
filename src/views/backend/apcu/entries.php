<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Apcu\entities\CacheEntry;
use Besnovatyj\Apcu\entities\EntriesPage;
use Besnovatyj\Apcu\forms\backend\EntriesFilterForm;
use Besnovatyj\Backend\Widgets\pagination\LinkPager;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/**
 * Записи пользовательского кэша — порт раздела `OB_USER_CACHE` из apc.php.
 *
 * @var View $this
 * @var EntriesFilterForm $filter
 * @var EntriesPage|null $page null — фильтр не прошёл валидацию (ошибки показаны в форме)
 * @var string $dateFormat формат для Formatter::asDatetime (`php:...`)
 */

$this->title = 'APCu — записи кэша';
$this->params['breadcrumbs'][] = ['label' => 'APCu', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Записи';

$formatter = Yii::$app->formatter;
$date = static fn(int $ts): string => $ts > 0 ? (string)$formatter->asDatetime($ts, $dateFormat) : '—';

/** Заголовок колонки со ссылкой сортировки: повторный клик по активной колонке меняет направление. */
$sortHeader = static function (string $sort, string $label) use ($filter): string {
    $dir = $filter->dir;
    $icon = '';
    if ($filter->sort === $sort) {
        $dir = $dir === EntriesFilterForm::DIR_ASC ? EntriesFilterForm::DIR_DESC : EntriesFilterForm::DIR_ASC;
        $icon = $filter->dir === EntriesFilterForm::DIR_ASC
            ? ' <i class="bi bi-sort-up"></i>'
            : ' <i class="bi bi-sort-down"></i>';
    }
    $params = array_merge(['entries'], $filter->queryParams(), ['sort' => $sort, 'dir' => $dir]);
    return Html::a(Html::encode($label) . $icon, $params, ['class' => 'text-decoration-none text-reset']);
};

$isActiveScope = $filter->scope !== EntriesFilterForm::SCOPE_DELETED;
$columns = 8 + ($isActiveScope ? 1 : 0);
?>
<h1><?= Html::encode($this->title) ?></h1>

<?= $this->render('_nav', ['active' => 'entries']) ?>

<div class="card mb-3">
    <div class="card-body">
        <?php $form = ActiveForm::begin([
            'method' => 'get',
            'action' => ['entries'],
            'options' => ['class' => 'row gx-2 gy-2 align-items-end'],
            'enableClientValidation' => false,
        ]); ?>
            <div class="col-auto">
                <?= $form->field($filter, 'scope', ['options' => ['class' => 'mb-0']])
                    ->dropDownList(EntriesFilterForm::scopeOptions(), ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-auto">
                <?= $form->field($filter, 'sort', ['options' => ['class' => 'mb-0']])
                    ->dropDownList(EntriesFilterForm::sortOptions(), ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-auto">
                <?= $form->field($filter, 'dir', ['options' => ['class' => 'mb-0']])
                    ->dropDownList(EntriesFilterForm::dirOptions(), ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-auto">
                <?php
                $countOptions = EntriesFilterForm::countOptions();
                if (!isset($countOptions[$filter->count])) {     // значение из настроек модуля вне стандартного набора
                    $countOptions = [$filter->count => 'Top ' . $filter->count] + $countOptions;
                }
                ?>
                <?= $form->field($filter, 'count', ['options' => ['class' => 'mb-0']])
                    ->dropDownList($countOptions, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($filter, 'search', ['options' => ['class' => 'mb-0']])
                    ->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'например ^tag_|blog']) ?>
            </div>
            <div class="col-auto">
                <?= Html::submitButton('<i class="bi bi-search me-1"></i>Показать', ['class' => 'btn btn-primary btn-sm']) ?>
                <?= Html::a('Сбросить', ['entries'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php if ($page === null): ?>
    <div class="alert alert-danger">Параметры фильтра некорректны — исправьте и повторите.</div>
<?php else: ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= $isActiveScope ? 'Активные записи' : 'Удалённые записи' ?></span>
            <span>
                <?php if ($page->total !== $page->totalInScope): ?>
                    <span class="badge bg-primary">Найдено: <?= $page->total ?></span>
                <?php endif; ?>
                <span class="badge bg-secondary">Всего: <?= $page->totalInScope ?></span>
            </span>
        </div>

        <?php if ($page->entries === []): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">Записей нет.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="sticky-top bg-body">
                    <tr>
                        <th><?= $sortHeader(EntriesFilterForm::SORT_KEY, 'Ключ') ?></th>
                        <th class="text-end"><?= $sortHeader(EntriesFilterForm::SORT_HITS, 'Хиты') ?></th>
                        <th class="text-end"><?= $sortHeader(EntriesFilterForm::SORT_SIZE, 'Размер') ?></th>
                        <th><?= $sortHeader(EntriesFilterForm::SORT_ACCESS, 'Последний доступ') ?></th>
                        <th><?= $sortHeader(EntriesFilterForm::SORT_MTIME, 'Изменена') ?></th>
                        <th><?= $sortHeader(EntriesFilterForm::SORT_CREATED, 'Создана') ?></th>
                        <th class="text-end"><?= $sortHeader(EntriesFilterForm::SORT_TTL, 'TTL') ?></th>
                        <th><?= $sortHeader(EntriesFilterForm::SORT_DELETED, 'Удалена') ?></th>
                        <?php if ($isActiveScope): ?>
                            <th></th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php /** @var CacheEntry $entry */ ?>
                    <?php foreach ($page->entries as $entry): ?>
                        <?php
                        $isExpanded = $page->expanded !== null && $page->expanded->key === $entry->key;
                        // повторный клик по раскрытой записи сворачивает её (как SH в apc.php)
                        $currentPage = $page->pagination !== null ? $page->pagination->getPage() + 1 : 1;
                        $toggleUrl = Url::to(array_merge(
                            ['entries'],
                            $filter->queryParams(),
                            $currentPage > 1 ? ['page' => $currentPage] : [],
                            $isExpanded ? [] : ['key' => $entry->key],
                            ['#' => $entry->anchorId()],
                        ));
                        ?>
                        <tr id="<?= $entry->anchorId() ?>"<?= $isExpanded ? ' class="table-active"' : '' ?>>
                            <td class="text-break">
                                <?= Html::a(
                                    '<i class="bi ' . ($isExpanded ? 'bi-chevron-down' : 'bi-chevron-right') . ' me-1"></i>'
                                    . '<code>' . Html::encode($entry->key) . '</code>',
                                    $toggleUrl,
                                    ['class' => 'text-decoration-none', 'title' => 'Показать значение']
                                ) ?>
                            </td>
                            <td class="text-end"><?= $formatter->asInteger($entry->hits) ?></td>
                            <td class="text-end text-nowrap" title="<?= $entry->memSize ?> байт">
                                <?= Html::encode($formatter->asShortSize($entry->memSize, 1)) ?>
                            </td>
                            <td class="text-nowrap"><?= Html::encode($date($entry->accessTime)) ?></td>
                            <td class="text-nowrap"><?= Html::encode($date($entry->mtime)) ?></td>
                            <td class="text-nowrap"><?= Html::encode($date($entry->creationTime)) ?></td>
                            <td class="text-end text-nowrap">
                                <?= $entry->ttl > 0 ? $entry->ttl . ' с' : '<span class="text-muted">нет</span>' ?>
                            </td>
                            <td class="text-nowrap"><?= Html::encode($date($entry->deletionTime)) ?></td>
                            <?php if ($isActiveScope): ?>
                                <td class="text-end text-nowrap">
                                    <?= Html::a(
                                        '<i class="bi bi-trash"></i>',
                                        ['delete'],
                                        [
                                            'class' => 'btn btn-sm btn-outline-danger',
                                            'title' => 'Удалить запись',
                                            'data-method' => 'post',
                                            'data-params' => ['key' => $entry->key],
                                            'data-confirm' => 'Удалить запись «' . $entry->key . '» из APCu?',
                                        ]
                                    ) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php if ($isExpanded): ?>
                            <tr>
                                <td colspan="<?= $columns ?>" class="bg-light">
                                    <?= $this->render('_value', ['value' => $page->expanded, 'dateFormat' => $dateFormat]) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($page->pagination !== null && $page->pagination->getPageCount() > 1): ?>
            <div class="card-footer clearfix">
                <nav aria-label="Страницы" class="nav-pagination">
                    <?= LinkPager::widget(['pagination' => $page->pagination]) ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($page->expanded !== null && !array_any($page->entries, static fn(CacheEntry $e): bool => $e->key === $page->expanded->key)): ?>
        <div class="card mt-3">
            <div class="card-header">Значение записи вне текущей страницы</div>
            <div class="card-body">
                <?= $this->render('_value', ['value' => $page->expanded, 'dateFormat' => $dateFormat]) ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
