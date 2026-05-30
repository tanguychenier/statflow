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

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidTeamRoleException;
use App\Identity\Domain\ValueObject\TeamRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeamRole::class)]
final class TeamRoleTest extends TestCase
{
    #[Test]
    public function itParsesEveryValidRole(): void
    {
        self::assertSame(TeamRole::Owner, TeamRole::fromString('owner'));
        self::assertSame(TeamRole::Admin, TeamRole::fromString('admin'));
        self::assertSame(TeamRole::Editor, TeamRole::fromString('editor'));
        self::assertSame(TeamRole::Viewer, TeamRole::fromString('viewer'));
    }

    #[Test]
    public function itExposesTheFourFrozenValues(): void
    {
        self::assertSame(['owner', 'admin', 'editor', 'viewer'], TeamRole::values());
    }

    #[Test]
    public function itRejectsAnUnknownRole(): void
    {
        $this->expectException(InvalidTeamRoleException::class);

        TeamRole::fromString('superuser');
    }

    #[Test]
    public function privilegeOrderingIsTotal(): void
    {
        self::assertTrue(TeamRole::Owner->isAtLeast(TeamRole::Viewer));
        self::assertTrue(TeamRole::Admin->isAtLeast(TeamRole::Editor));
        self::assertTrue(TeamRole::Editor->isAtLeast(TeamRole::Editor));
        self::assertFalse(TeamRole::Viewer->isAtLeast(TeamRole::Editor));
        self::assertFalse(TeamRole::Editor->isAtLeast(TeamRole::Admin));
    }

    #[Test]
    public function capabilitiesMatchTheRoleTable(): void
    {
        self::assertTrue(TeamRole::Admin->canManageMembers());
        self::assertTrue(TeamRole::Admin->canManageApiKeys());
        self::assertFalse(TeamRole::Editor->canManageMembers());

        self::assertTrue(TeamRole::Editor->canWriteResources());
        self::assertFalse(TeamRole::Viewer->canWriteResources());

        self::assertTrue(TeamRole::Owner->canDeleteTeam());
        self::assertFalse(TeamRole::Admin->canDeleteTeam());
    }
}
