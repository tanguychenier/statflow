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

use App\Identity\Application\Command\RemoveTeamMemberCommand;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\MembershipNotFoundException;
use App\Identity\Domain\Exception\TeamRuleViolationException;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Removes a member from a team (openapi.yaml: Owner/Admin only). The owner cannot
 * be removed while they are the sole owner — ownership must be transferred first
 * — which keeps the "always one owner" invariant intact.
 */
final readonly class RemoveTeamMemberHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private TeamMembershipRepository $memberships,
        private AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(RemoveTeamMemberCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $teamId = Uuid::fromString($command->teamId);
        $targetUserId = Uuid::fromString($command->userId);

        $this->accessGuard->requireTeam($teamId);
        $this->accessGuard->requireRole($actorId, $teamId, TeamRole::Admin, 'team_member:remove');

        $membership = $this->memberships->findByTeamAndUser($teamId, $targetUserId);

        if ($membership === null) {
            throw MembershipNotFoundException::forUserInTeam($targetUserId, $teamId);
        }

        if ($membership->isOwner() && $this->memberships->countOwners($teamId) <= 1) {
            throw TeamRuleViolationException::cannotRemoveSoleOwner();
        }

        $this->memberships->remove($membership);

        $this->auditLogger->record(
            action: 'team_member.removed',
            context: $command->auditContext,
            teamId: $teamId,
            resourceType: 'team_member',
            resourceId: $membership->id()->getValue(),
            payload: [
                'user_id' => $targetUserId->getValue(),
            ],
        );
    }
}
