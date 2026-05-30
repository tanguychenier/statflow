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

use App\Identity\Application\Command\ChangeMemberRoleCommand;
use App\Identity\Application\DTO\TeamMemberView;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\MembershipNotFoundException;
use App\Identity\Domain\Exception\PermissionDeniedException;
use App\Identity\Domain\Exception\TeamRuleViolationException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Changes a member's role (openapi.yaml: Owner/Admin only). Two invariants hold:
 * the team must keep at least one owner (the sole owner cannot be demoted), and a
 * member can only be promoted to owner by the current owner transferring
 * ownership — which also demotes the previous owner to admin so "one owner per
 * team" stays true.
 */
final readonly class ChangeMemberRoleHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private TeamMembershipRepository $memberships,
        private UserRepository $users,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeMemberRoleCommand $command): TeamMemberView
    {
        $actorId = Uuid::fromString($command->actorId);
        $teamId = Uuid::fromString($command->teamId);
        $targetUserId = Uuid::fromString($command->userId);
        $newRole = TeamRole::fromString($command->role);

        $this->accessGuard->requireTeam($teamId);
        $actorMembership = $this->accessGuard->requireRole($actorId, $teamId, TeamRole::Admin, 'team_member:update');

        $membership = $this->memberships->findByTeamAndUser($teamId, $targetUserId);

        if ($membership === null) {
            throw MembershipNotFoundException::forUserInTeam($targetUserId, $teamId);
        }

        $now = $this->clock->now();

        if ($membership->isOwner() && $newRole !== TeamRole::Owner && $this->memberships->countOwners($teamId) <= 1) {
            throw TeamRuleViolationException::cannotDemoteSoleOwner();
        }

        if ($newRole === TeamRole::Owner) {
            $this->transferOwnership($actorMembership, $membership, $now);
        } else {
            $membership->changeRole($newRole, $now);
            $this->memberships->save($membership);
        }

        $this->auditLogger->record(
            action: 'team_member.role_changed',
            context: $command->auditContext,
            teamId: $teamId,
            resourceType: 'team_member',
            resourceId: $membership->id()->getValue(),
            payload: [
                'user_id' => $targetUserId->getValue(),
                'role' => $newRole->value,
            ],
        );

        $user = $this->users->findById($targetUserId);

        if ($user === null) {
            throw UserNotFoundException::withId($targetUserId);
        }

        return TeamMemberView::fromEntities($membership, $user);
    }

    /**
     * Promote the target to owner and demote the acting owner to admin, keeping
     * exactly one owner. Only the current owner may initiate this.
     */
    private function transferOwnership(
        TeamMembership $actorMembership,
        TeamMembership $target,
        DateTimeImmutable $now,
    ): void {
        if (!$actorMembership->isOwner()) {
            throw PermissionDeniedException::requires('team:transfer_ownership');
        }

        $actorMembership->changeRole(TeamRole::Admin, $now);
        $target->changeRole(TeamRole::Owner, $now);

        $this->memberships->save($actorMembership);
        $this->memberships->save($target);
    }
}
