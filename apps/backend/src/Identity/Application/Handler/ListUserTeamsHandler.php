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

use App\Identity\Application\DTO\TeamView;
use App\Identity\Application\Query\ListUserTeamsQuery;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Lists the teams the user belongs to, each annotated with the user's role and
 * the member/site counts (openapi.yaml /teams GET).
 */
final readonly class ListUserTeamsHandler
{
    public function __construct(
        private TeamRepository $teams,
        private TeamMembershipRepository $memberships,
    ) {
    }

    /**
     * @return list<TeamView>
     */
    public function __invoke(ListUserTeamsQuery $query): array
    {
        $userId = Uuid::fromString($query->userId);
        $roleByTeam = $this->mapRolesByTeam($userId);

        $views = [];
        foreach ($this->teams->findTeamsForUser($userId) as $team) {
            $views[] = TeamView::fromEntity(
                $team,
                $this->teams->countMembers($team->id()),
                $this->teams->countActiveSites($team->id()),
                $roleByTeam[$team->id()->getValue()] ?? null,
            );
        }

        return $views;
    }

    /**
     * @return array<string, TeamRole>
     */
    private function mapRolesByTeam(Uuid $userId): array
    {
        $roleByTeam = [];

        foreach ($this->memberships->findByUser($userId) as $membership) {
            if (!$membership->isPending()) {
                $roleByTeam[$membership->teamId()->getValue()] = $membership->role();
            }
        }

        return $roleByTeam;
    }
}
