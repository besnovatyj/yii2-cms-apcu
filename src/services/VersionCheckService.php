<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Apcu\services;

use Besnovatyj\Apcu\entities\VersionCheck;
use Besnovatyj\Apcu\repositories\ApcuRepository;
use Throwable;

/**
 * Проверка версии APCu по RSS-ленте PECL — порт раздела `OB_VERSION_CHECK` из apc.php.
 *
 * Делает ИСХОДЯЩИЙ HTTP-запрос с сервера, поэтому вызывается только по явной кнопке, а не при
 * загрузке страницы. Правила показа changelog как в оригинале: для актуальной версии — три
 * последних релиза, для устаревшей — все релизы новее установленной.
 */
final class VersionCheckService
{
    private const string FEED_URL = 'https://pecl.php.net/feeds/pkg_apcu.rss';
    private const int TIMEOUT = 5;
    private const int LATEST_CHANGELOG_ITEMS = 3;

    public function __construct(
        private readonly ApcuRepository $repo,
        /** Прокси вида `tcp://host:port`; null — без прокси. */
        private readonly ?string $proxy,
    ) {
    }

    public function check(): VersionCheck
    {
        $installed = $this->repo->version();

        try {
            $rss = $this->fetchFeed();
        } catch (Throwable $e) {
            return new VersionCheck($installed, null, false, [], $e->getMessage());
        }

        $releases = $this->parseReleases($rss);
        if ($releases === []) {
            return new VersionCheck($installed, null, false, [], 'Не удалось разобрать ленту версий.');
        }

        $latest = $releases[0]['version'];
        $isLatest = version_compare($installed, $latest, '>=');

        $changelog = [];
        foreach ($releases as $release) {
            if ($isLatest) {
                if (count($changelog) >= self::LATEST_CHANGELOG_ITEMS) {
                    break;
                }
            } elseif (version_compare($installed, $release['version'], '>=')) {
                break;
            }
            $changelog[] = $release;
        }

        return new VersionCheck($installed, $latest, $isLatest, $changelog, null);
    }

    /**
     * Релизы из ленты, новые первыми (порядок RSS). Первый `<title>` ленты — заголовок канала
     * «APCu x.y.z», далее у каждого `<item>` title = «APCu x.y.z», description = changelog.
     *
     * @return list<array{version: string, changes: string}>
     */
    private function parseReleases(string $rss): array
    {
        $releases = [];
        if (preg_match_all('!<item>(.*?)</item>!s', $rss, $items) < 1) {
            return [];
        }
        foreach ($items[1] as $item) {
            if (!preg_match('!<title>\s*APCu\s+([0-9][0-9A-Za-z.\-]*)\s*</title>!', $item, $t)) {
                continue;
            }
            $changes = '';
            if (preg_match('!<description>(.*?)</description>!s', $item, $d)) {
                $changes = trim(html_entity_decode(strip_tags(
                    preg_replace('!^\s*<!\[CDATA\[(.*)\]\]>\s*$!s', '$1', $d[1]) ?? $d[1]
                ), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $releases[] = ['version' => $t[1], 'changes' => $changes];
        }
        return $releases;
    }

    /**
     * @throws \RuntimeException при сетевой ошибке
     */
    private function fetchFeed(): string
    {
        $body = function_exists('curl_init') ? $this->fetchWithCurl() : $this->fetchWithStream();

        // gzip по magic-числам — как в оригинале (сервер может отдать сжатый ответ без заголовка)
        if (strlen($body) > 2 && ord($body[0]) === 0x1f && ord($body[1]) === 0x8b) {
            $decoded = @gzdecode($body);
            if ($decoded !== false) {
                $body = $decoded;
            }
        }
        if ($body === '') {
            throw new \RuntimeException('Пустой ответ от pecl.php.net.');
        }
        return $body;
    }

    private function fetchWithCurl(): string
    {
        $ch = curl_init(self::FEED_URL);
        if ($ch === false) {
            throw new \RuntimeException('curl_init() не удался.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'yii2-cms-apcu/' . $this->repo->version(),
        ]);
        if ($this->proxy !== null && $this->proxy !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Ошибка запроса к pecl.php.net: ' . $error);
        }
        if ($status >= 400) {
            throw new \RuntimeException('pecl.php.net ответил HTTP ' . $status . '.');
        }
        return (string)$body;
    }

    private function fetchWithStream(): string
    {
        $http = ['timeout' => self::TIMEOUT, 'follow_location' => 1, 'max_redirects' => 3];
        if ($this->proxy !== null && $this->proxy !== '') {
            $http['proxy'] = $this->proxy;
            $http['request_fulluri'] = true;
        }
        $context = stream_context_create(['http' => $http]);
        $body = @file_get_contents(self::FEED_URL, false, $context);
        if ($body === false) {
            $last = error_get_last();
            throw new \RuntimeException(
                'Не удалось получить ленту: ' . ($last['message'] ?? 'allow_url_fopen выключен или сеть недоступна')
            );
        }
        return $body;
    }
}
