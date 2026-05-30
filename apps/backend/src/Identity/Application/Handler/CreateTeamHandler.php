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

use App\Identity\Application\Command\CreateTeamCommand;
use App\Identity\Application\DTO\TeamView;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\Service\SlugAllocator;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Creates a shared team and assigns the creator as founding owner
 * (openapi.yaml /teams POST).
 */
final readonly class CreateTeamHandler
{
    public function __construct(
        private TeamRepository $teams,
        private TeamMembershipRepository $memberships,
        private UserRepository $users,
        private SlugAllocator $slugAllocator,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateTeamCommand $command): TeamView
    {
        $ownerId = Uuid::fromString($command->ownerId);

        if ($this->users->findById($ownerId) === null) {
            throw UserNotFoundException::withId($ownerId);
        }

        $now = $this->clock->now();
        $slug = $this->slugAllocator->allocate($command->name);

        $team = Team::createShared(Uuid::generate(), $command->name, $slug, $ownerId, $now);
        $this->teams->save($team);

        $membership = TeamMembership::founder(Uuid::generate(), $team->id(), $ownerId, $now);
        $this->memberships->save($membership);

        $this->auditLogger->record(
            action: 'team.created',
            context: $command->auditContext,
            teamId: $team->id(),
            resourceType: 'team',
            resourceId: $team->id()->getValue(),
            payload: [
                'name' => $team->name(),
                'slug' => $slug->getValue(),
            ],
        );

        return TeamView::fromEntity($team, 1, 0, TeamRole::Owner);
    }
}
