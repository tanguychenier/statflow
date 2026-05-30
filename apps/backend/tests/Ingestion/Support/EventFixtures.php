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

namespace App\Tests\Ingestion\Support;

use App\Ingestion\Domain\Model\Site;

/**
 * Canonical and wire event payloads for tests, plus a default Site. Keeping the
 * fixtures in one place stops every test from re-spelling the contract.
 */
final class EventFixtures
{
    public const TRACKER_KEY = 'stk_abcdef1234567890';

    public const SITE_ID = '3f1a9b2d-2c3a-4b5c-8e9f-0a1b2c3d4e5f';

    public const EVENT_ID = '8f14e45f-ce64-4f1a-9b2d-2c3a4b5c6d7e';

    /**
     * A minimal valid canonical pageview (long keys, ISO timestamp).
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function canonicalPageview(array $overrides = []): array
    {
        return array_merge([
            'event_id' => self::EVENT_ID,
            'site_key' => self::TRACKER_KEY,
            'event_name' => 'pageview',
            'timestamp' => '2025-06-15T14:30:00.123Z',
            'seq' => 0,
            'tracker_version' => '1.0.0',
            'url' => 'https://example.com/blog/post-1',
            'pathname' => '/blog/post-1',
            'hostname' => 'example.com',
        ], $overrides);
    }

    /**
     * A minimal valid wire pageview (short keys, Unix-ms timestamp).
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function wirePageview(array $overrides = []): array
    {
        return array_merge([
            'eid' => self::EVENT_ID,
            'k' => self::TRACKER_KEY,
            'e' => 'pageview',
            'ts' => 1749997800123,
            'seq' => 0,
            'tv' => '1.0.0',
            'u' => 'https://example.com/blog/post-1',
            'p' => '/blog/post-1',
            'h' => 'example.com',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function site(array $overrides = []): Site
    {
        /** @var string $id */
        $id = $overrides['id'] ?? self::SITE_ID;
        /** @var string $trackerKey */
        $trackerKey = $overrides['trackerKey'] ?? self::TRACKER_KEY;
        /** @var bool $trackingEnabled */
        $trackingEnabled = $overrides['trackingEnabled'] ?? true;
        /** @var bool $botFilteringEnabled */
        $botFilteringEnabled = $overrides['botFilteringEnabled'] ?? true;
        /** @var list<string> $allowedDomains */
        $allowedDomains = $overrides['allowedDomains'] ?? [];
        /** @var list<string> $excludedIps */
        $excludedIps = $overrides['excludedIps'] ?? [];
        /** @var list<string> $excludedCountries */
        $excludedCountries = $overrides['excludedCountries'] ?? [];
        /** @var list<string>|null $customEventNames */
        $customEventNames = $overrides['customEventNames'] ?? null;

        return new Site(
            id: $id,
            trackerKey: $trackerKey,
            trackingEnabled: $trackingEnabled,
            botFilteringEnabled: $botFilteringEnabled,
            allowedDomains: $allowedDomains,
            excludedIps: $excludedIps,
            excludedCountries: $excludedCountries,
            customEventNames: $customEventNames,
        );
    }
}
