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

namespace App\Tests\Unit\Sites\Application\Dto;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Application\Dto\SiteSettingsView;
use App\Sites\Application\Dto\SiteView;
use App\Sites\Application\Dto\TrackerKeyRotationResult;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerKey;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteView::class)]
#[CoversClass(SiteSettingsView::class)]
#[CoversClass(TrackerKeyRotationResult::class)]
final class SiteViewTest extends TestCase
{
    #[Test]
    public function siteViewMatchesOpenApiShape(): void
    {
        $site = $this->site();

        $array = SiteView::fromSite($site)->toArray();

        self::assertSame(
            ['id', 'team_id', 'name', 'domain', 'tracker_key', 'timezone', 'created_at', 'updated_at'],
            array_keys($array),
        );
        self::assertSame('example.com', $array['domain']);
    }

    #[Test]
    public function settingsViewExposesNestedTrackerConfig(): void
    {
        $array = SiteSettingsView::fromSite($this->site())->toArray();

        self::assertArrayHasKey('tracker_config', $array);
        self::assertIsArray($array['tracker_config']);
        self::assertSame(
            ['track_clicks', 'track_scroll', 'track_engagement_time', 'track_outbound_links', 'hash_based_routing', 'ignored_selectors', 'sampling_rate'],
            array_keys($array['tracker_config']),
        );
        self::assertSame(365, $array['data_retention_days']);
    }

    #[Test]
    public function rotationResultSerialisesBothFields(): void
    {
        $result = new TrackerKeyRotationResult('stk_new', '2026-01-01T00:00:00+00:00');

        self::assertSame(
            [
                'tracker_key' => 'stk_new',
                'old_key_valid_until' => '2026-01-01T00:00:00+00:00',
            ],
            $result->toArray(),
        );
    }

    private function site(): Site
    {
        return Site::register(
            id: Uuid::generate(),
            teamId: Uuid::generate(),
            name: SiteName::fromString('Example'),
            domain: Hostname::fromString('example.com'),
            timezone: Timezone::default(),
            trackerKey: TrackerKey::fromString('stk_' . str_repeat('a', 32)),
            now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }
}
