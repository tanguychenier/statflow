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

namespace App\Tests\Unit\Sites\Domain\Model;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Model\SiteSettings;
use App\Sites\Domain\ValueObject\AllowedDomainList;
use App\Sites\Domain\ValueObject\ExcludedIpList;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\RetentionDays;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerConfig;
use App\Sites\Domain\ValueObject\TrackerKey;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Site::class)]
#[CoversClass(SiteSettings::class)]
final class SiteTest extends TestCase
{
    private const SITE_ID = '11111111-1111-4111-8111-111111111111';

    private const TEAM_ID = '22222222-2222-4222-8222-222222222222';

    #[Test]
    public function registerSeedsDefaultsAndSettings(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $site = $this->newSite($now);

        self::assertSame(self::SITE_ID, $site->id()->getValue());
        self::assertSame(self::TEAM_ID, $site->teamId()->getValue());
        self::assertSame('Example', $site->name()->value());
        self::assertSame('example.com', $site->domain()->value());
        self::assertSame('Europe/Paris', $site->timezone()->value());
        self::assertTrue($site->trackingEnabled());
        self::assertFalse($site->isDeleted());
        self::assertNull($site->retentionDays());
        self::assertSame(RetentionDays::DEFAULT, $site->effectiveRetentionDays());
        self::assertSame($now, $site->createdAt());
        self::assertSame($now, $site->updatedAt());
        self::assertSame(self::SITE_ID, $site->settings()->siteId());
        self::assertTrue($site->settings()->allowedDomains()->isEmpty());
    }

    #[Test]
    public function renameTouchesUpdatedAtOnlyOnChange(): void
    {
        $created = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $site = $this->newSite($created);

        $site->rename(SiteName::fromString('Example'), new DateTimeImmutable('2026-02-01'));
        self::assertSame($created, $site->updatedAt(), 'no-op rename must not touch updated_at');

        $later = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
        $site->rename(SiteName::fromString('Renamed'), $later);
        self::assertSame('Renamed', $site->name()->value());
        self::assertSame($later, $site->updatedAt());
    }

    #[Test]
    public function changeDomainAndTimezoneAreIdempotentOnSameValue(): void
    {
        $created = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $site = $this->newSite($created);

        $site->changeDomain(Hostname::fromString('example.com'), new DateTimeImmutable('2026-02-01'));
        $site->changeTimezone(Timezone::fromString('Europe/Paris'), new DateTimeImmutable('2026-02-01'));
        self::assertSame($created, $site->updatedAt());

        $t = new DateTimeImmutable('2026-04-01T00:00:00+00:00');
        $site->changeDomain(Hostname::fromString('new.example.com'), $t);
        $site->changeTimezone(Timezone::fromString('UTC'), $t);
        self::assertSame('new.example.com', $site->domain()->value());
        self::assertSame('UTC', $site->timezone()->value());
    }

    #[Test]
    public function rotateTrackerKeyReplacesTheKeyWithNoGraceWindow(): void
    {
        // ADR-0009 §1 / postgres-schema.sql §4: exactly one tracker key per site;
        // rotation overwrites the value and revokes the old one immediately.
        $created = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
        $site = $this->newSite($created);
        $oldKey = $site->trackerKey();

        $newKey = TrackerKey::fromString('stk_' . str_repeat('b', 32));
        $rotatedAt = new DateTimeImmutable('2026-02-01T09:00:00+00:00');
        $site->rotateTrackerKey($newKey, $rotatedAt);

        self::assertTrue($newKey->equals($site->trackerKey()));
        self::assertFalse($oldKey->equals($site->trackerKey()));
        self::assertSame($rotatedAt, $site->updatedAt());
    }

    #[Test]
    public function softDeleteIsIdempotentAndDisablesTracking(): void
    {
        $created = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $site = $this->newSite($created);

        $deletedAt = new DateTimeImmutable('2026-05-01T00:00:00+00:00');
        $site->softDelete($deletedAt);

        self::assertTrue($site->isDeleted());
        self::assertSame($deletedAt, $site->deletedAt());
        self::assertFalse($site->trackingEnabled());

        // Second delete is a no-op: deleted_at stays at the first timestamp.
        $site->softDelete(new DateTimeImmutable('2026-06-01T00:00:00+00:00'));
        self::assertSame($deletedAt, $site->deletedAt());
    }

    #[Test]
    public function replaceSettingsAppliesRetentionAndTrackerConfig(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $site = $this->newSite($now);

        $site->replaceSettings(
            allowedDomains: AllowedDomainList::fromArray(['*.example.com']),
            excludedIps: ExcludedIpList::fromArray(['10.0.0.1']),
            stripQueryParams: true,
            customDomainEnabled: true,
            retentionDays: RetentionDays::fromInt(90),
            trackerConfig: TrackerConfig::default(),
            now: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
        );

        self::assertSame(90, $site->retentionDays()?->value());
        self::assertSame(90, $site->effectiveRetentionDays());
        self::assertSame(['*.example.com'], $site->settings()->allowedDomains()->toArray());
        self::assertSame(['10.0.0.1'], $site->settings()->excludedIps()->toArray());
        self::assertTrue($site->settings()->stripQueryParams());
        self::assertTrue($site->settings()->customDomainEnabled());
    }

    #[Test]
    public function replaceSettingsWithNullRetentionInheritsDefault(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $site = $this->newSite($now);

        $site->replaceSettings(
            allowedDomains: AllowedDomainList::empty(),
            excludedIps: ExcludedIpList::empty(),
            stripQueryParams: false,
            customDomainEnabled: false,
            retentionDays: null,
            trackerConfig: TrackerConfig::default(),
            now: $now,
        );

        self::assertNull($site->retentionDays());
        self::assertSame(RetentionDays::DEFAULT, $site->effectiveRetentionDays());
    }

    private function newSite(DateTimeImmutable $now): Site
    {
        return Site::register(
            id: Uuid::fromString(self::SITE_ID),
            teamId: Uuid::fromString(self::TEAM_ID),
            name: SiteName::fromString('Example'),
            domain: Hostname::fromString('example.com'),
            timezone: Timezone::fromString('Europe/Paris'),
            trackerKey: TrackerKey::fromString('stk_' . str_repeat('a', 32)),
            now: $now,
        );
    }
}
