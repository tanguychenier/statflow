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

namespace App\Tests\Unit\Reporting\Domain\Model;

use App\Reporting\Domain\Model\TeamRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeamRole::class)]
final class TeamRoleTest extends TestCase
{
    #[Test]
    public function everyRoleMayView(): void
    {
        foreach (TeamRole::cases() as $role) {
            self::assertTrue($role->canViewReporting());
        }
    }

    #[Test]
    public function editorAndAboveMayManage(): void
    {
        self::assertTrue(TeamRole::Owner->canManageReporting());
        self::assertTrue(TeamRole::Admin->canManageReporting());
        self::assertTrue(TeamRole::Editor->canManageReporting());
        self::assertFalse(TeamRole::Viewer->canManageReporting());
    }

    #[Test]
    public function rankIsOrdered(): void
    {
        self::assertTrue(TeamRole::Owner->isAtLeast(TeamRole::Admin));
        self::assertFalse(TeamRole::Viewer->isAtLeast(TeamRole::Editor));
        self::assertSame(TeamRole::Editor, TeamRole::fromString('editor'));
    }
}
