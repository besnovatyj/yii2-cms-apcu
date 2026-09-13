<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\controllers\backend;

use Besnovatyj\Apcu\forms\backend\EntriesFilterForm;
use Besnovatyj\Apcu\services\EntriesService;
use Besnovatyj\Apcu\services\StatsService;
use Besnovatyj\Apcu\services\VersionCheckService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

/**
 * Экраны APCu — порт apc.php: статистика хоста, записи кэша, проверка версии.
 *
 *  - index:   статистика хоста (OB_HOST_STATS);
 *  - entries: список записей с фильтром/сортировкой/постраничкой и раскрытием значения (OB_USER_CACHE);
 *  - version: сравнение с PECL (OB_VERSION_CHECK) — GET показывает кнопку, POST выполняет запрос;
 *  - delete:  POST, удалить запись по ключу (DU в оригинале);
 *  - clear:   POST, очистить весь кэш (CC в оригинале).
 *
 * Destructive-действия только POST + CSRF (в оригинале — GET-ссылки). Доступ — RBAC-гейт бэкенда.
 * Если расширение недоступно, страницы чтения показывают заглушку, действия бросают исключение.
 */
class ApcuController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly StatsService $stats,
        private readonly EntriesService $entries,
        private readonly VersionCheckService $versionCheck,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'entries' => ['GET'],
                    'version' => ['GET', 'POST'],
                    'delete' => ['POST'],
                    'clear' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Страницы не кэшируются браузером — статистика живая (как `Cache-Control: no-store` в apc.php).
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        Yii::$app->response->headers
            ->set('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->set('Pragma', 'no-cache');
        return true;
    }

    public function actionIndex(): string
    {
        if (!$this->stats->isAvailable()) {
            return $this->render('unavailable');
        }

        return $this->render('index', [
            'stats' => $this->stats->collect(),
            'dateFormat' => $this->dateFormat(),
        ]);
    }

    public function actionEntries(): string
    {
        if (!$this->stats->isAvailable()) {
            return $this->render('unavailable');
        }

        $filter = new EntriesFilterForm();
        $filter->count = $this->defaultPageSize();
        $filter->load(Yii::$app->request->get());
        $valid = $filter->validate();

        return $this->render('entries', [
            'filter' => $filter,
            'page' => $valid ? $this->entries->getPage($filter) : null,
            'dateFormat' => $this->dateFormat(),
        ]);
    }

    public function actionVersion(): string
    {
        if (!$this->stats->isAvailable()) {
            return $this->render('unavailable');
        }

        return $this->render('version', [
            'result' => Yii::$app->request->isPost ? $this->versionCheck->check() : null,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionDelete(): Response
    {
        $key = Yii::$app->request->post('key');
        if (!is_string($key) || $key === '') {
            throw new BadRequestHttpException('Не передан ключ записи.');
        }

        if ($this->entries->delete($key)) {
            Yii::$app->session->setFlash('success', 'Запись «' . $key . '» удалена из APCu.');
        } else {
            Yii::$app->session->setFlash('warning', 'Запись «' . $key . '» не найдена (возможно, уже истекла).');
        }

        return $this->redirectBack(['entries']);
    }

    public function actionClear(): Response
    {
        $this->entries->clear();
        Yii::$app->session->setFlash('success', 'Пользовательский кэш APCu очищен.');

        return $this->redirectBack(['index']);
    }

    /**
     * После POST — назад на страницу, с которой пришли (сохраняет фильтр списка), иначе на $fallback.
     */
    private function redirectBack(array $fallback): Response
    {
        $referrer = Yii::$app->request->referrer;
        return $this->redirect($referrer ?: $fallback);
    }

    private function defaultPageSize(): int
    {
        $size = (int)($this->module->params['pageSize'] ?? 50);
        return $size > 0 ? $size : 50;
    }

    private function dateFormat(): string
    {
        $format = trim((string)($this->module->params['dateFormat'] ?? ''));
        return 'php:' . ($format !== '' ? $format : 'Y-m-d H:i:s');
    }
}
