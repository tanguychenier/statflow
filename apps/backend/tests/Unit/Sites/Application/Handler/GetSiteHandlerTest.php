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

namespace App\Tests\Unit\Sites\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Application\Handler\GetSiteHandler;
use App\Sites\Application\Handler\GetSiteSettingsHandler;
use App\Sites\Application\Query\GetSiteQuery;
use App\Sites\Application\Query\GetSiteSettingsQuery;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerKey;
use App\Tests\Unit\Sites\Fake\InMemorySiteRepository;
use App\Tests\Unit\Sites\Fake\InMemoryTeamMembershipProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetSiteHandler::class)]
#[CoversClass(GetSiteSettingsHandler::class)]
final class GetSiteHandlerTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const OUTSIDER = '99999999-9999-4999-8999-999999999999';

    private const TEAM = '22222222-2222-4222-8222-222222222222';

    private InMemorySiteRepository $sites;

    private InMemoryTeamMembershipProvider $memberships;

    private Site $site;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->memberships = new InMemoryTeamMembershipProvider();
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Viewer);
        $this->site = Site::register(
            id: Uuid::generate(),
            teamId: Uuid::fromString(self::TEAM),
            name: SiteName::fromString('Site'),
            domain: Hostname::fromString('example.com'),
            timezone: Timezone::default(),
            trackerKey: TrackerKey::fromString('stk_' . str_repeat('a', 32)),
            now: new DateTimeImmutable('2026-01-01'),
        );
        $this->sites->save($this->site);
    }

    #[Test]
    public function viewerCanReadSite(): void
    {
        $handler = new GetSiteHandler($this->sites, new SiteAccessPolicy($this->memberships));

        $view = $handler(new GetSiteQuery(self::USER, $this->site->id()->getValue()));

        self::assertSame('example.com', $view->domain);
        self::assertStringStartsWith('stk_', $view->trackerKey);
    }

    #[Test]
    public function outsiderGetsNotFound(): void
    {
        $handler = new GetSiteHandler($this->sites, new SiteAccessPolicy($this->memberships));

        $this->expectException(SiteNotFoundException::class);

        $handler(new GetSiteQuery(self::OUTSIDER, $this->site->id()->getValue()));
    }

    #[Test]
    public function missingSiteIsNotFound(): void
    {
        $handler = new GetSiteHandler($this->sites, new SiteAccessPolicy($this->memberships));

        $this->expectException(SiteNotFoundException::class);

        $handler(new GetSiteQuery(self::USER, Uuid::generate()->getValue()));
    }

    #[Test]
    public function viewerCanReadSettings(): void
    {
        $handler = new GetSiteSettingsHandler($this->sites, new SiteAccessPolicy($this->memberships));

        $view = $handler(new GetSiteSettingsQuery(self::USER, $this->site->id()->getValue()));

        self::assertSame(365, $view->dataRetentionDays);
        self::assertTrue($view->trackClicks);
    }

    #[Test]
    public function settingsForMissingSiteIsNotFound(): void
    {
        $handler = new GetSiteSettingsHandler($this->sites, new SiteAccessPolicy($this->memberships));

        $this->expectException(SiteNotFoundException::class);

        $handler(new GetSiteSettingsQuery(self::USER, Uuid::generate()->getValue()));
    }
}
