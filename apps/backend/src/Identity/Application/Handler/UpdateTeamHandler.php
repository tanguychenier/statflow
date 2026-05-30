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

use App\Identity\Application\Command\UpdateTeamCommand;
use App\Identity\Application\DTO\TeamView;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Renames a team. Requires admin or owner (openapi.yaml: Owner/Admin only).
 */
final readonly class UpdateTeamHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private TeamRepository $teams,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateTeamCommand $command): TeamView
    {
        $actorId = Uuid::fromString($command->actorId);
        $teamId = Uuid::fromString($command->teamId);

        $team = $this->accessGuard->requireTeam($teamId);
        $membership = $this->accessGuard->requireRole($actorId, $teamId, TeamRole::Admin, 'team:update');

        if ($command->name !== null) {
            $team->rename($command->name, $this->clock->now());
            $this->teams->save($team);

            $this->auditLogger->record(
                action: 'team.updated',
                context: $command->auditContext,
                teamId: $teamId,
                resourceType: 'team',
                resourceId: $teamId->getValue(),
                payload: [
                    'name' => $command->name,
                ],
            );
        }

        return TeamView::fromEntity(
            $team,
            $this->teams->countMembers($teamId),
            $this->teams->countActiveSites($teamId),
            $membership->role(),
        );
    }
}
