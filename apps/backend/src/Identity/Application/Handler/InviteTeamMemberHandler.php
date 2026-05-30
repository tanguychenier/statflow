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

namespace App\Identity\Application\Handler;

use App\Identity\Application\Command\InviteTeamMemberCommand;
use App\Identity\Application\DTO\TeamMemberView;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\TeamRuleViolationException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\IdentityMailer;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Invites an existing user to a team with a chosen role (openapi.yaml: Owner/
 * Admin only). The invitation is pending until the invitee accepts on next login.
 * Owner cannot be assigned through an invitation — ownership is transferred, not
 * granted. The invitee must be a registered user (FK constraint on team_members).
 */
final readonly class InviteTeamMemberHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private TeamMembershipRepository $memberships,
        private UserRepository $users,
        private IdentityMailer $mailer,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(InviteTeamMemberCommand $command): TeamMemberView
    {
        $actorId = Uuid::fromString($command->actorId);
        $teamId = Uuid::fromString($command->teamId);
        $role = TeamRole::fromString($command->role);

        if ($role === TeamRole::Owner) {
            throw TeamRuleViolationException::ownerNotAssignable();
        }

        $team = $this->accessGuard->requireTeam($teamId);
        $this->accessGuard->requireRole($actorId, $teamId, TeamRole::Admin, 'team_member:invite');

        $email = EmailAddress::fromString($command->email);
        $invitee = $this->users->findByEmail($email);

        if ($invitee === null || $invitee->isDeleted()) {
            throw UserNotFoundException::withEmail($email);
        }

        if ($this->memberships->findByTeamAndUser($teamId, $invitee->id()) !== null) {
            throw TeamRuleViolationException::alreadyMember();
        }

        $now = $this->clock->now();
        $membership = TeamMembership::invite(
            Uuid::generate(),
            $teamId,
            $invitee->id(),
            $role,
            $actorId,
            $now,
        );
        $this->memberships->save($membership);

        $actor = $this->users->findById($actorId);
        $this->mailer->sendTeamInvitation($email, $team->name(), $actor?->name() ?? '');

        $this->auditLogger->record(
            action: 'team_member.invited',
            context: $command->auditContext,
            teamId: $teamId,
            resourceType: 'team_member',
            resourceId: $membership->id()->getValue(),
            payload: [
                'email' => $email->getValue(),
                'role' => $role->value,
            ],
        );

        return TeamMemberView::fromEntities($membership, $invitee);
    }
}
