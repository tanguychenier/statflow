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
use App\Identity\Application\Query\GetTeamQuery;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Port\TeamRepository;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class GetTeamHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private TeamRepository $teams,
    ) {
    }

    public function __invoke(GetTeamQuery $query): TeamView
    {
        $actorId = Uuid::fromString($query->actorId);
        $teamId = Uuid::fromString($query->teamId);

        $team = $this->accessGuard->requireTeam($teamId);
        $membership = $this->accessGuard->requireMembership($actorId, $teamId);

        return TeamView::fromEntity(
            $team,
            $this->teams->countMembers($teamId),
            $this->teams->countActiveSites($teamId),
            $membership->role(),
        );
    }
}
