<?php

declare(strict_types=1);

/*
 * This file is part of Statflow.
 *
 * (c) Tanguy Chénier <tanguychenier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Ingestion\Domain\Service;

/**
 * Derives `referrer_source` from the referrer URL (clickhouse-schema.sql:
 * 'google', 'twitter', 'direct', …). A referrer on the same host as the event
 * is internal navigation and classified as 'direct'.
 */
final class ReferrerClassifier
{
    /**
     * Referrer host substring → canonical source label.
     */
    private const SOURCE_MAP = [
        'google.' => 'google',
        'bing.' => 'bing',
        'duckduckgo.' => 'duckduckgo',
        'yahoo.' => 'yahoo',
        'yandex.' => 'yandex',
        'baidu.' => 'baidu',
        'ecosia.' => 'ecosia',
        't.co' => 'twitter',
        'twitter.' => 'twitter',
        'x.com' => 'twitter',
        'facebook.' => 'facebook',
        'fb.com' => 'facebook',
        'instagram.' => 'instagram',
        'linkedin.' => 'linkedin',
        'lnkd.in' => 'linkedin',
        'youtube.' => 'youtube',
        'youtu.be' => 'youtube',
        'reddit.' => 'reddit',
        'pinterest.' => 'pinterest',
        'github.' => 'github',
        'news.ycombinator.com' => 'hackernews',
        'producthunt.' => 'producthunt',
    ];

    public function classify(?string $referrer, string $eventHostname): string
    {
        if ($referrer === null || trim($referrer) === '') {
            return 'direct';
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return 'direct';
        }

        $host = strtolower($host);

        if ($this->isInternal($host, strtolower($eventHostname))) {
            return 'direct';
        }

        foreach (self::SOURCE_MAP as $needle => $label) {
            if (str_contains($host, $needle)) {
                return $label;
            }
        }

        // Unknown external referrer: keep the bare host as the source.
        return $this->stripWww($host);
    }

    private function isInternal(string $referrerHost, string $eventHost): bool
    {
        return $this->stripWww($referrerHost) === $this->stripWww($eventHost);
    }

    private function stripWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
