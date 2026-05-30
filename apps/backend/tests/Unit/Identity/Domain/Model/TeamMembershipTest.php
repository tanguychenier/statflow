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

use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeamMembership::class)]
final class TeamMembershipTest extends TestCase
{
    private const NOW = '2026-05-29T10:00:00+00:00';

    #[Test]
    public function aFounderIsAnAcceptedOwner(): void
    {
        $membership = TeamMembership::founder(Uuid::generate(), Uuid::generate(), Uuid::generate(), new DateTimeImmutable(self::NOW));

        self::assertTrue($membership->isOwner());
        self::assertFalse($membership->isPending());
        self::assertSame('active', $membership->status());
        self::assertNull($membership->invitedBy());
    }

    #[Test]
    public function anInvitationIsPendingUntilAccepted(): void
    {
        $invitedBy = Uuid::generate();
        $membership = TeamMembership::invite(
            Uuid::generate(),
            Uuid::generate(),
            Uuid::generate(),
            TeamRole::Editor,
            $invitedBy,
            new DateTimeImmutable(self::NOW),
        );

        self::assertTrue($membership->isPending());
        self::assertSame('invited', $membership->status());
        self::assertTrue($membership->invitedBy()?->equals($invitedBy));

        $acceptedAt = new DateTimeImmutable('2026-05-30T10:00:00+00:00');
        $membership->accept($acceptedAt);

        self::assertFalse($membership->isPending());
        self::assertEquals($acceptedAt, $membership->acceptedAt());
    }

    #[Test]
    public function acceptingTwiceKeepsTheFirstTimestamp(): void
    {
        $membership = TeamMembership::invite(Uuid::generate(), Uuid::generate(), Uuid::generate(), TeamRole::Viewer, Uuid::generate(), new DateTimeImmutable(self::NOW));
        $first = new DateTimeImmutable('2026-05-30T10:00:00+00:00');

        $membership->accept($first);
        $membership->accept(new DateTimeImmutable('2026-06-02T10:00:00+00:00'));

        self::assertEquals($first, $membership->acceptedAt());
    }

    #[Test]
    public function itChangesRole(): void
    {
        $membership = TeamMembership::invite(Uuid::generate(), Uuid::generate(), Uuid::generate(), TeamRole::Viewer, Uuid::generate(), new DateTimeImmutable(self::NOW));

        $membership->changeRole(TeamRole::Admin, new DateTimeImmutable(self::NOW));

        self::assertSame(TeamRole::Admin, $membership->role());
    }
}
