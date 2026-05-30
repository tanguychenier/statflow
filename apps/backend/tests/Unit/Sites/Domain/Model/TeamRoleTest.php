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

use App\Sites\Domain\Model\TeamRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeamRole::class)]
final class TeamRoleTest extends TestCase
{
    #[Test]
    public function rankReflectsPrivilegeOrder(): void
    {
        self::assertGreaterThan(TeamRole::Admin->rank(), TeamRole::Owner->rank());
        self::assertGreaterThan(TeamRole::Editor->rank(), TeamRole::Admin->rank());
        self::assertGreaterThan(TeamRole::Viewer->rank(), TeamRole::Editor->rank());
    }

    #[Test]
    public function isAtLeastIsInclusiveAndOrdered(): void
    {
        self::assertTrue(TeamRole::Admin->isAtLeast(TeamRole::Admin));
        self::assertTrue(TeamRole::Owner->isAtLeast(TeamRole::Viewer));
        self::assertFalse(TeamRole::Viewer->isAtLeast(TeamRole::Editor));
    }

    #[Test]
    public function manageRequiresEditorOrAbove(): void
    {
        self::assertTrue(TeamRole::Owner->canManageSites());
        self::assertTrue(TeamRole::Admin->canManageSites());
        self::assertTrue(TeamRole::Editor->canManageSites());
        self::assertFalse(TeamRole::Viewer->canManageSites());
    }

    #[Test]
    public function administerRequiresAdminOrAbove(): void
    {
        self::assertTrue(TeamRole::Owner->canAdministerSites());
        self::assertTrue(TeamRole::Admin->canAdministerSites());
        self::assertFalse(TeamRole::Editor->canAdministerSites());
        self::assertFalse(TeamRole::Viewer->canAdministerSites());
    }

    #[Test]
    public function deleteRequiresOwner(): void
    {
        self::assertTrue(TeamRole::Owner->canDeleteSites());
        self::assertFalse(TeamRole::Admin->canDeleteSites());
        self::assertFalse(TeamRole::Editor->canDeleteSites());
        self::assertFalse(TeamRole::Viewer->canDeleteSites());
    }

    #[Test]
    public function fromStringResolvesEnum(): void
    {
        self::assertSame(TeamRole::Editor, TeamRole::fromString('editor'));
    }
}
