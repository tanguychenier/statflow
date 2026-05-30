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

use App\Ingestion\Domain\Model\CanonicalEvent;

/**
 * Parses UTM parameters from the event URL server-side (event-contract.md §2).
 *
 * UTM values are never carried as wire fields; they are extracted from the
 * `url` query string. Values explicitly supplied on the canonical event (a
 * server-side integration) take precedence and are left untouched.
 */
final class UtmParser
{
    private const UTM_KEYS = [
        'utm_source' => 'utmSource',
        'utm_medium' => 'utmMedium',
        'utm_campaign' => 'utmCampaign',
        'utm_term' => 'utmTerm',
        'utm_content' => 'utmContent',
    ];

    public function enrich(CanonicalEvent $event): CanonicalEvent
    {
        $fromUrl = $this->parseQuery($event->url);

        return $event->withUtm(
            $event->utmSource ?? $fromUrl['utm_source'],
            $event->utmMedium ?? $fromUrl['utm_medium'],
            $event->utmCampaign ?? $fromUrl['utm_campaign'],
            $event->utmTerm ?? $fromUrl['utm_term'],
            $event->utmContent ?? $fromUrl['utm_content'],
        );
    }

    /**
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string}
     */
    private function parseQuery(string $url): array
    {
        $result = [
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
        ];

        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return $result;
        }

        parse_str($query, $params);
        foreach (self::UTM_KEYS as $param => $_) {
            if (isset($params[$param]) && is_string($params[$param]) && $params[$param] !== '') {
                $result[$param] = mb_substr($params[$param], 0, 200);
            }
        }

        return $result;
    }
}
