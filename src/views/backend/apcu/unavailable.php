<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use yii\web\View;

/**
 * Заглушка, если расширение не загружено или выключено (в apc.php — «APC does not appear to be running»).
 *
 * @var View $this
 */

$this->title = 'APCu';
$this->params['breadcrumbs'][] = $this->title;
?>
<h1><?= $this->title ?></h1>

<div class="alert alert-warning">
    <strong>Кэш недоступен.</strong> Расширение APCu не загружено либо выключено (<code>apc.enabled=0</code>).
    Для CLI дополнительно требуется <code>apc.enable_cli=1</code>, но модуль показывает кэш web-пула php-fpm.
</div>
