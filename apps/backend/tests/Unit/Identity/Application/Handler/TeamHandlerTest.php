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

namespace App\Tests\Unit\Identity\Application\Handler;

use App\Identity\Application\Command\CreateTeamCommand;
use App\Identity\Application\Command\DeleteTeamCommand;
use App\Identity\Application\Command\UpdateTeamCommand;
use App\Identity\Application\Handler\CreateTeamHandler;
use App\Identity\Application\Handler\DeleteTeamHandler;
use App\Identity\Application\Handler\GetTeamHandler;
use App\Identity\Application\Handler\ListUserTeamsHandler;
use App\Identity\Application\Handler\UpdateTeamHandler;
use App\Identity\Application\Query\GetTeamQuery;
use App\Identity\Application\Query\ListUserTeamsQuery;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\PermissionDeniedException;
use App\Identity\Domain\Exception\TeamNotFoundException;
use App\Identity\Domain\Exception\TeamRuleViolationException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Service\SlugAllocator;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Identity\Support\InMemoryTeamMembershipRepository;
use App\Tests\Identity\Support\InMemoryTeamRepository;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\RecordingAuditLogger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateTeamHandler::class)]
#[CoversClass(GetTeamHandler::class)]
#[CoversClass(UpdateTeamHandler::class)]
#[CoversClass(DeleteTeamHandler::class)]
#[CoversClass(ListUserTeamsHandler::class)]
#[CoversClass(TeamAccessGuard::class)]
final class TeamHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryTeamMembershipRepository $memberships;

    private InMemoryTeamRepository $teams;

    private TeamAccessGuard $guard;

    private RecordingAuditLogger $audit;

    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->memberships = new InMemoryTeamMembershipRepository();
        $this->teams = new InMemoryTeamRepository($this->memberships);
        $this->guard = new TeamAccessGuard($this->teams, $this->memberships);
        $this->audit = new RecordingAuditLogger();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));
    }

    #[Test]
    public function itCreatesASharedTeamWithTheCreatorAsOwner(): void
    {
        $owner = $this->givenUser();
        $handler = new CreateTeamHandler($this->teams, $this->memberships, $this->users, new SlugAllocator($this->teams), $this->audit, $this->clock);

        $view = $handler(new CreateTeamCommand($owner->id()->getValue(), 'Acme', AuditContext::system()));

        self::assertSame('Acme', $view->name);
        self::assertSame('owner', $view->currentUserRole);
        self::assertSame(1, $view->memberCount);
        self::assertTrue($this->audit->hasAction('team.created'));
    }

    #[Test]
    public function creatingForAMissingUserFails(): void
    {
        $handler = new CreateTeamHandler($this->teams, $this->memberships, $this->users, new SlugAllocator($this->teams), $this->audit, $this->clock);

        $this->expectException(UserNotFoundException::class);

        $handler(new CreateTeamCommand(Uuid::generate()->getValue(), 'Acme', AuditContext::system()));
    }

    #[Test]
    public function aMemberCanReadTheTeam(): void
    {
        [$team, $ownerId] = $this->givenTeamWithOwner();
        $this->teams->setActiveSiteCount($team->id(), 3);
        $handler = new GetTeamHandler($this->guard, $this->teams);

        $view = $handler(new GetTeamQuery($ownerId->getValue(), $team->id()->getValue()));

        self::assertSame('owner', $view->currentUserRole);
        self::assertSame(3, $view->siteCount);
    }

    #[Test]
    public function aNonMemberSeesANotFoundNotForbidden(): void
    {
        [$team] = $this->givenTeamWithOwner();
        $handler = new GetTeamHandler($this->guard, $this->teams);

        $this->expectException(TeamNotFoundException::class);

        $handler(new GetTeamQuery(Uuid::generate()->getValue(), $team->id()->getValue()));
    }

    #[Test]
    public function anAdminCanRenameTheTeam(): void
    {
        [$team, , $adminId] = $this->givenTeamWithOwnerAndAdmin();
        $handler = new UpdateTeamHandler($this->guard, $this->teams, $this->audit, $this->clock);

        $view = $handler(new UpdateTeamCommand($adminId->getValue(), $team->id()->getValue(), 'Renamed', AuditContext::system()));

        self::assertSame('Renamed', $view->name);
        self::assertTrue($this->audit->hasAction('team.updated'));
    }

    #[Test]
    public function aViewerCannotRenameTheTeam(): void
    {
        [$team, , $viewerId] = $this->givenTeamWithOwnerAndViewer();
        $handler = new UpdateTeamHandler($this->guard, $this->teams, $this->audit, $this->clock);

        $this->expectException(PermissionDeniedException::class);

        $handler(new UpdateTeamCommand($viewerId->getValue(), $team->id()->getValue(), 'Renamed', AuditContext::system()));
    }

    #[Test]
    public function onlyTheOwnerCanDeleteTheTeam(): void
    {
        [$team, , $adminId] = $this->givenTeamWithOwnerAndAdmin();
        $handler = new DeleteTeamHandler($this->guard, $this->teams, $this->audit, $this->clock);

        $this->expectException(PermissionDeniedException::class);

        $handler(new DeleteTeamCommand($adminId->getValue(), $team->id()->getValue(), AuditContext::system()));
    }

    #[Test]
    public function theOwnerDeletesASharedTeam(): void
    {
        [$team, $ownerId] = $this->givenTeamWithOwner();
        $handler = new DeleteTeamHandler($this->guard, $this->teams, $this->audit, $this->clock);

        $handler(new DeleteTeamCommand($ownerId->getValue(), $team->id()->getValue(), AuditContext::system()));

        self::assertNull($this->teams->findById($team->id()));
        self::assertTrue($this->audit->hasAction('team.deleted'));
    }

    #[Test]
    public function deletingAPersonalTeamIsRejected(): void
    {
        $ownerId = Uuid::generate();
        $team = Team::createPersonal(Uuid::generate(), 'Personal', TeamSlug::fromString('personal'), $ownerId, $this->clock->now());
        $this->teams->save($team);
        $this->memberships->save(TeamMembership::founder(Uuid::generate(), $team->id(), $ownerId, $this->clock->now()));
        $handler = new DeleteTeamHandler($this->guard, $this->teams, $this->audit, $this->clock);

        $this->expectException(TeamRuleViolationException::class);

        $handler(new DeleteTeamCommand($ownerId->getValue(), $team->id()->getValue(), AuditContext::system()));
    }

    #[Test]
    public function listingExcludesPendingInvitations(): void
    {
        [$team, $ownerId] = $this->givenTeamWithOwner();
        $pendingTeam = Team::createShared(Uuid::generate(), 'Pending', TeamSlug::fromString('pending'), Uuid::generate(), $this->clock->now());
        $this->teams->save($pendingTeam);
        $this->memberships->save(TeamMembership::invite(Uuid::generate(), $pendingTeam->id(), $ownerId, TeamRole::Editor, Uuid::generate(), $this->clock->now()));

        $handler = new ListUserTeamsHandler($this->teams, $this->memberships);
        $views = $handler(new ListUserTeamsQuery($ownerId->getValue()));

        self::assertCount(1, $views);
        self::assertSame($team->id()->getValue(), $views[0]->id);
    }

    /**
     * @return array{0: Team, 1: Uuid}
     */
    private function givenTeamWithOwner(): array
    {
        $ownerId = Uuid::generate();
        $team = Team::createShared(Uuid::generate(), 'Acme', TeamSlug::fromString('acme'), $ownerId, $this->clock->now());
        $this->teams->save($team);
        $this->memberships->save(TeamMembership::founder(Uuid::generate(), $team->id(), $ownerId, $this->clock->now()));

        return [$team, $ownerId];
    }

    /**
     * @return array{0: Team, 1: Uuid, 2: Uuid}
     */
    private function givenTeamWithOwnerAndAdmin(): array
    {
        [$team, $ownerId] = $this->givenTeamWithOwner();
        $adminId = Uuid::generate();
        $admin = TeamMembership::invite(Uuid::generate(), $team->id(), $adminId, TeamRole::Admin, $ownerId, $this->clock->now());
        $admin->accept($this->clock->now());
        $this->memberships->save($admin);

        return [$team, $ownerId, $adminId];
    }

    /**
     * @return array{0: Team, 1: Uuid, 2: Uuid}
     */
    private function givenTeamWithOwnerAndViewer(): array
    {
        [$team, $ownerId] = $this->givenTeamWithOwner();
        $viewerId = Uuid::generate();
        $viewer = TeamMembership::invite(Uuid::generate(), $team->id(), $viewerId, TeamRole::Viewer, $ownerId, $this->clock->now());
        $viewer->accept($this->clock->now());
        $this->memberships->save($viewer);

        return [$team, $ownerId, $viewerId];
    }

    private function givenUser(): User
    {
        $user = User::register(Uuid::generate(), EmailAddress::fromString('owner@example.com'), 'Owner', HashedPassword::fromHash('hash'), $this->clock->now());
        $this->users->save($user);

        return $user;
    }
}
