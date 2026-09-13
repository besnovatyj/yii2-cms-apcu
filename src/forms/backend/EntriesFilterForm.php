<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\forms\backend;

use Besnovatyj\Forms\BaseForm;
use Throwable;

/**
 * Параметры списка записей — порт GET-параметров `SCOPE`/`SORT1`/`SORT2`/`COUNT`/`SEARCH` из apc.php.
 *
 * Имена значений сохранены оригинальные (одна буква), чтобы поведение было узнаваемым:
 *  - scope:  A — активные (`cache_list`), D — удалённые (`deleted_list`);
 *  - sort:   H хиты, Z размер, S ключ, A последний доступ, M изменение, C создание, T TTL, D удаление;
 *  - dir:    D по убыванию, A по возрастанию;
 *  - count:  записей на странице, 0 — все;
 *  - search: регулярное выражение (без ограничителей), регистронезависимое; `/` экранируется сам.
 *
 * Форма GET-овская, без префикса имени — параметры в URL плоские (`?scope=A&sort=H...`).
 */
final class EntriesFilterForm extends BaseForm
{
    public const string SCOPE_ACTIVE = 'A';
    public const string SCOPE_DELETED = 'D';

    public const string SORT_HITS = 'H';
    public const string SORT_SIZE = 'Z';
    public const string SORT_KEY = 'S';
    public const string SORT_ACCESS = 'A';
    public const string SORT_MTIME = 'M';
    public const string SORT_CREATED = 'C';
    public const string SORT_TTL = 'T';
    public const string SORT_DELETED = 'D';

    public const string DIR_DESC = 'D';
    public const string DIR_ASC = 'A';

    /** Варианты размера страницы, как в apc.php (0 — все). */
    public const array COUNT_OPTIONS = [10, 20, 50, 100, 150, 200, 500, 0];

    public string $scope = self::SCOPE_ACTIVE;
    public string $sort = self::SORT_HITS;
    public string $dir = self::DIR_DESC;
    public int $count = 20;
    public string $search = '';

    /** Ключ записи, значение которой раскрыть в списке (аналог `SH` в apc.php, но сам ключ, а не md5). */
    public ?string $key = null;

    public function formName(): string
    {
        return '';
    }

    public function rules(): array
    {
        return [
            [['scope', 'sort', 'dir', 'search', 'key'], 'string'],
            ['scope', 'in', 'range' => array_keys(self::scopeOptions())],
            ['sort', 'in', 'range' => array_keys(self::sortOptions())],
            ['dir', 'in', 'range' => array_keys(self::dirOptions())],
            ['count', 'integer', 'min' => 0, 'max' => 10000],
            ['search', 'validateRegex'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'scope' => 'Область',
            'sort' => 'Сортировка',
            'dir' => 'Направление',
            'count' => 'Показывать',
            'search' => 'Поиск (regex)',
        ];
    }

    /**
     * Регулярное выражение проверяется пробным `preg_match`, как в оригинале: preg_quote не
     * применяется намеренно — пользователь может задавать подшаблоны.
     */
    public function validateRegex(string $attribute): void
    {
        $pattern = $this->searchPattern();
        if ($pattern === null) {
            return;
        }
        try {
            if (@preg_match($pattern, 'test') === false) {
                $this->addError($attribute, 'Некорректное регулярное выражение: ' . preg_last_error_msg());
            }
        } catch (Throwable $e) {
            $this->addError($attribute, 'Некорректное регулярное выражение: ' . $e->getMessage());
        }
    }

    /** Готовый паттерн с ограничителями либо null, если поиск не задан. */
    public function searchPattern(): ?string
    {
        if ($this->search === '') {
            return null;
        }
        return '/' . str_replace('/', '\\/', $this->search) . '/i';
    }

    /**
     * Параметры для ссылок (сортировка колонок, постраничка) — только непустые.
     *
     * @return array<string, string|int>
     */
    public function queryParams(): array
    {
        $params = [
            'scope' => $this->scope,
            'sort' => $this->sort,
            'dir' => $this->dir,
            'count' => $this->count,
        ];
        if ($this->search !== '') {
            $params['search'] = $this->search;
        }
        return $params;
    }

    /** @return array<string, string> */
    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_ACTIVE => 'Активные',
            self::SCOPE_DELETED => 'Удалённые',
        ];
    }

    /** @return array<string, string> */
    public static function sortOptions(): array
    {
        return [
            self::SORT_HITS => 'Хиты',
            self::SORT_SIZE => 'Размер',
            self::SORT_KEY => 'Ключ',
            self::SORT_ACCESS => 'Последний доступ',
            self::SORT_MTIME => 'Изменена',
            self::SORT_CREATED => 'Создана',
            self::SORT_TTL => 'TTL',
            self::SORT_DELETED => 'Удалена',
        ];
    }

    /** @return array<string, string> */
    public static function dirOptions(): array
    {
        return [
            self::DIR_DESC => 'По убыванию',
            self::DIR_ASC => 'По возрастанию',
        ];
    }

    /** @return array<int, string> */
    public static function countOptions(): array
    {
        $options = [];
        foreach (self::COUNT_OPTIONS as $count) {
            $options[$count] = $count === 0 ? 'Все' : 'Top ' . $count;
        }
        return $options;
    }
}
