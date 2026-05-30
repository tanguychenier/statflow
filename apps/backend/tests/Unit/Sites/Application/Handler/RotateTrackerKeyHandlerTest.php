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
use App\Sites\Application\Command\RotateTrackerKeyCommand;
use App\Sites\Application\Handler\RotateTrackerKeyHandler;
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
use App\Tests\Unit\Sites\Fake\SequenceTrackerKeyGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RotateTrackerKeyHandler::class)]
final class RotateTrackerKeyHandlerTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const TEAM = '22222222-2222-4222-8222-222222222222';

    private InMemorySiteRepository $sites;

    private InMemoryTeamMembershipProvider $memberships;

    private Site $site;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->memberships = new InMemoryTeamMembershipProvider();
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
    public function adminRotatesKeyAndOldKeyIsRevokedImmediately(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Admin);

        $result = ($this->handler())(new RotateTrackerKeyCommand(self::USER, $this->site->id()->getValue()));

        self::assertSame('stk_' . str_repeat('b', 32), $result->trackerKey);
        // One key per site (ADR-0009 §1): old_key_valid_until is the rotation
        // instant — the clock's now — not a future grace deadline.
        self::assertSame('2026-03-01T11:00:00+00:00', $result->oldKeyValidUntil);

        $reloaded = $this->sites->findById($this->site->id());
        self::assertNotNull($reloaded);
        self::assertSame('stk_' . str_repeat('b', 32), $reloaded->trackerKey()->value());
    }

    #[Test]
    public function editorCannotRotate(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Editor);

        $this->expectException(PermissionDeniedException::class);

        ($this->handler())(new RotateTrackerKeyCommand(self::USER, $this->site->id()->getValue()));
    }

    #[Test]
    public function missingSiteIsNotFound(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Owner);

        $this->expectException(SiteNotFoundException::class);

        ($this->handler())(new RotateTrackerKeyCommand(self::USER, Uuid::generate()->getValue()));
    }

    private function handler(): RotateTrackerKeyHandler
    {
        return new RotateTrackerKeyHandler(
            $this->sites,
            new SequenceTrackerKeyGenerator(str_repeat('b', 32)),
            new SiteAccessPolicy($this->memberships),
            new FrozenClock(new DateTimeImmutable('2026-03-01T11:00:00+00:00')),
        );
    }
}
