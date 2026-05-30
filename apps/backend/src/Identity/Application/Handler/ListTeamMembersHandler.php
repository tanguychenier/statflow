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

use App\Identity\Application\DTO\TeamMemberView;
use App\Identity\Application\Query\ListTeamMembersQuery;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Lists the members of a team (openapi.yaml /teams/{id}/members GET). Any
 * accepted member may read the roster.
 */
final readonly class ListTeamMembersHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private TeamMembershipRepository $memberships,
        private UserRepository $users,
    ) {
    }

    /**
     * @return list<TeamMemberView>
     */
    public function __invoke(ListTeamMembersQuery $query): array
    {
        $actorId = Uuid::fromString($query->actorId);
        $teamId = Uuid::fromString($query->teamId);

        $this->accessGuard->requireTeam($teamId);
        $this->accessGuard->requireMembership($actorId, $teamId);

        $views = [];
        foreach ($this->memberships->findByTeam($teamId) as $membership) {
            $user = $this->users->findById($membership->userId());

            if ($user === null) {
                continue;
            }

            $views[] = TeamMemberView::fromEntities($membership, $user);
        }

        return $views;
    }
}
