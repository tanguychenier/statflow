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

namespace App\Tests\Unit\Identity\Domain\Model;

use App\Identity\Domain\Exception\TeamRuleViolationException;
use App\Identity\Domain\Model\Team;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Team::class)]
final class TeamTest extends TestCase
{
    private const NOW = '2026-05-29T10:00:00+00:00';

    #[Test]
    public function itCreatesAPersonalTeam(): void
    {
        $owner = Uuid::generate();
        $team = Team::createPersonal(Uuid::generate(), 'Personal', TeamSlug::fromString('personal'), $owner, new DateTimeImmutable(self::NOW));

        self::assertTrue($team->isPersonal());
        self::assertTrue($team->ownerId()->equals($owner));
        self::assertSame('free', $team->plan());
        self::assertSame(100000, $team->monthlyEventQuota());
    }

    #[Test]
    public function itCreatesASharedTeam(): void
    {
        $team = $this->sharedTeam();

        self::assertFalse($team->isPersonal());
    }

    #[Test]
    public function itRenamesAndTouchesUpdatedAt(): void
    {
        $team = $this->sharedTeam();
        $later = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

        $team->rename('Renamed', $later);

        self::assertSame('Renamed', $team->name());
        self::assertEquals($later, $team->updatedAt());
    }

    #[Test]
    public function itTransfersOwnership(): void
    {
        $team = $this->sharedTeam();
        $newOwner = Uuid::generate();

        $team->transferOwnership($newOwner, new DateTimeImmutable(self::NOW));

        self::assertTrue($team->ownerId()->equals($newOwner));
    }

    #[Test]
    public function itSoftDeletesASharedTeam(): void
    {
        $team = $this->sharedTeam();

        $team->softDelete(new DateTimeImmutable(self::NOW));

        self::assertTrue($team->isDeleted());
    }

    #[Test]
    public function itRefusesToDeleteAPersonalTeam(): void
    {
        $team = Team::createPersonal(Uuid::generate(), 'Personal', TeamSlug::fromString('personal'), Uuid::generate(), new DateTimeImmutable(self::NOW));

        $this->expectException(TeamRuleViolationException::class);

        $team->softDelete(new DateTimeImmutable(self::NOW));
    }

    private function sharedTeam(): Team
    {
        return Team::createShared(
            Uuid::generate(),
            'Acme',
            TeamSlug::fromString('acme'),
            Uuid::generate(),
            new DateTimeImmutable(self::NOW),
        );
    }
}
