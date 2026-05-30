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

namespace App\Tests\Unit\Sites\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Exception\PermissionDeniedException;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerKey;
use App\Tests\Unit\Sites\Fake\InMemoryTeamMembershipProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteAccessPolicy::class)]
#[CoversClass(PermissionDeniedException::class)]
#[CoversClass(SiteNotFoundException::class)]
final class SiteAccessPolicyTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const TEAM = '22222222-2222-4222-8222-222222222222';

    private InMemoryTeamMembershipProvider $memberships;

    private SiteAccessPolicy $policy;

    private Site $site;

    protected function setUp(): void
    {
        $this->memberships = new InMemoryTeamMembershipProvider();
        $this->policy = new SiteAccessPolicy($this->memberships);
        $this->site = Site::register(
            id: Uuid::generate(),
            teamId: Uuid::fromString(self::TEAM),
            name: SiteName::fromString('Example'),
            domain: Hostname::fromString('example.com'),
            timezone: Timezone::default(),
            trackerKey: TrackerKey::fromString('stk_' . str_repeat('a', 32)),
            now: new DateTimeImmutable('2026-01-01'),
        );
    }

    #[Test]
    public function nonMemberSeesNotFoundForAnyAccess(): void
    {
        $this->expectException(SiteNotFoundException::class);

        $this->policy->assertCanView($this->user(), $this->site);
    }

    #[Test]
    public function viewerCanViewButCannotManage(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Viewer);

        $this->policy->assertCanView($this->user(), $this->site);

        $this->expectException(PermissionDeniedException::class);
        $this->policy->assertCanManage($this->user(), $this->site);
    }

    #[Test]
    public function editorCanManageButCannotAdminister(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Editor);

        $this->policy->assertCanManage($this->user(), $this->site);

        $this->expectException(PermissionDeniedException::class);
        $this->policy->assertCanAdminister($this->user(), $this->site);
    }

    #[Test]
    public function adminCanAdministerButCannotDelete(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Admin);

        $this->policy->assertCanAdminister($this->user(), $this->site);

        $this->expectException(PermissionDeniedException::class);
        $this->policy->assertCanDelete($this->user(), $this->site);
    }

    #[Test]
    public function ownerCanDelete(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Owner);

        $this->policy->assertCanDelete($this->user(), $this->site);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function createInTeamRequiresMembership(): void
    {
        $this->expectException(PermissionDeniedException::class);

        $this->policy->assertCanCreateInTeam($this->user(), Uuid::fromString(self::TEAM));
    }

    #[Test]
    public function createInTeamRequiresManageRole(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Viewer);

        $this->expectException(PermissionDeniedException::class);

        $this->policy->assertCanCreateInTeam($this->user(), Uuid::fromString(self::TEAM));
    }

    #[Test]
    public function editorCanCreateInTeam(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Editor);

        $this->policy->assertCanCreateInTeam($this->user(), Uuid::fromString(self::TEAM));

        $this->expectNotToPerformAssertions();
    }

    private function user(): Uuid
    {
        return Uuid::fromString(self::USER);
    }
}
