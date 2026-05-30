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

use App\Identity\Application\Command\DeleteTeamCommand;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Soft-deletes a team. Owner only (openapi.yaml). Personal teams cannot be
 * deleted — the domain enforces that invariant in Team::softDelete().
 */
final readonly class DeleteTeamHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private TeamRepository $teams,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteTeamCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $teamId = Uuid::fromString($command->teamId);

        $team = $this->accessGuard->requireTeam($teamId);
        $this->accessGuard->requireRole($actorId, $teamId, TeamRole::Owner, 'team:delete');

        $team->softDelete($this->clock->now());
        $this->teams->save($team);

        $this->auditLogger->record(
            action: 'team.deleted',
            context: $command->auditContext,
            teamId: $teamId,
            resourceType: 'team',
            resourceId: $teamId->getValue(),
        );
    }
}
