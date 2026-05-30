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
use App\Sites\Application\Command\DeleteSiteCommand;
use App\Sites\Application\Handler\DeleteSiteHandler;
use App\Sites\Domain\Exception\PermissionDeniedException;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerKey;
use App\Tests\Unit\Sites\Fake\FrozenClock;
use App\Tests\Unit\Sites\Fake\InMemorySiteRepository;
use App\Tests\Unit\Sites\Fake\InMemoryTeamMembershipProvider;
use App\Tests\Unit\Sites\Fake\RecordingDeletionScheduler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteSiteHandler::class)]
final class DeleteSiteHandlerTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const TEAM = '22222222-2222-4222-8222-222222222222';

    private InMemorySiteRepository $sites;

    private InMemoryTeamMembershipProvider $memberships;

    private RecordingDeletionScheduler $scheduler;

    private Site $site;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->memberships = new InMemoryTeamMembershipProvider();
        $this->scheduler = new RecordingDeletionScheduler();
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
    public function ownerSoftDeletesAndSchedulesPurge(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Owner);

        ($this->handler())(new DeleteSiteCommand(self::USER, $this->site->id()->getValue()));

        self::assertNull($this->sites->findById($this->site->id()), 'site no longer visible');
        self::assertSame([$this->site->id()->getValue()], $this->scheduler->scheduled);
    }

    #[Test]
    public function adminCannotDelete(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Admin);

        $this->expectException(PermissionDeniedException::class);

        ($this->handler())(new DeleteSiteCommand(self::USER, $this->site->id()->getValue()));
    }

    #[Test]
    public function missingSiteIsNotFound(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Owner);

        $this->expectException(SiteNotFoundException::class);

        ($this->handler())(new DeleteSiteCommand(self::USER, Uuid::generate()->getValue()));
    }

    private function handler(): DeleteSiteHandler
    {
        return new DeleteSiteHandler(
            $this->sites,
            new SiteAccessPolicy($this->memberships),
            $this->scheduler,
            new FrozenClock(new DateTimeImmutable('2026-05-01T00:00:00+00:00')),
        );
    }
}
