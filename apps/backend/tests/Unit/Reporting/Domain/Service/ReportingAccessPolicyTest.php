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

namespace App\Tests\Unit\Reporting\Domain\Service;

use App\Reporting\Domain\Exception\PermissionDeniedException;
use App\Reporting\Domain\Exception\SiteNotFoundException;
use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\InMemorySiteAccessProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportingAccessPolicy::class)]
final class ReportingAccessPolicyTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const SITE = '22222222-2222-4222-8222-222222222222';

    private InMemorySiteAccessProvider $access;

    private ReportingAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->access = new InMemorySiteAccessProvider();
        $this->policy = new ReportingAccessPolicy($this->access);
    }

    #[Test]
    public function viewerMayView(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $role = $this->policy->assertCanView($this->user(), $this->site());

        self::assertSame(TeamRole::Viewer, $role);
    }

    #[Test]
    public function nonMemberSeesNotFoundOnView(): void
    {
        $this->expectException(SiteNotFoundException::class);
        $this->policy->assertCanView($this->user(), $this->site());
    }

    #[Test]
    public function viewerMayNotManage(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $this->expectException(PermissionDeniedException::class);
        $this->policy->assertCanManage($this->user(), $this->site());
    }

    #[Test]
    public function editorMayManage(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $role = $this->policy->assertCanManage($this->user(), $this->site());

        self::assertSame(TeamRole::Editor, $role);
    }

    #[Test]
    public function nonMemberSeesNotFoundOnManage(): void
    {
        $this->expectException(SiteNotFoundException::class);
        $this->policy->assertCanManage($this->user(), $this->site());
    }

    private function user(): Uuid
    {
        return Uuid::fromString(self::USER);
    }

    private function site(): Uuid
    {
        return Uuid::fromString(self::SITE);
    }
}
