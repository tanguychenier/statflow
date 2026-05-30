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

use App\Identity\Application\Command\ChangeMemberRoleCommand;
use App\Identity\Application\Command\InviteTeamMemberCommand;
use App\Identity\Application\Command\RemoveTeamMemberCommand;
use App\Identity\Application\DTO\TeamMemberView;
use App\Identity\Application\Handler\ChangeMemberRoleHandler;
use App\Identity\Application\Handler\InviteTeamMemberHandler;
use App\Identity\Application\Handler\ListTeamMembersHandler;
use App\Identity\Application\Handler\RemoveTeamMemberHandler;
use App\Identity\Application\Query\ListTeamMembersQuery;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\PermissionDeniedException;
use App\Identity\Domain\Exception\TeamRuleViolationException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Model\User;
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
use App\Tests\Identity\Support\RecordingIdentityMailer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InviteTeamMemberHandler::class)]
#[CoversClass(ChangeMemberRoleHandler::class)]
#[CoversClass(RemoveTeamMemberHandler::class)]
#[CoversClass(ListTeamMembersHandler::class)]
final class TeamMemberHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryTeamMembershipRepository $memberships;

    private InMemoryTeamRepository $teams;

    private TeamAccessGuard $guard;

    private RecordingIdentityMailer $mailer;

    private RecordingAuditLogger $audit;

    private FixedClock $clock;

    private Team $team;

    private Uuid $ownerId;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->memberships = new InMemoryTeamMembershipRepository();
        $this->teams = new InMemoryTeamRepository($this->memberships);
        $this->guard = new TeamAccessGuard($this->teams, $this->memberships);
        $this->mailer = new RecordingIdentityMailer();
        $this->audit = new RecordingAuditLogger();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));

        $owner = $this->givenUser('owner@example.com', 'Owner');
        $this->ownerId = $owner->id();
        $this->team = Team::createShared(Uuid::generate(), 'Acme', TeamSlug::fromString('acme'), $this->ownerId, $this->clock->now());
        $this->teams->save($this->team);
        $this->memberships->save(TeamMembership::founder(Uuid::generate(), $this->team->id(), $this->ownerId, $this->clock->now()));
    }

    #[Test]
    public function anAdminInvitesAnExistingUserAsPending(): void
    {
        $invitee = $this->givenUser('bob@example.com', 'Bob');
        $handler = $this->inviteHandler();

        $view = $handler(new InviteTeamMemberCommand(
            $this->ownerId->getValue(),
            $this->team->id()->getValue(),
            'bob@example.com',
            'editor',
            AuditContext::system(),
        ));

        self::assertSame('invited', $view->status);
        self::assertSame('editor', $view->role);
        self::assertCount(1, $this->mailer->invitations);
        self::assertTrue($this->audit->hasAction('team_member.invited'));
        self::assertNotNull($this->memberships->findByTeamAndUser($this->team->id(), $invitee->id()));
    }

    #[Test]
    public function invitingWithTheOwnerRoleIsRejected(): void
    {
        $this->givenUser('bob@example.com', 'Bob');
        $handler = $this->inviteHandler();

        $this->expectException(TeamRuleViolationException::class);

        $handler(new InviteTeamMemberCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'bob@example.com', 'owner', AuditContext::system()));
    }

    #[Test]
    public function invitingAnUnknownUserFails(): void
    {
        $handler = $this->inviteHandler();

        $this->expectException(UserNotFoundException::class);

        $handler(new InviteTeamMemberCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'ghost@example.com', 'viewer', AuditContext::system()));
    }

    #[Test]
    public function invitingAnExistingMemberIsAConflict(): void
    {
        $bob = $this->givenUser('bob@example.com', 'Bob');
        $this->acceptMember($bob->id(), TeamRole::Editor);
        $handler = $this->inviteHandler();

        $this->expectException(TeamRuleViolationException::class);

        $handler(new InviteTeamMemberCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'bob@example.com', 'viewer', AuditContext::system()));
    }

    #[Test]
    public function aViewerCannotInvite(): void
    {
        $viewer = $this->givenUser('viewer@example.com', 'Vic');
        $this->acceptMember($viewer->id(), TeamRole::Viewer);
        $this->givenUser('bob@example.com', 'Bob');
        $handler = $this->inviteHandler();

        $this->expectException(PermissionDeniedException::class);

        $handler(new InviteTeamMemberCommand($viewer->id()->getValue(), $this->team->id()->getValue(), 'bob@example.com', 'viewer', AuditContext::system()));
    }

    #[Test]
    public function changingARoleSucceeds(): void
    {
        $bob = $this->givenUser('bob@example.com', 'Bob');
        $this->acceptMember($bob->id(), TeamRole::Viewer);
        $handler = $this->changeRoleHandler();

        $view = $handler(new ChangeMemberRoleCommand($this->ownerId->getValue(), $this->team->id()->getValue(), $bob->id()->getValue(), 'admin', AuditContext::system()));

        self::assertSame('admin', $view->role);
        self::assertTrue($this->audit->hasAction('team_member.role_changed'));
    }

    #[Test]
    public function promotingToOwnerTransfersOwnershipAndDemotesThePreviousOwner(): void
    {
        $bob = $this->givenUser('bob@example.com', 'Bob');
        $this->acceptMember($bob->id(), TeamRole::Admin);
        $handler = $this->changeRoleHandler();

        $handler(new ChangeMemberRoleCommand($this->ownerId->getValue(), $this->team->id()->getValue(), $bob->id()->getValue(), 'owner', AuditContext::system()));

        self::assertSame(TeamRole::Owner, $this->memberships->findByTeamAndUser($this->team->id(), $bob->id())?->role());
        self::assertSame(TeamRole::Admin, $this->memberships->findByTeamAndUser($this->team->id(), $this->ownerId)?->role());
        self::assertSame(1, $this->memberships->countOwners($this->team->id()));
    }

    #[Test]
    public function aNonOwnerCannotPromoteToOwner(): void
    {
        $admin = $this->givenUser('admin@example.com', 'Ada');
        $this->acceptMember($admin->id(), TeamRole::Admin);
        $bob = $this->givenUser('bob@example.com', 'Bob');
        $this->acceptMember($bob->id(), TeamRole::Editor);
        $handler = $this->changeRoleHandler();

        $this->expectException(PermissionDeniedException::class);

        $handler(new ChangeMemberRoleCommand($admin->id()->getValue(), $this->team->id()->getValue(), $bob->id()->getValue(), 'owner', AuditContext::system()));
    }

    #[Test]
    public function theSoleOwnerCannotBeDemoted(): void
    {
        $handler = $this->changeRoleHandler();

        $this->expectException(TeamRuleViolationException::class);

        $handler(new ChangeMemberRoleCommand($this->ownerId->getValue(), $this->team->id()->getValue(), $this->ownerId->getValue(), 'admin', AuditContext::system()));
    }

    #[Test]
    public function aMemberIsRemoved(): void
    {
        $bob = $this->givenUser('bob@example.com', 'Bob');
        $this->acceptMember($bob->id(), TeamRole::Editor);
        $handler = $this->removeHandler();

        $handler(new RemoveTeamMemberCommand($this->ownerId->getValue(), $this->team->id()->getValue(), $bob->id()->getValue(), AuditContext::system()));

        self::assertNull($this->memberships->findByTeamAndUser($this->team->id(), $bob->id()));
        self::assertTrue($this->audit->hasAction('team_member.removed'));
    }

    #[Test]
    public function theSoleOwnerCannotBeRemoved(): void
    {
        $handler = $this->removeHandler();

        $this->expectException(TeamRuleViolationException::class);

        $handler(new RemoveTeamMemberCommand($this->ownerId->getValue(), $this->team->id()->getValue(), $this->ownerId->getValue(), AuditContext::system()));
    }

    #[Test]
    public function listingMembersJoinsUserDetails(): void
    {
        $bob = $this->givenUser('bob@example.com', 'Bob');
        $this->acceptMember($bob->id(), TeamRole::Editor);
        $handler = new ListTeamMembersHandler($this->guard, $this->memberships, $this->users);

        $views = $handler(new ListTeamMembersQuery($this->ownerId->getValue(), $this->team->id()->getValue()));

        self::assertCount(2, $views);
        $emails = array_map(static fn (TeamMemberView $v): string => $v->email, $views);
        self::assertContains('bob@example.com', $emails);
    }

    private function inviteHandler(): InviteTeamMemberHandler
    {
        return new InviteTeamMemberHandler($this->guard, $this->memberships, $this->users, $this->mailer, $this->audit, $this->clock);
    }

    private function changeRoleHandler(): ChangeMemberRoleHandler
    {
        return new ChangeMemberRoleHandler($this->guard, $this->memberships, $this->users, $this->audit, $this->clock);
    }

    private function removeHandler(): RemoveTeamMemberHandler
    {
        return new RemoveTeamMemberHandler($this->guard, $this->memberships, $this->audit);
    }

    private function acceptMember(Uuid $userId, TeamRole $role): void
    {
        $membership = TeamMembership::invite(Uuid::generate(), $this->team->id(), $userId, $role, $this->ownerId, $this->clock->now());
        $membership->accept($this->clock->now());
        $this->memberships->save($membership);
    }

    private function givenUser(string $email, string $name): User
    {
        $user = User::register(Uuid::generate(), EmailAddress::fromString($email), $name, HashedPassword::fromHash('hash'), $this->clock->now());
        $this->users->save($user);

        return $user;
    }
}
